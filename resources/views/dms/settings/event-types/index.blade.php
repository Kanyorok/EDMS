@extends('dms.layout')

@section('title','Event Types')

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/css/select2.min.css') }}">
    <style>
        .select2-container {
            width: 100% !important;
        }

        #propertiesTable textarea {
            min-width: 180px;
        }
    </style>
@endsection

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="#">DMS</a></li>
    <li class="breadcrumb-item"><a href="#">Settings</a></li>
    <li class="breadcrumb-item"><a href="#">Event Types</a></li>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-0">Event Types</h5>
                            <small class="text-muted">A business occasion in an external system that produces a document: its document type, the screens that can raise it, and the properties the user fills in.</small>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            @can('create', \App\Models\DMS\EventType::class)
                                <button class="btn btn-primary btn-sm add-event-type" type="button">
                                    <i class="fas fa-plus-circle"></i> Add event type
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="eventTypesTable"
                               class="table table-striped dataTable no-footer dtr-inline w-100">
                            <thead>
                            <tr>
                                <th>No.</th>
                                <th>Code</th>
                                <th>{{ __('common.name') }}</th>
                                <th>System</th>
                                <th>Document types</th>
                                <th>Record type</th>
                                <th>Modules</th>
                                <th>Properties</th>
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

    <div class="modal fade" id="EventTypeModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add event type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="eventTypeForm" action="{{ route('event-types.store') }}"> @csrf
                        <input type="hidden" name="_method" id="FormMethod" value="POST">

                        <div class="alert alert-danger d-none" id="eventFormErrors" role="alert"></div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="SourceSystemId">Source system <span class="text-danger">*</span></label>
                                <select class="form-control" name="SourceSystemId" id="SourceSystemId" required>
                                    <option value=""></option>
                                    @foreach($sourceSystems as $system)
                                        <option value="{{ $system->Id }}">{{ $system->Name }} ({{ $system->Code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="Code">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="Code" name="Code" required maxlength="50">
                            </div>
                            <div class="col-md-5 mb-3">
                                <label class="form-label" for="Name">{{ __('common.name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="Name" name="Name" required maxlength="150">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="Description">{{ __('common.description') }}</label>
                            <input type="text" class="form-control" id="Description" name="Description" maxlength="255">
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="DocumentTypeIds">Document types <span class="text-danger">*</span></label>
                                <select class="form-control" name="DocumentTypeIds[]" id="DocumentTypeIds" multiple required>
                                    @foreach($documentTypes as $type)
                                        <option value="{{ $type->Id }}"
                                                data-linked="{{ $type->linkedToTypes->pluck('Id')->implode(',') }}">{{ $type->Name }} ({{ $type->Code }})</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">The documents a person can pick from when filing under this event. Each keeps its own repository, extensions, size limit and expiry rule.</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="LinkedToTypeId">Linked-to type</label>
                                <select class="form-control" name="LinkedToTypeId" id="LinkedToTypeId">
                                    <option value="">— None —</option>
                                    @foreach($linkedToTypes as $linked)
                                        <option value="{{ $linked->Id }}">{{ $linked->Name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="ExternalEntityType">Record type <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ExternalEntityType" name="ExternalEntityType" required maxlength="50" placeholder="ACCOUNT" style="text-transform: uppercase">
                                <small class="text-muted">What the file belongs to in the external system, e.g. ACCOUNT, LOAN, APPLICATION.</small>
                            </div>
                        </div>

                        <h6 class="mb-2">Review &amp; validation</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="ReviewerUserId">Default reviewer</label>
                                <select class="form-control" name="ReviewerUserId" id="ReviewerUserId">
                                    <option value=""></option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->Id }}">{{ $user->Name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="ReviewerTeamId">or default reviewing team</label>
                                <select class="form-control" name="ReviewerTeamId" id="ReviewerTeamId">
                                    <option value=""></option>
                                    @foreach($teams as $team)
                                        <option value="{{ $team->TeamID }}">{{ $team->Name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Any team member can complete the review.</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="ValidationTypeId">Validation type</label>
                                <select class="form-control" name="ValidationTypeId" id="ValidationTypeId">
                                    <option value=""></option>
                                    @foreach($validationTypes as $type)
                                        <option value="{{ $type->Id }}">{{ $type->Name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="text-muted small mt-n2 mb-3">Used when the external system asks for a review or validation of a document. It may name a different reviewer for one request; with none set here and none named, a review cannot be requested.</p>

                        <div class="mb-3">
                            <label class="form-label" for="Modules">Screens that can raise this event</label>
                            <select class="form-control" name="Modules[]" id="Modules" multiple></select>
                            <small class="text-muted">Module IDs in the external system (type a number and press Enter). CBS example: 1330 Account Documents, 1094 Loan Maintenance, 1098 Loan Disbursement.</small>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Properties</h6>
                            <div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="addStandardProperties">
                                    <i class="fas fa-list-check"></i> Add standard CBS properties
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="addProperty">
                                    <i class="fas fa-plus"></i> Add property
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle" id="propertiesTable">
                                <thead>
                                <tr>
                                    <th style="width:22%">Name</th>
                                    <th style="width:22%">Label</th>
                                    <th style="width:16%">Type</th>
                                    <th class="text-center" style="width:8%">Required</th>
                                    <th>Options <small class="text-muted">(list type: one VALUE=Label per line)</small></th>
                                    <th style="width:4%"></th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <p class="text-muted small mb-3" id="noPropertiesHint">No properties yet — the external system will only ask for the file.</p>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="AllowReplace" id="AllowReplace" value="1" checked>
                                    <label class="form-check-label" for="AllowReplace">External system may replace the file with a new version</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="IsActive" id="IsActive" value="1" checked>
                                    <label class="form-check-label" for="IsActive">Active</label>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <div class="mt-4">
                            <button type="button" class="btn btn-secondary float-start" data-bs-dismiss="modal">cancel</button>
                            <button class="btn btn-primary float-end" id="saveEventTypeBtn" type="submit">
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
        let eventTypesTable = null;
        let propertyRowCounter = 0;
        const $eventTypeModal = $('#EventTypeModal');
        const eventTypeStoreUrl = '{{ route('event-types.store') }}';
        const eventTypeIndexUrl = '{{ route('event-types.index') }}';
        const propertyDataTypes = @json($dataTypes);

        // Mirrors the CBS document row (class, location, received by/date) so DMS is complete on its own.
        const standardCbsProperties = [
            {Name: 'cbs.class', Label: 'Document class', DataType: 'ls', IsRequired: true, Options: [
                    {value: 'IDEN', label: 'Identification'}, {value: 'ADDR', label: 'Address'},
                    {value: 'INCM', label: 'Income'}, {value: 'SECU', label: 'Security'}, {value: 'OTHE', label: 'Other'}]},
            {Name: 'cbs.location', Label: 'Location', DataType: 'st', IsRequired: false, Options: null},
            {Name: 'cbs.received_by', Label: 'Received by', DataType: 'st', IsRequired: true, Options: null},
            {Name: 'cbs.received_date', Label: 'Received date', DataType: 'dt', IsRequired: true, Options: null},
            {Name: 'cbs.remarks', Label: 'Remarks', DataType: 'st', IsRequired: false, Options: null},
        ];

        // A document type that restricts its linked-to types can only sit under an event naming one of them, so with
        // several document types chosen only the linked-to types every restricting one allows stay pickable.
        function filterLinkedToTypes() {
            const restricted = $('#DocumentTypeIds option:selected').map(function () {
                return [String($(this).data('linked') || '').split(',').filter(Boolean)];
            }).get().filter(list => list.length > 0);
            const allowed = restricted.length === 0 ? null : restricted.reduce((a, b) => a.filter(x => b.includes(x)));
            $('#LinkedToTypeId option').each(function () {
                const value = String($(this).val());
                $(this).prop('disabled', value !== '' && allowed !== null && !allowed.includes(value));
            });
            if ($('#LinkedToTypeId option:selected').prop('disabled')) {
                $('#LinkedToTypeId').val('');
            }
            $('#LinkedToTypeId').trigger('change.select2');
        }

        function initialiseEventTypeModalSelects() {
            ['SourceSystemId', 'DocumentTypeIds', 'LinkedToTypeId', 'ReviewerUserId', 'ReviewerTeamId', 'ValidationTypeId'].forEach(function (id) {
                const $select = $('#' + id);
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }
                $select.select2({dropdownParent: $eventTypeModal, width: '100%', allowClear: !['SourceSystemId', 'DocumentTypeIds'].includes(id), placeholder: id === 'DocumentTypeIds' ? 'Pick the documents this event offers' : (id === 'SourceSystemId' ? 'Select' : '— None —')});
            });

            const $modules = $('#Modules');
            if ($modules.hasClass('select2-hidden-accessible')) {
                $modules.select2('destroy');
            }
            $modules.select2({dropdownParent: $eventTypeModal, width: '100%', tags: true, tokenSeparators: [',', ' '], placeholder: 'e.g. 1330'});
        }

        function typeOptionsHtml(selected) {
            return Object.entries(propertyDataTypes).map(([value, label]) =>
                `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`).join('');
        }

        function optionsToText(options) {
            return (options || []).map(o => o.value === o.label ? o.value : `${o.value}=${o.label}`).join('\n');
        }

        function addPropertyRow(property) {
            const i = propertyRowCounter++;
            const p = Object.assign({Name: '', Label: '', DataType: 'st', IsRequired: false, Options: null}, property || {});
            const row = $(`
                <tr>
                    <td><input type="text" class="form-control form-control-sm" name="Properties[${i}][Name]" maxlength="100" required placeholder="cbs.received_by"></td>
                    <td><input type="text" class="form-control form-control-sm" name="Properties[${i}][Label]" maxlength="150" required></td>
                    <td><select class="form-select form-select-sm property-type" name="Properties[${i}][DataType]">${typeOptionsHtml(p.DataType)}</select></td>
                    <td class="text-center"><input type="checkbox" class="form-check-input" name="Properties[${i}][IsRequired]" value="1" ${p.IsRequired ? 'checked' : ''}></td>
                    <td><textarea class="form-control form-control-sm property-options" name="Properties[${i}][Options]" rows="2" placeholder="IDEN=Identification"></textarea></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-property"><i class="fas fa-times"></i></button></td>
                </tr>`);
            row.find('input[name$="[Name]"]').val(p.Name);
            row.find('input[name$="[Label]"]').val(p.Label);
            row.find('.property-options').val(optionsToText(p.Options));
            $('#propertiesTable tbody').append(row);
            syncOptionsVisibility(row);
            $('#noPropertiesHint').toggle($('#propertiesTable tbody tr').length === 0);
        }

        function syncOptionsVisibility($row) {
            $row.find('.property-options').prop('disabled', $row.find('.property-type').val() !== 'ls')
                .toggleClass('bg-light', $row.find('.property-type').val() !== 'ls');
        }

        function resetEventTypeForm() {
            const $form = $('#eventTypeForm');
            $form[0].reset();
            $('#eventFormErrors').addClass('d-none').empty();
            $('#propertiesTable tbody').empty();
            $('#noPropertiesHint').show();
            $('#Modules').empty();
            $('#IsActive, #AllowReplace').prop('checked', true);
            propertyRowCounter = 0;
        }

        $(function () {
            $.fn.dataTable.ext.errMode = 'none';
            fetchEventTypesTable();

            $('#DocumentTypeIds').on('change', filterLinkedToTypes);

            $(document).on('click', '.add-event-type', function () {
                resetEventTypeForm();
                $('.modal-title', $eventTypeModal).html('Add event type');
                $('#FormMethod').val('POST');
                $('#eventTypeForm').attr('action', eventTypeStoreUrl);
                initialiseEventTypeModalSelects();
                filterLinkedToTypes();
                $eventTypeModal.modal('show');
            });

            $(document).on('click', '.edit-event-type', function () {
                const event = $(this).data('event');
                resetEventTypeForm();
                $('.modal-title', $eventTypeModal).html('Edit ' + event.Code);
                $('#FormMethod').val('PUT');
                $('#eventTypeForm').attr('action', eventTypeIndexUrl + '/' + event.Id);

                $('#SourceSystemId').val(event.SourceSystemId);
                $('#Code').val(event.Code);
                $('#Name').val(event.Name);
                $('#Description').val(event.Description);
                $('#DocumentTypeIds').val((event.DocumentTypeIds || []).map(String));
                $('#LinkedToTypeId').val(event.LinkedToTypeId || '');
                $('#ReviewerUserId').val(event.ReviewerUserId || '');
                $('#ReviewerTeamId').val(event.ReviewerTeamId || '');
                $('#ValidationTypeId').val(event.ValidationTypeId || '');
                $('#ExternalEntityType').val(event.ExternalEntityType);
                $('#AllowReplace').prop('checked', !!event.AllowReplace);
                $('#IsActive').prop('checked', !!event.IsActive);
                (event.Modules || []).forEach(m => $('#Modules').append(new Option(m, m, true, true)));
                (event.Properties || []).forEach(addPropertyRow);

                initialiseEventTypeModalSelects();
                $('#LinkedToTypeId').val(event.LinkedToTypeId || '').trigger('change.select2');
                $('#ReviewerUserId').val(event.ReviewerUserId || '').trigger('change.select2');
                $('#ReviewerTeamId').val(event.ReviewerTeamId || '').trigger('change.select2');
                $('#ValidationTypeId').val(event.ValidationTypeId || '').trigger('change.select2');
                $eventTypeModal.modal('show');
            });

            $('#addProperty').on('click', () => addPropertyRow());

            $('#addStandardProperties').on('click', function () {
                const existing = $('#propertiesTable input[name$="[Name]"]').map((_, el) => $(el).val()).get();
                standardCbsProperties.filter(p => !existing.includes(p.Name)).forEach(addPropertyRow);
            });

            $(document).on('click', '.remove-property', function () {
                $(this).closest('tr').remove();
                $('#noPropertiesHint').toggle($('#propertiesTable tbody tr').length === 0);
            });

            $(document).on('change', '.property-type', function () {
                syncOptionsVisibility($(this).closest('tr'));
            });

            $(document).on('click', '.delete-event-type', function () {
                const id = $(this).data('id');
                const code = $(this).data('code');

                if (!confirm('Remove event type ' + code + '?')) {
                    return;
                }

                $.ajax({
                    url: eventTypeIndexUrl + '/' + id,
                    method: 'POST',
                    data: {_method: 'DELETE', _token: '{{ csrf_token() }}'},
                    success: function (res) {
                        nSuccess(res.message);
                        fetchEventTypesTable();
                    },
                    error: function (jqXHR) {
                        nWarning(jqXHR.responseJSON?.message || 'removing the event type failed');
                    }
                });
            });

            // Not saveForm(): validation errors here are keyed like Properties.0.Name, which have no
            // matching element id, so they are listed together at the top of the form instead.
            $('form#eventTypeForm').submit(function (e) {
                e.preventDefault();
                const $form = $(this);
                const $btn = $('#saveEventTypeBtn');
                const label = $btn.html();
                const $errors = $('#eventFormErrors').addClass('d-none').empty();

                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> please wait');
                $.ajax({
                    url: $form.attr('action'),
                    type: 'post',
                    dataType: 'json',
                    data: $form.serialize(),
                    success: function (res) {
                        nSuccess(res.message);
                        $eventTypeModal.modal('hide');
                        fetchEventTypesTable();
                    },
                    error: function (jqXHR) {
                        const errors = jqXHR.responseJSON?.errors;
                        if (jqXHR.status === 422 && errors) {
                            const items = Object.values(errors).flat().map(m => $('<li>').text(m));
                            $errors.append($('<ul class="mb-0">').append(items)).removeClass('d-none');
                            $eventTypeModal.animate({scrollTop: 0}, 200);
                        } else {
                            nWarning(jqXHR.responseJSON?.message || 'saving the event type failed');
                        }
                    },
                    complete: function () {
                        $btn.prop('disabled', false).html(label);
                    }
                });
            });
        });

        function fetchEventTypesTable() {
            if (eventTypesTable === null) {
                eventTypesTable = $('#eventTypesTable').DataTable({
                    processing: true,
                    serverSide: true,
                    responsive: true,
                    ajax: {
                        url: eventTypeIndexUrl,
                        error: function (jqXHR) {
                            codeNotify(jqXHR.status);
                        }
                    },
                    columns: [
                        {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                        {data: 'Code', name: 'Code'},
                        {data: 'Name', name: 'Name'},
                        {data: 'System', name: 'System', orderable: false, searchable: false},
                        {data: 'DocumentTypes', name: 'DocumentTypes', orderable: false, searchable: false},
                        {data: 'ExternalEntityType', name: 'ExternalEntityType'},
                        {data: 'Modules', name: 'Modules', orderable: false, searchable: false},
                        {data: 'PropertyCount', name: 'PropertyCount', orderable: false, searchable: false},
                        {data: 'Active', name: 'IsActive', searchable: false},
                        {data: 'action', name: 'action', orderable: false, searchable: false},
                    ],
                    order: [[1, 'asc']],
                    oLanguage: {
                        sEmptyTable: 'no event types defined yet !'
                    }
                });

                eventTypesTable.on('error', function () {
                    nWarning('an issue occurred while loading event types.');
                });
            } else {
                eventTypesTable.ajax.reload();
            }
        }
    </script>
@endsection
