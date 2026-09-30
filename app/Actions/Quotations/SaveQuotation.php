<?php

namespace App\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\DiscountLimit;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\User;
use App\Notifications\QuotationDiscountApprovalRequested;
use App\Services\NumberSeriesService;
use App\Services\QuotationCalculator;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Creates a quotation for an open enquiry or edits a draft (SRS v6.1 §4).
 * A discount above the preparer's role limit puts it into PendingApproval.
 */
class SaveQuotation
{
    public function __construct(
        private readonly QuotationCalculator $calculator,
        private readonly NumberSeriesService $numbers,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function handle(
        User $actor,
        Enquiry $enquiry,
        array $lines,
        CarbonInterface $validUntil,
        string|int|float|null $exchangeValue,
        string|int|float|null $financeAmount,
        ?string $terms,
        ?string $remarks,
        ?Quotation $quotation = null,
    ): Quotation {
        if ($quotation === null && $enquiry->isClosed() && ! $enquiry->pipelineStage?->is_completion) {
            throw new BusinessRuleException(__('Quotations cannot be prepared for a closed enquiry.'), 'enquiry_closed');
        }

        if ($enquiry->pipeline_stage_id === null) {
            throw new BusinessRuleException(__('The enquiry must be validated before a quotation is prepared.'), 'not_in_pipeline');
        }

        if ($quotation !== null && ! $quotation->status->isEditable()) {
            throw new BusinessRuleException(__('Only draft quotations can be edited. Create a revision instead.'), 'quotation_locked');
        }

        if ($lines === []) {
            throw new BusinessRuleException(__('Add at least one line.'), 'quotation_empty');
        }

        if ($validUntil->isBefore(today())) {
            throw new BusinessRuleException(__('The validity date cannot be in the past.'), 'past_validity');
        }

        $calculation = $this->calculator->calculate($lines, $exchangeValue, $financeAmount);
        $totals = $calculation['totals'];
        $withinLimit = DiscountLimit::allows($actor, $totals['discount_total'], $totals['discount_percent']);

        $quotation = DB::transaction(function () use ($actor, $enquiry, $calculation, $totals, $validUntil, $terms, $remarks, $quotation, $withinLimit): Quotation {
            $attributes = [
                ...$totals,
                'valid_until' => $validUntil,
                'terms' => $terms,
                'remarks' => $remarks,
                'status' => $withinLimit ? QuotationStatus::Draft : QuotationStatus::PendingApproval,
                'discount_approved_by' => null,
                'discount_approved_at' => null,
                'approval_remarks' => null,
            ];

            if ($quotation === null) {
                $quotation = Quotation::create([
                    ...$attributes,
                    'quotation_no' => $this->numbers->next('quotation', $enquiry->branch),
                    'version' => 1,
                    'enquiry_id' => $enquiry->id,
                    'farmer_id' => $enquiry->farmer_id,
                    'customer_id' => $enquiry->customer_id,
                    'branch_id' => $enquiry->branch_id,
                    'prepared_by_employee_id' => $actor->employee?->id,
                ]);
            } else {
                $quotation->update($attributes);
                $quotation->items()->delete();
            }

            $quotation->items()->createMany($calculation['lines']);
            $enquiry->forceFill(['last_activity_at' => now()])->save();

            return $quotation;
        });

        if (! $withinLimit) {
            $approvers = User::withPermissionInBranch('quotations.approve_discount', $quotation->branch_id, $actor)
                ->filter(fn (User $user) => DiscountLimit::allows($user, $totals['discount_total'], $totals['discount_percent']));
            Notification::send($approvers, new QuotationDiscountApprovalRequested($quotation));
        }

        return $quotation;
    }
}
