@extends('layouts.app')
@section('title', 'Approval Workflow Details')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
<style>
    .select2-container--bootstrap4 .select2-selection--single {
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        height: calc(2.375rem + 2px);
        padding: 0.375rem 0.75rem;
    }

    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        line-height: 1.5;
    }

    .final-stage-row {
        background-color: #d4edda !important;
    }
</style>
@endsection

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('settings.workflows.index') }}">Approval Configuration</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $approval->Name }}</li>
    </ol>
</nav>

<div class="card shadow p-4 rounded-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">View Approval Workflow</h4>
        <a href="{{ route('settings.workflows.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back to list
        </a>
    </div>

    <dl class="row">
        <dt class="col-sm-3">{{ __('common.name') }}</dt>
        <dd class="col-sm-9">{{ $approval->Name }}</dd>

        <dt class="col-sm-3">{{ __('common.description') }}</dt>
        <dd class="col-sm-9">{{ $approval->Description }}</dd>



        <dt class="col-sm-3">Has Final Stage</dt>
        <dd class="col-sm-9">
            <span class="badge {{ !empty($approval->FinalStage) ? 'bg-success' : 'bg-secondary' }}">
                {{ !empty($approval->FinalStage) ? 'Yes' : 'No' }}
            </span>
        </dd>



        <dt class="col-sm-3">{{ __('legal.created_on') }}</dt>
        <dd class="col-sm-9">{{ \Carbon\Carbon::parse($approval->CreatedOn)->format('d-m-Y H:i') }}</dd>
    </dl>

    <hr>

    {{-- Add Stage Form --}}
    <div class="card shadow p-4 rounded-4 mt-4" id="stageFormCard">
        <h4 class="mb-4">➕ Add Approval Stage</h4>

        @if(!empty($approval->FinalStage))
        <div class="alert alert-warning d-flex align-items-center mb-0">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div>
                <strong>This workflow already has a final stage.</strong>
                New stages can't be added until the final stage designation is removed. To make room for another
                stage, edit the stage marked <span class="badge bg-success">FINAL</span> below and uncheck
                "Mark as Final Stage".
            </div>
        </div>
        @else
        <form id="stageForm">
            @csrf
            <input type="hidden" name="WorkFlowId" value="{{ $approval->Id }}">

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Stage Name</label>
                    <input type="text" name="StageName" class="form-control" required placeholder="e.g. Committee Stage">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Escalation Limit (days)</label>
                    <input type="number" name="EscalationLimit" class="form-control" required min="1">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Approval Type</label>
                    <select name="WorkFlowTypeId" id="TypeID" class="form-select" required>
                        <option value="">-- Select Type --</option>
                        @foreach($approvalTypes as $type)
                        <option value="{{ $type->Id }}" data-code="{{ $type->TypeID }}">{{ $type->TypeID }} - {{ $type->Name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- AMT Limit --}}
            <div class="row mb-3" id="limitGroup" style="display: none;">
                <div class="col-md-4">
                    <label class="form-label">Approval Limit (AMOUNT only)</label>
                    {{--
                        <select name="WorkFlowLimitId" id="WorkflowLimitID" class="form-control select2">
                            <option value="">Select WorkFlow Limit</option>
                            @foreach($workflowLimits as $limit)
                                <option value="{{ $limit->Id }}">{{ $limit->Source }}</option>
                    @endforeach
                    </select>
                    --}}
                </div>
            </div>

            {{-- Count --}}
            <div class="row mb-3" id="countGroup" style="display: none;">
                <div class="col-md-4">
                    <label class="form-label">Approver Count (COUNT only)</label>
                    <input type="number" name="Count" class="form-control" min="1">
                </div>
            </div>

            {{-- Permission --}}
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Approval Permission</label>
                    <select name="PermissionId" id="PermissionId" class="form-control select2" required>
                        <option value="">Select Permission</option>
                        @foreach($permissions as $permission)
                        @php
                            $permissionEnum = \App\Enums\Core\PermissionEnum::tryFrom($permission->name);
                        @endphp
                        <option value="{{ $permission->id }}">
                            {{ $permissionEnum?->subName() ?? $permission->name }}
                            @if($permissionEnum) ({{ $permission->name }}) @endif
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 d-flex align-items-end pt-2">
                    <div class="form-check">
                        <input type="hidden" name="IsFinalStage" value="0">
                        <input type="checkbox" class="form-check-input" name="IsFinalStage" value="1" id="IsFinalStage">
                        <label class="form-check-label" for="IsFinalStage">
                            <strong>Mark as Final Stage</strong> (No more stages can be added after this)
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">💾 Save Stage</button>
            </div>
        </form>
        @endif
    </div>

    {{-- Approvers Modal --}}
    <div class="modal fade" id="approversModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approvers for Stage</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul id="approversList" class="list-group">
                        <li class="list-group-item text-center">{{ __('common.loading') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Added Stages Table --}}
    <div class="mt-5">
        <h5 class="mb-3">🧾 Added Stages</h5>
        <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>{{ __('common.name') }}</th>
                    <th>{{ __('common.type') }}</th>
                    <th>Permission</th>
                    <th>Stage Permission</th>
                    <th>{{ __('common.role') }}</th>
                    <th>Cut-off</th>
                    <th>Final</th>
                    <th class="text-nowrap">Action</th>
                </tr>
            </thead>
            <tbody id="stagesTable">
                @forelse ($stages as $index => $stage)
                @php
                // Check if this is the last stage (final stage)
                $isLastStage = $index === count($stages) - 1;
                $isFinalStage = $approval->IsFinalStage && $isLastStage;
                @endphp
                <tr id="stage-{{ $stage->Id }}" class="{{ $isFinalStage ? 'final-stage-row' : '' }} {{ ! $stage->IsActive ? 'table-secondary text-muted' : '' }}">
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $stage->StageName }}
                        @if($isFinalStage)
                        <span class="badge bg-success ms-2">FINAL</span>
                        @endif
                        @if(! $stage->IsActive)
                        <span class="badge bg-secondary ms-2">DISABLED</span>
                        @endif
                    </td>
                    <td>{{ $stage->type_name->Name ?? $stage->type_name->TypeID ?? '-' }}</td>
                    <td>{{ $stage->overridePermission?->name ?? '-' }}</td>
                    @php
                        $stageScopedPermissionName = 'workflowstage_' . str_replace(' ', '', (string) $stage->StageName);
                        $hasStageScopedPermission = \Spatie\Permission\Models\Permission::where('name', $stageScopedPermissionName)->exists();
                    @endphp
                    <td>{{ $hasStageScopedPermission ? $stageScopedPermissionName : ($stage->permission?->name ?? '-') }}</td>
                    @php
                        $stageRoleIds = $stage->permission?->roles?->pluck('id')->map(fn ($id) => (int) $id)->values() ?? collect();
                    @endphp
                    <td id="stage-role-cell-{{ $stage->Id }}">{{ $stage->permission?->roles?->pluck('name')->implode(', ') ?: '-' }}</td>
                    <td>{{ $stage->EscalationLimit ?? '-' }}</td>
                    <td>
                        <span class="badge {{ $isFinalStage ? 'bg-success' : 'bg-secondary' }}">
                            {{ $isFinalStage ? 'Yes' : 'No' }}
                        </span>
                    </td>
                    <td class="text-nowrap">
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-sm btn-info view-approvers" data-id="{{ $stage->Id }}" title="View Approvers">👀</button>
                            <button type="button"
                                class="btn btn-sm btn-primary manage-stage-roles"
                                data-id="{{ $stage->Id }}"
                                data-stage="{{ $stage->StageName }}"
                                data-role-ids='@json($stageRoleIds)'
                                title="Manage stage roles">
                                Roles
                            </button>
                            <button type="button"
                                class="btn btn-sm btn-warning edit-stage"
                                data-id="{{ $stage->Id }}"
                                data-stage-name="{{ $stage->StageName }}"
                                data-escalation-limit="{{ $stage->EscalationLimit }}"
                                data-workflow-type-id="{{ $stage->WorkFlowTypeId }}"
                                data-count="{{ $stage->Count }}"
                                data-permission-id="{{ $stage->OverridePermissionId }}"
                                data-is-final="{{ $isFinalStage ? '1' : '0' }}"
                                title="Edit stage">
                                ✏️
                            </button>
                            <button type="button"
                                class="btn btn-sm {{ $stage->IsActive ? 'btn-outline-danger' : 'btn-outline-success' }} toggle-stage-active"
                                data-id="{{ $stage->Id }}"
                                data-active="{{ $stage->IsActive ? '1' : '0' }}"
                                data-is-final="{{ $isFinalStage ? '1' : '0' }}"
                                title="{{ $stage->IsActive ? 'Disable stage' : 'Enable stage' }}">
                                {{ $stage->IsActive ? 'Disable' : 'Enable' }}
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr id="noStages">
                    <td colspan="9" class="text-center text-muted">No stages added yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>

<div class="modal fade" id="stageRolesModal" tabindex="-1" aria-labelledby="stageRolesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stageRolesModalLabel">Stage Roles</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="stageRolesStageId">
                <div id="stageRolesList" class="row g-2"></div>
                <div id="stageRolesEmpty" class="alert alert-warning mb-0 d-none">
                    No roles with this workflow's approval permission were found.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="saveStageRoles">Save Roles</button>
            </div>
        </div>
    </div>
</div>

{{-- Edit Stage Modal --}}
<div class="modal fade" id="editStageModal" tabindex="-1" aria-labelledby="editStageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editStageModalLabel">Edit Stage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editStageForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="WorkFlowId" value="{{ $approval->Id }}">
                <input type="hidden" id="editStageId">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Stage Name</label>
                            <input type="text" name="StageName" id="editStageName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Escalation Limit (days)</label>
                            <input type="number" name="EscalationLimit" id="editEscalationLimit" class="form-control" min="1" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Approval Type</label>
                            <select name="WorkFlowTypeId" id="editTypeID" class="form-select select2-modal" required>
                                <option value="">-- Select Type --</option>
                                @foreach($approvalTypes as $type)
                                <option value="{{ $type->Id }}" data-code="{{ $type->TypeID }}">{{ $type->TypeID }} - {{ $type->Name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6" id="editCountGroup" style="display: none;">
                            <label class="form-label">Approver Count (COUNT only)</label>
                            <input type="number" name="Count" id="editCount" class="form-control" min="1">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Approval Permission</label>
                            <select name="PermissionId" id="editPermissionId" class="form-control select2-modal" required>
                                <option value="">Select Permission</option>
                                @foreach($permissions as $permission)
                                @php
                                    $permissionEnum = \App\Enums\Core\PermissionEnum::tryFrom($permission->name);
                                @endphp
                                <option value="{{ $permission->id }}">
                                    {{ $permissionEnum?->subName() ?? $permission->name }}
                                    @if($permissionEnum) ({{ $permission->name }}) @endif
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end pt-2">
                            <div class="form-check">
                                <input type="hidden" name="IsFinalStage" value="0">
                                <input type="checkbox" class="form-check-input" name="IsFinalStage" value="1" id="editIsFinalStage">
                                <label class="form-check-label" for="editIsFinalStage">Final Stage</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Safer boolean export
    let workflowHasFinalStage = @json(!empty($approval->FinalStage));
    const workflowId = {{ $approval->Id }};
    const stageRoleCandidates = @json($stageRoleCandidates->map(fn ($role) => ['id' => (int) $role->id, 'name' => $role->name])->values());
    const STORAGE_KEY = 'workflow-stage-form-' + workflowId;

    console.log('Initial workflow state:', {
        workflowId: workflowId,
        hasFinalStage: workflowHasFinalStage,
        finalStageName: @json($approval->FinalStage ?? null)
    });

    // Function to reload workflow state from server
    function reloadWorkflowState() {
        console.log('Reloading workflow state...');
        
        return $.ajax({
            url: '/settings/workflows/' + workflowId + '/state',
            method: 'GET',
            cache: false,
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            },
            success: function(res) {
                if (res.status === 'success') {
                    workflowHasFinalStage = res.workflow.IsFinalStage;
                    
                    console.log('Workflow state reloaded:', {
                        hasFinalStage: workflowHasFinalStage,
                        stageCount: res.stages.length
                    });
                    
                    // Update form visibility
                    updateFormVisibility(workflowHasFinalStage);
                    
                    // Rebuild stages table
                    rebuildStagesTable(res.stages);
                    
                    return res;
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to reload workflow state:', error);
            }
        });
    }

    // Function to rebuild stages table
    function rebuildStagesTable(stages) {
        const tbody = $('#stagesTable');
        tbody.empty();
        
        if (stages.length === 0) {
            tbody.append(
                '<tr id="noStages">' +
                '<td colspan="9" class="text-center text-muted">No stages added yet.</td>' +
                '</tr>'
            );
            return;
        }
        
        stages.forEach(function(stage, index) {
            const rowClass = stage.IsFinalStage ? 'final-stage-row' : '';
            const finalBadge = stage.IsFinalStage ? '<span class="badge bg-success ms-2">FINAL</span>' : '';
            const finalBadgeCell = stage.IsFinalStage ? 
                '<span class="badge bg-success">Yes</span>' : 
                '<span class="badge bg-secondary">No</span>';
            
            const typeText = stage.type ? (stage.type.Name || stage.type.TypeID || '-') : '-';
            const permissionText = stage.base_permission ? (stage.base_permission.name || '-') : '-';
            const stagePermissionText = stage.stage_permission
                ? (stage.stage_permission.name || '-')
                : (stage.permission ? (stage.permission.name || '-') : '-');
            const roleNameText = stage.role_name || '-';
            const escalationText = stage.EscalationLimit || '-';
            
            const isActive = stage.IsActive !== false;
            const disabledBadge = isActive ? '' : '<span class="badge bg-secondary ms-2">DISABLED</span>';
            const toggleClass = isActive ? 'btn-outline-danger' : 'btn-outline-success';
            const toggleLabel = isActive ? 'Disable' : 'Enable';

            tbody.append(
                '<tr id="stage-' + stage.Id + '" class="' + rowClass + (isActive ? '' : ' table-secondary text-muted') + '">' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' + stage.StageName + ' ' + finalBadge + disabledBadge + '</td>' +
                '<td>' + typeText + '</td>' +
                '<td>' + permissionText + '</td>' +
                '<td>' + stagePermissionText + '</td>' +
                '<td>' + roleNameText + '</td>' +
                '<td>' + escalationText + '</td>' +
                '<td>' + finalBadgeCell + '</td>' +
                '<td>' +
                '<div class="d-flex">' +
                '<button type="button" class="btn btn-sm btn-info view-approvers me-1" ' +
                'data-id="' + stage.Id + '" title="View Approvers">👀</button>' +
                '<button type="button" class="btn btn-sm btn-warning edit-stage me-1" ' +
                'data-id="' + stage.Id + '" title="Edit stage">✏️</button>' +
                '<button type="button" class="btn btn-sm ' + toggleClass + ' toggle-stage-active" ' +
                'data-id="' + stage.Id + '" data-active="' + (isActive ? '1' : '0') + '" ' +
                'data-is-final="' + (stage.IsFinalStage ? '1' : '0') + '" title="' + toggleLabel + ' stage">' +
                toggleLabel +
                '</button>' +
                '</div>' +
                '</td>' +
                '</tr>'
            );
        });
        
        console.log('Stages table rebuilt with ' + stages.length + ' stages');
    }

    // Enhanced save form state
    function saveFormState() {
        try {
            var permData = $('#PermissionId').select2('data');
            var permissionText = (permData && permData.length > 0 && permData[0].text) ? permData[0].text : '';

            var limitData = $('#WorkflowLimitID').select2('data');
            var workFlowLimitText = (limitData && limitData.length > 0 && limitData[0].text) ? limitData[0].text : '';

            const formData = {
                StageName: $('input[name="StageName"]').val(),
                EscalationLimit: $('input[name="EscalationLimit"]').val(),
                WorkFlowTypeId: $('#TypeID').val(),
                WorkFlowLimitId: $('#WorkflowLimitID').val(),
                Count: $('input[name="Count"]').val(),
                PermissionId: $('#PermissionId').val(),
                IsFinalStage: $('#IsFinalStage').is(':checked'),
                PermissionText: permissionText,
                WorkFlowLimitText: workFlowLimitText,
                lastUpdated: Date.now()
            };
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(formData));
        } catch (error) {
            console.error('Error saving form state:', error);
        }
    }

    function loadFormState() {
        try {
            const saved = sessionStorage.getItem(STORAGE_KEY);
            if (saved) {
                const formData = JSON.parse(saved);
                console.log('Loading saved form state:', formData);

                $('input[name="StageName"]').val(formData.StageName || '');
                $('input[name="EscalationLimit"]').val(formData.EscalationLimit || '');

                if (formData.WorkFlowTypeId) {
                    $('#TypeID').val(formData.WorkFlowTypeId).trigger('change');
                }

                setTimeout(function() {
                    if (formData.WorkFlowLimitId) {
                        $('#WorkflowLimitID').val(formData.WorkFlowLimitId).trigger('change');
                    }
                    if (formData.Count) {
                        $('input[name="Count"]').val(formData.Count);
                    }
                    if (formData.PermissionId) {
                        $('#PermissionId').val(formData.PermissionId).trigger('change');
                    }
                    $('#IsFinalStage').prop('checked', !!formData.IsFinalStage);
                }, 300);
            }
        } catch (error) {
            console.error('Error loading form state:', error);
        }
    }

    function clearFormState() {
        try {
            sessionStorage.removeItem(STORAGE_KEY);
            console.log('Form state cleared');
        } catch (error) {
            console.error('Error clearing form state:', error);
        }
    }

    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap4',
        placeholder: '🔍 Type to search...',
        allowClear: true
    });

    // Select2 fields inside the Edit Stage modal need dropdownParent so the
    // search box stays clickable/focusable while Bootstrap's modal is open.
    $('.select2-modal').select2({
        theme: 'bootstrap4',
        placeholder: '🔍 Type to search...',
        allowClear: true,
        dropdownParent: $('#editStageModal')
    });

    setTimeout(loadFormState, 100);

    let saveTimeout;
    function debouncedSave() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(saveFormState, 500);
    }

    $('#stageForm').on('input change keyup', '.form-control, .form-select, .form-check-input', debouncedSave);
    $('#stageForm').on('select2:select select2:unselect', debouncedSave);

    function toggleFields() {
        const selected = $('#TypeID').find(':selected').data('code');
        $('#limitGroup').toggle(selected === 'AMT');
        $('#countGroup').toggle(selected === 'CNT');
        debouncedSave();
    }

    $('#TypeID').on('change', toggleFields);
    setTimeout(toggleFields, 200);

    function renumberStages() {
        $('#stagesTable tr:not(#noStages)').each(function(i) {
            $(this).find('td:first').text(i + 1);
        });
    }

    function updateFormVisibility(hasFinalStage) {
        workflowHasFinalStage = hasFinalStage;
        console.log('Updating form visibility:', {hasFinalStage: hasFinalStage});
        
        if (hasFinalStage) {
            $('#stageForm').hide();
            if ($('#stageFormCard .alert-warning').length === 0) {
                $('#stageFormCard').prepend(
                    '<div class="alert alert-warning">' +
                    '<strong>⚠️ Notice:</strong> This workflow already has a final stage. ' +
                    'You cannot add more stages until you remove the final stage designation.' +
                    '</div>'
                );
            }
        } else {
            $('#stageForm').show();
            $('#stageFormCard .alert-warning').remove();
        }
    }

    // Initial form visibility check
    updateFormVisibility(workflowHasFinalStage);

    // Form submission
    $('#stageForm').on('submit', function(e) {
        e.preventDefault();

        if (workflowHasFinalStage) {
            alert('This workflow already has a final stage. Cannot add more stages.');
            return;
        }

        const isFinalStage = $('#IsFinalStage').is(':checked');

        if (isFinalStage) {
            if (!confirm('Are you sure you want to mark this as the FINAL stage? ' +
                'You will not be able to add more stages after this.')) {
                return;
            }
        }

        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm"></span> Saving...'
        );

        $.ajax({
            url: "{{ route('settings.workflow_stages.store') }}",
            method: "POST",
            data: $(this).serialize(),
            cache: false,
            success: function(res) {
                submitBtn.prop('disabled', false).html(originalText);

                if (res.status === 'success') {
                    alert(res.message);
                    
                    // Reset form
                    $('#stageForm')[0].reset();
                    $('#PermissionId').val(null).trigger('change');
                    $('#WorkflowLimitID').val(null).trigger('change');
                    clearFormState();
                    
                    // Reload entire workflow state
                    // reloadWorkflowState().then(function() {
                    //     console.log('Workflow state reloaded after stage creation');
                    // });
                    window.location.reload();
                } else {
                    alert(res.message || 'Failed to create stage');
                }
            },
            error: function(xhr) {
                submitBtn.prop('disabled', false).html(originalText);
                var errorMsg = 'Error creating stage';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
                console.error('Stage creation error:', xhr);
            }
        });
    });

    // Disable / enable stage
    $(document).on('click', '.toggle-stage-active', function(e) {
        e.preventDefault();

        const btn = $(this);
        const id = btn.data('id');
        const isActive = btn.data('active') == '1';
        const isFinal = btn.data('is-final') == '1';
        const action = isActive ? 'disable' : 'enable';

        let confirmMsg = isActive
            ? 'Disable this stage? It will stop being used for new approvals, but any pending or historical approvals already at this stage will keep working.'
            : 'Re-enable this stage so it can be used for new approvals again?';

        if (isActive && isFinal) {
            confirmMsg = 'This is the FINAL stage. Disabling it will clear the final-stage designation. Are you sure?';
        }

        if (!confirm(confirmMsg)) return;

        const originalText = btn.html();
        btn.prop('disabled', true).html('...');
        const token = $('meta[name="csrf-token"]').attr('content');

        $.ajax({
            url: '/settings/workflow-stages/' + id + '/' + action,
            method: 'POST',
            data: { _method: 'PATCH', _token: token },
            cache: false,
            success: function(res) {
                btn.prop('disabled', false).html(originalText);

                if (res.status === 'success') {
                    alert(res.message);
                    window.location.reload();
                } else {
                    alert(res.message || ('Failed to ' + action + ' stage'));
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(originalText);
                const message = xhr.responseJSON?.message || ('Error ' + (isActive ? 'disabling' : 'enabling') + ' stage');
                alert(message);
            }
        });
    });

    // Edit stage
    function editToggleFields() {
        const selected = $('#editTypeID').find(':selected').data('code');
        $('#editCountGroup').toggle(selected === 'CNT');
    }
    $('#editTypeID').on('change', editToggleFields);

    $(document).on('click', '.edit-stage', function() {
        const btn = $(this);

        $('#editStageId').val(btn.data('id'));
        $('#editStageName').val(btn.data('stage-name'));
        $('#editEscalationLimit').val(btn.data('escalation-limit'));
        $('#editTypeID').val(btn.data('workflow-type-id')).trigger('change');
        $('#editCount').val(btn.data('count'));
        $('#editPermissionId').val(btn.data('permission-id')).trigger('change');
        $('#editIsFinalStage').prop('checked', btn.data('is-final') == '1');
        editToggleFields();

        new bootstrap.Modal(document.getElementById('editStageModal')).show();
    });

    $('#editStageForm').on('submit', function(e) {
        e.preventDefault();

        const id = $('#editStageId').val();
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

        $.ajax({
            url: '/settings/workflow-stages/' + id,
            method: 'POST',
            data: $(this).serialize() + '&_method=PUT',
            cache: false,
            success: function(res) {
                submitBtn.prop('disabled', false).html(originalText);

                if (res.status === 'success') {
                    alert(res.message);
                    window.location.reload();
                } else {
                    alert(res.message || 'Failed to update stage');
                }
            },
            error: function(xhr) {
                submitBtn.prop('disabled', false).html(originalText);
                const message = xhr.responseJSON?.message || 'Error updating stage';
                alert(message);
            }
        });
    });

    // Save form state periodically
    $(window).on('beforeunload', saveFormState);
    $(document).on('click', 'a', saveFormState);
    setInterval(saveFormState, 30000);

    // View approvers
    $(document).on('click', '.view-approvers', function() {
        const stageId = $(this).data('id');
        const modal = new bootstrap.Modal(document.getElementById('approversModal'));
        const list = $('#approversList');

        list.html('<li class="list-group-item text-center">Loading...</li>');
        modal.show();

        $.get('/settings/workflow/stage/' + stageId + '/approvers', function(res) {
            list.empty();
            if (res.users && res.users.length > 0) {
                $.each(res.users, function(i, user) {
                    list.append(
                        '<li class="list-group-item">' +
                        user.Name + ' <small class="text-muted">(' + user.Email + ')</small>' +
                        '</li>'
                    );
                });
            } else {
                list.append('<li class="list-group-item text-center text-muted">No users found.</li>');
            }
        }).fail(function() {
            list.html('<li class="list-group-item text-center text-danger">Failed to load approvers.</li>');
        });
    });

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function parseStageRoleIds(rawValue) {
        if (Array.isArray(rawValue)) {
            return rawValue.map(Number);
        }

        try {
            const parsed = JSON.parse(rawValue || '[]');
            return Array.isArray(parsed) ? parsed.map(Number) : [];
        } catch (error) {
            return [];
        }
    }

    $(document).on('click', '.manage-stage-roles', function() {
        const button = $(this);
        const stageId = button.data('id');
        const stageName = button.data('stage');
        const selectedRoleIds = parseStageRoleIds(button.attr('data-role-ids'));
        const selectedSet = new Set(selectedRoleIds);
        const list = $('#stageRolesList');

        $('#stageRolesStageId').val(stageId);
        $('#stageRolesModalLabel').text('Stage Roles - ' + stageName);
        list.empty();

        if (!stageRoleCandidates.length) {
            $('#stageRolesEmpty').removeClass('d-none');
            $('#saveStageRoles').prop('disabled', true);
        } else {
            $('#stageRolesEmpty').addClass('d-none');
            $('#saveStageRoles').prop('disabled', false);

            stageRoleCandidates.forEach(function(role) {
                const roleId = Number(role.id);
                const checked = selectedSet.has(roleId) ? 'checked' : '';

                list.append(
                    '<div class="col-md-6">' +
                    '<label class="border rounded p-2 w-100 mb-0">' +
                    '<input type="checkbox" class="stage-role-checkbox me-2" value="' + roleId + '" ' + checked + '> ' +
                    escapeHtml(role.name) +
                    '</label>' +
                    '</div>'
                );
            });
        }

        new bootstrap.Modal(document.getElementById('stageRolesModal')).show();
    });

    $('#saveStageRoles').on('click', function() {
        const saveBtn = $(this);
        const stageId = $('#stageRolesStageId').val();
        const selectedRoleIds = $('.stage-role-checkbox:checked')
            .map(function() {
                return Number($(this).val());
            })
            .get();
        const originalText = saveBtn.text();

        saveBtn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: '/settings/workflow-stages/' + stageId + '/roles',
            method: 'PUT',
            data: {
                _token: '{{ csrf_token() }}',
                role_ids: selectedRoleIds
            },
            success: function(res) {
                if (res.status === 'success') {
                    $('#stage-role-cell-' + stageId).text(res.role_name || '-');
                    $('.manage-stage-roles[data-id="' + stageId + '"]').attr('data-role-ids', JSON.stringify(selectedRoleIds));
                    bootstrap.Modal.getInstance(document.getElementById('stageRolesModal')).hide();
                    alert(res.message);
                } else {
                    alert(res.message || 'Failed to update stage roles');
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Failed to update stage roles';
                alert(message);
            },
            complete: function() {
                saveBtn.prop('disabled', false).text(originalText);
            }
        });
    });

    console.log('Workflow stage form initialized');
});
</script>
@endpush
