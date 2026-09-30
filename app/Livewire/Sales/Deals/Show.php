<?php

namespace App\Livewire\Sales\Deals;

use App\Actions\Deals\ApplyQuotationToDeal;
use App\Actions\Deals\DealApprovalFlow;
use App\Enums\DealDecision;
use App\Enums\QuotationStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Deal;
use App\Models\Quotation;
use App\Services\DealReadiness;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithUi;

    #[Locked]
    public int $dealId;

    /** @var array<string, mixed> */
    public array $terms = [];

    public mixed $modal = null;

    public string $decisionRemarks = '';

    public function mount(Deal $deal): void
    {
        $this->authorize('deals.view');
        abort_unless(Deal::query()->visibleTo(Auth::user())->whereKey($deal->id)->exists(), 404);

        $this->dealId = $deal->id;
        $this->fillTerms($deal);
    }

    public function saveTerms(DealApprovalFlow $flow): void
    {
        $this->authorize('deals.update');

        $validated = $this->validate([
            'terms.expected_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'terms.booking_amount' => ['nullable', 'numeric', 'min:0'],
            'terms.finance_required' => ['boolean'],
            'terms.finance_amount' => ['nullable', 'numeric', 'min:0', 'required_if_accepted:terms.finance_required'],
            'terms.rto_required' => ['boolean'],
            'terms.insurance_required' => ['boolean'],
            'terms.pdi_required' => ['boolean'],
            'terms.remarks' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['terms.expected_delivery_date' => __('expected delivery date'), 'terms.finance_amount' => __('finance amount'), 'terms.booking_amount' => __('booking amount')])['terms'];

        if ($this->attempt(fn () => $flow->updateTerms($this->deal(), [...$validated, 'remarks' => $validated['remarks'] ?: null]))) {
            $this->toast(__('Deal terms saved.'));
        }
    }

    public function applyQuotation(int $quotationId, ApplyQuotationToDeal $apply): void
    {
        $this->authorize('deals.update');
        $deal = $this->deal();
        $quotation = Quotation::query()->with('items')->where('enquiry_id', $deal->enquiry_id)->findOrFail($quotationId);

        if ($this->attempt(fn () => $apply->handle($deal, $quotation))) {
            $this->fillTerms($deal->fresh());
            $this->toast(__('Figures from :no applied.', ['no' => $quotation->reference()]));
        }
    }

    public function submit(DealApprovalFlow $flow): void
    {
        $this->authorize('deals.update');

        if ($this->attempt(fn () => $flow->submit(Auth::user(), $this->deal()))) {
            $this->toast(__('Deal submitted for approval.'));
        }
    }

    public function openDecision(string $decision): void
    {
        $this->authorize('deals.approve');
        $this->resetValidation();
        $this->decisionRemarks = '';
        $this->modal = DealDecision::tryFrom($decision)?->value;
    }

    public function decide(DealApprovalFlow $flow): void
    {
        $this->authorize('deals.approve');
        $decision = DealDecision::tryFrom((string) $this->modal) ?? abort(422);

        $this->validate(['decisionRemarks' => [$decision === DealDecision::Approved ? 'nullable' : 'required', 'string', 'max:2000']], attributes: ['decisionRemarks' => __('remarks')]);

        if ($this->attempt(fn () => $flow->decide(Auth::user(), $this->deal(), $decision, $this->decisionRemarks ?: null))) {
            $this->modal = null;
            $this->toast(__('Deal :decision.', ['decision' => mb_strtolower($decision->label())]));
        }
    }

    public function render(DealReadiness $readiness): mixed
    {
        $deal = Deal::query()->with([
            'stage', 'items', 'customer.village.tehsil.district', 'farmer', 'enquiry.exchangeTractor', 'quotation', 'primarySalesman', 'branch', 'order:id,deal_id,order_no',
            'approvals.user', 'statusHistory.fromStage', 'statusHistory.toStage', 'statusHistory.user',
        ])->findOrFail($this->dealId);
        $user = Auth::user();

        return view('livewire.sales.deals.show', [
            'deal' => $deal,
            'missing' => $deal->isEditable() ? $readiness->missing($deal) : [],
            'acceptedQuotations' => Quotation::query()->where('enquiry_id', $deal->enquiry_id)->where('status', QuotationStatus::Accepted)->get(),
            'quotations' => Quotation::query()->where('enquiry_id', $deal->enquiry_id)->orderByDesc('id')->get(),
            'canDecide' => $user->can('deals.approve') && $deal->isAwaitingApproval()
                && $user->id !== $deal->submitted_by && $user->employee?->id !== $deal->primary_salesman_employee_id,
        ])->title($deal->deal_no);
    }

    private function deal(): Deal
    {
        return Deal::query()->with(['stage', 'items', 'enquiry.exchangeTractor', 'primarySalesman.user'])->findOrFail($this->dealId);
    }

    private function fillTerms(Deal $deal): void
    {
        $this->terms = [
            'expected_delivery_date' => $deal->expected_delivery_date?->toDateString() ?? '',
            'booking_amount' => $deal->booking_amount,
            'finance_required' => $deal->finance_required,
            'finance_amount' => $deal->finance_amount,
            'rto_required' => $deal->rto_required,
            'insurance_required' => $deal->insurance_required,
            'pdi_required' => $deal->pdi_required,
            'remarks' => (string) $deal->remarks,
        ];
    }
}
