<?php

use App\Http\Controllers\DMS\Dashboard\DocumentDashboardLayoutsController;
use App\Http\Controllers\DMS\Dashboard\DocumentDashboardWidgetsController;
use App\Http\Controllers\DMS\Files\DocumentActionsController;
use App\Http\Controllers\DMS\Files\DocumentActivityController;
use App\Http\Controllers\DMS\Files\DocumentArchiveController;
use App\Http\Controllers\DMS\Files\DocumentCheckOutController;
use App\Http\Controllers\DMS\Files\DocumentController;
use App\Http\Controllers\DMS\Files\DocumentDownloadController;
use App\Http\Controllers\DMS\Files\DocumentFolioController;
use App\Http\Controllers\DMS\Files\DocumentRelationOptionsController;
use App\Http\Controllers\DMS\Files\DocumentSelectController;
use App\Http\Controllers\DMS\Files\DocumentTypeLinkedToTypesController;
use App\Http\Controllers\DMS\Files\FolioOptionsController;
use App\Http\Controllers\DMS\Files\DocumentLegalHoldController as FileLegalHoldController;
use App\Http\Controllers\DMS\Files\DocumentMoveController;
use App\Http\Controllers\DMS\Files\DocumentPermissionController;
use App\Http\Controllers\DMS\Files\DocumentPreviewController;
use App\Http\Controllers\DMS\Files\DocumentPreviewPdfController;
use App\Http\Controllers\DMS\Files\DocumentRawController;
use App\Http\Controllers\DMS\Files\DocumentRecentController;
use App\Http\Controllers\DMS\Files\DocumentTagsController;
use App\Http\Controllers\DMS\Files\DocumentReviewController as FileReviewController;
use App\Http\Controllers\DMS\Files\DocumentUploadController;
use App\Http\Controllers\DMS\Files\DocumentValidationController as FileValidationController;
use App\Http\Controllers\DMS\Files\DocumentVersionPreviewPdfController;
use App\Http\Controllers\DMS\Files\DocumentVersionRawController;
use App\Http\Controllers\DMS\Files\DocumentVersionsController;
use App\Http\Controllers\DMS\Files\TrashDocumentController;
use App\Http\Controllers\DMS\LegalHold\DocumentLegalHoldController;
use App\Http\Controllers\DMS\LegalHold\LegalHoldController;
use App\Http\Controllers\DMS\PhysicalFileActivityController;
use App\Http\Controllers\DMS\PhysicalFileCheckoutController;
use App\Http\Controllers\DMS\PhysicalFileController;
use App\Http\Controllers\DMS\PhysicalFileMovementController;
use App\Http\Controllers\DMS\PhysicalFilePermissionController;
use App\Http\Controllers\DMS\PhysicalFileRequestController;
use App\Http\Controllers\DMS\Repo\RepositoryController;
use App\Http\Controllers\DMS\Repo\RepositoryMoveController;
use App\Http\Controllers\DMS\Repo\RepositoryPermissionController;
use App\Http\Controllers\DMS\ReportsController;
use App\Http\Controllers\DMS\Review\DocumentReviewAnnotationController;
use App\Http\Controllers\DMS\Review\DocumentReviewController;
use App\Http\Controllers\DMS\SearchController;
use App\Http\Controllers\DMS\Settings\DocumentTypeController as DMSDocumentTypeController;
use App\Http\Controllers\DMS\Settings\EventTypeController;
use App\Http\Controllers\DMS\Settings\LinkedToTypeController;
use App\Http\Controllers\DMS\Settings\SourceSystemController;
use App\Http\Controllers\DMS\Settings\RegistryController;
use App\Http\Controllers\DMS\Settings\RegistryPermissionController;
use App\Http\Controllers\DMS\Settings\StorageLocationController;
use App\Http\Controllers\DMS\Tags\DocumentTagController;
use App\Http\Controllers\DMS\Tags\TagController;
use App\Http\Controllers\DMS\Tags\TaggingRuleController;
use App\Http\Controllers\DMS\Verification\DocumentValidationController;
use Illuminate\Support\Facades\Route;

// Used by DocumentService::preview() to stream raw file bytes for inline <img>/<video>/<iframe>
// previews. Deliberately outside the DMS module-permission group below: it's embedded from many
// pages (Supplier docs, HR disciplinary records, CRM documents, etc.) whose own controllers have
// already authorized access to this specific document via their own checks before minting the
// signed URL - the signature is the authorization here, scoped to one document for a few minutes.
Route::get('dms/document/{document}/raw', [DocumentRawController::class, 'show'])
    ->middleware('signed')
    ->name('dms.file.raw');

// Same signed-URL-is-the-authorization pattern as dms.file.raw above, but streams a
// LibreOffice-converted PDF rendition for Word/RTF/ODT documents so they can be embedded
// in an <iframe> the same way native PDFs are, instead of falling back to "no preview".
Route::get('dms/document/{document}/preview-pdf', [DocumentPreviewPdfController::class, 'show'])
    ->middleware('signed')
    ->name('dms.file.preview-pdf');

// Same signed-URL-is-the-authorization pattern as above, scoped to one historical version so
// the version history panel can preview an older version without checking it out or touching
// the current file.
Route::get('dms/document/{document}/version/{version}/raw', [DocumentVersionRawController::class, 'show'])
    ->middleware('signed')
    ->name('dms.file.version-raw');

Route::get('dms/document/{document}/version/{version}/preview-pdf', [DocumentVersionPreviewPdfController::class, 'show'])
    ->middleware('signed')
    ->name('dms.file.version-preview-pdf');

Route::middleware(['module:700000'])->namespace('DMS')->prefix('dms')->group(function () {
    Route::get('search', SearchController::class)->name('dms.search');
    Route::get('recent', DocumentRecentController::class)->name('repo.recent');
    Route::get('bulk-upload', DocumentUploadController::class)->name('files.upload');
    Route::post('document-types/{documentType}/folios', DocumentFolioController::class)->name('document-types.folios.store');
    Route::get('document-types/{documentType}/linked-to-types', DocumentTypeLinkedToTypesController::class)->name('document-types.linked-to-types');
    Route::get('folios/options', FolioOptionsController::class)->name('dms.folios.options');
    Route::get('document-relations/options', DocumentRelationOptionsController::class)->name('dms.document-relations.options');
    Route::get('documents/select2', DocumentSelectController::class)->name('documents.select2');
    Route::prefix('document/{document}')->group(function () {
        //mark for validation -> ()
        Route::get('legal-holds', FileLegalHoldController::class)->name('file.legal-holds');
        Route::get('activities', DocumentActivityController::class)->name('file.activities');
        Route::get('embed-preview', DocumentPreviewController::class)->name('file.embed-preview');
        Route::get('preview', [DocumentActionsController::class, 'preview'])->name('file.preview');
        Route::put('file-visibility', [DocumentPermissionController::class, 'visibility'])->name('file.visibility');
        Route::resource('file-validation', FileValidationController::class)->only(['index', 'store']);
        Route::resource('file-move', DocumentMoveController::class)->only(['index', 'store']);
        Route::resource('file-download', DocumentDownloadController::class)->only(['index', 'store']);
        Route::resource('document-checkouts', DocumentCheckOutController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('document-archive', DocumentArchiveController::class)->only(['store', 'destroy']);
        Route::resource('document-tags', DocumentTagsController::class)->only(['create', 'store']);
        Route::get('document-versions', [DocumentVersionsController::class, 'index'])->name('document-versions.index');
        Route::get('document-versions/{version}/preview', [DocumentVersionsController::class, 'preview'])->name('document-versions.preview');
        Route::resource('document-review', FileReviewController::class)->only(['index', 'store']);
        Route::resource('file-permissions', DocumentPermissionController::class)->only(['index', 'store', 'destroy']);
    });

    Route::prefix('review')->group(function () {
        Route::get('/', [DocumentReviewController::class, 'index'])->name('dms.review.index');
        Route::get('{documentReview}', [DocumentReviewController::class, 'show'])->name('dms.review.show');
        Route::put('{documentReview}', [DocumentReviewController::class, 'update'])->name('dms.review.update');
        Route::post('{documentReview}/annotations', [DocumentReviewAnnotationController::class, 'store'])->name('dms.review.annotations.store');
        Route::delete('{documentReview}/annotations/{annotation}', [DocumentReviewAnnotationController::class, 'destroy'])->name('dms.review.annotations.destroy');
    });

    Route::prefix('repo/{repository}')->group(function () {
        Route::put('repo-visibility', [RepositoryPermissionController::class, 'visibility'])->name('repo.visibility');

        Route::resource('repo-permissions', RepositoryPermissionController::class)->only(['index', 'store', 'destroy']);
        Route::resource('repo-move', RepositoryMoveController::class)->only(['index', 'store']);
        Route::resource('files', DocumentController::class)->parameters(['files' => 'document'])->except('create');
    });
    Route::resource('repo', RepositoryController::class)->parameters(['repo' => 'repository'])->except('create');

    Route::put('validation/{documentValidation}/approve', [DocumentValidationController::class, 'approve'])->name('dms.validation.approve');
    Route::put('validation/{documentValidation}/reject', [DocumentValidationController::class, 'reject'])->name('dms.validation.reject');
    Route::put('validation/{documentValidation}/resubmit', [DocumentValidationController::class, 'resubmit'])->name('dms.validation.resubmit');
    Route::resource('validation', DocumentValidationController::class)->names([
        'index' => 'dms.validation.index',
        'show' => 'dms.validation.show',
        'update' => 'dms.validation.update',
        'destroy' => 'dms.validation.destroy',
    ])->only(['index', 'show', 'update', 'destroy']);

    Route::prefix('file-tags/{d_m_s_tags}')->group(function () {
        Route::get('files', DocumentTagController::class)->name('file-tags.files');
        Route::resource('tagging-rules', TaggingRuleController::class)
            ->parameters(['tagging-rules' => 'document_tagging_rules'])->except(['show', 'edit', 'update']);
    });
    Route::resource('file-tags', TagController::class)->parameters(['file-tags' => 'd_m_s_tags'])->except('edit');

    Route::prefix('legal-hold/{d_m_s_legal_hold}')->namespace('LegalHold')->group(function () {
        Route::get('activities', 'LegalHoldActivityController')->name('hold-files.activities');
        Route::resource('hold-files', DocumentLegalHoldController::class)->parameters(['hold-files' => 'document'])->except('edit');
        Route::post('release', [LegalHoldController::class, 'release'])->name('legal-hold.release');
    });
    Route::get('legal-hold/create-document', [LegalHoldController::class, 'createDocument'])->name('legal-hold.create-document');
    Route::get('legal-hold/document-status', [LegalHoldController::class, 'documentStatus'])->name('legal-hold.document-status');
    Route::post('legal-hold/lock-document', [LegalHoldController::class, 'lockDocument'])->name('legal-hold.lock-document');
    Route::post('legal-hold/unlock-document', [LegalHoldController::class, 'unlockDocument'])->name('legal-hold.unlock-document');
    Route::resource('legal-hold', LegalHoldController::class)->parameters(['legal-hold' => 'dMSLegalHold'])->except('edit');

    Route::resource('document-trashed', TrashDocumentController::class)->parameters(['document-trashed' => 'document'])
        ->names([
            'update' => 'document-trashed.restore',
        ])->only(['index', 'store', 'update', 'destroy']);

    //settings
    // Physical storage locations. The registry in play arrives as a query
    // parameter and is checked against the login branch on every action, so
    // these cannot be reduced to a plain nested resource.
    Route::get('storage-locations/parents', [StorageLocationController::class, 'parents'])
        ->name('storage-locations.parents');
    Route::get('storage-locations/tree', [StorageLocationController::class, 'tree'])
        ->name('storage-locations.tree');
    Route::get('storage-locations/registries', [StorageLocationController::class, 'registries'])
        ->name('storage-locations.registries');
    Route::get('registry/requests', [PhysicalFileRequestController::class, 'index'])
        ->name('registry.requests.index');
    Route::post('registry/requests', [PhysicalFileRequestController::class, 'store'])
        ->name('registry.requests.store');
    Route::get('registry/requests/{physicalFileRequest}/history', [PhysicalFileRequestController::class, 'history'])
        ->name('registry.requests.history');
    Route::put('registry/requests/{physicalFileRequest}', [PhysicalFileRequestController::class, 'update'])
        ->name('registry.requests.update');
    // Handing the file over and taking an approval back are separate acts on a
    // decided request, not further decisions, so each has its own endpoint.
    Route::put('registry/requests/{physicalFileRequest}/handover', [PhysicalFileRequestController::class, 'handover'])
        ->name('registry.requests.handover');
    Route::put('registry/requests/{physicalFileRequest}/cancel', [PhysicalFileRequestController::class, 'cancel'])
        ->name('registry.requests.cancel');
    // Closing a loan answers to the checkout, not to the request that opened it.
    Route::put('registry/checkouts/{physicalFileCheckout}/checkin', [PhysicalFileCheckoutController::class, 'checkin'])
        ->name('registry.checkouts.checkin');
    Route::get('registry/physical-files/relation-options', [PhysicalFileController::class, 'relationOptions'])
        ->name('registry.physical-files.relation-options');
    Route::get('registry/physical-files', [PhysicalFileController::class, 'index'])
        ->name('registry.physical-files.index');
    Route::post('registry/physical-files', [PhysicalFileController::class, 'store'])
        ->name('registry.physical-files.store');
    Route::put('registry/physical-files/movements/{physicalFileMovement}/receive', [PhysicalFileController::class, 'receive'])
        ->name('registry.physical-files.receive');
    // Registered after relation-options so that static segment still wins.
    Route::get('registry/physical-files/{physicalFile}', [PhysicalFileController::class, 'show'])
        ->name('registry.physical-files.show');
    Route::get('registry/physical-files/{physicalFile}/documents', [PhysicalFileController::class, 'documents'])
        ->name('registry.physical-files.documents.index');
    Route::get('registry/physical-files/{physicalFile}/documents-panel', [PhysicalFileController::class, 'documentsPanel'])
        ->name('registry.physical-files.documents.panel');
    Route::post('registry/physical-documents/{physicalDocument}/soft-copy', [PhysicalFileController::class, 'uploadSoftCopy'])
        ->name('registry.physical-documents.soft-copy');
    Route::get('registry/physical-files/{physicalFile}/activities', PhysicalFileActivityController::class)
        ->name('registry.physical-files.activities');
    Route::get('registry/physical-files/{physicalFile}/movements', PhysicalFileMovementController::class)
        ->name('registry.physical-files.movements');
    Route::get('registry/physical-files/{physicalFile}/permissions', [PhysicalFilePermissionController::class, 'index'])
        ->name('registry.physical-files.permissions.index');
    Route::post('registry/physical-files/{physicalFile}/permissions', [PhysicalFilePermissionController::class, 'store'])
        ->name('registry.physical-files.permissions.store');
    Route::delete('registry/physical-files/{physicalFile}/permissions/{permission}', [PhysicalFilePermissionController::class, 'destroy'])
        ->name('registry.physical-files.permissions.destroy');
    Route::post('registry/physical-files/{physicalFile}/documents', [PhysicalFileController::class, 'storeDocuments'])
        ->name('registry.physical-files.documents.store');
    Route::resource('storage-locations', StorageLocationController::class)
        ->parameters(['storage-locations' => 'storageLocation'])
        ->only(['index', 'store', 'update', 'destroy']);

    // Sharing a registry is a branch matter, not a head-office one: it decides
    // who may work the registry, so it sits outside the resource below.
    Route::get('registries/{registry}/permissions', [RegistryPermissionController::class, 'index'])
        ->name('registries.permissions.index');
    Route::post('registries/{registry}/permissions', [RegistryPermissionController::class, 'store'])
        ->name('registries.permissions.store');
    Route::delete('registries/{registry}/permissions/{permission}', [RegistryPermissionController::class, 'destroy'])
        ->name('registries.permissions.destroy');

    // Registries themselves are head-office-only; the check is in the
    // controller rather than middleware because it needs the login branch.
    Route::resource('registries', RegistryController::class)
        ->parameters(['registries' => 'registry'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('linked-to-types', LinkedToTypeController::class)
        ->parameters(['linked-to-types' => 'linkedToType'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('document-types', DMSDocumentTypeController::class)
        ->parameters(['document-types' => 'documentType'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('source-systems', SourceSystemController::class)
        ->parameters(['source-systems' => 'sourceSystem'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('event-types', EventTypeController::class)
        ->parameters(['event-types' => 'eventType'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('document-signature/{dMSSignature}/documents', \App\Http\Controllers\DMS\Settings\SignatureDocumentsController::class)->name('document-signature.documents');
    Route::resource('document-signature', \App\Http\Controllers\DMS\Settings\SignatureController::class)->parameters(['document-signature' => 'dMSSignature']);//->except('edit');

    Route::resource('document-validation-type/{documentValidationType}/doc-validation-type-approvers', \App\Http\Controllers\DMS\Settings\ValidationTypeApproverController::class)->only(['index', 'store', 'destroy']);
    Route::put('document-validation-type/{documentValidationType}/workflow', [\App\Http\Controllers\DMS\Settings\DocumentValidationTypeController::class, 'workflow'])->name('document-validation-type.workflow');
    Route::resource('document-validation-type', \App\Http\Controllers\DMS\Settings\DocumentValidationTypeController::class)->parameters(['document-signature' => 'documentValidationType'])->except(['create', 'edit']);

    //DMSDashboardWidgets
    Route::get('documentmanagementdashboard', [DocumentDashboardWidgetsController::class, 'index'])->name('documentdashboardwidgets.index');
    Route::get('documentmanagementdashboard/create', [DocumentDashboardWidgetsController::class, 'create'])->name('documentdashboardwidgets.create');
    Route::get('documentmanagementdashboard/edit/{Id}', [DocumentDashboardWidgetsController::class, 'edit'])->name('documentdashboardwidgets.edit');
    Route::post('documentmanagementdashboard/store', [DocumentDashboardWidgetsController::class, 'store'])->name('documentdashboardwidgets.store');
    Route::put('documentmanagementdashboard/widgets/update/{Id}', [DocumentDashboardWidgetsController::class, 'updateWidget'])->name('documentdashboardwidgets.update');
    Route::delete('documentmanagementdashboard/widgets/delete/{Id}', [DocumentDashboardWidgetsController::class, 'deleteWidget'])->name('documentdashboardwidgets.delete');

    //DMSDashboardWidgets
    Route::get('documentdashboardlayouts/create', [DocumentDashboardLayoutsController::class, 'create'])->name('documentdashboardlayouts.create');
    Route::put('documentdashboardlayouts/save-layout', [DocumentDashboardLayoutsController::class, 'saveLayout'])->name('documentdashboardlayouts.saveLayout');

    //reports
    Route::get('reports/{report}/{format}', [ReportsController::class, 'export'])->name('dms-reports.export');
    Route::resource('reports', ReportsController::class)->only(['index', 'show'])->names([
        'index' => 'dms-reports.index',
        'show' => 'dms-reports.show',
    ]);
});
