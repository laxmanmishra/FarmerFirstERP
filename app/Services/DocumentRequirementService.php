<?php

namespace App\Services;

use App\Enums\DocumentLevel;
use App\Enums\RequirementState;
use App\Exceptions\BusinessRuleException;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\DocumentRequirementRule;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Document Requirement Engine (SRS §199, docs/DOCUMENT_REQUIREMENTS.md §5):
 * rules → one requirement per order × type × department → search the repository →
 * link an existing document or upload → verify → satisfied.
 */
class DocumentRequirementService
{
    public const AUTO_LINK_SETTING = 'documents.auto_link_existing';

    /**
     * Creates or refreshes the order's requirements from the active rules. Idempotent;
     * requirements are never deleted, only switched to Not Required, so links and
     * history survive a change of plan. Waived requirements are left alone.
     */
    public function sync(Order $order, ?User $actor = null): void
    {
        $order->loadMissing(['fulfilment.tasks', 'documentRequirements']);
        $tasksByType = $order->fulfilment?->tasks->keyBy('fulfilment_task_type_id') ?? collect();
        $existing = $order->documentRequirements->keyBy(fn (DocumentRequirement $requirement) => $requirement->document_type_id.'-'.$requirement->department_id);

        $rules = DocumentRequirementRule::query()->active()
            ->whereHas('documentType', fn (Builder $query) => $query->active())
            ->with('department:id,code')
            ->get();

        DB::transaction(function () use ($order, $rules, $tasksByType, $existing): void {
            foreach ($rules as $rule) {
                $task = $rule->fulfilment_task_type_id ? $tasksByType->get($rule->fulfilment_task_type_id) : null;

                if ($rule->fulfilment_task_type_id !== null && $task === null) {
                    continue;
                }

                $state = $task === null ? RequirementState::Required : match ($task->requirement_state) {
                    RequirementState::NotRequired => RequirementState::NotRequired,
                    RequirementState::Conditional => RequirementState::Conditional,
                    default => RequirementState::Required,
                };

                $attributes = [
                    'document_requirement_rule_id' => $rule->id,
                    'fulfilment_task_id' => $task?->id,
                    'blocks_delivery' => $rule->blocks_delivery,
                    'responsible_employee_id' => $task !== null ? $task->responsible_employee_id : $order->primary_salesman_employee_id,
                ];

                $requirement = $existing->get($rule->document_type_id.'-'.$rule->department_id);

                if ($requirement === null) {
                    $order->documentRequirements()->create($attributes + [
                        'document_type_id' => $rule->document_type_id,
                        'department_id' => $rule->department_id,
                        'requirement_state' => $state,
                        'due_date' => $rule->due_offset_days !== null ? $order->order_date->copy()->addDays($rule->due_offset_days) : null,
                    ]);

                    continue;
                }

                $requirement->fill($attributes);

                if ($requirement->requirement_state !== RequirementState::Waived) {
                    $requirement->requirement_state = $state;
                }

                $requirement->save();
            }
        });

        if ($actor !== null && SystemSetting::get(self::AUTO_LINK_SETTING, true)) {
            $this->autoLink($order->fresh('documentRequirements'), $actor);
        }
    }

    /**
     * Usable repository documents for the requirement, best first: reusable types are
     * searched across the customer, others only within the same order.
     *
     * @return Collection<int, Document>
     */
    public function candidates(DocumentRequirement $requirement): Collection
    {
        $requirement->loadMissing(['documentType', 'order']);
        $type = $requirement->documentType;

        return Document::query()
            ->with(['type', 'currentVersion'])
            ->where('document_type_id', $type->id)
            ->where('customer_id', $requirement->order->customer_id)
            ->when(! $type->is_reusable, fn (Builder $query) => $type->level === DocumentLevel::Deal
                ? $query->where('deal_id', $requirement->order->deal_id)
                : $query->where('order_id', $requirement->order_id))
            ->current()
            ->orderByRaw("case when status = 'verified' then 0 else 1 end")
            ->latest('id')
            ->get();
    }

    /**
     * "Use Existing": links a repository document to the requirement (SRS §199.3).
     */
    public function link(User $actor, DocumentRequirement $requirement, Document $document): DocumentRequirement
    {
        if ($requirement->requirement_state === RequirementState::NotRequired) {
            throw new BusinessRuleException(__('This document is not required for the order.'), 'requirement_not_required');
        }

        if ($requirement->order()->value('cancelled_at') !== null) {
            throw new BusinessRuleException(__('The order is cancelled.'), 'order_cancelled');
        }

        if (! $this->candidates($requirement)->contains('id', $document->id)) {
            throw new BusinessRuleException(__('This document cannot be used here: it is of another type, customer or order, or it is rejected or expired.'), 'document_not_linkable');
        }

        $requirement->update(['document_id' => $document->id, 'linked_by' => $actor->id, 'linked_at' => now()]);

        return $requirement;
    }

    /**
     * Links usable reusable documents to still-empty requirements (configurable, SRS §199.3).
     */
    public function autoLink(Order $order, User $actor): int
    {
        $linked = 0;

        foreach ($order->documentRequirements->whereNull('document_id')->where('requirement_state', '!=', RequirementState::NotRequired) as $requirement) {
            $candidate = $this->candidates($requirement)->first(fn (Document $document) => $document->type->is_reusable && $document->isUsable());

            if ($candidate !== null) {
                $requirement->update(['document_id' => $candidate->id, 'linked_by' => $actor->id, 'linked_at' => now()]);
                $linked++;
            }
        }

        return $linked;
    }
}
