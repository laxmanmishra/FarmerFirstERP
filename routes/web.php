<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\SwitchBranchController;
use App\Livewire\Admin;
use App\Livewire\Auth\ChangePassword;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::livewire('/login', Login::class)->name('login');
});

Route::middleware(['auth', 'auth.session', 'active'])->group(function (): void {
    Route::post('/logout', LogoutController::class)->name('logout');
    Route::livewire('/account/password', ChangePassword::class)->name('password.change');

    Route::middleware('password.changed')->group(function (): void {
        Route::livewire('/dashboard', Dashboard::class)->name('dashboard');
        Route::post('/branch/switch', SwitchBranchController::class)->name('branch.switch');

        Route::prefix('admin')->name('admin.')->group(function (): void {
            Route::livewire('/users', Admin\Users\Index::class)->name('users.index');
            Route::livewire('/employees', Admin\Employees\Index::class)->name('employees.index');
            Route::livewire('/roles', Admin\Roles\Index::class)->name('roles.index');
            Route::livewire('/roles/{role}', Admin\Roles\Edit::class)->name('roles.edit');
            Route::livewire('/departments', Admin\Departments\Index::class)->name('departments.index');
            Route::livewire('/branches', Admin\Branches\Index::class)->name('branches.index');
            Route::livewire('/geography', Admin\Geography\Index::class)->name('geography.index');
            Route::livewire('/settings', Admin\Settings\Index::class)->name('settings.index');
            Route::livewire('/audit-logs', Admin\AuditLogs\Index::class)->name('audit-logs.index');
        });
    });
});
