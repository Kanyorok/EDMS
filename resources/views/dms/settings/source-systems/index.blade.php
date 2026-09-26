@extends('dms.layout')

@section('title','Source Systems')

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
    <li class="breadcrumb-item"><a href="#">Source Systems</a></li>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-0">Source Systems</h5>
                            <small class="text-muted">External systems (e.g. core banking) that send documents to the DMS. Their uploads are filed under the service account.</small>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            @can('create', \App\Models\DMS\SourceSystem::class)
                                <button class="btn btn-primary btn-sm add-source-system" type="button">
                                    <i class="fas fa-plus-circle"></i> Add source system
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="sourceSystemsTable"
                               class="table table-striped dataTable no-footer dtr-inline w-100">
                            <thead>
                            <tr>
                                <th>No.</th>
                                <th>Code</th>
                                <th>{{ __('common.name') }}</th>
                                <th>{{ __('common.description') }}</th>
                                <th>Service account</th>
                                <th>Events</th>
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

    <div class="modal fade" id="SourceSystemModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add source system</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="sourceSystemForm" action="{{ route('source-systems.store') }}"> @csrf
                        <input type="hidden" name="_method" id="FormMethod" value="POST">

                        <div class="mb-3">
                            <label class="form-label" for="Code">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="Code" name="Code" required maxlength="50" placeholder="CBS">
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
                            <label class="form-label" for="ServiceUserId">Service account <span class="text-danger">*</span></label>
                            <select class="form-control" name="ServiceUserId" id="ServiceUserId" required>
                                <option value=""></option>
                                @foreach($users as $user)
                                    <option value="{{ $user->Id }}">{{ $user->Name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Documents this system uploads are recorded as created by this user. The person who acted in the external system is kept as a document property.</small>
                            <p id="ServiceUserId_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="IsActive" id="IsActive" value="1" checked>
                            <label class="form-check-label" for="IsActive">Active</label>
                        </div>

                        <hr>
                        <div class="mt-4">
                            <button type="button" class="btn btn-secondary float-start" data-bs-dismiss="modal">cancel</button>
                            <button class="btn btn-primary float-end" id="saveSourceSystemBtn" type="submit">
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
        let sourceSystemsTable = null;
        const $sourceSystemModal = $('#SourceSystemModal');
        const sourceSystemStoreUrl = '{{ route('source-systems.store') }}';
        const sourceSystemIndexUrl = '{{ route('source-systems.index') }}';

        /** Rebuilt each time the modal opens; select2 mis-sizes itself inside a hidden modal. */
        function initialiseSourceSystemModalSelects() {
            const $select = $('#ServiceUserId');
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            $select.select2({
                dropdownParent: $sourceSystemModal,
                placeholder: 'Pick the service account',
                width: '100%'
            });
        }

        $(function () {
            $.fn.dataTable.ext.errMode = 'none';
            fetchSourceSystemsTable();

            $(document).on('click', '.add-source-system', function () {
                resetSourceSystemForm();
                $('.modal-title', $sourceSystemModal).html('Add source system');
                $('#FormMethod').val('POST');
                $('#sourceSystemForm').attr('action', sourceSystemStoreUrl);
                initialiseSourceSystemModalSelects();
                $sourceSystemModal.modal('show');
            });

            $(document).on('click', '.edit-source-system', function () {
                const system = $(this).data('system');
                resetSourceSystemForm();
                $('.modal-title', $sourceSystemModal).html('Edit ' + system.Code);
                $('#FormMethod').val('PUT');
                $('#sourceSystemForm').attr('action', sourceSystemIndexUrl + '/' + system.Id);

                $('#Code').val(system.Code);
                $('#Name').val(system.Name);
                $('#Description').val(system.Description);
                $('#ServiceUserId').val(system.ServiceUserId);
                $('#IsActive').prop('checked', !!system.IsActive);
                initialiseSourceSystemModalSelects();

                $sourceSystemModal.modal('show');
            });

            $(document).on('click', '.delete-source-system', function () {
                const id = $(this).data('id');
                const code = $(this).data('code');

                if (!confirm('Remove source system ' + code + '?')) {
                    return;
                }

                $.ajax({
                    url: sourceSystemIndexUrl + '/' + id,
                    method: 'POST',
                    data: {_method: 'DELETE', _token: '{{ csrf_token() }}'},
                    success: function (res) {
                        nSuccess(res.message);
                        fetchSourceSystemsTable();
                    },
                    error: function (jqXHR) {
                        nWarning(jqXHR.responseJSON?.message || 'removing the source system failed');
                    }
                });
            });

            $('form#sourceSystemForm').submit(async function (e) {
                e.preventDefault();
                if (await saveForm($(this), $('#saveSourceSystemBtn'), false, true, true)) {
                    fetchSourceSystemsTable();
                    $sourceSystemModal.modal('hide');
                }
            });
        });

        function resetSourceSystemForm() {
            const $form = $('#sourceSystemForm');
            $form[0].reset();
            $form.find('.error').addClass('d-none').html('');
            $form.find('.is-invalid').removeClass('is-invalid');
            $('#IsActive').prop('checked', true);
        }

        function fetchSourceSystemsTable() {
            if (sourceSystemsTable === null) {
                sourceSystemsTable = $('#sourceSystemsTable').DataTable({
                    processing: true,
                    serverSide: true,
                    responsive: true,
                    ajax: {
                        url: sourceSystemIndexUrl,
                        error: function (jqXHR) {
                            codeNotify(jqXHR.status);
                        }
                    },
                    columns: [
                        {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                        {data: 'Code', name: 'Code'},
                        {data: 'Name', name: 'Name'},
                        {data: 'Description', name: 'Description', searchable: false},
                        {data: 'ServiceUser', name: 'ServiceUser', orderable: false, searchable: false},
                        {data: 'Events', name: 'Events', orderable: false, searchable: false},
                        {data: 'Active', name: 'IsActive', searchable: false},
                        {data: 'action', name: 'action', orderable: false, searchable: false},
                    ],
                    order: [[1, 'asc']],
                    oLanguage: {
                        sEmptyTable: 'no source systems defined yet !'
                    }
                });

                sourceSystemsTable.on('error', function () {
                    nWarning('an issue occurred while loading source systems.');
                });
            } else {
                sourceSystemsTable.ajax.reload();
            }
        }
    </script>
@endsection
