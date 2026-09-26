@extends('dms.layout')

@section('title','Registries')

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
    <li class="breadcrumb-item"><a href="{{ route('storage-locations.index') }}">Registry</a></li>
    <li class="breadcrumb-item"><a href="#">Registries</a></li>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-0">Registries</h5>
                            <small class="text-muted">
                                Departments that custody physical records. Each is assigned to a branch;
                                its shelving is then managed from that branch under Storage Locations.
                            </small>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            @can('create', \App\Models\DMS\Registry::class)
                                <button class="btn btn-primary btn-sm add-registry" type="button">
                                    <i class="fas fa-plus-circle"></i> Add Registry
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <table id="registriesTable"
                           class="table table-striped dataTable no-footer dtr-inline w-100 table-responsive">
                        <thead>
                        <tr>
                            <th>No.</th>
                            <th>Code</th>
                            <th>{{ __('common.name') }}</th>
                            <th>Branch</th>
                            <th>Parent</th>
                            <th>Head of Registry</th>
                            <th>Status</th>
                            <th>Archive</th>
                            <th>Locations</th>
                            <th>{{ __('common.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="RegistryModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Registry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="registryForm" action="{{ route('registries.store') }}"> @csrf
                        <input type="hidden" name="_method" id="FormMethod" value="POST">

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="Code">Code</label>
                                <input type="text" class="form-control" id="Code" name="Code"
                                       maxlength="20" placeholder="auto-generated">
                                <small class="text-muted" id="CodeHint">Generated automatically when the registry is created.</small>
                                <p id="Code_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label" for="Name">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="Name" name="Name" required
                                       maxlength="150" placeholder="Head Office Registry">
                                <p id="Name_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="Description">{{ __('common.description') }}</label>
                            <textarea name="Description" id="Description" rows="2" class="form-control"
                                      maxlength="255"></textarea>
                            <p id="Description_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="BranchId">Branch</label>
                                <select class="form-control" name="BranchId" id="BranchId">
                                    <option value="">— unassigned (head office only) —</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->Id }}">{{ $branch->BranchID }} - {{ $branch->Name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Decides which branch may manage its shelving.</small>
                                <p id="BranchId_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="ParentRegistryId">Reports to</label>
                                <select class="form-control" name="ParentRegistryId" id="ParentRegistryId">
                                    <option value="">— none (top level) —</option>
                                    @foreach($parents as $parent)
                                        <option value="{{ $parent->Id }}">{{ $parent->Code }} - {{ $parent->Name }}</option>
                                    @endforeach
                                </select>
                                <p id="ParentRegistryId_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label" for="HodUserID">Head of Registry <span class="text-danger">*</span></label>
                                <select class="form-control" name="HodUserID" id="HodUserID" required></select>
                                <small class="text-muted">Sanctions requests raised against this registry.</small>
                                <p id="HodUserID_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="Visibility">Visibility <span class="text-danger">*</span></label>
                                <select class="form-control" name="Visibility" id="Visibility" required>
                                    <option value="pub" selected>Public</option>
                                    <option value="pri">Private</option>
                                </select>
                                <p id="Visibility_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="IsArchive" id="IsArchive" value="1">
                            <label class="form-check-label" for="IsArchive">
                                Holds archived / semi-current records
                            </label>
                        </div>

                        <hr>
                        <div class="mt-4">
                            <button type="button" class="btn btn-secondary float-start" data-bs-dismiss="modal">
                                cancel
                            </button>
                            <button class="btn btn-primary float-end" id="saveRegistryBtn" type="submit">
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
        let registriesTable = null;
        const $registryModal = $('#RegistryModal');
        const registryStoreUrl = '{{ route('registries.store') }}';
        const registryIndexUrl = '{{ route('registries.index') }}';

        $(function () {
            $.fn.dataTable.ext.errMode = 'none';
            fetchRegistriesTable();

            $('#HodUserID').select2({
                placeholder: 'Select a user',
                minimumInputLength: 2,
                dropdownParent: $registryModal,
                ajax: {
                    url: '{!! route('users.select2') !!}',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({q: $.trim(params.term)}),
                    processResults: data => ({
                        results: $.map(data, item => ({text: item.Name, id: item.UserID}))
                    }),
                    cache: true
                }
            });

            $(document).on('click', '.add-registry', function () {
                resetRegistryForm();
                $('.modal-title').html('Add Registry');
                $('#FormMethod').val('POST');
                $('#registryForm').attr('action', registryStoreUrl);
                $('#Code').prop('disabled', true).val('').attr('placeholder', 'auto-generated');
                $('#CodeHint').text('Generated automatically when the registry is created.');
                $registryModal.modal('show');
            });

            $(document).on('click', '.edit-registry', function () {
                const registry = $(this).data('registry');
                resetRegistryForm();
                $('.modal-title').html('Edit ' + registry.Code);
                $('#FormMethod').val('PUT');
                $('#registryForm').attr('action', registryIndexUrl + '/' + registry.Id);

                $('#Code').prop('disabled', false).val(registry.Code).attr('placeholder', 'HQ-REG');
                $('#CodeHint').text('Renaming rewrites every storage path beneath this registry.');
                $('#Name').val(registry.Name);
                $('#Description').val(registry.Description);
                $('#BranchId').val(registry.BranchId || '');
                $('#Visibility').val(registry.Visibility || 'pub');
                $('#IsArchive').prop('checked', !!registry.IsArchive);

                // A registry may not be offered as its own parent.
                $('#ParentRegistryId option').prop('disabled', false);
                $('#ParentRegistryId option[value="' + registry.Id + '"]').prop('disabled', true);
                $('#ParentRegistryId').val(registry.ParentRegistryId || '');

                if (registry.HodUserID) {
                    const option = new Option(registry.HodName || registry.HodUserID,
                        registry.HodUserID, true, true);
                    $('#HodUserID').append(option).trigger('change');
                }

                $registryModal.modal('show');
            });

            $(document).on('click', '.delete-registry', function () {
                const id = $(this).data('id');
                const code = $(this).data('code');

                if (!confirm('Remove registry ' + code + '?')) {
                    return;
                }

                $.ajax({
                    url: registryIndexUrl + '/' + id,
                    method: 'POST',
                    data: {_method: 'DELETE', _token: '{{ csrf_token() }}'},
                    success: function (res) {
                        nSuccess(res.message);
                        fetchRegistriesTable();
                    },
                    error: function (jqXHR) {
                        nWarning(jqXHR.responseJSON?.message || 'removing the registry failed');
                    }
                });
            });

            $('form#registryForm').submit(async function (e) {
                e.preventDefault();
                if (await saveForm($(this), $('#saveRegistryBtn'), false, true, true)) {
                    fetchRegistriesTable();
                    $registryModal.modal('hide');
                }
            });
        });

        function resetRegistryForm() {
            const $form = $('#registryForm');
            $form[0].reset();
            $form.find('.error').addClass('d-none').html('');
            $form.find('.is-invalid').removeClass('is-invalid');
            $('#HodUserID').empty().trigger('change');
            $('#ParentRegistryId option').prop('disabled', false);
        }

        function fetchRegistriesTable() {
            if (registriesTable === null) {
                registriesTable = $('#registriesTable').DataTable({
                    processing: true,
                    serverSide: true,
                    responsive: true,
                    ajax: {
                        url: registryIndexUrl,
                        error: function (jqXHR) {
                            codeNotify(jqXHR.status);
                        }
                    },
                    columns: [
                        {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                        {data: 'Code', name: 'Code'},
                        {data: 'Name', name: 'Name'},
                        {data: 'Branch', name: 'BranchId', searchable: false},
                        {data: 'Parent', name: 'parent.Code', searchable: false},
                        {data: 'Hod', name: 'hod.Name', searchable: false},
                        {data: 'Status', name: 'status.Description', searchable: false},
                        {data: 'Archive', name: 'IsArchive', searchable: false},
                        {data: 'locations_count', name: 'locations_count', searchable: false},
                        {data: 'action', name: 'action', orderable: false, searchable: false},
                    ],
                    order: [[1, 'asc']],
                    oLanguage: {
                        sEmptyTable: 'no registries defined yet !'
                    }
                });

                registriesTable.on('error', function () {
                    nWarning('an issue occurred while loading registries.');
                });
            } else {
                registriesTable.ajax.reload();
            }
        }
    </script>
@endsection
