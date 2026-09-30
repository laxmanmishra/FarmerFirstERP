<?php

namespace App\Actions\Queries;

use App\Enums\QueryStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\FileQuery;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Queries raised by financers / RTO / insurers on a department file (SRS §71):
 * Open → In Progress → Submitted → Resolved. The caller passes the module permission.
 */
class FileQueryFlow
{
    /**
     * @param  Model  $file  a department file with a `queries()` morph-many relation
     */
    public function raise(User $actor, Model $file, string $permission, string $party, string $subject, ?string $description, ?CarbonInterface $dueDate, ?Employee $assignee): FileQuery
    {
        $this->assertAllowed($actor, $permission);

        if (trim($party) === '' || trim($subject) === '') {
            throw new BusinessRuleException(__('Record who raised the query and what it is about.'), 'query_incomplete');
        }

        if ($dueDate !== null && $dueDate->lt(today())) {
            throw new BusinessRuleException(__('The due date cannot be in the past.'), 'past_due');
        }

        return $file->queries()->create([
            'raised_by_party' => $party,
            'subject' => $subject,
            'description' => $description,
            'status' => QueryStatus::Open,
            'due_date' => $dueDate,
            'assigned_employee_id' => $assignee?->id,
        ]);
    }

    public function update(User $actor, FileQuery $query, string $permission, QueryStatus $status, ?string $response): FileQuery
    {
        $this->assertAllowed($actor, $permission);

        if (! in_array($status, $query->status->next(), true)) {
            throw new BusinessRuleException(__('A query cannot move from :from to :to.', ['from' => $query->status->label(), 'to' => $status->label()]), 'query_transition');
        }

        if ($status === QueryStatus::Resolved && trim((string) ($response ?? $query->response)) === '') {
            throw new BusinessRuleException(__('Record how the query was resolved.'), 'response_required');
        }

        $query->update([
            'status' => $status,
            'response' => ($response !== null && trim($response) !== '') ? $response : $query->response,
            'resolved_at' => $status === QueryStatus::Resolved ? now() : null,
            'resolved_by' => $status === QueryStatus::Resolved ? $actor->id : null,
        ]);

        return $query;
    }

    private function assertAllowed(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new BusinessRuleException(__('You are not allowed to manage queries on this file.'), 'not_allowed');
        }
    }
}
