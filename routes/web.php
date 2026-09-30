<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\EnquiryAttachmentController;
use App\Http\Controllers\QuotationPrintController;
use App\Http\Controllers\SwitchBranchController;
use App\Livewire\Admin;
use App\Livewire\Auth\ChangePassword;
use App\Livewire\Auth\Login;
use App\Livewire\Crm;
use App\Livewire\Dashboard;
use App\Livewire\Sales;
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

        Route::prefix('crm')->name('crm.')->group(function (): void {
            Route::livewire('/farmers', Crm\Farmers\Index::class)->name('farmers.index');
            Route::livewire('/farmers/{farmer}', Crm\Farmers\Show::class)->name('farmers.show');
            Route::livewire('/enquiries', Crm\Enquiries\Index::class)->name('enquiries.index');
            Route::livewire('/enquiries/create', Crm\Enquiries\Form::class)->name('enquiries.create');
            Route::livewire('/enquiries/{enquiry}', Crm\Enquiries\Show::class)->name('enquiries.show');
            Route::livewire('/enquiries/{enquiry}/edit', Crm\Enquiries\Form::class)->name('enquiries.edit');
            Route::get('/attachments/{attachment}', EnquiryAttachmentController::class)->name('enquiries.attachments.show');
            Route::livewire('/telecaller', Crm\Telecaller\Index::class)->name('telecaller.index');
            Route::livewire('/follow-ups', Crm\FollowUps\Index::class)->name('follow-ups.index');
            Route::livewire('/pipeline', Crm\Pipeline\Index::class)->name('pipeline.index');
            Route::livewire('/reopen-requests', Crm\ReopenRequests\Index::class)->name('reopen-requests.index');
            Route::livewire('/territory', Crm\Territory\Index::class)->name('territory.index');
        });

        Route::prefix('sales')->name('sales.')->group(function (): void {
            Route::livewire('/customers', Sales\Customers\Index::class)->name('customers.index');
            Route::livewire('/customers/{customer}', Sales\Customers\Show::class)->name('customers.show');
            Route::livewire('/quotations', Sales\Quotations\Index::class)->name('quotations.index');
            Route::livewire('/quotations/create', Sales\Quotations\Form::class)->name('quotations.create');
            Route::livewire('/quotations/{quotation}', Sales\Quotations\Show::class)->name('quotations.show');
            Route::livewire('/quotations/{quotation}/edit', Sales\Quotations\Form::class)->name('quotations.edit');
            Route::get('/quotations/{quotation}/print', QuotationPrintController::class)->name('quotations.print');
            Route::livewire('/deals', Sales\Deals\Index::class)->name('deals.index');
            Route::livewire('/deal-approvals', Sales\Deals\Index::class)->name('deal-approvals.index');
            Route::livewire('/deals/{deal}', Sales\Deals\Show::class)->name('deals.show');
        });

        Route::prefix('admin')->name('admin.')->group(function (): void {
            Route::livewire('/users', Admin\Users\Index::class)->name('users.index');
            Route::livewire('/employees', Admin\Employees\Index::class)->name('employees.index');
            Route::livewire('/roles', Admin\Roles\Index::class)->name('roles.index');
            Route::livewire('/roles/{role}', Admin\Roles\Edit::class)->name('roles.edit');
            Route::livewire('/departments', Admin\Departments\Index::class)->name('departments.index');
            Route::livewire('/branches', Admin\Branches\Index::class)->name('branches.index');
            Route::livewire('/geography', Admin\Geography\Index::class)->name('geography.index');
            Route::livewire('/geography/import', Admin\Geography\Import::class)->name('geography.import');
            Route::livewire('/products', Admin\Products\Index::class)->name('products.index');
            Route::livewire('/workflows', Admin\Workflows\Index::class)->name('workflows.index');
            Route::livewire('/workflows/{definition}', Admin\Workflows\Edit::class)->name('workflows.edit');
            Route::livewire('/settings', Admin\Settings\Index::class)->name('settings.index');
            Route::livewire('/audit-logs', Admin\AuditLogs\Index::class)->name('audit-logs.index');
        });
    });
});
