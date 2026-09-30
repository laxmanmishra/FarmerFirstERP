<?php

namespace App\Livewire\Fulfilment\Accounts;

use App\Actions\Accounts\AccountFileFlow;
use App\Actions\Accounts\PaymentFlow;
use App\Actions\Accounts\RefundFlow;
use App\Enums\PayerType;
use App\Enums\PaymentMode;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\AccountFile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\WorkflowStage;
use App\Models\WorkflowStatusHistory;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Account file workspace (SRS §91–113): payment ledger with per-payment verification and
 * clearance, receipts, bounces, reversals, refunds with approval, and the file status.
 */
class Show extends Component
{
    use InteractsWithUi;

    public const MODALS = ['record', 'status', 'assign', 'reject', 'return', 'reverse', 'refund', 'refund_reject', 'refund_pay'];

    #[Locked]
    public int $fileId;

    #[Url(except: 'ledger')]
    public string $tab = 'ledger';

    public mixed $modal = null;

    #[Locked]
    public ?int $targetId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(AccountFile $file): void
    {
        $this->authorize('accounts.view');
        abort_unless(AccountFile::query()->visibleTo(Auth::user())->whereKey($file->id)->exists(), 404);

        $this->fileId = $file->id;
        $this->tab = in_array($this->tab, ['ledger', 'documents', 'timeline'], true) ? $this->tab : 'ledger';
    }

    public function open(string $modal, ?int $targetId = null): void
    {
        abort_unless(in_array($modal, self::MODALS, true), 404);

        $this->resetValidation();
        $this->targetId = $targetId;
        $this->form = match ($modal) {
            'record' => ['payer_type' => PayerType::Customer->value, 'mode' => PaymentMode::Cash->value, 'amount' => '', 'reference_no' => '', 'instrument_date' => '',
                'bank_name' => '', 'received_on' => today()->toDateString(), 'remarks' => ''],
            'status' => ['stage_id' => '', 'remarks' => ''],
            'assign' => ['employee_id' => (string) ($this->file()->responsible_employee_id ?? '')],
            'refund' => ['amount' => $this->file()->refundable(), 'reason' => ''],
            'refund_pay' => ['mode' => PaymentMode::Neft->value, 'reference_no' => ''],
            default => ['reason' => ''],
        };
        $this->modal = $modal;
    }

    public function recordPayment(PaymentFlow $flow): void
    {
        $this->authorize('accounts.record_payment');

        $validated = $this->validate([
            'form.payer_type' => ['required', Rule::enum(PayerType::class)],
            'form.mode' => ['required', Rule::enum(PaymentMode::class)],
            'form.amount' => ['required', 'numeric', 'gt:0'],
            'form.reference_no' => ['nullable', 'string', 'max:100'],
            'form.instrument_date' => ['nullable', 'date'],
            'form.bank_name' => ['nullable', 'string', 'max:150'],
            'form.received_on' => ['required', 'date', 'before_or_equal:today'],
            'form.remarks' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['form.amount' => __('amount'), 'form.received_on' => __('received on')])['form'];

        if ($this->attempt(fn () => $flow->record(Auth::user(), $this->file(), $validated))) {
            $this->modal = null;
            $this->toast(__('Payment recorded; someone else must verify it.'));
        }
    }

    public function verify(int $paymentId, PaymentFlow $flow): void
    {
        if ($this->attempt(fn () => $flow->verify(Auth::user(), $this->payment($paymentId)))) {
            $this->toast(__('Payment verified; receipt issued.'));
        }
    }

    public function clear(int $paymentId, PaymentFlow $flow): void
    {
        if ($this->attempt(fn () => $flow->clear(Auth::user(), $this->payment($paymentId)))) {
            $this->toast(__('Payment cleared.'));
        }
    }

    public function applyWithReason(PaymentFlow $flow): void
    {
        $this->validate(['form.reason' => ['required', 'string', 'max:1000']], attributes: ['form.reason' => __('reason')]);
        $payment = $this->payment((int) $this->targetId);
        $reason = $this->form['reason'];

        $done = $this->attempt(fn () => match ($this->modal) {
            'reject' => $flow->reject(Auth::user(), $payment, $reason),
            'return' => $flow->markReturned(Auth::user(), $payment, $reason),
            'reverse' => $flow->reverse(Auth::user(), $payment, $reason),
            default => abort(404),
        });

        if ($done) {
            $this->modal = null;
            $this->toast(__('Payment :no updated.', ['no' => $payment->payment_no]));
        }
    }

    public function saveStatus(AccountFileFlow $flow, WorkflowService $workflow): void
    {
        $file = $this->file();
        $targets = $this->targets($file, $workflow);

        $this->validate(['form.stage_id' => ['required', Rule::in($targets->pluck('id')->map(fn (int $id) => (string) $id)->all())], 'form.remarks' => ['nullable', 'string', 'max:1000']],
            attributes: ['form.stage_id' => __('status')]);
        $stage = $targets->firstWhere('id', (int) $this->form['stage_id']);

        if ($this->attempt(fn () => $flow->move(Auth::user(), $file, $stage, $this->form['remarks'] ?: null))) {
            $this->modal = null;
            $this->toast(__('Status set to :stage.', ['stage' => $stage->name]));
        }
    }

    public function saveAssignment(AccountFileFlow $flow): void
    {
        $this->validate(['form.employee_id' => ['nullable', 'integer', 'exists:employees,id']]);
        $employee = $this->form['employee_id'] !== '' ? Employee::query()->find($this->form['employee_id']) : null;

        if ($this->attempt(fn () => $flow->assign(Auth::user(), $this->file(), $employee))) {
            $this->modal = null;
            $this->toast(__('Responsible employee updated.'));
        }
    }

    public function requestRefund(RefundFlow $flow): void
    {
        $this->validate(['form.amount' => ['required', 'numeric', 'gt:0'], 'form.reason' => ['required', 'string', 'max:1000']], attributes: ['form.amount' => __('amount')]);

        if ($this->attempt(fn () => $flow->request(Auth::user(), $this->file(), (string) $this->form['amount'], $this->form['reason']))) {
            $this->modal = null;
            $this->toast(__('Refund requested; another person must approve it.'));
        }
    }

    public function approveRefund(int $refundId, RefundFlow $flow): void
    {
        if ($this->attempt(fn () => $flow->decide(Auth::user(), $this->refund($refundId), true, null))) {
            $this->toast(__('Refund approved.'));
        }
    }

    public function rejectRefund(RefundFlow $flow): void
    {
        $this->validate(['form.reason' => ['required', 'string', 'max:1000']], attributes: ['form.reason' => __('reason')]);

        if ($this->attempt(fn () => $flow->decide(Auth::user(), $this->refund((int) $this->targetId), false, $this->form['reason']))) {
            $this->modal = null;
            $this->toast(__('Refund rejected.'));
        }
    }

    public function payRefund(RefundFlow $flow): void
    {
        $this->validate(['form.mode' => ['required', Rule::enum(PaymentMode::class)], 'form.reference_no' => ['nullable', 'string', 'max:100']]);

        if ($this->attempt(fn () => $flow->pay(Auth::user(), $this->refund((int) $this->targetId), PaymentMode::from($this->form['mode']), $this->form['reference_no'] ?: null))) {
            $this->modal = null;
            $this->toast(__('Refund paid and entered in the ledger.'));
        }
    }

    public function render(WorkflowService $workflow): mixed
    {
        $file = AccountFile::query()->with([
            'stage.definition', 'responsible', 'branch',
            'order' => fn ($query) => $query->with(['customer', 'stage', 'financeFile.stage', 'financeFile.financer:id,name']),
            'payments' => fn ($query) => $query->with(['receipt', 'recorder:id,name', 'verifier:id,name', 'reverses:id,payment_no']),
            'refunds' => fn ($query) => $query->with(['requester:id,name', 'decider:id,name']),
        ])->findOrFail($this->fileId);
        $user = Auth::user();

        return view('livewire.fulfilment.accounts.show', [
            'file' => $file,
            'open' => ! $file->stage->is_final,
            'targets' => $this->modal === 'status' ? $this->targets($file, $workflow) : collect(),
            'employees' => $this->modal === 'assign'
                ? Employee::query()->where('is_active', true)->whereHas('departments', fn ($query) => $query->where('code', 'ACCOUNTS'))->orderBy('name')->pluck('name', 'id')
                : collect(),
            'modes' => collect(PaymentMode::cases())->mapWithKeys(fn (PaymentMode $mode) => [$mode->value => $mode->label()]),
            'refundModes' => collect(PaymentMode::cases())->reject(fn (PaymentMode $mode) => $mode === PaymentMode::FinanceDisbursement)->mapWithKeys(fn (PaymentMode $mode) => [$mode->value => $mode->label()]),
            'userId' => $user->id,
            'departmentId' => Department::query()->where('code', 'ACCOUNTS')->value('id'),
            'timeline' => $this->tab === 'timeline' ? $this->timeline($file) : [],
        ])->title($file->file_no);
    }

    /**
     * @return Collection<int, WorkflowStage>
     */
    private function targets(AccountFile $file, WorkflowService $workflow): Collection
    {
        return $workflow->availableTargets($file->stage, Auth::user())->reject(fn (WorkflowStage $stage) => $stage->is_system)->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timeline(AccountFile $file): array
    {
        return WorkflowStatusHistory::query()->with(['fromStage', 'toStage', 'user:id,name'])
            ->where('subject_type', $file->getMorphClass())->where('subject_id', $file->id)
            ->latest('id')->get()
            ->map(fn (WorkflowStatusHistory $history) => [
                'at' => $history->created_at,
                'title' => ($history->fromStage ? $history->fromStage->name.' → ' : '').$history->toStage->name,
                'body' => $history->remarks,
                'actor' => $history->user?->name,
                'tone' => $history->toStage->color,
                'icon' => 'calculator',
            ])->all();
    }

    private function file(): AccountFile
    {
        return AccountFile::query()->with(['stage.definition', 'order', 'task', 'payments', 'refunds', 'branch'])->findOrFail($this->fileId);
    }

    private function payment(int $id): Payment
    {
        return Payment::query()->with(['accountFile', 'receipt'])->where('account_file_id', $this->fileId)->findOrFail($id);
    }

    private function refund(int $id): RefundRequest
    {
        return RefundRequest::query()->where('account_file_id', $this->fileId)->findOrFail($id);
    }
}
