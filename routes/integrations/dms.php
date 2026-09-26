<?php

use App\Http\Controllers\DMS\API\DocumentPreviewController;
use App\Http\Controllers\DMS\API\ExternalDocumentController;
use App\Http\Controllers\DMS\API\ExternalSourceTokenController;
use App\Http\Middleware\AuthenticateExternalSource;
use App\Http\Middleware\ResolveExternalSourceSystem;
use App\Http\Controllers\DMS\API\DocumentVerificationController;
use Illuminate\Support\Facades\Route;

/** ==================================================================================================================== */

Route::prefix('dms')->middleware([\App\Http\Middleware\DMSAuthMiddleware::class])->namespace('DMS/API')->group(function () {
    Route::get('preview', [DocumentPreviewController::class, '__invoke']);

    // Callers may name the validation type with a "validation_type" field (a ValidationTypeId);
    // payloads without one fall back to config('dms.verification.default_validation_type').
    Route::post('verification/data', [DocumentVerificationController::class, 'store']);
});

/** ==================================================================================================================== */
// External systems (core banking through the gateway) filing and reading documents. {system} is a
// SourceSystem code; every route below only ever reaches that system's own events and documents.
// Authenticated by the system's service user via a short-lived token (see ExternalSourceTokenController).
Route::prefix('dms/external')->group(function () {
    // Reached through a link from the "link" call, not by the caller directly: signed and short-lived,
    // so it carries no Basic auth.
    Route::get('documents/{documentId}/content', [ExternalDocumentController::class, 'content'])
        ->middleware('signed')
        ->name('dms.external.content');

    // The one call that takes credentials. Throttled per IP; everything else takes the token it returns.
    Route::post('{system}/token', [ExternalSourceTokenController::class, 'store'])->middleware('throttle:10,1');

    Route::prefix('{system}')
        ->middleware([AuthenticateExternalSource::class, ResolveExternalSourceSystem::class])
        ->group(function () {
            Route::get('events', [ExternalDocumentController::class, 'events']);
            Route::get('documents', [ExternalDocumentController::class, 'index']);
            Route::post('documents', [ExternalDocumentController::class, 'store']);
            Route::get('documents/{documentId}', [ExternalDocumentController::class, 'show']);
            Route::post('documents/{documentId}/link', [ExternalDocumentController::class, 'link']);
            Route::post('documents/{documentId}/replace', [ExternalDocumentController::class, 'replace']);
            Route::post('documents/{documentId}/review', [ExternalDocumentController::class, 'review']);
            Route::post('documents/{documentId}/validation', [ExternalDocumentController::class, 'validation']);
        });
});
