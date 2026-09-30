<?php

namespace App\Livewire\Fulfilment\Documents;

use App\Actions\Documents\DocumentVerificationFlow;
use App\Actions\Documents\StoreDocument;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * One repository document: versions, verification history, where it is used and who opened it.
 */
class Show extends Component
{
    use InteractsWithUi, WithFileUploads;

    #[Locked]
    public int $documentId;

    /** 'version' | 'reject'; false/null when closed. */
    public mixed $modal = null;

    /** @var UploadedFile|null */
    public $file = null;

    /** @var array{reference_no: string, issue_date: string, expiry_date: string, remarks: string} */
    public array $meta = ['reference_no' => '', 'issue_date' => '', 'expiry_date' => '', 'remarks' => ''];

    public string $reason = '';

    public function mount(Document $document): void
    {
        $this->authorize('documents.view');
        abort_unless(Document::query()->visibleTo(Auth::user())->whereKey($document->id)->exists(), 404);

        $this->documentId = $document->id;
    }

    public function open(string $mode): void
    {
        abort_unless(in_array($mode, ['version', 'reject'], true), 404);

        if ($mode === 'version') {
            $this->authorize('documents.upload');
        }

        $this->resetValidation();
        $this->file = null;
        $this->meta = ['reference_no' => '', 'issue_date' => '', 'expiry_date' => '', 'remarks' => ''];
        $this->reason = '';
        $this->modal = $mode;
    }

    public function uploadVersion(StoreDocument $store): void
    {
        $this->authorize('documents.upload');
        $document = $this->document();
        $type = $document->type;

        $this->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', $type->extensions()), 'max:'.$type->max_size_kb],
            'meta.reference_no' => ['nullable', 'string', 'max:100'],
            'meta.issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'meta.expiry_date' => [$type->expiry_applicable ? 'required' : 'nullable', 'date', 'after_or_equal:today'],
            'meta.remarks' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['meta.expiry_date' => __('expiry date')]);

        $meta = array_map(fn (string $value) => $value === '' ? null : $value, $this->meta);

        if ($this->attempt(fn () => $store->addVersion(Auth::user(), $document, $this->file, $meta))) {
            $this->modal = null;
            $this->file = null;
            $this->toast(__('Version uploaded; it needs verification again.'));
        }
    }

    public function startVerification(DocumentVerificationFlow $flow): void
    {
        if ($this->attempt(fn () => $flow->start(Auth::user(), $this->document()))) {
            $this->toast(__('Verification started.'));
        }
    }

    public function verify(DocumentVerificationFlow $flow): void
    {
        if ($this->attempt(fn () => $flow->verify(Auth::user(), $this->document(), null))) {
            $this->toast(__('Document verified.'));
        }
    }

    public function reject(DocumentVerificationFlow $flow): void
    {
        $this->validate(['reason' => ['required', 'string', 'max:1000']]);

        if ($this->attempt(fn () => $flow->reject(Auth::user(), $this->document(), $this->reason))) {
            $this->modal = null;
            $this->toast(__('Document rejected.'));
        }
    }

    public function render(DocumentVerificationFlow $flow): mixed
    {
        $document = Document::query()->with([
            'type', 'customer:id,name,customer_no,mobile', 'order:id,order_no', 'deal:id,deal_no', 'department:id,name',
            'currentVersion.uploader:id,name', 'versions.uploader:id,name', 'verifications.user:id,name', 'verifications.version:id,version',
            'requirements' => fn ($query) => $query->with(['order:id,order_no,cancelled_at', 'department:id,name']),
        ])->findOrFail($this->documentId);
        $user = Auth::user();
        $canAudit = $user->canAny(['documents.dashboard', 'documents.view_sensitive']);

        return view('livewire.fulfilment.documents.show', [
            'document' => $document,
            'canViewFile' => $document->canViewFile($user),
            'canVerify' => $flow->canVerify($user, $document),
            'accessLogs' => $canAudit ? $document->accessLogs()->with(['user:id,name', 'version:id,version'])->limit(50)->get() : null,
        ])->title($document->document_no);
    }

    private function document(): Document
    {
        return Document::query()->with(['type', 'customer', 'currentVersion.uploader'])->findOrFail($this->documentId);
    }
}
