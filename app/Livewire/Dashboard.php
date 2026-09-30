<?php

namespace App\Livewire;

use App\Enums\ApprovalStatus;
use App\Enums\Temperature;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\FollowUp;
use App\Models\ReopenRequest;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public function mount(): void
    {
        $this->authorize('dashboard.view');
    }

    public function render(): mixed
    {
        $user = Auth::user()->load(['employee.departments', 'employee.designation', 'employee.manager']);

        $organisation = [];

        if ($user->can('users.view')) {
            $organisation[] = ['label' => __('Active users'), 'value' => User::query()->active()->count(), 'icon' => 'user-circle', 'href' => route('admin.users.index'), 'tone' => 'brand'];
            $organisation[] = ['label' => __('Locked accounts'), 'value' => User::query()->where('locked_until', '>', now())->count(), 'icon' => 'lock', 'href' => route('admin.users.index', ['status' => 'locked']), 'tone' => 'rose'];
        }

        if ($user->can('employees.view')) {
            $organisation[] = ['label' => __('Employees'), 'value' => Employee::query()->active()->count(), 'icon' => 'users', 'href' => route('admin.employees.index'), 'tone' => 'sky'];
        }

        if ($user->can('branches.view')) {
            $organisation[] = ['label' => __('Branches'), 'value' => Branch::query()->active()->count(), 'icon' => 'building', 'href' => route('admin.branches.index'), 'tone' => 'amber'];
        }

        $crm = $this->crmKpis($user);

        $recentActivity = $user->can('audit.view')
            ? AuditLog::query()->with('user:id,name')->latest('id')->limit(8)->get()
            : collect();

        return view('livewire.dashboard', [
            'user' => $user,
            'organisation' => $organisation,
            'crm' => $crm,
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * CRM KPIs computed live from the records the user may see (INV-11, INV-12).
     *
     * @return list<array{label: string, value: int, icon: string, href: string, tone: string}>
     */
    private function crmKpis(User $user): array
    {
        $kpis = [];
        $visible = fn () => Enquiry::query()->visibleTo($user);

        if ($user->canAny(['enquiries.view_own', 'enquiries.view_team', 'enquiries.view_all'])) {
            $kpis[] = ['label' => __('Open enquiries'), 'value' => $visible()->open()->count(), 'icon' => 'inbox', 'href' => route('crm.enquiries.index'), 'tone' => 'brand'];
            $kpis[] = ['label' => __('Awaiting validation'), 'value' => $visible()->open()->whereNull('pipeline_stage_id')->count(), 'icon' => 'phone', 'href' => route('crm.enquiries.index', ['view' => 'validation']), 'tone' => 'amber'];
            $kpis[] = ['label' => __('Active pipeline'), 'value' => $visible()->open()->whereNotNull('pipeline_stage_id')->count(), 'icon' => 'columns', 'href' => route('crm.enquiries.index', ['view' => 'pipeline']), 'tone' => 'sky'];
            $kpis[] = ['label' => __('Extra hot'), 'value' => $visible()->open()->where('temperature', Temperature::ExtraHot)->count(), 'icon' => 'flag', 'href' => route('crm.enquiries.index', ['temperature' => Temperature::ExtraHot->value]), 'tone' => 'rose'];

            $won = WorkflowStage::query()->ofDefinition(WorkflowDefinition::SALES_PIPELINE)->where('is_final', true)->where('is_completion', true)->pluck('id');
            $kpis[] = ['label' => __('Won this month'), 'value' => $visible()->whereIn('pipeline_stage_id', $won)->where('closed_at', '>=', now()->startOfMonth())->count(), 'icon' => 'trophy', 'href' => route('crm.enquiries.index', ['view' => 'closed']), 'tone' => 'brand'];
        }

        if ($user->can('follow_ups.view')) {
            $followUps = fn () => FollowUp::query()->visibleTo($user);
            $kpis[] = ['label' => __('Follow-ups due today'), 'value' => $followUps()->dueToday()->count(), 'icon' => 'calendar', 'href' => route('crm.follow-ups.index'), 'tone' => 'amber'];
            $kpis[] = ['label' => __('Overdue follow-ups'), 'value' => $followUps()->overdue()->count(), 'icon' => 'exclamation', 'href' => route('crm.follow-ups.index', ['tab' => 'overdue']), 'tone' => 'rose'];
        }

        if ($user->can('enquiries.reopen')) {
            $kpis[] = ['label' => __('Reopen requests'), 'value' => ReopenRequest::query()->where('status', ApprovalStatus::Pending)
                ->whereIn('enquiry_id', $visible()->select('id'))->count(), 'icon' => 'refresh', 'href' => route('crm.reopen-requests.index'), 'tone' => 'violet'];
        }

        return $kpis;
    }
}
