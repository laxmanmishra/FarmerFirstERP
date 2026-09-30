<?php

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Enums\RequirementState;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\Order;
use App\Models\User;
use App\Services\NumberSeriesService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Uploads into the central repository (SRS §185, §195): a new document, or a new
 * version of an existing one. Files go to the private disk; every version is kept.
 */
class StoreDocument
{
    public const DISK = 'local';

    public function __construct(private readonly NumberSeriesService $numbers) {}

    /**
     * @param  array{reference_no?: ?string, issue_date?: ?string, expiry_date?: ?string, remarks?: ?string}  $meta
     */
    public function upload(User $actor, DocumentType $type, Customer $customer, ?Order $order, UploadedFile $file, array $meta = [], ?DocumentRequirement $requirement = null): Document
    {
        if (! $type->is_active) {
            throw new BusinessRuleException(__('The document type ":name" is inactive.', ['name' => $type->name]), 'document_type_inactive');
        }

        if ($order === null && ! $type->is_reusable) {
            throw new BusinessRuleException(__(':name belongs to an order; upload it from the order.', ['name' => $type->name]), 'document_needs_order');
        }

        if ($order !== null && ($order->customer_id !== $customer->id || $order->isCancelled())) {
            throw new BusinessRuleException(__('Documents cannot be added to this order.'), 'order_not_open');
        }

        if ($requirement !== null && ($requirement->order_id !== $order?->id || $requirement->document_type_id !== $type->id
            || $requirement->requirement_state === RequirementState::NotRequired)) {
            throw new BusinessRuleException(__('The upload does not match the requirement.'), 'requirement_mismatch');
        }

        $meta = $this->validate($type, $file, $meta);
        $path = $this->storeFile($customer, $file);

        try {
            return DB::transaction(function () use ($actor, $type, $customer, $order, $file, $meta, $requirement, $path): Document {
                $document = Document::create([
                    'document_no' => $this->numbers->next('document'),
                    'document_type_id' => $type->id,
                    'customer_id' => $customer->id,
                    'deal_id' => $order?->deal_id,
                    'order_id' => $order?->id,
                    'department_id' => $requirement?->department_id ?? $type->default_department_id,
                    'status' => DocumentStatus::Uploaded,
                    'current_version' => 1,
                    'uploaded_by' => $actor->id,
                    ...$meta,
                ]);

                $this->recordVersion($document, 1, $file, $path, $actor, $meta['remarks'] ?? null);

                $requirement?->update(['document_id' => $document->id, 'linked_by' => $actor->id, 'linked_at' => now()]);

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($path);

            throw $exception;
        }
    }

    /**
     * A corrected, renewed or re-scanned file. The document must be verified again;
     * linked requirements re-evaluate automatically (DOCUMENT_REQUIREMENTS §5.5).
     *
     * @param  array{reference_no?: ?string, issue_date?: ?string, expiry_date?: ?string, remarks?: ?string}  $meta
     */
    public function addVersion(User $actor, Document $document, UploadedFile $file, array $meta = []): Document
    {
        $document->loadMissing(['type', 'customer']);
        $meta = $this->validate($document->type, $file, $meta);
        $path = $this->storeFile($document->customer, $file);

        try {
            return DB::transaction(function () use ($actor, $document, $file, $meta, $path): Document {
                $document = Document::query()->lockForUpdate()->findOrFail($document->id);
                $version = $document->current_version + 1;

                $this->recordVersion($document, $version, $file, $path, $actor, $meta['remarks'] ?? null);

                $document->update([
                    ...array_filter($meta, fn ($value) => $value !== null),
                    'current_version' => $version,
                    'status' => DocumentStatus::Uploaded,
                    'uploaded_by' => $actor->id,
                    'verified_by' => null,
                    'verified_at' => null,
                    'rejection_reason' => null,
                    'expired_at' => null,
                ]);

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($path);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{reference_no: ?string, issue_date: ?string, expiry_date: ?string, remarks: ?string}
     */
    private function validate(DocumentType $type, UploadedFile $file, array $meta): array
    {
        $validated = Validator::make(['file' => $file, ...$meta], [
            'file' => ['required', 'file', 'mimes:'.implode(',', $type->extensions()), 'max:'.$type->max_size_kb],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'expiry_date' => [$type->expiry_applicable ? 'required' : 'nullable', 'date', 'after_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['expiry_date' => __('expiry date'), 'issue_date' => __('issue date'), 'reference_no' => __('document number')])->validate();

        return [
            'reference_no' => ($validated['reference_no'] ?? '') !== '' ? $validated['reference_no'] : null,
            'issue_date' => $validated['issue_date'] ?? null ?: null,
            'expiry_date' => $type->expiry_applicable ? $validated['expiry_date'] : null,
            'remarks' => ($validated['remarks'] ?? '') !== '' ? $validated['remarks'] : null,
        ];
    }

    private function storeFile(Customer $customer, UploadedFile $file): string
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());

        return $file->storeAs("documents/{$customer->id}", Str::uuid()->toString().'.'.$extension, self::DISK);
    }

    private function recordVersion(Document $document, int $version, UploadedFile $file, string $path, User $actor, ?string $remarks): void
    {
        $document->versions()->create([
            'version' => $version,
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $actor->id,
            'remarks' => $remarks,
        ]);
    }
}
