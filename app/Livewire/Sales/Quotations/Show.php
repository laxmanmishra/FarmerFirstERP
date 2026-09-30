<?php

namespace App\Livewire\Sales\Quotations;

use App\Actions\Quotations\QuotationLifecycle;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\DiscountLimit;
use App\Models\Quotation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithUi;

    #[Locked]
    public int $quotationId;

    public mixed $modal = null;

    public string $remarks = '';

    public function mount(Quotation $quotation): void
    {
        $this->authorize('quotations.view');
        abort_unless(Quotation::query()->visibleTo(Auth::user())->whereKey($quotation->id)->exists(), 404);
        $this->quotationId = $quotation->id;
    }

    public function open(string $modal): void
    {
        $this->resetValidation();
        $this->remarks = '';
        $this->modal = in_array($modal, ['approve', 'accept', 'decline'], true) ? $modal : null;
    }

    public function approveDiscount(QuotationLifecycle $lifecycle): void
    {
        $this->authorize('quotations.approve_discount');

        if ($this->attempt(fn () => $lifecycle->approveDiscount(Auth::user(), $this->quotation(), $this->remarks ?: null))) {
            $this->modal = null;
            $this->toast(__('Discount approved. The quotation can now be issued.'));
        }
    }

    public function issue(QuotationLifecycle $lifecycle): void
    {
        $this->authorize('quotations.create');

        if ($this->attempt(fn () => $lifecycle->issue(Auth::user(), $this->quotation()))) {
            $this->toast(__('Quotation issued to the customer.'));
        }
    }

    public function decide(QuotationLifecycle $lifecycle): void
    {
        $this->authorize('quotations.create');
        $accepted = $this->modal === 'accept';
        $this->validate(['remarks' => [$accepted ? 'nullable' : 'required', 'string', 'max:1000']], attributes: ['remarks' => __('remarks')]);

        if ($this->attempt(fn () => $lifecycle->decide(Auth::user(), $this->quotation(), $accepted, $this->remarks ?: null))) {
            $this->modal = null;
            $this->toast($accepted ? __('Marked as accepted by the customer.') : __('Marked as declined.'));
        }
    }

    public function revise(QuotationLifecycle $lifecycle): mixed
    {
        $this->authorize('quotations.create');

        if ($revision = $this->attempt(fn () => $lifecycle->revise(Auth::user(), $this->quotation()))) {
            session()->flash('toast', ['type' => 'success', 'message' => __('Version :v created. Edit it and issue again.', ['v' => $revision->version])]);

            return $this->redirectRoute('sales.quotations.edit', $revision, navigate: true);
        }

        return null;
    }

    public function render(): mixed
    {
        $quotation = Quotation::query()->with(['items', 'enquiry.farmer.village.tehsil.district', 'enquiry.deal', 'customer', 'preparedBy', 'discountApprover', 'branch'])->findOrFail($this->quotationId);
        $user = Auth::user();

        return view('livewire.sales.quotations.show', [
            'quotation' => $quotation,
            'versions' => Quotation::query()->where('quotation_no', $quotation->quotation_no)->orderByDesc('version')->get(['id', 'version', 'status', 'net_amount', 'created_at']),
            'canApprove' => $user->can('quotations.approve_discount')
                && $user->employee?->id !== $quotation->prepared_by_employee_id
                && DiscountLimit::allows($user, $quotation->discount_total, $quotation->discount_percent),
            'isLatest' => ! Quotation::query()->where('quotation_no', $quotation->quotation_no)->where('version', '>', $quotation->version)->exists(),
        ])->title($quotation->reference());
    }

    private function quotation(): Quotation
    {
        return Quotation::query()->with(['items', 'enquiry.deal.stage'])->findOrFail($this->quotationId);
    }
}
