<?php

namespace App\Livewire\Fulfilment\Documents;

use App\Actions\Documents\DocumentVerificationFlow;
use App\Actions\Documents\StoreDocument;
use App\Enums\DocumentStatus;
use App\Enums\RequirementState;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Order;
use App\Services\DocumentRequirementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Order document checklist (SRS §199): per requirement — use an existing repository
 * document, upload, upload a corrected version, verify or reject.
 */
class Checklist extends Component
{
    use InteractsWithUi, WithFileUploads;

    #[Locked]
    public int $orderId;

    /** Limits the checklist to one department (department file screens). */
    #[Locked]
    public ?int $departmentId = null;

    /** 'upload' | 'link' | 'reject'; false/null when closed. */
    public mixed $modal = null;

    #[Locked]
    public ?int $requirementId = null;

    /** @var UploadedFile|null */
    public $file = null;

    /** @var array{reference_no: string, issue_date: string, expiry_date: string, remarks: string} */
    public array $meta = ['reference_no' => '', 'issue_date' => '', 'expiry_date' => '', 'remarks' => ''];

    public string $reason = '';

    public function mount(int $orderId, ?int $departmentId = null): void
    {
        $this->authorize('documents.view');
        abort_unless(Order::query()->visibleTo(Auth::user())->whereKey($orderId)->exists(), 404);

        $this->orderId = $orderId;
        $this->departmentId = $departmentId;
    }

    public function open(int $requirementId, string $mode): void
    {
        abort_unless(in_array($mode, ['upload', 'link', 'reject'], true), 404);
        $this->authorize($mode === 'reject' ? 'documents.view' : 'documents.upload');

        $this->resetValidation();
        $this->requirementId = $this->requirement($requirementId)->id;
        $this->file = null;
        $this->meta = ['reference_no' => '', 'issue_date' => '', 'expiry_date' => '', 'remarks' => ''];
        $this->reason = '';
        $this->modal = $mode;
    }

    public function saveUpload(StoreDocument $store): void
    {
        $this->authorize('documents.upload');
        $requirement = $this->requirement((int) $this->requirementId);
        $type = $requirement->documentType;

        $this->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', $type->extensions()), 'max:'.$type->max_size_kb],
            'meta.reference_no' => ['nullable', 'string', 'max:100'],
            'meta.issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'meta.expiry_date' => [$type->expiry_applicable ? 'required' : 'nullable', 'date', 'after_or_equal:today'],
            'meta.remarks' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['meta.expiry_date' => __('expiry date'), 'meta.issue_date' => __('issue date'), 'meta.reference_no' => __('document number')]);

        $meta = array_map(fn (string $value) => $value === '' ? null : $value, $this->meta);
        $replace = $requirement->document !== null && in_array($requirement->document->status, [DocumentStatus::Rejected, DocumentStatus::Expired], true);

        $done = $this->attempt(fn () => $replace
            ? $store->addVersion(Auth::user(), $requirement->document, $this->file, $meta)
            : $store->upload(Auth::user(), $type, $requirement->order->customer, $requirement->order, $this->file, $meta, $requirement));

        if ($done) {
            $this->closeAndRefresh($replace ? __('New version uploaded; it needs verification again.') : __(':type uploaded.', ['type' => $type->name]));
        }
    }

    public function link(int $documentId, DocumentRequirementService $service): void
    {
        $this->authorize('documents.upload');
        $requirement = $this->requirement((int) $this->requirementId);
        $document = Document::query()->findOrFail($documentId);

        if ($this->attempt(fn () => $service->link(Auth::user(), $requirement, $document))) {
            $this->closeAndRefresh(__(':no linked — no second upload needed.', ['no' => $document->document_no]));
        }
    }

    public function startVerification(int $requirementId, DocumentVerificationFlow $flow): void
    {
        $document = $this->requirement($requirementId)->document ?? abort(404);

        if ($this->attempt(fn () => $flow->start(Auth::user(), $document))) {
            $this->closeAndRefresh(__('Verification started.'));
        }
    }

    public function verify(int $requirementId, DocumentVerificationFlow $flow): void
    {
        $document = $this->requirement($requirementId)->document ?? abort(404);

        if ($this->attempt(fn () => $flow->verify(Auth::user(), $document, null))) {
            $this->closeAndRefresh(__(':type verified.', ['type' => $document->type->name]));
        }
    }

    public function reject(DocumentVerificationFlow $flow): void
    {
        $this->validate(['reason' => ['required', 'string', 'max:1000']]);
        $document = $this->requirement((int) $this->requirementId)->document ?? abort(404);

        if ($this->attempt(fn () => $flow->reject(Auth::user(), $document, $this->reason))) {
            $this->closeAndRefresh(__('Document rejected; the uploader has been told why.'));
        }
    }

    public function render(DocumentRequirementService $service, DocumentVerificationFlow $flow): mixed
    {
        $order = Order::query()->with('customer:id,name')->findOrFail($this->orderId);
        $requirements = DocumentRequirement::query()
            ->where('order_id', $this->orderId)
            ->when($this->departmentId, fn ($query, int $id) => $query->where('department_id', $id))
            ->with(['documentType', 'department:id,name,sort_order', 'responsible:id,name', 'document.type', 'document.currentVersion.uploader:id,name'])
            ->get()
            ->sortBy([fn ($a, $b) => $a->department->sort_order <=> $b->department->sort_order, fn ($a, $b) => $a->documentType->sort_order <=> $b->documentType->sort_order]);
        $user = Auth::user();
        $current = $this->requirementId ? $requirements->firstWhere('id', $this->requirementId) : null;

        return view('livewire.fulfilment.documents.checklist', [
            'order' => $order,
            'groups' => $requirements->where('requirement_state', '!=', RequirementState::NotRequired)->groupBy(fn (DocumentRequirement $requirement) => $requirement->department->name),
            'notRequired' => $requirements->where('requirement_state', RequirementState::NotRequired),
            'reusable' => $requirements->mapWithKeys(fn (DocumentRequirement $requirement) => [
                $requirement->id => ! $order->isCancelled() && ! $requirement->isSatisfied() && $requirement->requirement_state->isApplicable()
                    ? $service->candidates($requirement)->reject(fn (Document $document) => $document->id === $requirement->document_id)->count() : 0,
            ]),
            'canVerify' => $requirements->mapWithKeys(fn (DocumentRequirement $requirement) => [
                $requirement->id => $requirement->document !== null && $flow->canVerify($user, $requirement->document),
            ]),
            'current' => $current,
            'candidates' => $this->modal === 'link' && $current ? $service->candidates($current)->reject(fn (Document $document) => $document->id === $current->document_id) : collect(),
        ]);
    }

    private function requirement(int $id): DocumentRequirement
    {
        return DocumentRequirement::query()
            ->with(['documentType', 'order.customer', 'document.type', 'document.currentVersion'])
            ->where('order_id', $this->orderId)
            ->findOrFail($id);
    }

    private function closeAndRefresh(string $message): void
    {
        $this->modal = null;
        $this->file = null;
        $this->toast($message);
        $this->dispatch('documents-changed');
    }
}
