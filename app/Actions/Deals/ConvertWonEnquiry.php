<?php

namespace App\Actions\Deals;

use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\NumberSeriesService;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\DB;

/**
 * WON → customer duplicate check → CUSTOMER_ID → draft Deal (SRS §4, §12, §14).
 *
 * - A farmer becomes at most one customer (UNIQUE farmer_id), so a repeat buyer is linked, not duplicated.
 * - A different farmer record sharing the mobile number is flagged as a possible duplicate for review.
 * - Idempotent: running twice for the same enquiry returns the existing deal (UNIQUE enquiry_id).
 */
class ConvertWonEnquiry
{
    public function __construct(
        private readonly NumberSeriesService $numbers,
        private readonly WorkflowService $workflow,
        private readonly ApplyQuotationToDeal $applyQuotation,
    ) {}

    public function handle(Enquiry $enquiry, User $actor): Deal
    {
        return DB::transaction(function () use ($enquiry, $actor): Deal {
            $enquiry = Enquiry::query()->with(['farmer', 'deal'])->lockForUpdate()->findOrFail($enquiry->id);

            if ($enquiry->deal !== null) {
                return $enquiry->deal;
            }

            $customer = $this->customerFor($enquiry);
            $enquiry->forceFill(['customer_id' => $customer->id])->save();
            Quotation::query()->where('enquiry_id', $enquiry->id)->update(['customer_id' => $customer->id]);

            $draft = $this->workflow->initialStage(WorkflowDefinition::DEAL);

            $deal = Deal::create([
                'deal_no' => $this->numbers->next('deal', $enquiry->branch),
                'enquiry_id' => $enquiry->id,
                'customer_id' => $customer->id,
                'farmer_id' => $enquiry->farmer_id,
                'branch_id' => $enquiry->branch_id,
                'primary_salesman_employee_id' => $enquiry->assigned_employee_id,
                'stage_id' => $draft->id,
                'expected_delivery_date' => $enquiry->expected_purchase_date,
            ]);

            $this->workflow->recordInitial($deal, $draft, $actor, ['event' => 'created_from_won_enquiry', 'enquiry_id' => $enquiry->id]);

            $accepted = Quotation::query()->with('items')->where('enquiry_id', $enquiry->id)->where('status', QuotationStatus::Accepted)->latest('id')->first();

            if ($accepted !== null) {
                $this->applyQuotation->handle($deal->load('stage'), $accepted);
            }

            return $deal;
        });
    }

    private function customerFor(Enquiry $enquiry): Customer
    {
        $farmer = $enquiry->farmer;
        $existing = Customer::query()->where('farmer_id', $farmer->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $numbers = array_values(array_filter([$farmer->mobile, $farmer->alternate_mobile]));
        $possibleDuplicate = Customer::query()
            ->where(fn ($query) => $query->whereIn('mobile', $numbers)->orWhereIn('alternate_mobile', $numbers))
            ->oldest('id')
            ->first();

        return Customer::create([
            'customer_no' => $this->numbers->next('customer', $enquiry->branch),
            'farmer_id' => $farmer->id,
            'branch_id' => $enquiry->branch_id,
            'name' => $farmer->name,
            'father_name' => $farmer->father_name,
            'mobile' => $farmer->mobile,
            'alternate_mobile' => $farmer->alternate_mobile,
            'whatsapp_number' => $farmer->whatsapp_number,
            'village_id' => $farmer->village_id,
            'address' => $farmer->address,
            'pin_code' => $farmer->pin_code,
            'customer_since' => today(),
            'source_enquiry_id' => $enquiry->id,
            'possible_duplicate_of_id' => $possibleDuplicate?->id,
        ]);
    }
}
