<?php

namespace App\Actions\Quotations;

use App\Actions\Deals\ApplyQuotationToDeal;
use App\Enums\QuotationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\DiscountLimit;
use App\Models\Quotation;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Discount approval, issue, customer decision and revision of quotations.
 *
 *   Draft ──issue──▶ Issued ──accept──▶ Accepted
 *     ▲                 └──decline──▶ Declined
 *   PendingApproval ──approve──▶ Draft (approved)
 *   Issued/Declined/Draft ──revise──▶ new version (Draft); old ▶ Superseded
 */
class QuotationLifecycle
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ApplyQuotationToDeal $applyToDeal,
    ) {}

    public function approveDiscount(User $approver, Quotation $quotation, ?string $remarks): Quotation
    {
        if ($quotation->status !== QuotationStatus::PendingApproval) {
            throw new BusinessRuleException(__('This quotation is not awaiting discount approval.'), 'not_pending_approval');
        }

        if ($approver->employee?->id !== null && $approver->employee->id === $quotation->prepared_by_employee_id) {
            throw new BusinessRuleException(__('You cannot approve the discount on your own quotation.'), 'self_approval');
        }

        if (! $approver->can('quotations.approve_discount') || ! DiscountLimit::allows($approver, $quotation->discount_total, $quotation->discount_percent)) {
            throw new BusinessRuleException(__('This discount is above your approval limit.'), 'discount_above_limit');
        }

        $quotation->update([
            'status' => QuotationStatus::Draft,
            'discount_approved_by' => $approver->id,
            'discount_approved_at' => now(),
            'approval_remarks' => $remarks,
        ]);
        $this->audit->record('discount_approved', 'quotations', $quotation, newValues: ['discount_total' => $quotation->discount_total, 'discount_percent' => $quotation->discount_percent], reason: $remarks);

        return $quotation;
    }

    public function issue(User $actor, Quotation $quotation): Quotation
    {
        if ($quotation->status !== QuotationStatus::Draft) {
            throw new BusinessRuleException(
                $quotation->status === QuotationStatus::PendingApproval ? __('The discount must be approved before issuing.') : __('Only draft quotations can be issued.'),
                'quotation_not_issuable',
            );
        }

        if ($quotation->valid_until->isBefore(today())) {
            throw new BusinessRuleException(__('The validity date has passed. Update it before issuing.'), 'past_validity');
        }

        $quotation->update(['status' => QuotationStatus::Issued, 'issued_at' => now()]);

        return $quotation;
    }

    /**
     * Records the customer's decision. Accepting supersedes any other open or accepted
     * quotation of the enquiry and refreshes an editable deal with the new figures.
     */
    public function decide(User $actor, Quotation $quotation, bool $accepted, ?string $remarks): Quotation
    {
        if ($quotation->status !== QuotationStatus::Issued) {
            throw new BusinessRuleException(__('Only issued quotations can be accepted or declined.'), 'quotation_not_issued');
        }

        if ($accepted && $quotation->isExpired()) {
            throw new BusinessRuleException(__('This quotation expired on :date. Revise it with a new validity date.', ['date' => $quotation->valid_until->format('d M Y')]), 'quotation_expired');
        }

        $deal = $quotation->enquiry->deal;

        if ($accepted && $deal !== null && ! $deal->isEditable()) {
            throw new BusinessRuleException(__('The deal for this enquiry is already under approval or approved; its figures can no longer change.'), 'deal_locked');
        }

        return DB::transaction(function () use ($quotation, $accepted, $remarks, $deal): Quotation {
            if ($accepted) {
                Quotation::query()->where('enquiry_id', $quotation->enquiry_id)->whereKeyNot($quotation->id)
                    ->whereIn('status', [QuotationStatus::Accepted, QuotationStatus::Issued, QuotationStatus::Draft, QuotationStatus::PendingApproval])
                    ->update(['status' => QuotationStatus::Superseded]);
            }

            $quotation->update([
                'status' => $accepted ? QuotationStatus::Accepted : QuotationStatus::Declined,
                'decided_at' => now(),
                'decision_remarks' => $remarks,
            ]);

            if ($accepted && $deal !== null) {
                $this->applyToDeal->handle($deal, $quotation);
            }

            return $quotation;
        });
    }

    /**
     * Creates the next version as an editable draft; the previous version is superseded.
     */
    public function revise(User $actor, Quotation $quotation): Quotation
    {
        if (in_array($quotation->status, [QuotationStatus::Accepted, QuotationStatus::Superseded], true)) {
            throw new BusinessRuleException(__('Accepted or superseded quotations cannot be revised.'), 'quotation_not_revisable');
        }

        if (Quotation::query()->where('quotation_no', $quotation->quotation_no)->where('version', '>', $quotation->version)->exists()) {
            throw new BusinessRuleException(__('A newer version already exists.'), 'quotation_not_latest');
        }

        return DB::transaction(function () use ($actor, $quotation): Quotation {
            $revision = $quotation->replicate(['status', 'issued_at', 'decided_at', 'decision_remarks', 'discount_approved_by', 'discount_approved_at', 'approval_remarks', 'created_by', 'updated_by']);
            $revision->fill([
                'version' => $quotation->version + 1,
                'status' => QuotationStatus::Draft,
                'prepared_by_employee_id' => $actor->employee?->id ?? $quotation->prepared_by_employee_id,
                'valid_until' => $quotation->valid_until->isPast() ? today()->addDays(15) : $quotation->valid_until,
            ]);

            // Needs approval again if the new preparer's limit does not cover the discount.
            if (! DiscountLimit::allows($actor, $quotation->discount_total, $quotation->discount_percent)) {
                $revision->status = QuotationStatus::PendingApproval;
            }

            $revision->save();
            $revision->items()->createMany($quotation->items->map->commercialAttributes()->all());
            $quotation->update(['status' => QuotationStatus::Superseded]);

            return $revision;
        });
    }
}
