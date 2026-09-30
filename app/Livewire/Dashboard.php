<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
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

        $recentActivity = $user->can('audit.view')
            ? AuditLog::query()->with('user:id,name')->latest('id')->limit(8)->get()
            : collect();

        return view('livewire.dashboard', [
            'user' => $user,
            'organisation' => $organisation,
            'recentActivity' => $recentActivity,
        ]);
    }
}
