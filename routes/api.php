<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\GeographyController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/token', [AuthTokenController::class, 'store'])->middleware('throttle:auth')->name('auth.token');

    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
        Route::post('auth/logout', [AuthTokenController::class, 'destroy'])->name('auth.logout');
        Route::get('me', MeController::class)->name('me');
        Route::get('branches', [BranchController::class, 'index'])->name('branches.index');

        Route::prefix('geography')->name('geography.')->middleware('permission:geography.view')->group(function (): void {
            Route::get('districts', [GeographyController::class, 'districts'])->name('districts');
            Route::get('districts/{district}/tehsils', [GeographyController::class, 'tehsils'])->name('tehsils');
            Route::get('tehsils/{tehsil}/villages', [GeographyController::class, 'villages'])->name('villages');
        });
    });
});
