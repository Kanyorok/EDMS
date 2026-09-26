<?php

use App\Http\Controllers\Api\Auth\OtpController;
use App\Http\Controllers\API\DMS\DocumentApiController;
use App\Http\Controllers\API\Enums\CodeDetailsController;
use App\Http\Controllers\Settings\Codes\ApiCountryController;
use App\Http\Controllers\Settings\Codes\ApiCurrencyController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth/otp')->group(function (): void {
    Route::post('send', [OtpController::class, 'send']);
    Route::post('verify', [OtpController::class, 'verify']);
});

Route::get('health', fn () => response()->json([
    'status' => 'ok',
    'timestamp' => now(),
    'service' => 'DMS API',
]));

Route::middleware(['auth:sanctum', \App\Http\Middleware\VerifiedUser::class])->group(function (): void {
    Route::get('dms/documents', [DocumentApiController::class, 'index']);
    Route::post('dms/documents', [DocumentApiController::class, 'store']);
    Route::get('dms/preview', [DocumentApiController::class, 'preview']);
});

Route::prefix('v1')->group(function (): void {
    Route::get('currencies', [ApiCurrencyController::class, 'list']);
    Route::get('countries', [ApiCountryController::class, 'list']);
    Route::get('countries/{country}/localities', [ApiCountryController::class, 'localities']);
    require __DIR__.'/integrations/dms.php';
});

Route::get('enums/{codeId}', [CodeDetailsController::class, 'index']);
