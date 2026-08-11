<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Police\ComplaintController as PoliceComplaintController;
use App\Http\Controllers\Police\ExportController;

// ── Auth (public) ──────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('otp/send',      [AuthController::class, 'sendOtp']);
    Route::post('otp/verify',    [AuthController::class, 'verifyOtp']);
    Route::post('logout',        [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::post('police/login',  [AuthController::class, 'policeLogin']);
    Route::post('police/logout', [AuthController::class, 'policeLogout'])->middleware('auth:sanctum');
});

// ── Citizen (OTP auth) ─────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('complaints',     [ComplaintController::class, 'store']);
    Route::get('complaints/{id}', [ComplaintController::class, 'show']);
    Route::get('me/quota',        [ComplaintController::class, 'quota']);
});

// Public complaint tracker (no auth)
Route::get('track/{id}', [ComplaintController::class, 'show']);

// ── Police (credential auth + role) ───────────────────────────────────
Route::prefix('police')
    ->middleware(['auth:sanctum', 'role:officer,supervisor,admin'])
    ->group(function () {
        Route::get('complaints',        [PoliceComplaintController::class, 'index']);
        Route::patch('complaints/{id}', [PoliceComplaintController::class, 'update']);
        Route::get('export/csv',        [ExportController::class, 'csv']);
        Route::get('export/pdf',        [ExportController::class, 'pdf']);
    });