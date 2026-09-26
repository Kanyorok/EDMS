@extends('dms.layout')

@section('title','Document Types')

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/css/select2.min.css') }}">
    <style>
        .select2-container {
            width: 100% !important;
        }
    </style>
@endsection

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="#">DMS</a></li>
    <li class="breadcrumb-item"><a href="#">Settings</a></li>
    <li class="breadcrumb-item"><a href="#">Document Types</a></li>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-0">Document Types</h5>
                            <small class="text-muted">Manage document types and their upload rules.</small>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            @can('create', \App\Models\DMS\DMSDocumentType::class)
                                <button class="btn btn-primary btn-sm add-document-type" type="button">
                                    <i class="fas fa-plus-circle"></i> Add document type
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="documentTypesTable"
                               class="table table-striped dataTable no-footer dtr-inline w-100">
                            <thead>
                            <tr>
                                <th>No.</th>
                                <th>Document Type ID</th>
                                <th>{{ __('common.name') }}</th>
                                <th>Repository</th>
                                <th>Tags</th>
                                <th>Linked-to types</th>
                                <th>Extensions</th>
                                <th>Active</th>
                                <th>{{ __('common.actions') }}</th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="DocumentTypeModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add document type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="documentTypeForm" action="{{ route('document-types.store') }}"> @csrf
                        <input type="hidden" name="_method" id="FormMethod" value="POST">

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="Code">Document Type ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="Code" name="Code" required maxlength="50" placeholder="e.g. BANK_STMT">
                                <p id="Code_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label" for="Name">{{ __('common.name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="Name" name="Name" required maxlength="150">
                                <p id="Name_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label" for="RepositoryId">Repository</label>
                                <select class="form-control" name="RepositoryId" id="RepositoryId">
                                    <option value="">— None —</option>
                                    @foreach($repositories as $repository)
                                        <option value="{{ $repository->Id }}">{{ $repository->Name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">The repository documents of this type file into.</small>
                                <p id="RepositoryId_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="MaxSizeMB">Max size (MB) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="MaxSizeMB" name="MaxSizeMB" min="1" max="1024" value="10" required>
                                <p id="MaxSizeMB_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="TagId">Tags</label>
                            <select class="form-control" name="TagId" id="TagId">
                                <option value="">— None —</option>
                                @foreach($tags as $tag)
                                    <option value="{{ $tag->Id }}">{{ $tag->Name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Documents uploaded with this type inherit this tag.</small>
                            <p id="TagId_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="EntityApplicability">Entity applicability</label>
                            <select class="form-control" id="EntityApplicability" name="EntityApplicability">
                                <option value="">— None —</option>
                                @foreach($entityApplicabilities as $entity)
                                    <option value="{{ $entity->Description }}">{{ $entity->Description }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Broad category, used only to filter/search documents in the repository browser.</small>
                            <p id="EntityApplicability_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="LinkedToTypeIds">Applicable linked-to types</label>
                            <select class="form-control" name="LinkedToTypeIds[]" id="LinkedToTypeIds" multiple>
                                @foreach($linkedToTypes as $type)
                                    <option value="{{ $type->Id }}">{{ $type->Code }} - {{ $type->Name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Which Linked-to Type(s) this document type may be used against. The upload form's Linked-to Type list is filtered to this set.</small>
                            <p id="LinkedToTypeIds_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="AllowedExtensions">Allowed extensions</label>
                            <select class="form-control" name="AllowedExtensionsList[]" id="AllowedExtensions" multiple></select>
                            <small class="text-muted">Leave empty to allow any extension. Type to add one not in the list.</small>
                            <p id="AllowedExtensions_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="ExpiryRequired" id="ExpiryRequired" value="1">
                                    <label class="form-check-label" for="ExpiryRequired">Expiry required</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="IsActive" id="IsActive" value="1" checked>
                                    <label class="form-check-label" for="IsActive">Active</label>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <div class="mt-4">
                            <button type="button" class="btn btn-secondary float-start" data-bs-dismiss="modal">cancel</button>
                            <button class="btn btn-primary float-end" id="saveDocumentTypeBtn" type="submit">
                                <i class="fas fa-save"></i> save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('assets/libs/select2/js/select2.full.min.js') }}"></script>
    <script>
        let documentTypesTable = null;
        const $documentTypeModal = $('#DocumentTypeModal');
        const documentTypeStoreUrl = '{{ route('document-types.store') }}';
        const documentTypeIndexUrl = '{{ route('document-types.index') }}';
        const allowedExtensions = @json(\App\Enums\Core\ExtensionsEnum::getAll()->reject(fn($e) => $e === \App\Enums\Core\ExtensionsEnum::None)->pluck('value')->values());

        /**
         * Select2 sizes itself off the DOM at init time, and this modal is
         * display:none until shown - initialising once at page load can
         * leave it rendering as if it were a plain, unstyled <select>.
         * Reinitialising (destroy + rebuild) every time the modal is about
         * to open is the reliable fix, same pattern used on the upload
         * form's dynamically-populated selects. AllowedExtensions is
         * deliberately excluded: its tags:true options get custom entries
         * appended to the live DOM when editing a type with an extension
         * outside the base list, and destroying/rebuilding it from the
         * static allowedExtensions list alone would drop those again.
         */
        function initialiseDocumentTypeModalSelects() {
            [$('#RepositoryId'), $('#TagId'), $('#EntityApplicability'), $('#LinkedToTypeIds')]
                .forEach($el => { if ($el.hasClass('select2-hidden-accessible')) { $el.select2('destroy'); } });

            $('#RepositoryId, #TagId, #EntityApplicability').select2({
                dropdownParent: $documentTypeModal,
                placeholder: '— None —',
                allowClear: true,
                width: '100%'
            });

            $('#LinkedToTypeIds').select2({
                dropdownParent: $documentTypeModal,
                placeholder: 'Any linked-to type (unfiltered)',
                width: '100%'
            });
        }

        $(function () {
            $.fn.dataTable.ext.errMode = 'none';
            fetchDocumentTypesTable();

            $('#AllowedExtensions').select2({
                dropdownParent: $documentTypeModal,
                tags: true,
                tokenSeparators: [',', ' '],
                placeholder: 'e.g. pdf, jpg, png',
                data: allowedExtensions.map(ext => ({id: ext, text: '.' + ext}))
            });
            initialiseDocumentTypeModalSelects();

            $(document).on('click', '.add-document-type', function () {
                resetDocumentTypeForm();
                $('.modal-title', $documentTypeModal).html('Add document type');
                $('#FormMethod').val('POST');
                $('#documentTypeForm').attr('action', documentTypeStoreUrl);
                $('#Code').prop('disabled', false);
                initialiseDocumentTypeModalSelects();
                $documentTypeModal.modal('show');
            });

            $(document).on('click', '.edit-document-type', function () {
                const type = $(this).data('type');
                resetDocumentTypeForm();
                $('.modal-title', $documentTypeModal).html('Edit ' + type.Code);
                $('#FormMethod').val('PUT');
                $('#documentTypeForm').attr('action', documentTypeIndexUrl + '/' + type.Id);

                $('#Code').val(type.Code);
                $('#Name').val(type.Name);
                $('#RepositoryId').val(type.RepositoryId || '');
                $('#TagId').val(type.TagId || '');
                $('#EntityApplicability').val(type.EntityApplicability || '');
                $('#LinkedToTypeIds').val((type.LinkedToTypeIds || []).map(String));
                $('#MaxSizeMB').val(type.MaxSizeMB || 10);
                $('#ExpiryRequired').prop('checked', !!type.ExpiryRequired);
                $('#IsActive').prop('checked', !!type.IsActive);
                initialiseDocumentTypeModalSelects();

                const extensions = (type.AllowedExtensions || '').split(',').filter(Boolean);
                extensions.forEach(ext => {
                    if ($('#AllowedExtensions option[value="' + ext + '"]').length === 0) {
                        $('#AllowedExtensions').append(new Option('.' + ext, ext, true, true));
                    }
                });
                $('#AllowedExtensions').val(extensions).trigger('change');

                $documentTypeModal.modal('show');
            });

            $(document).on('click', '.delete-document-type', function () {
                const id = $(this).data('id');
                const code = $(this).data('code');

                if (!confirm('Remove document type ' + code + '?')) {
                    return;
                }

                $.ajax({
                    url: documentTypeIndexUrl + '/' + id,
                    method: 'POST',
                    data: {_method: 'DELETE', _token: '{{ csrf_token() }}'},
                    success: function (res) {
                        nSuccess(res.message);
                        fetchDocumentTypesTable();
                    },
                    error: function (jqXHR) {
                        nWarning(jqXHR.responseJSON?.message || 'removing the document type failed');
                    }
                });
            });

            $('form#documentTypeForm').submit(async function (e) {
                e.preventDefault();

                // select2 posts AllowedExtensionsList[], the request wants a single
                // comma-separated AllowedExtensions field - collapse it here.
                $(this).find('input[name="AllowedExtensions"]').remove();
                $('<input>').attr({type: 'hidden', name: 'AllowedExtensions'})
                    .val(($('#AllowedExtensions').val() || []).join(','))
                    .appendTo($(this));

                if (await saveForm($(this), $('#saveDocumentTypeBtn'), false, true, true)) {
                    fetchDocumentTypesTable();
                    $documentTypeModal.modal('hide');
                }
            });
        });

        function resetDocumentTypeForm() {
            const $form = $('#documentTypeForm');
            $form[0].reset();
            $form.find('.error').addClass('d-none').html('');
            $form.find('.is-invalid').removeClass('is-invalid');
            $('#AllowedExtensions').val(null).trigger('change');
            $('#RepositoryId, #TagId, #EntityApplicability').val('').trigger('change');
            $('#LinkedToTypeIds').val([]).trigger('change');
            $('#IsActive').prop('checked', true);
        }

        function fetchDocumentTypesTable() {
            if (documentTypesTable === null) {
                documentTypesTable = $('#documentTypesTable').DataTable({
                    processing: true,
                    serverSide: true,
                    responsive: true,
                    ajax: {
                        url: documentTypeIndexUrl,
                        error: function (jqXHR) {
                            codeNotify(jqXHR.status);
                        }
                    },
                    columns: [
                        {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                        {data: 'Code', name: 'Code'},
                        {data: 'Name', name: 'Name'},
                        {data: 'Repository', name: 'repository.Name', searchable: false},
                        {data: 'Tags', name: 'tag.Name', searchable: false},
                        {data: 'LinkedToTypes', name: 'linkedToTypes.Code', searchable: false, orderable: false},
                        {data: 'Extensions', name: 'AllowedExtensions', searchable: false},
                        {data: 'Active', name: 'IsActive', searchable: false},
                        {data: 'action', name: 'action', orderable: false, searchable: false},
                    ],
                    order: [[1, 'asc']],
                    oLanguage: {
                        sEmptyTable: 'no document types defined yet !'
                    }
                });

                documentTypesTable.on('error', function () {
                    nWarning('an issue occurred while loading document types.');
                });
            } else {
                documentTypesTable.ajax.reload();
            }
        }
    </script>
@endsection
