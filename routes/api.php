<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\EnquiryController;
use App\Http\Controllers\Api\V1\FarmerController;
use App\Http\Controllers\Api\V1\FollowUpController;
use App\Http\Controllers\Api\V1\GeographyController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/token', [AuthTokenController::class, 'store'])->middleware('throttle:auth')->name('auth.token');

    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
        Route::post('auth/logout', [AuthTokenController::class, 'destroy'])->name('auth.logout');
        Route::get('me', MeController::class)->name('me');
        Route::get('branches', [BranchController::class, 'index'])->name('branches.index');

        Route::get('farmers', [FarmerController::class, 'index'])->name('farmers.index');
        Route::post('farmers', [FarmerController::class, 'store'])->name('farmers.store');
        Route::get('farmers/{farmer}', [FarmerController::class, 'show'])->name('farmers.show');

        Route::get('enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
        Route::post('enquiries/duplicates', [EnquiryController::class, 'duplicates'])->name('enquiries.duplicates');
        Route::post('enquiries', [EnquiryController::class, 'store'])->name('enquiries.store');
        Route::get('enquiries/{enquiry}', [EnquiryController::class, 'show'])->name('enquiries.show');

        Route::get('follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index');
        Route::post('follow-ups/{followUp}/complete', [FollowUpController::class, 'complete'])->name('follow-ups.complete');

        Route::prefix('geography')->name('geography.')->middleware('permission:geography.view')->group(function (): void {
            Route::get('districts', [GeographyController::class, 'districts'])->name('districts');
            Route::get('districts/{district}/tehsils', [GeographyController::class, 'tehsils'])->name('tehsils');
            Route::get('tehsils/{tehsil}/villages', [GeographyController::class, 'villages'])->name('villages');
        });
    });
});
