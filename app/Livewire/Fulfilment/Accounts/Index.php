<?php

namespace App\Livewire\Fulfilment\Accounts;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Models\AccountFile;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Accounts work queue (SRS §91–113): account files with live balances, the payment
 * verification queue and refunds awaiting approval.
 */
class Index extends Component
{
    use WithDataTable;

    /** @var list<string> */
    protected array $sortable = ['file_no', 'receivable_amount', 'updated_at'];

    #[Url(except: 'files')]
    public string $tab = 'files';

    #[Url(except: '')]
    public string $stage = '';

    /** '' | 'short' | 'cleared' | 'excess' */
    #[Url(except: '')]
    public string $position = '';

    #[Url(except: false)]
    public bool $open = true;

    public function mount(): void
    {
        $this->authorize('accounts.view');
        $this->normaliseTab();
    }

    public function updated(string $property): void
    {
        if ($property === 'tab') {
            $this->normaliseTab();
        }

        $this->resetPage();
    }

    public function render(): mixed
    {
        $user = Auth::user();
        $counted = "(select coalesce(sum(amount), 0) from payments where payments.account_file_id = account_files.id and payments.status in ('".implode("','", array_map(fn (PaymentStatus $status) => $status->value, PaymentStatus::counted()))."'))";

        $data = match ($this->tab) {
            'verification' => ['payments' => Payment::query()
                ->whereIn('account_file_id', AccountFile::query()->visibleTo($user)->select('account_files.id'))
                ->whereIn('status', [PaymentStatus::PendingVerification, PaymentStatus::Verified])
                ->with(['accountFile:id,file_no,order_id', 'accountFile.order:id,order_no,customer_id', 'accountFile.order.customer:id,name', 'recorder:id,name'])
                ->oldest('id')->paginate($this->perPage)],
            'refunds' => ['refunds' => RefundRequest::query()
                ->whereIn('account_file_id', AccountFile::query()->visibleTo($user)->select('account_files.id'))
                ->whereIn('status', [RefundStatus::Requested, RefundStatus::Approved])
                ->with(['accountFile:id,file_no,order_id', 'accountFile.order:id,order_no,customer_id', 'accountFile.order.customer:id,name', 'requester:id,name'])
                ->oldest('id')->paginate($this->perPage)],
            default => ['files' => $this->applySorting(AccountFile::query()->visibleTo($user)
                ->with(['stage', 'responsible:id,name', 'order:id,order_no,customer_id,finance_required,cancelled_at', 'order.customer:id,name,mobile'])
                ->addSelect(['cleared_total' => fn ($query) => $query->selectRaw('coalesce(sum(amount), 0)')->from('payments')
                    ->whereColumn('payments.account_file_id', 'account_files.id')->whereIn('payments.status', PaymentStatus::counted())])
                ->addSelect(['uncleared_count' => fn ($query) => $query->selectRaw('count(*)')->from('payments')
                    ->whereColumn('payments.account_file_id', 'account_files.id')->whereIn('payments.status', [PaymentStatus::PendingVerification, PaymentStatus::Verified])])
                ->when($this->open, fn (Builder $query) => $query->whereHas('stage', fn (Builder $query) => $query->where('is_final', false)))
                ->when(ctype_digit($this->stage), fn (Builder $query) => $query->where('stage_id', $this->stage))
                ->when($this->position === 'short', fn (Builder $query) => $query->whereRaw("receivable_amount > {$counted}"))
                ->when($this->position === 'cleared', fn (Builder $query) => $query->whereRaw("receivable_amount = {$counted}"))
                ->when($this->position === 'excess', fn (Builder $query) => $query->whereRaw("receivable_amount < {$counted}"))
                ->when($this->searchTerm(), fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                    ->where('file_no', 'like', $term)
                    ->orWhereHas('order', fn (Builder $query) => $query->where('order_no', 'like', $term)
                        ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('mobile', 'like', $term))))), 'updated_at')
                ->paginate($this->perPage)],
        };

        return view('livewire.fulfilment.accounts.index', $data + [
            'tabs' => $this->tabs(),
            'stages' => WorkflowStage::query()->ofDefinition(WorkflowDefinition::ACCOUNTS)->orderBy('sequence')->get(),
        ])->title(__('Accounts'));
    }

    /**
     * @return array<string, string>
     */
    private function tabs(): array
    {
        return array_filter([
            'files' => __('Account files'),
            'verification' => Auth::user()->canAny(['accounts.verify_payment', 'accounts.clear_payment']) ? __('Payments to verify / clear') : null,
            'refunds' => Auth::user()->canAny(['accounts.approve_refund', 'accounts.clear_payment']) ? __('Refunds') : null,
        ]);
    }

    private function normaliseTab(): void
    {
        $this->tab = array_key_exists($this->tab, $this->tabs()) ? $this->tab : 'files';
    }
}
