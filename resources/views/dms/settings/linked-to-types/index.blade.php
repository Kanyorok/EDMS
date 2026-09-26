@extends('dms.layout')

@section('title','Linked-to Types')

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
    <li class="breadcrumb-item"><a href="#">Linked-to Types</a></li>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-0">Linked-to Types</h5>
                            <small class="text-muted">Define which business entity types documents can be linked to.</small>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            @can('create', \App\Models\DMS\LinkedToType::class)
                                <button class="btn btn-primary btn-sm add-linked-to-type" type="button">
                                    <i class="fas fa-plus-circle"></i> Add linked-to type
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="linkedToTypesTable"
                               class="table table-striped dataTable no-footer dtr-inline w-100">
                            <thead>
                            <tr>
                                <th>No.</th>
                                <th>Code</th>
                                <th>{{ __('common.name') }}</th>
                                <th>{{ __('common.description') }}</th>
                                <th>Source relation</th>
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

    <div class="modal fade" id="LinkedToTypeModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add linked-to type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="linkedToTypeForm" action="{{ route('linked-to-types.store') }}"> @csrf
                        <input type="hidden" name="_method" id="FormMethod" value="POST">

                        <div class="mb-3">
                            <label class="form-label" for="Code">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="Code" name="Code" required maxlength="50">
                            <p id="Code_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="Name">{{ __('common.name') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="Name" name="Name" required maxlength="150">
                            <p id="Name_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="Description">{{ __('common.description') }}</label>
                            <textarea name="Description" id="Description" rows="2" class="form-control" maxlength="255"></textarea>
                            <p id="Description_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="SourceRelation">Source relation</label>
                            <select class="form-control" name="SourceRelation" id="SourceRelation">
                                <option value="">— None (manual record picker) —</option>
                                @foreach($relationTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Which live-record lookup the upload form's Link Record search uses for this type. Leave unset if none is wired up yet.</small>
                            <p id="SourceRelation_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="IsActive" id="IsActive" value="1" checked>
                            <label class="form-check-label" for="IsActive">Active</label>
                        </div>

                        <hr>
                        <div class="mt-4">
                            <button type="button" class="btn btn-secondary float-start" data-bs-dismiss="modal">cancel</button>
                            <button class="btn btn-primary float-end" id="saveLinkedToTypeBtn" type="submit">
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
        let linkedToTypesTable = null;
        const $linkedToTypeModal = $('#LinkedToTypeModal');
        const linkedToTypeStoreUrl = '{{ route('linked-to-types.store') }}';
        const linkedToTypeIndexUrl = '{{ route('linked-to-types.index') }}';

        /**
         * Select2 sizes itself off the DOM at init time, and this modal is
         * display:none until shown - reinitialising (destroy + rebuild)
         * every time it's about to open keeps the dropdown reliably
         * searchable rather than rendering unstyled.
         */
        function initialiseLinkedToTypeModalSelects() {
            const $select = $('#SourceRelation');
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            $select.select2({
                dropdownParent: $linkedToTypeModal,
                placeholder: '— None (manual record picker) —',
                allowClear: true,
                width: '100%'
            });
        }

        $(function () {
            $.fn.dataTable.ext.errMode = 'none';
            fetchLinkedToTypesTable();
            initialiseLinkedToTypeModalSelects();

            $(document).on('click', '.add-linked-to-type', function () {
                resetLinkedToTypeForm();
                $('.modal-title', $linkedToTypeModal).html('Add linked-to type');
                $('#FormMethod').val('POST');
                $('#linkedToTypeForm').attr('action', linkedToTypeStoreUrl);
                initialiseLinkedToTypeModalSelects();
                $linkedToTypeModal.modal('show');
            });

            $(document).on('click', '.edit-linked-to-type', function () {
                const type = $(this).data('type');
                resetLinkedToTypeForm();
                $('.modal-title', $linkedToTypeModal).html('Edit ' + type.Code);
                $('#FormMethod').val('PUT');
                $('#linkedToTypeForm').attr('action', linkedToTypeIndexUrl + '/' + type.Id);

                $('#Code').val(type.Code);
                $('#Name').val(type.Name);
                $('#Description').val(type.Description);
                $('#SourceRelation').val(type.SourceRelation || '');
                $('#IsActive').prop('checked', !!type.IsActive);
                initialiseLinkedToTypeModalSelects();

                $linkedToTypeModal.modal('show');
            });

            $(document).on('click', '.delete-linked-to-type', function () {
                const id = $(this).data('id');
                const code = $(this).data('code');

                if (!confirm('Remove linked-to type ' + code + '?')) {
                    return;
                }

                $.ajax({
                    url: linkedToTypeIndexUrl + '/' + id,
                    method: 'POST',
                    data: {_method: 'DELETE', _token: '{{ csrf_token() }}'},
                    success: function (res) {
                        nSuccess(res.message);
                        fetchLinkedToTypesTable();
                    },
                    error: function (jqXHR) {
                        nWarning(jqXHR.responseJSON?.message || 'removing the linked-to type failed');
                    }
                });
            });

            $('form#linkedToTypeForm').submit(async function (e) {
                e.preventDefault();
                if (await saveForm($(this), $('#saveLinkedToTypeBtn'), false, true, true)) {
                    fetchLinkedToTypesTable();
                    $linkedToTypeModal.modal('hide');
                }
            });
        });

        function resetLinkedToTypeForm() {
            const $form = $('#linkedToTypeForm');
            $form[0].reset();
            $form.find('.error').addClass('d-none').html('');
            $form.find('.is-invalid').removeClass('is-invalid');
            $('#IsActive').prop('checked', true);
        }

        function fetchLinkedToTypesTable() {
            if (linkedToTypesTable === null) {
                linkedToTypesTable = $('#linkedToTypesTable').DataTable({
                    processing: true,
                    serverSide: true,
                    responsive: true,
                    ajax: {
                        url: linkedToTypeIndexUrl,
                        error: function (jqXHR) {
                            codeNotify(jqXHR.status);
                        }
                    },
                    columns: [
                        {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                        {data: 'Code', name: 'Code'},
                        {data: 'Name', name: 'Name'},
                        {data: 'Description', name: 'Description', searchable: false},
                        {data: 'SourceRelation', name: 'SourceRelation', searchable: false},
                        {data: 'Active', name: 'IsActive', searchable: false},
                        {data: 'action', name: 'action', orderable: false, searchable: false},
                    ],
                    order: [[1, 'asc']],
                    oLanguage: {
                        sEmptyTable: 'no linked-to types defined yet !'
                    }
                });

                linkedToTypesTable.on('error', function () {
                    nWarning('an issue occurred while loading linked-to types.');
                });
            } else {
                linkedToTypesTable.ajax.reload();
            }
        }
    </script>
@endsection
