@php use App\Enums\Core\ExtensionsEnum; @endphp
@php use App\Enums\Core\VisibilityEnum; @endphp
@extends('dms.layout')

@section('title','Bulk Upload')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="#">DMS</a></li>
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/libs/dropzone/dropzone.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/css/select2.min.css') }}">
    <style>
        .select2-container {
            width: 100% !important;
        }
        /* Dropzone Styling */
        .dropzone {
            border: 2px dashed #ccc;
            border-radius: 8px;
            min-height: 200px;
            padding: 20px;
            background: #fafafa;
            transition: all 0.3s ease;
        }

        .dropzone.dz-drag-hover {
            border-color: #007bff;
            background: #f0f8ff;
        }

        .dropzone.dz-disabled {
            opacity: 0.6;
            pointer-events: none;
        }

        .dz-preview {
            border-radius: 5px;
            margin: 10px;
            padding: 10px;
            border: 1px solid #e0e0e0;
            background: white;
        }

        .dz-preview.dz-error {
            border-color: #dc3545;
            background: #fff5f5;
        }

        .dz-success-mark, .dz-error-mark {
            display: none; /* Hide default icons */
        }

        /* Upload Button Styling */
        #upload-all-btn {
            transition: all 0.3s ease;
        }

        #upload-all-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* File Preview Styling */
        .dz-filename {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .dz-size {
            color: #666;
            font-size: 12px;
        }

        /* Progress Bar */
        .dz-progress {
            width: 100%;
            height: 5px;
            background: #e0e0e0;
            border-radius: 3px;
            margin-top: 10px;
            overflow: hidden;
        }

        .dropzone .dz-preview .dz-progress {
            top: 60%;
        }

        .dz-upload {
            background: #007bff;
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
    </style>
@endsection
@section('content')
    <div class="row">
        <div class="col-12 file-manger-wrapper">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Upload document</h5>
                    <small class="text-muted">Files upload against the details below - repository and permissions
                        are inherited from the document type.</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="LinkedToTypeId">Linked to <span class="text-danger">*</span></label>
                            <select class="form-control" id="LinkedToTypeId" disabled>
                                <option value="">Select a document type first</option>
                            </select>
                            <p id="LinkedToTypeId_error" class="invalid-feedback d-none error" role="alert"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label d-block">Record <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-outline-secondary btn-sm link-record-btn">
                                <i class="fas fa-link"></i> Link Client, Lead or Employee
                            </button>
                            <div class="row g-2 align-items-end link-record-row d-none">
                                <div class="col-md-4">
                                    <label class="form-label mb-1">Related to</label>
                                    <select class="form-control relation-type" id="Related">
                                        <option value="">— choose type —</option>
                                        @foreach(\App\Http\Controllers\DMS\Files\DocumentRelationOptionsController::RELATION_TYPES as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label mb-1">Record</label>
                                    <select class="form-control relation-target" id="LinkedId"></select>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-outline-danger remove-link-record"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                            <p id="LinkedId_error" class="invalid-feedback d-none error" role="alert"></p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="DocumentTypeId">Document type <span class="text-danger">*</span></label>
                            <select class="form-control" id="DocumentTypeId">
                                <option value="">Select document type</option>
                                @foreach($documentTypes as $type)
                                    <option value="{{ $type->Id }}"
                                            data-repository="{{ $type->repository->RepositoryId ?? $root->RepositoryId }}"
                                            data-category="{{ $type->repository->Name ?? $root->Name }}"
                                            data-tag="{{ $type->tag->Name ?? '' }}"
                                            data-extensions="{{ $type->AllowedExtensions }}"
                                            data-max-size="{{ $type->MaxSizeMB }}"
                                            data-expiry-required="{{ $type->ExpiryRequired ? '1' : '0' }}">{{ $type->Code }} - {{ $type->Name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block" id="DocumentTypeHint">Decides which files are accepted and where they're filed.</small>
                            <small class="text-primary d-none" id="DocumentTypeParams"></small>
                            <p id="DocumentTypeId_error" class="invalid-feedback d-none error" role="alert"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label d-block">Folio (optional)</label>
                            <div class="btn-group btn-group-sm mb-2" role="group" id="FolioMode">
                                <input type="radio" class="btn-check" name="FolioModeOption" id="FolioModeNone" value="none" checked>
                                <label class="btn btn-outline-secondary" for="FolioModeNone">Standalone</label>
                                <input type="radio" class="btn-check" name="FolioModeOption" id="FolioModeNew" value="new">
                                <label class="btn btn-outline-secondary" for="FolioModeNew">New case</label>
                                <input type="radio" class="btn-check" name="FolioModeOption" id="FolioModeExisting" value="existing">
                                <label class="btn btn-outline-secondary" for="FolioModeExisting">Existing case</label>
                            </div>
                            <input type="text" class="form-control d-none" id="FolioCode" readonly placeholder="Select a document type to generate a case reference">
                            <select class="form-control d-none" id="FolioSelect"></select>
                            <input type="hidden" id="FolioId" value="">
                            <small class="text-muted">Only needed if this document belongs to a multi-document case.</small>
                            <p id="FolioId_error" class="invalid-feedback d-none error" role="alert"></p>
                        </div>
                    </div>

                    <div class="row expiry-row d-none">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="ExpiryDate">Expiry date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="ExpiryDate">
                            <small class="text-muted">This document type requires an expiry date.</small>
                            <p id="ExpiryDate_error" class="invalid-feedback d-none error" role="alert"></p>
                        </div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label" for="Notes">Notes (optional)</label>
                        <textarea class="form-control" id="Notes" rows="2" maxlength="2000" placeholder="e.g. Statement period: Jan-Mar 2026"></textarea>
                        <p id="Notes_error" class="invalid-feedback d-none error" role="alert"></p>
                    </div>
                </div>
            </div>

            <div class="card" id="uploadCard">
                <div class="card-header">
                    <h5 class="mb-0">Files</h5>
                </div>

                <div class="card-body p-0 border border-top">
                    <form action="{{ route('files.store', [$root->RepositoryId]) }}"
                          id="upload-form" class="dropzone">@csrf
                    </form>
                    <div class="upload-controls mt-3 mx-3">
                        <button id="cancel-all-btn" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-times"></i> Cancel All
                        </button>
                        <button id="remove-all-btn" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-trash"></i> Remove All
                        </button>
                        <span id="file-count" class="badge badge-info ml-2">0 files</span>
                    </div>
                </div>
            </div>
            <div class="table-responsive card bg-transparent border-0 shadow-none">
                <table class="table table-borderless file-card" style="min-height: 300px;">
                    <tbody id="fileContents"></tbody>
                </table>
                <div class="d-grid text-center" id="filesMessage"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="dmsActionsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">..</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <div class="onboarding-content with-gradient d-none modal-item text-center"
                         id="trashFileModal">
                        <h4 class="text-danger">
                            Trash Document <b class="rm-file-name"></b> ?
                        </h4>
                        <div class="alert alert-warning" role="alert">
                            <b>Note</b>This file will be deleted permanently
                        </div>
                        <form id="trashFileForm" method="post"> @csrf
                            <div class="mt-4">@method('delete')
                                <button type="button" class="btn btn-secondary float-start"
                                        data-bs-dismiss="modal">
                                    no, cancel
                                </button>
                                <button class="btn btn-danger float-end" id="trashFileBtn"
                                        type="submit"><i
                                        class="fas fa-trash"></i> yes, delete
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script src="{{ asset('assets/libs/dropzone/dropzone.min.js') }}"></script>
    <script src="{{ asset('assets/libs/select2/js/select2.full.min.js') }}"></script>
    <script>const $Modal = $('#dmsActionsModal');
        let dropzoneInstance;
        const rootRepositoryCode = '{{ $root->RepositoryId }}';
        const filesStoreUrlTemplate = '{{ route('files.store', ['__REPO__']) }}';
        const folioCreateUrlTemplate = '{{ route('document-types.folios.store', ['documentType' => '__ID__']) }}';
        const linkedToTypesUrlTemplate = '{{ route('document-types.linked-to-types', ['documentType' => '__ID__']) }}';
        const folioOptionsUrl = '{{ route('dms.folios.options') }}';
        const documentRelationOptionsUrl = '{{ route('dms.document-relations.options') }}';
        const defaultMaxFilesize = parseInt('{{ config('app.dms.file_size') }}');

        function initialiseRelationSelect() {
            const $target = $('#LinkedId');

            $target.select2({
                placeholder: 'Search by name or number',
                minimumInputLength: 2,
                ajax: {
                    url: documentRelationOptionsUrl,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({type: $('#Related').val(), q: params.term}),
                    processResults: data => data
                }
            });

            $('#Related').on('change', function () {
                $target.val(null).trigger('change');
            });
        }

        function initialiseFolioSelect() {
            $('#FolioSelect').select2({
                placeholder: 'Search for an existing case by code or name',
                minimumInputLength: 2,
                ajax: {
                    url: folioOptionsUrl,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({q: params.term}),
                    processResults: data => data
                }
            }).on('change', function () {
                $('#FolioId').val($(this).val() || '');
            });
        }

        /**
         * Select2 reads its option list straight off the underlying <select>
         * each time it opens, but a disabled/enabled toggle needs a fresh
         * init to render correctly - simplest reliable way to swap options
         * on a live Select2 is destroy, replace the <option>s, reinit.
         */
        function setLinkedToTypeOptions(html, disabled, placeholder) {
            const $select = $('#LinkedToTypeId');
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            $select.html(html).prop('disabled', disabled)
                .select2({placeholder: placeholder, allowClear: true, width: '100%'});
        }

        function refreshLinkedToTypes() {
            const documentTypeId = $('#DocumentTypeId').val();
            const previous = $('#LinkedToTypeId').val();

            $('#Related').val('').trigger('change');
            $('.link-record-row').addClass('d-none');
            $('.link-record-btn').removeClass('d-none');

            if (!documentTypeId) {
                setLinkedToTypeOptions('<option></option>', true, 'Select a document type first');
                return;
            }

            setLinkedToTypeOptions('<option></option>', true, 'Loading…');

            $.get(linkedToTypesUrlTemplate.replace('__ID__', documentTypeId), function (res) {
                let options = '<option></option>';
                res.data.forEach(type => {
                    options += '<option value="' + type.Id + '" data-source-relation="' + (type.SourceRelation || '') + '">' + type.Name + '</option>';
                });
                setLinkedToTypeOptions(options, false, 'Select linked-to type');
                if (previous && $('#LinkedToTypeId').find('option[value="' + previous + '"]').length) {
                    $('#LinkedToTypeId').val(previous).trigger('change');
                }
            });
        }

        function applySourceRelation() {
            const sourceRelation = $('#LinkedToTypeId').find(':selected').data('source-relation') || '';

            if (!sourceRelation) {
                $('#Related').prop('disabled', false);
                return;
            }

            $('.link-record-btn').addClass('d-none');
            $('.link-record-row').removeClass('d-none');
            $('#Related').val(sourceRelation).prop('disabled', true).trigger('change');
        }

        function expiryRequired() {
            return $('#DocumentTypeId').find(':selected').data('expiry-required') == '1';
        }

        function uploadRules() {
            const mode = $('#FolioMode input:checked').val();
            const folioId = $('#FolioId').val();
            return {
                LinkedToTypeId: $('#LinkedToTypeId').val(),
                Related: $('#Related').val(),
                LinkedId: $('#LinkedId').val(),
                DocumentTypeId: $('#DocumentTypeId').val(),
                FolioId: (mode !== 'none' && folioId && folioId !== 'null' && !isNaN(folioId)) ? folioId : null,
                ExpiryDate: $('#ExpiryDate').val(),
                Notes: $.trim($('#Notes').val()),
            };
        }

        function missingUploadFields() {
            const rules = uploadRules();
            const missing = [];
            if (!rules.LinkedToTypeId) missing.push('Linked to');
            if (!rules.Related || !rules.LinkedId) missing.push('Record (click "Link Client, Lead or Employee")');
            if (!rules.DocumentTypeId) missing.push('Document type');
            if (expiryRequired() && !rules.ExpiryDate) missing.push('Expiry date');
            if ($('#FolioMode input:checked').val() !== 'none' && !rules.FolioId) missing.push('Folio (case)');
            return missing;
        }

        function isReady() {
            return missingUploadFields().length === 0;
        }

        function reevaluateDropzoneFiles() {
            if (!dropzoneInstance) return;
            if (isReady()) {
                dropzoneInstance.files.forEach(file => {
                    if (file.status === Dropzone.REJECTED || file.previewElement?.classList.contains('dz-error')) {
                        file.status = Dropzone.QUEUED;
                        file.accepted = true;
                        file.previewElement?.classList.remove('dz-error', 'dz-complete');
                        const errMsg = file.previewElement?.querySelector('.dz-error-message');
                        if (errMsg) errMsg.remove();
                    }
                });
                if (dropzoneInstance.submitButton) {
                    dropzoneInstance.submitButton.disabled = false;
                }
            }
        }

        function refreshFolio() {
            const mode = $('#FolioMode input:checked').val();
            const documentTypeId = $('#DocumentTypeId').val();

            if (mode !== 'new' || !documentTypeId) {
                return;
            }
            $('#FolioId').val('');
            $('#FolioCode').val('generating…');
            $.post(folioCreateUrlTemplate.replace('__ID__', documentTypeId), {_token: '{{ csrf_token() }}'}, function (res) {
                $('#FolioId').val(res.data.id);
                $('#FolioCode').val(res.data.code);
            });
        }

        Dropzone.options.uploadForm = {
            autoProcessQueue: false, // Disable auto upload
            url: filesStoreUrlTemplate.replace('__REPO__', rootRepositoryCode),
            maxFilesize: defaultMaxFilesize, // MB
            maxFiles: 30, // Optional: limit number of files
            parallelUploads: 3,
            uploadMultiple: false,
            addRemoveLinks: true, // Add remove file links
            dictRemoveFile: "Remove",
            dictCancelUpload: "Cancel",

            acceptedFiles: "{{ implode(", ",ExtensionsEnum::getAllMimeTypes()) }}",

            dictDefaultMessage: `
                        <div class="dropzone-msg dz-message needsclick">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                            <h3>Drop files here or click to upload.</h3>
                            <span class="note needsclick">Fill in Linked to, the linked record and Document type above first - they decide what's accepted and where files are filed.</span>
        </div>`,

            params: function () {
                return uploadRules();
            },

            accept: function (file, done) {
                const missing = missingUploadFields();
                if (missing.length) {
                    done('Missing: ' + missing.join(', '));
                    return;
                }
                done();
            },

            init: function () {
                dropzoneInstance = this;

                const submitButton = document.createElement("button");
                submitButton.id = "upload-all-btn";
                submitButton.className = "btn btn-primary float-end";
                submitButton.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Upload All Files';
                submitButton.disabled = true; // Disable initially

                // Insert button after dropzone
                $('.upload-controls').append(submitButton);

                // Store button reference
                this.submitButton = submitButton;

                // Event listeners
                submitButton.addEventListener("click", () => {
                    const missing = missingUploadFields();
                    if (missing.length) {
                        nWarning('Missing before uploading: ' + missing.join(', ') + '.');
                        return;
                    }
                    this.processQueue();
                });

                // Listen to events to update button state
                this.on("addedfile", (file) => {
                    // Enable button when files are added
                    if (this.files.length > 0) {
                        this.submitButton.disabled = false;
                        this.submitButton.innerHTML = `<i class="fas fa-cloud-upload-alt"></i> Upload ${this.files.length} File(s)`;
                    }
                });

                this.on("removedfile", (file) => {
                    // Update button text or disable if no files
                    if (this.files.length === 0) {
                        this.submitButton.disabled = true;
                        this.submitButton.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Upload All Files';
                    } else {
                        this.submitButton.innerHTML = `<i class="fas fa-cloud-upload-alt"></i> Upload ${this.files.length} File(s)`;
                    }
                });

                this.on("sendingmultiple", () => {
                    // Called when all files are being sent
                    $('.upload-controls').addClass('d-none');
                    this.submitButton.disabled = true;
                    this.submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
                });

                this.on("successmultiple", (files, response) => {
                    // Called when all files uploaded successfully
                    nSuccess(response.message || 'All files uploaded successfully!');
                    appendFiles(response.data, true);

                    // Reset button
                    this.submitButton.disabled = true;
                    this.submitButton.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Upload All Files';

                    // Clear all files from preview
                    this.removeAllFiles(true);
                });

                this.on("errormultiple", (files, response) => {
                    // Handle multiple file errors
                    this.submitButton.disabled = false;
                    this.submitButton.innerHTML = `<i class="fas fa-cloud-upload-alt"></i> Upload ${this.files.length} File(s)`;
                });

                // Individual file success/error
                this.on("success", (file, response) => {
                    // Optional: handle individual file success
                    file.previewElement.classList.add("dz-success");
                });

                this.on("error", (file, message) => {
                    const msg = (typeof message === 'string') ? message : message.message;
                    nWarning(msg + ' : ' + file.name);

                    // Keep error files in the list so user can retry
                    file.previewElement.classList.add("dz-error");

                    // Add retry button to errored files
                    const retryButton = Dropzone.createElement(
                        '<button class="btn btn-sm btn-outline-primary mt-2"><i class="fas fa-redo"></i> Retry</button>'
                    );

                    retryButton.addEventListener("click", (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        // Remove old error message
                        const errorMessage = file.previewElement.querySelector(".dz-error-message");
                        if (errorMessage) errorMessage.remove();

                        // Reset file status
                        file.status = Dropzone.ADDED;
                        file.accepted = true;
                        file.previewElement.classList.remove("dz-error");

                        // Remove retry button
                        retryButton.remove();

                        // Process this single file
                        this.processFile(file);
                    });

                    // Add retry button if not already present
                    if (!file.previewElement.querySelector(".retry-button")) {
                        retryButton.classList.add("retry-button");
                        file.previewElement.querySelector(".dz-details").appendChild(retryButton);
                    }
                });

                this.on("complete", (file) => {
                    // Optional: do something when individual file upload completes
                    if (!file.previewElement.classList.contains("dz-error")) {
                        // Remove successful files after delay
                        setTimeout(() => {
                            this.removeFile(file);
                        }, 2000);
                    }
                    if (dropzoneInstance.getQueuedFiles().length > 0) {
                        // Small delay to ensure proper state transition
                        setTimeout(() => {
                            dropzoneInstance.processQueue();
                        }, 100);
                    }
                });

                this.on("queuecomplete", () => {
                    // Re-enable button when queue completes
                    $('.upload-controls').removeClass('d-none');
                    this.submitButton.disabled = false;
                    this.submitButton.innerHTML = `<i class="fas fa-cloud-upload-alt"></i> Upload ${this.files.length} File(s)`;
                });
            },
            success: function (file, response) {
                file.previewElement.remove();
                // nSuccess(response.message);
                appendFiles(response.data, true);
            }, error: function (file, message) {
                msg = (typeof message === 'string') ? message : message.message
                nWarning(msg + ' : ' + file.name);
                file.previewElement.remove();
            }
        };
        $(function () {
            initialiseRelationSelect();
            initialiseFolioSelect();
            $('#DocumentTypeId').select2({placeholder: 'Select document type', allowClear: true, width: '100%'});
            setLinkedToTypeOptions('<option></option>', true, 'Select a document type first');

            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            $('#ExpiryDate').attr('min', tomorrow.toISOString().slice(0, 10));

            $(document).on('click', '.link-record-btn', function () {
                $(this).addClass('d-none');
                $('.link-record-row').removeClass('d-none');
            });

            $(document).on('click', '.remove-link-record', function () {
                $('#Related').prop('disabled', false).val('').trigger('change');
                $('#LinkedId').val(null).trigger('change');
                $('.link-record-row').addClass('d-none');
                $('.link-record-btn').removeClass('d-none');
                applySourceRelation();
            });

            $('#LinkedToTypeId, #LinkedId, #ExpiryDate').on('change', function () {
                reevaluateDropzoneFiles();
            });
            $('#LinkedToTypeId').on('change', applySourceRelation);

            document.getElementById('cancel-all-btn')?.addEventListener('click', function () {
                if (dropzoneInstance && confirm('Cancel all uploads?')) {
                    dropzoneInstance.removeAllFiles(true); // true = cancel current uploads
                }
            });

            // Remove All Files button
            document.getElementById('remove-all-btn')?.addEventListener('click', function () {
                if (dropzoneInstance && confirm('Remove all files?')) {
                    dropzoneInstance.removeAllFiles();
                }
            });

            // Update file count badge
            if (dropzoneInstance) {
                dropzoneInstance.on("addedfile", updateFileCount);
                dropzoneInstance.on("removedfile", updateFileCount);
                dropzoneInstance.on("queuecomplete", updateFileCount);

                function updateFileCount() {
                    const fileCount = dropzoneInstance.files.length;
                    const badge = document.getElementById('file-count');
                    if (badge) {
                        badge.textContent = fileCount + ' file' + (fileCount !== 1 ? 's' : '');
                    }
                }
            }

            $('#DocumentTypeId').on('change', function () {
                const $opt = $(this).find(':selected');
                const repoCode = $opt.data('repository') || rootRepositoryCode;
                const category = $opt.data('category') || '';
                const tag = $opt.data('tag') || '';
                const extensions = ($opt.data('extensions') || '').toString();
                const maxSize = parseFloat($opt.data('max-size')) || defaultMaxFilesize;
                const action = filesStoreUrlTemplate.replace('__REPO__', repoCode);

                $('#upload-form').attr('action', action);

                if (dropzoneInstance && dropzoneInstance.options) {
                    dropzoneInstance.options.url = action;
                    dropzoneInstance.options.acceptedFiles = extensions
                        ? extensions.split(',').map(ext => '.' + $.trim(ext)).join(',')
                        : null;
                    dropzoneInstance.options.maxFilesize = maxSize;
                }

                if (category) {
                    let text = 'Files into: ' + category;
                    text += tag ? ' · Tagged: ' + tag : ' · No tag applied';
                    text += ' · Max ' + maxSize + 'MB';
                    text += extensions ? ' · Allowed: ' + extensions.split(',').map(ext => '.' + $.trim(ext)).join(', ') : ' · Any extension';
                    $('#DocumentTypeParams').text(text).removeClass('d-none');
                } else {
                    $('#DocumentTypeParams').addClass('d-none');
                }

                if (expiryRequired()) {
                    $('.expiry-row').removeClass('d-none');
                    $('#ExpiryDate').prop('required', true);
                } else {
                    $('.expiry-row').addClass('d-none');
                    $('#ExpiryDate').prop('required', false).val('');
                }

                refreshLinkedToTypes();
                refreshFolio();
                reevaluateDropzoneFiles();
            });

            $('input[name="FolioModeOption"]').on('change', function () {
                const mode = $(this).val();
                $('#FolioId').val('');
                $('#FolioCode').addClass('d-none').val('');
                $('#FolioSelect').addClass('d-none').val(null).trigger('change.select2');

                if (mode === 'new') {
                    $('#FolioCode').removeClass('d-none');
                    refreshFolio();
                } else if (mode === 'existing') {
                    $('#FolioSelect').removeClass('d-none');
                }
                reevaluateDropzoneFiles();
            });

            $(document).on('click', '.file-action-trash', function () {
                $(".modal-title").html('<b class="text-danger">Trash</b>  : ' + $(this).data('title'));
                $("#trashFileForm").attr('action', $(this).data('url'));
                $(".rm-repo-name").html($(this).data('title'));
                $(".modal-item").addClass('d-none');
                $('#trashFileModal').removeClass('d-none');
                $Modal.modal('show');
            });

            $('form#trashFileForm').submit(async function (e) {
                e.preventDefault();
                const response = await saveForm($(this), $('#trashFileBtn'), false, true, true);
                if (response) {
                    $('#' + response.data.id).remove();
                    $Modal.modal('hide');
                }
            });

        });

        function appendFiles(file, prepend = false) {
            let usersContent = '';
            file.users.data.data.forEach(function (user) {
                usersContent += user.image;
            });
            if (file.users.hasMorePages) {
                usersContent += ' <span class="avtar avtar-xs bg-light-primary text-primary">+' + file.users.total + '</span>';
            }
            let tagsContent = '';
            file.tags.data.data.forEach(function (tag) {
                if (tag.visibility.value === '{{ VisibilityEnum::Private->value }}') {
                    tagsContent += '<span class="badge rounded-pill text-bg-primary">' + tag.Name + '</span>'
                } else {
                    tagsContent += '<span class="badge rounded-pill text-bg-danger">' + tag.Name + '</span>'
                }
            });

            let content = '<tr id="' + file.id + '" class="dbl-click-redirect-data" data-dbl_click_url="' + file.links.detail + '"> <td> <div class="d-flex align-items-center"><img src="' + file.type.img + '" alt="user-image" class="wid-35">' +
                '<h6 class="mb-0 ms-2 text-truncate">' + file.name + '</h6> </div> </td> <td>' + file.size.string + '</td> <td>' + file.dated.datetime + '</td>' +
                '<td> <div class="user-group p-1">' + usersContent + '  </div> </td>' +
                '<td> <div class="d-flex flex-wrap gap-2">' + tagsContent + ' </div> </td>' +
                '<td> <ul class="list-inline text-end"> <li class="list-inline-item mx-2"> ' + file.visibility.icon + ' </li>' +
                '<li class="list-inline-item"><div class="dropdown"><a class="avtar avtar-xs btn-link-secondary dropdown-toggle arrow-none" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="material-icons-two-tone f-18">more_vert</i></a><div class="dropdown-menu dropdown-menu-end" style="">' +
                '<a class="dropdown-item" href="' + file.links.detail + '">Details</a> ' +
                '<a class="dropdown-item click-summary-data" href="javascript:void(0)" data-summary_title=" ' + file.type.icon + ' ' + file.name + ' " data-click_url="' + file.links.summary + '"> share </a>' +
                '<a class="dropdown-item file-action-trash" data-title=" ' + file.type.icon + ' ' + file.name + ' " data-url="' + file.links.detail + '" href="#">Delete</a></div></div></li> </ul> </td> </tr>';
            if (prepend) {
                $('#fileContents').prepend(content);
            } else {
                $('#fileContents').append(content);
            }
        }
    </script>
@endsection
