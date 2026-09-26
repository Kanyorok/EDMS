@extends('layouts.app')
@section('title', 'Approval Configuration')

@section('content')
{{-- Sidebar navigation only returns this section, so keep widget styles with the content. --}}
<link href="{{ asset('assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" />
<link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Approval Configuration</h4>
    <button class="btn btn-primary modal-create-approval" type="button">
        <i class="fas fa-plus-circle"></i> New Approval WorkFlow
    </button>
</div>

<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="mainModuleFilter" class="form-label">Main Module</label>
                        <select id="mainModuleFilter" class="form-select">
                            <option value="">All</option>
                            @foreach($mainModules as $mainModule)
                            <option value="{{ $mainModule->Name }}">{{ $mainModule->Name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="missingFinalStageFilter">
                            <label class="form-check-label" for="missingFinalStageFilter">
                                Show only workflows missing a final stage
                            </label>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="workflowsTable" class="table table-bordered table-hover">
                        <thead class="table-secondary">
                            <tr>
                                <th>#</th>
                                <th>Section Name</th>
                                <th>{{ __('common.description') }}</th>
                                <th>Main Module</th>
                                <th>Final Stage</th>
                                <th style="width: 150px;">{{ __('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($workFlowGroups as $group)
                            <tr class="{{ $group->IsFinalStage ? '' : 'table-warning' }}">
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $group->Name }}</td>
                                <td>{{ $group->Description }}</td>
                                <td>{{ $group->mainModule->Name ?? '' }}</td>
                                <td>
                                    @if($group->IsFinalStage)
                                        <span class="badge bg-success">Configured</span>
                                    @else
                                        <span class="badge bg-danger" title="No stage in this workflow is marked as the final stage">
                                            <i class="fas fa-exclamation-triangle"></i> Missing
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('settings.workflows.show', $group->Id) }}" class="btn btn-outline-secondary" title="{{ __('common.view') }}">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-outline-primary btn-edit"
                                            data-id="{{ $group->Id }}"
                                            data-name="{{ $group->Name }}"
                                            data-description="{{ $group->Description }}"
                                            data-doc="{{ $tableToAlias[$group->Source] ?? '' }}"
                                            data-module="{{ $moduleBySource[$group->Source] ?? '' }}"
                                            data-main-module="{{ $group->MainModuleID ?? '' }}" title="{{ __('common.edit') }}">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="{{ route('settings.workflows.destroy', $group->Id) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="{{ __('common.delete') }}"
                                                onclick="return confirm('Delete this approval group?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal --}}
<div class="modal fade" id="ApprovalModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form method="POST" id="approvalGroupForm">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Approval WorkFlow</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="Name">Name <span class="text-danger">*</span></label>
                        <input type="text" name="Name" id="Name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="Description">Description <span class="text-danger">*</span></label>
                        <textarea name="Description" id="Description" rows="3" class="form-control" maxlength="1000" required></textarea>
                        <p id="Description_error" class="invalid-feedback d-none error" role="alert"></p>
                    </div>

                    <div class="mb-3">
                        <label for="DocType">Document Type <span class="text-danger">*</span></label>
                        <select name="DocType" id="DocType" class="form-control select2" required>
                            <option value="">-- Select --</option>
                            @foreach($labeledSourceOptions as $option)
                            <option value="{{ $option['alias'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="ModuleID">Module (Required for new sources)</label>
                        <select name="ModuleID" id="ModuleID" class="form-control select2">
                            <option value="">-- Select Module --</option>
                            @foreach($modules as $module)
                            <option value="{{ $module->ModuleID }}">{!! $module->indentation !!}{{ $module->Name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Select the module this document belongs to. Required if not already configured.</small>
                    </div>

                    <div class="mb-3">
                        <label for="MainModuleID">Main Module</label>
                        <select name="MainModuleID" id="MainModuleID" class="form-control select2">
                            <option value="">-- Select Main Module --</option>
                            @foreach($mainModules as $mainModule)
                            <option value="{{ $mainModule->ModuleID }}">{{ $mainModule->Name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Optional. Used to categorize and filter this workflow by its main module.</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button class="btn btn-primary" type="submit" id="modalSubmitBtn">{{ __('common.save') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const updateRouteTemplate = "{{ route('settings.workflows.update', ['id' => '__ID__']) }}";

    function loadScriptOnce(src, callback) {
        const existing = document.querySelector('script[data-once-src="' + src + '"]');
        if (existing) {
            if (existing.dataset.loaded) {
                callback();
            } else {
                existing.addEventListener('load', callback, { once: true });
            }
            return;
        }
        const s = document.createElement('script');
        s.src = src;
        s.dataset.onceSrc = src;
        s.onload = function() {
            s.dataset.loaded = '1';
            callback();
        };
        document.head.appendChild(s);
    }

    function ensureDependencies(callback) {
        if (typeof $.fn.DataTable === 'function' && typeof $.fn.select2 === 'function') {
            callback();
            return;
        }
        const ensureSelect2 = function() {
            if (typeof $.fn.select2 === 'function') {
                callback();
            } else {
                loadScriptOnce("{{ asset('assets/libs/select2/js/select2.min.js') }}", callback);
            }
        };

        if (typeof $.fn.DataTable === 'function') {
            ensureSelect2();
        } else {
            loadScriptOnce("{{ asset('assets/libs/dataTables/jquery.dataTables.min.js') }}", ensureSelect2);
        }
    }

    function initApprovalConfigPage() {
        const $modal = $('#ApprovalModal');
        if (!$modal.length || $modal.data('approval-config-initialized')) {
            return;
        }
        $modal.data('approval-config-initialized', true);

        const $form = $('#approvalGroupForm');
        const $submitBtn = $('#modalSubmitBtn');

        if ($.fn.DataTable.isDataTable('#workflowsTable')) {
            $('#workflowsTable').DataTable().destroy();
        }

        const workflowsTable = $('#workflowsTable').DataTable({
            columnDefs: [
                { targets: 0, orderable: false, searchable: false },
                { targets: -1, orderable: false, searchable: false }
            ],
            order: [[1, 'asc']],
            pageLength: 10,
            language: {
                search: "Search:",
                searchPlaceholder: "Search workflows...",
                emptyTable: "No approval workflows configured yet."
            }
        });

        // Main Module filter (exact match against the "Main Module" column)
        $('#mainModuleFilter').on('change', function() {
            const value = $(this).val();
            const query = value ? '^' + $.fn.dataTable.util.escapeRegex(value) + '$' : '';
            workflowsTable.column(3).search(query, true, false).draw();
        });

        // Missing final stage filter (matches the "Final Stage" column's "Missing" badge)
        $('#missingFinalStageFilter').on('change', function() {
            workflowsTable.column(4).search(this.checked ? 'Missing' : '').draw();
        });

        $('#DocType').select2({
            dropdownParent: $modal,
            width: '100%',
            placeholder: "Search Document Type...",
            allowClear: true
        });

        $('#ModuleID').select2({
            dropdownParent: $modal,
            width: '100%',
            placeholder: "Select Module...",
            allowClear: true
        });

        $('#MainModuleID').select2({
            dropdownParent: $modal,
            width: '100%',
            placeholder: "Select Main Module...",
            allowClear: true
        });

        // Create
        $('.modal-create-approval').on('click', function() {
            $form.attr('action', "{{ route('settings.workflows.store') }}");
            $('#formMethod').val('POST');
            $('.modal-title').text('New Approval WorkFlow');
            $form[0].reset();
            $('#DocType').val('').trigger('change');
            $('#ModuleID').val('').trigger('change');
            $('#MainModuleID').val('').trigger('change');
            $modal.modal('show');
        });

        // Edit (delegated: DataTables moves paginated rows in/out of the DOM,
        // so a direct .btn-edit binding only ever reaches page-1 rows)
        $('#workflowsTable').on('click', '.btn-edit', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const description = $(this).data('description');
            const docType = $(this).data('doc');
            const moduleId = $(this).data('module');
            const mainModuleId = $(this).data('main-module');

            const updateRoute = updateRouteTemplate.replace('__ID__', id);
            $form.attr('action', updateRoute);
            $('#formMethod').val('PUT');
            $('.modal-title').text('Edit Approval WorkFlow');

            $('#Name').val(name);
            $('#Description').val(description);
            $('#DocType').val(docType).trigger('change');
            $('#ModuleID').val(moduleId).trigger('change');
            $('#MainModuleID').val(mainModuleId).trigger('change');

            $modal.modal('show');
        });
    }

    function boot() {
        ensureDependencies(initApprovalConfigPage);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endsection