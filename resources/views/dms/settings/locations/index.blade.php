@extends('dms.layout')

@section('title','Registry')

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/css/select2.min.css') }}">
    <style>
        .select2-container { width: 100% !important; }
        .physical-document-card { border-left: 3px solid var(--bs-primary); }
        .registry-tree { max-height: 30rem; overflow: auto; }
        .registry-tree-node { display: flex; align-items: center; gap: .45rem; padding: .42rem .6rem; border-radius: .3rem; cursor: pointer; }
        .registry-tree-node:hover, .registry-tree-node.active { background: rgba(var(--bs-primary-rgb), .12); color: var(--bs-primary); }
        .registry-tree-node .tree-toggle { width: 1rem; text-align: center; opacity: .65; }
        .registry-compact-table th { font-size: .72rem; text-transform: uppercase; color: var(--bs-secondary-color); white-space: nowrap; }
        .registry-compact-table td { vertical-align: middle; }
        .registry-empty { padding: 2rem 1rem; text-align: center; color: var(--bs-secondary-color); }
        .registry-detail dt { font-weight: 500; color: var(--bs-secondary-color); }
        @media (max-width: 767.98px) {
            .registry-action { width: 100%; }
        }
    </style>
@endsection

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="#">DMS</a></li>
    <li class="breadcrumb-item"><a href="#">Registry</a></li>
@endsection

@section('content')
    @php
        // More than one registry in scope means the branch (or head office)
        // runs several, so the switcher earns its place.
        $canSwitchRegistry = $registryCount > 1;
        $activeTab = $incomingCounts['files'] > 0 ? 'incoming' : 'storage';
        $attentionCount = $incomingCounts['overdue'] + $incomingCounts['issues'];
    @endphp

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="mb-0">
                                    {{ $registry ? $registry->Code . ' - ' . $registry->Name : 'Registry' }}
                                </h5>
                                @if($canSwitchRegistry)
                                    <button type="button" class="btn btn-sm btn-outline-secondary switch-registry-open"
                                            title="Switch to another registry">
                                        <i class="fas fa-exchange-alt me-1"></i> Switch
                                    </button>
                                @endif
                            </div>
                            @if($registry)
                                <small class="text-muted">
                                    Head of registry: {{ $registry->hod?->Name ?: 'not assigned' }}
                                    <span class="mx-1">&bull;</span>
                                    {{ $registry->status?->Description ?: 'active' }}
                                    @if($registry->IsArchive)
                                        <span class="badge bg-secondary ms-1">Archive</span>
                                    @endif
                                </small>
                            @endif
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            @if($registry)
                                @if($canOperateRegistry)
                                    <div class="input-group" style="width: 20rem;">
                                        <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                        <input id="RegistryFileSearch" class="form-control" type="search"
                                               placeholder="Scan barcode or file reference">
                                        <button class="btn btn-outline-secondary" id="RegistryFileSearchButton"
                                                type="button" aria-label="Search"><i class="fas fa-search"></i></button>
                                    </div>
                                    @if($canManageRegistry)
                                        <button type="button" class="btn btn-primary registry-action add-physical-file"
                                                title="Register a paper file">
                                            <i class="fas fa-folder-plus me-1"></i> Register File
                                        </button>
                                        <button type="button" class="btn btn-outline-primary registry-action add-location">
                                            <i class="fas fa-plus me-1"></i> Add Location
                                        </button>
                                    @endif
                                @else
                                    <button type="button" class="btn btn-primary registry-action request-file"
                                            @disabled($requestableFiles->isEmpty())>
                                        <i class="fas fa-hand-paper me-1"></i> Request File
                                    </button>
                                @endif
                            @endif
                            {{-- Available whether or not this branch has a registry yet - this is how one gets
                                 created in the first place, so it can't be gated behind $registry already existing. --}}
                            @if($isHeadOffice)
                                @can('create', \App\Models\DMS\Registry::class)
                                    <a href="{{ route('registries.index') }}"
                                       class="btn btn-outline-secondary" title="Manage registries">
                                        <i class="fas fa-cog"></i> Manage Registries
                                    </a>
                                @endcan
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    @if(! $registry)
                        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-3 mb-0">
                            <span>No registry is assigned to your branch yet. Ask head office to create one
                                before adding shelving.</span>
                            @if($isHeadOffice)
                                @can('create', \App\Models\DMS\Registry::class)
                                    <a href="{{ route('registries.index') }}" class="btn btn-warning btn-sm text-nowrap">
                                        <i class="fas fa-plus"></i> Create Registry
                                    </a>
                                @endcan
                            @endif
                        </div>
                    @elseif(! $canOperateRegistry)
                        {{-- Requester view: ask for a file you may see, and follow your own requests. --}}
                        <div class="alert alert-light border d-flex gap-3 align-items-start">
                            <i class="fas fa-info-circle text-primary mt-1"></i>
                            <div>
                                <div class="fw-semibold">You can request files from this registry</div>
                                <small class="text-muted">
                                    Only files you are allowed to see can be requested. The registry team
                                    hands the file over once the request is approved.
                                </small>
                            </div>
                        </div>

                        {{-- Two things only: the files this user may see, and
                             the requests they themselves have made. --}}
                        <ul class="nav nav-tabs" id="requesterTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="my-files-tab" data-bs-toggle="tab"
                                        data-bs-target="#my-files-pane" type="button" role="tab"
                                        aria-controls="my-files-pane" aria-selected="true">
                                    <i class="fas fa-folder-open me-1"></i> Files I can see
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="my-requests-tab" data-bs-toggle="tab"
                                        data-bs-target="#my-requests-pane" type="button" role="tab"
                                        aria-controls="my-requests-pane" aria-selected="false">
                                    <i class="fas fa-hand-paper me-1"></i> My requests
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content pt-3">
                            <div class="tab-pane fade show active" id="my-files-pane" role="tabpanel"
                                 aria-labelledby="my-files-tab">
                                <table id="physicalFilesTable"
                                       class="table table-striped dataTable no-footer dtr-inline w-100 table-responsive">
                                    <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>File Number</th>
                                        <th>Title</th>
                                        <th>Documents</th>
                                        <th>Status</th>
                                        <th>{{ __('common.actions') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <div class="tab-pane fade" id="my-requests-pane" role="tabpanel"
                                 aria-labelledby="my-requests-tab">
                                <table id="physicalFileRequestsTable"
                                       class="table table-striped dataTable no-footer dtr-inline w-100 table-responsive">
                                    <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Physical File</th>
                                        <th>Required Period</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <ul class="nav nav-tabs" id="registryTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link @if($activeTab === 'incoming') active @endif" id="incoming-tab"
                                        data-bs-toggle="tab" data-bs-target="#incoming-pane" type="button" role="tab">
                                    <i class="fas fa-inbox me-1"></i> Incoming
                                    @if($incomingCounts['files'] > 0)
                                        <span class="badge bg-primary ms-1">{{ $incomingCounts['files'] }}</span>
                                    @endif
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link @if($activeTab === 'storage') active @endif" id="storage-tab"
                                        data-bs-toggle="tab" data-bs-target="#storage-pane" type="button" role="tab">
                                    <i class="fas fa-sitemap me-1"></i> Storage
                                    <span id="StorageCount" class="badge bg-light text-dark ms-1">&hellip;</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="files-tab" data-bs-toggle="tab"
                                        data-bs-target="#files-pane" type="button" role="tab">
                                    <i class="fas fa-folder-open me-1"></i> Files
                                    <span id="FilesCount" class="badge bg-light text-dark ms-1">{{ $registryStats['files'] }}</span>
                                    @if($attentionCount > 0)
                                        <span class="badge bg-danger ms-1" title="Overdue or with issues">{{ $attentionCount }}</span>
                                    @endif
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="requests-tab" data-bs-toggle="tab"
                                        data-bs-target="#requests-pane" type="button" role="tab">
                                    <i class="fas fa-hand-paper me-1"></i> Requests
                                    <span class="badge bg-light text-dark ms-1">{{ $registryStats['requests'] }}</span>
                                </button>
                            </li>
                            @if($canManagePermissions)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="permissions-tab" data-bs-toggle="tab"
                                            data-bs-target="#permissions-pane" type="button" role="tab">
                                        <i class="fas fa-user-shield me-1"></i> Permissions
                                    </button>
                                </li>
                            @endif
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="activity-tab" data-bs-toggle="tab"
                                        data-bs-target="#activity-pane" type="button" role="tab">
                                    <i class="fas fa-exchange-alt me-1"></i> Activity
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content pt-3">
                            <div class="tab-pane fade @if($activeTab === 'incoming') show active @endif"
                                 id="incoming-pane" role="tabpanel">
                                <p class="text-muted small">
                                    Files dispatched to this registry that nobody has confirmed receiving yet.
                                </p>
                                <div class="table-responsive">
                                    <table class="table table-hover registry-compact-table mb-0">
                                        <thead>
                                        <tr>
                                            <th>Movement</th>
                                            <th>File</th>
                                            <th>Sent from</th>
                                            <th>Dispatched</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($incomingMovements as $movement)
                                            <tr>
                                                <td>MOV-{{ str_pad((string) $movement->Id, 6, '0', STR_PAD_LEFT) }}</td>
                                                <td>
                                                    <strong>{{ $movement->physicalFile?->FileNumber }}</strong><br>
                                                    <small class="text-muted">{{ $movement->physicalFile?->Title }}</small>
                                                </td>
                                                <td>{{ $movement->fromHolder?->Name ?: ($movement->fromLocation?->Label ?: $movement->fromRegistry?->Name ?: 'External') }}</td>
                                                <td>{{ $movement->DispatchedOn?->format('d M Y') ?: '-' }}</td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-outline-success receive-movement"
                                                            data-url="{{ route('registry.physical-files.receive', $movement) }}">
                                                        <i class="fas fa-check-circle me-1"></i> Receive
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="registry-empty">No files are awaiting receipt.</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @if($incomingCounts['filing'] > 0 || $incomingCounts['rejected'] > 0)
                                    <p class="text-muted small mt-3 mb-0">
                                        {{ $incomingCounts['filing'] }} file(s) sitting in a tray awaiting filing
                                        <span class="mx-1">&bull;</span>
                                        {{ $incomingCounts['rejected'] }} rejected request(s) on record.
                                    </p>
                                @endif

                                {{-- The other half of the counter: what the registry
                                     has lent out and is waiting to get back. --}}
                                <h6 class="mt-4">Out on loan</h6>
                                <p class="text-muted small">
                                    Files in someone's hands. Checking one in returns custody to the
                                    registry and puts it back on the shelving.
                                </p>
                                <div class="table-responsive">
                                    <table class="table table-hover registry-compact-table mb-0">
                                        <thead>
                                        <tr>
                                            <th>File</th>
                                            <th>Issued to</th>
                                            <th>Out</th>
                                            <th>Due</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($onLoanCheckouts as $loan)
                                            @php $isLate = $loan->DueOn?->isPast(); @endphp
                                            <tr>
                                                <td>
                                                    <strong>{{ $loan->physicalFile?->FileNumber }}</strong><br>
                                                    <small class="text-muted">{{ $loan->physicalFile?->Title }}</small>
                                                </td>
                                                <td>{{ $loan->borrower?->Name ?: '-' }}</td>
                                                <td>{{ $loan->CheckedOutOn?->format('d M Y') ?: '-' }}</td>
                                                <td class="{{ $isLate ? 'text-danger fw-semibold' : '' }}">
                                                    {{ $loan->DueOn?->format('d M Y') ?: '-' }}
                                                    @if($isLate)
                                                        <br><small>overdue</small>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    @if($canCheckIn)
                                                        <button type="button" class="btn btn-sm btn-outline-success checkin-file"
                                                                data-id="{{ $loan->Id }}"
                                                                data-file="{{ $loan->physicalFile?->FileNumber }}"
                                                                data-borrower="{{ $loan->borrower?->Name ?: 'the borrower' }}"
                                                                data-registry="{{ $loan->physicalFile?->HomeRegistryId }}"
                                                                data-home-location="{{ $loan->physicalFile?->HomeLocationId }}">
                                                            <i class="fas fa-inbox me-1"></i> Check in
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="registry-empty">No files are out on loan.</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade @if($activeTab === 'storage') show active @endif"
                                 id="storage-pane" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <div class="input-group input-group-sm mb-2">
                                            <input id="StorageTreeFilter" type="search" class="form-control"
                                                   placeholder="Filter locations by code or label">
                                            <button type="button" class="btn btn-outline-secondary" id="RefreshStorageTree"
                                                    title="Reload locations">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                        {{-- Nodes arrive over storage-locations.tree once the page is up. --}}
                                        <div class="registry-tree border rounded p-2">
                                            <div class="registry-tree-node registry-root active">
                                                <span class="tree-toggle"></span>
                                                <i class="fas fa-building"></i><strong>{{ $registry->Name }}</strong>
                                            </div>
                                            <div id="StorageTreeNodes"></div>
                                            <div id="StorageTreeState" class="registry-empty">
                                                <span class="spinner-border spinner-border-sm me-2"></span>loading locations…
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-7">
                                        <div id="StorageNodeDetail" class="border rounded p-3 h-100">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <h6 id="NodeTitle" class="mb-0">{{ $registry->Name }}</h6>
                                                <span id="NodeStatus" class="badge bg-success">
                                                    {{ $registry->status?->Description ?: 'Active' }}
                                                </span>
                                            </div>
                                            <small id="NodePath" class="text-muted d-block mb-3">{{ $registry->Code }}</small>

                                            <dl class="row small registry-detail mb-3">
                                                <dt class="col-5">Type</dt>
                                                <dd id="NodeType" class="col-7">Registry</dd>
                                                <dt class="col-5">Sits inside</dt>
                                                <dd id="NodeParent" class="col-7">&mdash;</dd>
                                                <dt class="col-5">Files here</dt>
                                                <dd id="NodeDirectFiles" class="col-7">{{ $registryStats['files'] }}</dd>
                                                <dt class="col-5">Files below</dt>
                                                <dd id="NodeTotalFiles" class="col-7">{{ $registryStats['files'] }}</dd>
                                                <dt class="col-5">Capacity</dt>
                                                <dd id="NodeCapacity" class="col-7">&mdash;</dd>
                                            </dl>

                                            <div id="NodeAlerts" class="d-flex gap-2 flex-wrap mb-3">
                                                @if($incomingCounts['issues'] > 0)
                                                    <span class="badge bg-danger-subtle text-danger">
                                                        {{ $incomingCounts['issues'] }} with issues
                                                    </span>
                                                @endif
                                                @if($incomingCounts['overdue'] > 0)
                                                    <span class="badge bg-warning-subtle text-warning-emphasis">
                                                        {{ $incomingCounts['overdue'] }} overdue
                                                    </span>
                                                @endif
                                            </div>

                                            @if($canManageRegistry)
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <button type="button" class="btn btn-outline-primary btn-sm add-child-location" disabled>
                                                        <i class="fas fa-plus me-1"></i> Add inside
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm edit-location d-none">
                                                        <i class="fas fa-edit me-1"></i> Edit
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger btn-sm delete-location d-none">
                                                        <i class="fas fa-trash me-1"></i> Remove
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="files-pane" role="tabpanel">
                                @if($attentionCount > 0)
                                    <div class="accordion mb-3" id="AttentionAccordion">
                                        @if($incomingCounts['overdue'] > 0)
                                            <div class="accordion-item">
                                                <h2 class="accordion-header">
                                                    <button class="accordion-button collapsed" type="button"
                                                            data-bs-toggle="collapse" data-bs-target="#OverduePanel">
                                                        <i class="fas fa-clock text-danger me-2"></i>
                                                        {{ $incomingCounts['overdue'] }} overdue file(s)
                                                    </button>
                                                </h2>
                                                <div id="OverduePanel" class="accordion-collapse collapse"
                                                     data-bs-parent="#AttentionAccordion">
                                                    <div class="accordion-body p-0 table-responsive">
                                                        <table class="table registry-compact-table mb-0">
                                                            <thead>
                                                            <tr><th>File</th><th>Issued to</th><th>Due</th><th>Days late</th></tr>
                                                            </thead>
                                                            <tbody>
                                                            @foreach($overdueCheckouts as $checkout)
                                                                <tr>
                                                                    <td>
                                                                        <strong>{{ $checkout->physicalFile?->FileNumber }}</strong><br>
                                                                        <small class="text-muted">{{ $checkout->physicalFile?->Title }}</small>
                                                                    </td>
                                                                    <td>{{ $checkout->borrower?->Name ?: '-' }}</td>
                                                                    <td>{{ $checkout->DueOn?->format('d M Y') }}</td>
                                                                    <td class="text-danger fw-bold">
                                                                        {{ (int) $checkout->DueOn->diffInDays(now()) }}
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                        @if($incomingCounts['issues'] > 0)
                                            <div class="accordion-item">
                                                <h2 class="accordion-header">
                                                    <button class="accordion-button collapsed" type="button"
                                                            data-bs-toggle="collapse" data-bs-target="#IssuesPanel">
                                                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                                        {{ $incomingCounts['issues'] }} file(s) missing or damaged
                                                    </button>
                                                </h2>
                                                <div id="IssuesPanel" class="accordion-collapse collapse"
                                                     data-bs-parent="#AttentionAccordion">
                                                    <div class="accordion-body p-0 table-responsive">
                                                        <table class="table registry-compact-table mb-0">
                                                            <thead>
                                                            <tr><th>File</th><th>Issue</th><th>Home location</th></tr>
                                                            </thead>
                                                            <tbody>
                                                            @foreach($filesWithIssues as $file)
                                                                <tr>
                                                                    <td>
                                                                        <strong>{{ $file->FileNumber }}</strong><br>
                                                                        <small class="text-muted">{{ $file->Title }}</small>
                                                                    </td>
                                                                    <td><span class="badge bg-danger">{{ $file->status?->Description ?: 'Issue' }}</span></td>
                                                                    <td>{{ $file->homeLocation?->Label ?: $file->homeLocation?->Code ?: '-' }}</td>
                                                                </tr>
                                                            @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                @if($canManageRegistry)
                                    {{-- Shown once the tree reports the registry has no shelving yet. --}}
                                    <div class="alert alert-info py-2 d-none" id="NoLocationsHint">
                                        Add at least one storage location before registering a physical file.
                                    </div>
                                @endif

                                {{-- Four filters instead of one search box: a file is looked
                                     for by its number, its name, a paper inside it, or the
                                     record that paper was filed against. --}}
                                <div class="row g-2 align-items-end mb-3">
                                    <div class="col-lg-3 col-md-6">
                                        <label class="form-label small mb-1" for="FilterFileNumber">
                                            File number or barcode
                                        </label>
                                        <input type="search" class="form-control form-control-sm file-filter"
                                               id="FilterFileNumber" placeholder="HR/PF/0001">
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <label class="form-label small mb-1" for="FilterTitle">File title</label>
                                        <input type="search" class="form-control form-control-sm file-filter"
                                               id="FilterTitle" placeholder="Staff contracts">
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <label class="form-label small mb-1" for="FilterDocumentTitle">
                                            Document title
                                        </label>
                                        <input type="search" class="form-control form-control-sm file-filter"
                                               id="FilterDocumentTitle" placeholder="Signed contract">
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <label class="form-label small mb-1" for="FilterDocumentRelation">
                                            Linked client, lead or employee
                                        </label>
                                        <input type="search" class="form-control form-control-sm file-filter"
                                               id="FilterDocumentRelation" placeholder="Name or number">
                                    </div>
                                    <div class="col-12 d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                                id="ClearFileFilters">
                                            <i class="fas fa-times me-1"></i> Clear
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm" id="ApplyFileFilters">
                                            <i class="fas fa-filter me-1"></i> Filter
                                        </button>
                                    </div>
                                </div>

                                <table id="physicalFilesTable"
                                       class="table table-striped dataTable no-footer dtr-inline w-100 table-responsive">
                                    <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>File Number</th>
                                        <th>Title</th>
                                        <th>Current Custodian</th>
                                        <th>Documents</th>
                                        <th>Status</th>
                                        <th>Visibility</th>
                                        <th>{{ __('common.actions') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <div class="tab-pane fade" id="requests-pane" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <p class="text-muted small mb-0">
                                        Requests for files held here, and the decision taken on each.
                                    </p>
                                    <button type="button" class="btn btn-outline-primary btn-sm request-file"
                                            @disabled($requestableFiles->isEmpty())>
                                        <i class="fas fa-hand-paper me-1"></i> Request File
                                    </button>
                                </div>
                                <table id="physicalFileRequestsTable"
                                       class="table table-striped dataTable no-footer dtr-inline w-100 table-responsive">
                                    <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Physical File</th>
                                        <th>Requester</th>
                                        <th>Required Period</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>{{ __('common.actions') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            @if($canManagePermissions)
                            <div class="tab-pane fade" id="permissions-pane" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <p class="text-muted small mb-0">
                                        Who may work this registry beyond the branch that owns it.
                                        An <strong>Admin</strong> grant also lets them decide file
                                        requests, and is given to the head of the registry.
                                    </p>
                                    <button type="button" class="btn btn-primary btn-sm share-registry-btn">
                                        <i class="fas fa-user-plus me-1"></i> Grant
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table id="registryPermissionsTable"
                                           class="table table-striped no-footer dtr-inline w-100">
                                        <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>{{ __('common.party') }}</th>
                                            <th>{{ __('common.role') }}</th>
                                            <th>{{ __('common.dated') }}</th>
                                            <th>{{ __('common.actions') }}</th>
                                        </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            @endif

                            <div class="tab-pane fade" id="activity-pane" role="tabpanel">
                                <p class="text-muted small">The last few movements in and out of this registry.</p>
                                <div class="table-responsive">
                                    <table class="table registry-compact-table mb-0">
                                        <thead>
                                        <tr><th>Movement</th><th>File</th><th>From / To</th><th>Date</th></tr>
                                        </thead>
                                        <tbody>
                                        @forelse($recentMovements as $movement)
                                            @php
                                                $from = $movement->fromHolder?->Name ?: ($movement->fromLocation?->Label ?: $movement->fromRegistry?->Name ?: '—');
                                                $to = $movement->toHolder?->Name ?: ($movement->toLocation?->Label ?: $movement->toRegistry?->Name ?: '—');
                                            @endphp
                                            <tr>
                                                <td>
                                                    MOV-{{ str_pad((string) $movement->Id, 6, '0', STR_PAD_LEFT) }}<br>
                                                    <small class="text-muted">{{ $movement->movementType?->Description }}</small>
                                                </td>
                                                <td>{{ $movement->physicalFile?->FileNumber }}</td>
                                                <td>{{ $from }} &rarr; {{ $to }}</td>
                                                <td>{{ $movement->DispatchedOn?->format('d M Y') }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="registry-empty">No file movements yet.</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($registry && $canManageRegistry)
        <div class="modal fade" id="StorageLocationModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="StorageLocationModalTitle">Add Location</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" id="storageLocationForm"
                              action="{{ route('storage-locations.store') }}"> @csrf
                            <input type="hidden" name="_method" id="FormMethod" value="POST">
                            <input type="hidden" name="registry" id="FormRegistry" value="{{ $registry->Id }}">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="Code">Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="Code" name="Code" required
                                           maxlength="30" placeholder="CAB-04">
                                    <small class="text-muted">Letters, digits, dot, dash or underscore.</small>
                                    <p id="Code_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="Label">Label</label>
                                    <input type="text" class="form-control" id="Label" name="Label" maxlength="150"
                                           placeholder="Contracts cabinet">
                                    <p id="Label_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="ParentId">Sits inside</label>
                                <select class="form-control" name="ParentId" id="ParentId">
                                    <option value="">— top level in the registry —</option>
                                </select>
                                <p id="ParentId_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label" for="LocationTypeId">Type <span class="text-danger">*</span></label>
                                    <select class="form-control" name="LocationTypeId" id="LocationTypeId" required>
                                        @foreach($types as $type)
                                            <option value="{{ $type->ID }}">{{ $type->Description }}</option>
                                        @endforeach
                                    </select>
                                    <p id="LocationTypeId_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label" for="LocationPurposeId">Purpose <span class="text-danger">*</span></label>
                                    <select class="form-control" name="LocationPurposeId" id="LocationPurposeId" required>
                                        @foreach($purposes as $purpose)
                                            <option value="{{ $purpose->ID }}"
                                                    @selected($purpose->Value === 'standard')>{{ $purpose->Description }}</option>
                                        @endforeach
                                    </select>
                                    <p id="LocationPurposeId_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label" for="LocationStatusId">Status <span class="text-danger">*</span></label>
                                    <select class="form-control" name="LocationStatusId" id="LocationStatusId" required>
                                        @foreach($statuses as $status)
                                            <option value="{{ $status->ID }}"
                                                    @selected($status->Value === 'active')>{{ $status->Description }}</option>
                                        @endforeach
                                    </select>
                                    <p id="LocationStatusId_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label" for="Barcode">Barcode</label>
                                    <input type="text" class="form-control" id="Barcode" name="Barcode" maxlength="50">
                                    <p id="Barcode_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label" for="Capacity">Capacity</label>
                                    <input type="number" class="form-control" id="Capacity" name="Capacity" min="0">
                                    <p id="Capacity_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label" for="SortOrder">Sort order</label>
                                    <input type="number" class="form-control" id="SortOrder" name="SortOrder" min="0"
                                           value="0">
                                    <p id="SortOrder_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                            </div>

                            <hr>
                            <div class="mt-4">
                                <button type="button" class="btn btn-secondary float-start" data-bs-dismiss="modal">
                                    cancel
                                </button>
                                <button class="btn btn-primary float-end" id="saveLocationBtn" type="submit">
                                    <i class="fas fa-save"></i> save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Field ids are prefixed so validation messages land here and not on the
             location form, which owns the unprefixed Barcode/Capacity ids. --}}
        <div class="modal fade" id="PhysicalFileModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Register Physical File</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" id="physicalFileForm"
                              action="{{ route('registry.physical-files.store') }}"> @csrf
                            <input type="hidden" name="registry" value="{{ $registry->Id }}">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label" for="e_FileNumber">File number <span class="text-danger">*</span></label>
                                    <input class="form-control" id="e_FileNumber" name="FileNumber"
                                           maxlength="100" required placeholder="HR/PF/0001">
                                    <p id="e_FileNumber_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label" for="e_Title">File title <span class="text-danger">*</span></label>
                                    <input class="form-control" id="e_Title" name="Title" maxlength="255" required>
                                    <p id="e_Title_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="e_HomeLocationId">Home location <span class="text-danger">*</span></label>
                                    {{-- Filled from storage-locations.parents when the modal opens. --}}
                                    <select class="form-control" id="e_HomeLocationId" name="HomeLocationId" required>
                                        <option value="">— select the permanent home —</option>
                                    </select>
                                    <p id="e_HomeLocationId_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label" for="e_Barcode">Barcode</label>
                                    <input class="form-control" id="e_Barcode" name="Barcode" maxlength="100">
                                    <p id="e_Barcode_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label" for="e_Visibility">Visibility <span class="text-danger">*</span></label>
                                    <select class="form-control" id="e_Visibility" name="Visibility" required>
                                        <option value="pri" selected>Private</option>
                                        <option value="pub">Public</option>
                                    </select>
                                    <p id="e_Visibility_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="e_Description">File description</label>
                                <textarea class="form-control" id="e_Description" name="Description"
                                          rows="2" maxlength="1000"></textarea>
                            </div>

                            <div class="d-flex justify-content-between align-items-center border-top pt-3 mb-2">
                                <div>
                                    <h6 class="mb-0">Physical Documents</h6>
                                    <small class="text-muted">Add every paper document held in this file.</small>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm add-physical-document">
                                    <i class="fas fa-plus"></i> Add Document
                                </button>
                            </div>
                            <div id="PhysicalDocuments" class="physical-documents" data-next-index="1">
                                <div class="card physical-document-card mb-3" data-document-index="0" data-next-relation="0">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-2">
                                            <strong class="document-number">Document 1</strong>
                                            <button type="button" class="btn btn-sm btn-link text-danger remove-physical-document d-none">Remove</button>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-8 mb-3">
                                                <label class="form-label">Title <span class="text-danger">*</span></label>
                                                <input class="form-control" name="Documents[0][Title]" maxlength="255" required>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Document date</label>
                                                <input type="date" class="form-control" name="Documents[0][DocumentDate]">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="Documents[0][Description]"
                                                      rows="2" maxlength="1000" required></textarea>
                                        </div>
                                        <div class="document-relations"></div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm add-document-relation">
                                            <i class="fas fa-link"></i> Link Client, Lead or Employee
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between border-top pt-3">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="savePhysicalFile">
                                    <i class="fas fa-save"></i> Save Physical File
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    @endif

    @if($registry && $canManagePermissions)
        {{-- A registry share is the same special permission a document uses, so
             the party picker and the roles are the DMS's own. --}}
        <div class="modal fade" id="ShareRegistryModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Grant access to {{ $registry->Code }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" id="shareRegistryForm"
                              action="{{ route('registries.permissions.store', $registry->Id) }}"> @csrf
                            <div class="mb-3">
                                <label class="form-label" for="share_role">
                                    Role <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" name="share_role" id="share_role" required>
                                    <option selected disabled value="">select a role.</option>
                                    @foreach(\App\Enums\Core\RoleEnum::getAll() as $role)
                                        <option value="{{ $role->value }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <p id="share_role_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="share_party">User/Team</label>
                                <select class="form-control" name="share_party" id="share_party" required></select>
                                <p id="share_party_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <div class="d-flex justify-content-between pt-2">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="shareRegistryBtn">
                                    <i class="fas fa-user-plus"></i> grant
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="UnshareRegistryModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Remove grant</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <p class="mb-3">
                            Remove <b id="UnshareRegistryParty"></b>'s access to {{ $registry->Code }}?
                        </p>
                        <form method="post" id="unshareRegistryForm" action=""> @csrf
                            @method('delete')
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    no, keep
                                </button>
                                <button type="submit" class="btn btn-danger" id="unshareRegistryBtn">
                                    <i class="fas fa-trash"></i> yes, remove
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($registry)
        <div class="modal fade" id="PhysicalFileRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Request Physical File</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" id="physicalFileRequestForm"
                              action="{{ route('registry.requests.store') }}"> @csrf
                            <div class="mb-3">
                                <label class="form-label" for="PhysicalFileId">
                                    Physical file <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="PhysicalFileId" name="PhysicalFileId" required>
                                    <option value="">— select a file —</option>
                                    @foreach($requestableFiles as $file)
                                        <option value="{{ $file->Id }}">
                                            {{ $file->FileNumber }} - {{ $file->Title }}
                                            ({{ $file->status?->Description ?: 'unknown status' }})
                                        </option>
                                    @endforeach
                                </select>
                                <p id="PhysicalFileId_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="NeededFrom">Needed from <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="NeededFrom" name="NeededFrom"
                                           min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required>
                                    <p id="NeededFrom_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="RequestedUntil">Return by <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="RequestedUntil" name="RequestedUntil"
                                           min="{{ now()->toDateString() }}" value="{{ now()->addDays(7)->toDateString() }}" required>
                                    <p id="RequestedUntil_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="Purpose">Purpose <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="Purpose" name="Purpose" rows="3"
                                          maxlength="1000" required></textarea>
                                <p id="Purpose_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <div class="d-flex justify-content-between pt-2">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="submitPhysicalFileRequest">
                                    Submit Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
    @if($registry && $canManageRegistry)
        @include('dms.registry.files._document-modal')
    @endif
@endsection

@section('scripts')
    <script src="{{ asset('assets/libs/select2/js/select2.full.min.js') }}"></script>
    @if($registry && $canManageRegistry)
        {{-- Brings the add-documents modal and the document-card helpers the
             register-a-file form also builds on. --}}
        @include('dms.registry.files._document-form', ['documentModalMarkup' => false])
    @endif
    @if($registry)
        {{-- Request history in the offcanvas, the decision in a modal. --}}
        @include('dms.registry.requests._decide')
    @endif
    @if($canCheckIn)
        {{-- The return counter, for the loans listed under Incoming. --}}
        @include('dms.registry.checkouts._checkin')
    @endif
    <script>
        const registryId = '{{ $registry?->Id }}';
        const canOperateRegistry = @json($canOperateRegistry);
        const indexUrl = '{{ route('storage-locations.index') }}';
        const requestsUrl = '{{ route('registry.requests.index') }}';
        const registriesUrl = '{{ route('storage-locations.registries') }}';
        const physicalFilesUrl = '{{ route('registry.physical-files.index') }}';

        let physicalFileRequestsTable = null;
        let physicalFilesTable = null;

        @if($canManagePermissions)
        const registryPermissionsUrl = '{{ route('registries.permissions.index', $registry->Id) }}';
        @endif

        @if($canOperateRegistry)
        const treeUrl = '{{ route('storage-locations.tree') }}';
        const collapsed = new Set();
        const locationIcons = {
            cabinet: 'fa-archive', shelf: 'fa-layer-group', box: 'fa-box', tray: 'fa-inbox',
            bay: 'fa-th-large', compactor: 'fa-compress-arrows-alt'
        };

        // The shelving arrives over ajax, so everything about it is mutable
        // state the panel maintains as nodes are added, changed or removed.
        let storageTree = [];
        let nodesById = new Map();
        let branchNodes = new Set();
        let selectedStorageNode = null;

        // What the detail panel falls back to: the registry itself.
        const registrySummary = {
            title: @json($registry?->Name),
            path: @json($registry?->Code),
            status: @json($registry?->status?->Description ?: 'Active'),
            files: @json($registryStats['files']),
            issues: @json($incomingCounts['issues']),
            overdue: @json($incomingCounts['overdue'])
        };
        @endif

        @if($canManageRegistry)
        const storeUrl = '{{ route('storage-locations.store') }}';
        const parentsUrl = '{{ route('storage-locations.parents') }}';
        @endif

        function currentRegistry() {
            return registryId;
        }

        // Called by the shared decision modal once a request is answered.
        function afterRequestDecided() {
            if (physicalFileRequestsTable) physicalFileRequestsTable.ajax.reload(null, false);
        }

        // Called by the shared document form once papers have been filed.
        function afterDocumentsFiled() {
            if (physicalFilesTable) physicalFilesTable.ajax.reload(null, false);
        }

        $(function () {
            $.fn.dataTable.ext.errMode = 'none';

            @if($registry)
            fetchPhysicalFileRequestsTable();
            @endif

            // A table measures its columns wrong while its tab is hidden, so
            // they are adjusted once it is shown. recalc() belongs to the
            // Responsive extension, which this build does not load.
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
                const tables = $.fn.dataTable.tables({visible: true, api: true}).columns.adjust();

                if (tables.responsive) {
                    tables.responsive.recalc();
                }
            });

            // The registry list is a branch list, so it is fetched rather than
            // rendered with the page: it changes when registries do.
            $(document).on('click', '.switch-registry-open', function () {
                showOffCanvasMain(
                    'Switch registry',
                    registriesUrl + '?current=' + encodeURIComponent(currentRegistry())
                );
            });

            $(document).on('click', '.switch-registry', function () {
                const id = $(this).data('registry-id');
                if (!id || String(id) === String(registryId)) return;

                window.location.href = indexUrl + '?registry=' + encodeURIComponent(id);
            });

            $(document).on('input', '#RegistrySwitchFilter', function () {
                const term = $(this).val().trim().toLowerCase();
                $('#RegistrySwitchList .switch-registry').each(function () {
                    const haystack = String($(this).data('search') || '');
                    $(this).toggleClass('d-none', term !== '' && !haystack.includes(term));
                });
            });

            @if($registry)
            // Both audiences get a files table; it is the columns and the rows
            // behind it that differ.
            fetchPhysicalFilesTable();
            @endif

            @if($canOperateRegistry)
            loadStorageTree();
            @endif

            @if($canManagePermissions)
            // Built the first time its tab is shown: a table measures its
            // columns wrong while it is hidden.
            $('#permissions-tab').one('shown.bs.tab', fetchRegistryPermissionsTable);

            $('#share_party').select2({
                placeholder: 'Search a user or team (t:)',
                minimumInputLength: 2,
                dropdownParent: $('#ShareRegistryModal'),
                ajax: {
                    url: '{!! route('users.select2', ['with_teams' => 'rzr.co.ke']) !!}',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({q: $.trim(params.term)}),
                    processResults: data => ({
                        results: $.map(data, item => ({text: item.Name, id: item.UserID}))
                    }),
                    cache: true
                }
            });

            $(document).on('click', '.share-registry-btn', function () {
                const $form = $('#shareRegistryForm');

                $form.find('.error').addClass('d-none').html('');
                $form.find('.is-invalid').removeClass('is-invalid');
                $('#share_role').val('');
                $('#share_party').val(null).trigger('change');
                $('#ShareRegistryModal').modal('show');
            });

            $('form#shareRegistryForm').submit(async function (e) {
                e.preventDefault();

                if (await saveForm($(this), $('#shareRegistryBtn'), false, false, true)) {
                    $('#ShareRegistryModal').modal('hide');
                    fetchRegistryPermissionsTable();
                }
            });

            // The remove button is rendered by the permissions table.
            $(document).on('click', '.share-permission-trash', function () {
                $('#unshareRegistryForm').attr('action', $(this).data('click_url'));
                $('#UnshareRegistryParty').text($(this).data('info') || 'this party');
                $('#UnshareRegistryModal').modal('show');
            });

            $('form#unshareRegistryForm').submit(async function (e) {
                e.preventDefault();

                if (await saveForm($(this), $('#unshareRegistryBtn'), false, false, true)) {
                    $('#UnshareRegistryModal').modal('hide');
                    fetchRegistryPermissionsTable();
                }
            });
            @endif

            @if($canOperateRegistry)

            $('#RefreshStorageTree').on('click', function () {
                loadStorageTree();
            });

            $('#RegistryFileSearchButton').on('click', searchRegistryFiles);
            $('#RegistryFileSearch').on('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    searchRegistryFiles();
                }
            });

            $('#StorageTreeFilter').on('input', applyTreeVisibility);

            // The filters are applied on demand, not as you type.
            $('#ApplyFileFilters').on('click', reloadFilteredFiles);

            $(document).on('keydown', '.file-filter', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    reloadFilteredFiles();
                }
            });

            $('#ClearFileFilters').on('click', function () {
                $('.file-filter').val('');
                $('#RegistryFileSearch').val('');
                reloadFilteredFiles();
            });

            $(document).on('click', '.storage-node .tree-toggle', function (event) {
                const $chevron = $(this).find('i');
                if (!$chevron.length) return;

                event.stopPropagation();
                const id = Number($(this).closest('.storage-node').data('location-id'));
                if (collapsed.has(id)) {
                    collapsed.delete(id);
                } else {
                    collapsed.add(id);
                }
                $chevron.toggleClass('fa-chevron-down fa-chevron-right');
                applyTreeVisibility();
            });

            $(document).on('click', '.registry-root', function () {
                selectedStorageNode = null;
                showRegistrySummary();
            });

            $(document).on('click', '.storage-node', function () {
                const node = nodesById.get(Number($(this).data('location-id')));
                if (!node) return;

                selectedStorageNode = node;
                markSelectedNode();
                showStorageNode(node);
            });

            $(document).on('click', '.receive-movement', function () {
                const url = $(this).data('url');
                if (!confirm('Confirm that this physical file has been received?')) return;

                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {_method: 'PUT', _token: '{{ csrf_token() }}', registry: currentRegistry()},
                    success: function (res) {
                        nSuccess(res.message);
                        window.location.reload();
                    },
                    error: function (jqXHR) {
                        nWarning(jqXHR.responseJSON?.message || 'receiving the file failed');
                    }
                });
            });

            @endif

            $(document).on('click', '.request-file', function () {
                const $form = $('#physicalFileRequestForm');
                $form.find('.error').addClass('d-none').html('');
                $form.find('.is-invalid').removeClass('is-invalid');
                $('#PhysicalFileRequestModal').modal('show');
            });

            $('form#physicalFileRequestForm').submit(async function (e) {
                e.preventDefault();
                if (await saveForm($(this), $('#submitPhysicalFileRequest'), false, true, true)) {
                    physicalFileRequestsTable.ajax.reload(null, false);
                    $('#PhysicalFileRequestModal').modal('hide');
                    $(this)[0].reset();
                }
            });

            @if($canManageRegistry)
            $(document).on('click', '.add-physical-file', function () {
                $('#PhysicalFileModal').modal('show');
                loadHomeLocations();
            });


            $('form#physicalFileForm').submit(async function (e) {
                e.preventDefault();
                // isEdit = true so errors resolve against this form's e_ ids.
                if (await saveForm($(this), $('#savePhysicalFile'), false, true, true, true)) {
                    $('#PhysicalFileModal').modal('hide');
                    $('#PhysicalDocuments .physical-document-card:not(:first)').remove();
                    $('#PhysicalDocuments').attr('data-next-index', 1);
                    refreshDocumentNumbers($('#PhysicalDocuments'));
                    physicalFilesTable.ajax.reload(null, false);
                    $('#FilesCount').text(Number($('#FilesCount').text() || 0) + 1);
                }
            });


            $(document).on('click', '.add-location', function () {
                openLocationForm('Add Location', null);
            });

            $(document).on('click', '.add-child-location', function () {
                if (!selectedStorageNode) return;
                openLocationForm('Add Location inside ' + selectedStorageNode.label, selectedStorageNode.id);
            });

            $(document).on('click', '.edit-location', function () {
                const node = selectedStorageNode;
                if (!node) return;

                resetLocationForm();
                $('#StorageLocationModalTitle').html('Edit ' + node.code);
                $('#FormMethod').val('PUT');
                $('#storageLocationForm').attr('action', indexUrl + '/' + node.id);

                $('#Code').val(node.code);
                $('#Label').val(node.label === node.code ? '' : node.label);
                $('#LocationTypeId').val(node.typeId);
                $('#LocationPurposeId').val(node.purposeId);
                $('#LocationStatusId').val(node.statusId);
                $('#Barcode').val(node.barcode);
                $('#Capacity').val(node.capacity);
                $('#SortOrder').val(node.sortOrder);

                // Exclude self so a node cannot be offered as its own parent.
                loadParents(node.id).always(function () {
                    $('#ParentId').val(node.parentId || '');
                    $('#StorageLocationModal').modal('show');
                });
            });

            $(document).on('click', '.delete-location', function () {
                const node = selectedStorageNode;
                if (!node || !confirm('Remove location ' + node.code + '?')) return;

                $.ajax({
                    url: indexUrl + '/' + node.id,
                    method: 'POST',
                    data: {_method: 'DELETE', _token: '{{ csrf_token() }}'},
                    success: function (res) {
                        nSuccess(res.message);
                        removeTreeNode(res.id || node.id);
                    },
                    error: function (jqXHR) {
                        nWarning(jqXHR.responseJSON?.message || 'removing the location failed');
                    }
                });
            });

            $('form#storageLocationForm').submit(async function (e) {
                e.preventDefault();

                const saved = await saveForm($(this), $('#saveLocationBtn'), false, true, true);
                if (!saved) return;

                $('#StorageLocationModal').modal('hide');

                if (saved.structureChanged) {
                    // A rename or a move rewrites the path of every node below
                    // this one, so this is the one case worth refetching.
                    loadStorageTree();
                } else if (saved.node) {
                    upsertTreeNode(saved.node);
                }
            });
            @endif
        });

        @if($canOperateRegistry)
        /**
         * Pulls the whole shelving tree for the registry in play.
         *
         * Called once the page is up, from the refresh button, and after a
         * write that shifted paths underneath a node.
         */
        function loadStorageTree() {
            setTreeState('<span class="spinner-border spinner-border-sm me-2"></span>loading locations…');
            $('#RefreshStorageTree').prop('disabled', true);

            return $.getJSON(treeUrl, {registry: currentRegistry()})
                .done(function (nodes) {
                    storageTree = nodes || [];
                    renderStorageTree();
                })
                .fail(function (jqXHR) {
                    setTreeState('loading locations failed');
                    nWarning(jqXHR.responseJSON?.message || 'loading storage locations failed');
                })
                .always(function () {
                    $('#RefreshStorageTree').prop('disabled', false);
                });
        }

        function renderStorageTree() {
            // Path order is depth-first order, which is what the indenting assumes.
            storageTree.sort((a, b) => String(a.path).localeCompare(String(b.path)));
            nodesById = new Map(storageTree.map(node => [Number(node.id), node]));
            branchNodes = new Set(
                storageTree.filter(node => node.parentId).map(node => Number(node.parentId))
            );

            $('#StorageTreeNodes').html(storageTree.map(treeNodeHtml).join(''));
            $('#StorageCount').text(storageTree.length);
            $('#NoLocationsHint').toggleClass('d-none', storageTree.length > 0);
            setTreeState(storageTree.length ? '' : 'No storage locations yet.');

            // Hold the selection across a refresh, unless the node is gone.
            const stillThere = selectedStorageNode
                ? nodesById.get(Number(selectedStorageNode.id))
                : null;

            if (stillThere) {
                selectedStorageNode = stillThere;
                markSelectedNode();
                showStorageNode(stillThere);
            } else {
                selectedStorageNode = null;
                showRegistrySummary();
            }

            applyTreeVisibility();
        }

        function treeNodeHtml(node) {
            const id = Number(node.id);
            const icon = locationIcons[node.typeValue] || 'fa-folder';
            const chevron = branchNodes.has(id)
                ? '<i class="fas ' + (collapsed.has(id) ? 'fa-chevron-right' : 'fa-chevron-down') + ' small"></i>'
                : '';

            return '<div class="registry-tree-node storage-node" data-location-id="' + id + '"'
                + ' style="padding-left:' + (0.65 + (Number(node.depth) * 1.15)).toFixed(2) + 'rem">'
                + '<span class="tree-toggle">' + chevron + '</span>'
                + '<i class="fas ' + icon + '"></i><span>' + escapeHtml(node.label) + '</span></div>';
        }

        /** Adds the node when it is new, replaces it when it is not. */
        function upsertTreeNode(node) {
            const index = storageTree.findIndex(item => Number(item.id) === Number(node.id));

            if (index === -1) {
                storageTree.push(node);
            } else {
                storageTree[index] = node;
            }

            // A node you just added or edited should be on screen, even if it
            // landed inside a branch that was collapsed.
            let parentId = node.parentId ? Number(node.parentId) : null;
            while (parentId) {
                collapsed.delete(parentId);
                parentId = nodesById.get(parentId)?.parentId
                    ? Number(nodesById.get(parentId).parentId)
                    : null;
            }

            selectedStorageNode = node;
            renderStorageTree();
        }

        /** The service refuses to delete a node with children, so this is a leaf. */
        function removeTreeNode(id) {
            storageTree = storageTree.filter(item => Number(item.id) !== Number(id));
            collapsed.delete(Number(id));

            if (selectedStorageNode && Number(selectedStorageNode.id) === Number(id)) {
                selectedStorageNode = null;
            }

            renderStorageTree();
        }

        function markSelectedNode() {
            $('.registry-tree-node').removeClass('active');
            $('.storage-node[data-location-id="' + Number(selectedStorageNode.id) + '"]').addClass('active');
        }

        function setTreeState(html) {
            $('#StorageTreeState').toggleClass('d-none', html === '').html(html);
        }

        function escapeHtml(value) {
            return $('<div>').text(value === null || value === undefined ? '' : value).html();
        }

        /**
         * The toolbar scanner feeds the file-number filter, which matches a
         * barcode as readily as a file number.
         */
        function searchRegistryFiles() {
            const term = $('#RegistryFileSearch').val().trim();
            if (!term || !physicalFilesTable) return;

            const trigger = document.querySelector('#files-tab');
            if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();

            $('#FilterFileNumber').val(term);
            physicalFilesTable.ajax.reload();
        }

        function reloadFilteredFiles() {
            if (physicalFilesTable) physicalFilesTable.ajax.reload();
        }

        /**
         * Hides nodes whose ancestor is collapsed, or - while filtering - every
         * node that neither matches nor leads to a match.
         */
        function applyTreeVisibility() {
            const term = ($('#StorageTreeFilter').val() || '').trim().toLowerCase();
            let matches = null;

            if (term) {
                matches = new Set();
                storageTree.forEach(function (node) {
                    const haystack = (node.label + ' ' + node.code + ' ' + node.path).toLowerCase();
                    if (!haystack.includes(term)) return;

                    matches.add(Number(node.id));
                    let parentId = node.parentId ? Number(node.parentId) : null;
                    while (parentId) {
                        matches.add(parentId);
                        parentId = nodesById.get(parentId)?.parentId
                            ? Number(nodesById.get(parentId).parentId)
                            : null;
                    }
                });
            }

            $('.storage-node').each(function () {
                const id = Number($(this).data('location-id'));
                const visible = matches ? matches.has(id) : hasVisibleAncestry(nodesById.get(id));
                $(this).toggleClass('d-none', !visible);
            });
        }

        function hasVisibleAncestry(node) {
            let parentId = node?.parentId ? Number(node.parentId) : null;
            while (parentId) {
                if (collapsed.has(parentId)) return false;
                parentId = nodesById.get(parentId)?.parentId
                    ? Number(nodesById.get(parentId).parentId)
                    : null;
            }
            return true;
        }

        function showRegistrySummary() {
            $('.registry-tree-node').removeClass('active');
            $('.registry-root').addClass('active');

            $('#NodeTitle').text(registrySummary.title);
            $('#NodePath').text(registrySummary.path);
            $('#NodeStatus').text(registrySummary.status).removeClass('bg-secondary').addClass('bg-success');
            $('#NodeType').text('Registry');
            $('#NodeParent').text('—');
            $('#NodeDirectFiles').text(registrySummary.files);
            $('#NodeTotalFiles').text(registrySummary.files);
            $('#NodeCapacity').text('—');
            $('#NodeAlerts').html(alertBadges(registrySummary.issues, registrySummary.overdue));

            $('.add-child-location').prop('disabled', true);
            $('.edit-location, .delete-location').addClass('d-none');
        }

        function alertBadges(issues, overdue) {
            const badges = [];
            if (issues > 0) {
                badges.push('<span class="badge bg-danger-subtle text-danger">' + issues + ' with issues</span>');
            }
            if (overdue > 0) {
                badges.push('<span class="badge bg-warning-subtle text-warning-emphasis">' + overdue + ' overdue</span>');
            }
            return badges.join(' ');
        }

        function showStorageNode(node) {
            $('#NodeTitle').text(node.label);
            $('#NodePath').text(node.path);
            $('#NodeStatus').text(node.status)
                .toggleClass('bg-success', node.statusValue === 'active')
                .toggleClass('bg-secondary', node.statusValue !== 'active');
            $('#NodeType').text(node.type);
            $('#NodeParent').text(node.parent);
            $('#NodeDirectFiles').text(node.directFiles);
            $('#NodeTotalFiles').text(node.totalFiles);
            $('#NodeCapacity').text(
                node.capacity === null ? '—' : node.totalFiles + ' of ' + node.capacity + ' used'
            );

            $('#NodeAlerts').html(alertBadges(node.issues, node.overdue));

            $('.add-child-location').prop('disabled', false);
            $('.edit-location').toggleClass('d-none', !node.canUpdate);
            $('.delete-location').toggleClass('d-none', !node.canDelete);
        }
        @endif

        @if($canManageRegistry)
        function openLocationForm(title, parentId) {
            resetLocationForm();
            $('#StorageLocationModalTitle').html(title);
            $('#FormMethod').val('POST');
            $('#storageLocationForm').attr('action', storeUrl);
            loadParents(null).always(function () {
                $('#ParentId').val(parentId || '');
                $('#StorageLocationModal').modal('show');
            });
        }

        function resetLocationForm() {
            const $form = $('#storageLocationForm');
            $form[0].reset();
            $form.find('.error').addClass('d-none').html('');
            $form.find('.is-invalid').removeClass('is-invalid');
            $('#FormRegistry').val(currentRegistry());
            $('#SortOrder').val(0);
        }

        /**
         * The home-location list is the same set as the parent list, so it
         * comes from the same endpoint rather than being rendered with the page.
         */
        function loadHomeLocations() {
            const $home = $('#e_HomeLocationId');
            $home.prop('disabled', true).empty().append('<option value="">loading locations…</option>');

            return $.getJSON(parentsUrl, {registry: currentRegistry()})
                .done(function (options) {
                    $home.empty().append('<option value="">— select the permanent home —</option>');
                    $.each(options, function (i, option) {
                        $home.append($('<option>').val(option.Id).text(option.Label));
                    });
                    $home.prop('disabled', false);

                    if (!options.length) {
                        nWarning('add a storage location before registering a file');
                    }
                })
                .fail(function () {
                    $home.empty().append('<option value="">loading locations failed</option>');
                    nWarning('loading storage locations failed');
                });
        }

        function loadParents(excludeId) {
            return $.getJSON(parentsUrl, {
                registry: currentRegistry(),
                exclude: excludeId || ''
            }).done(function (options) {
                const $parent = $('#ParentId');
                $parent.empty().append('<option value="">— top level in the registry —</option>');
                $.each(options, function (i, option) {
                    $parent.append($('<option>').val(option.Id).text(option.Label));
                });
            }).fail(function () {
                nWarning('loading parent locations failed');
            });
        }

        @endif

        function fetchPhysicalFileRequestsTable() {
            // A requester only ever sees their own rows and can take no action
            // on them, so those two columns are left out for them.
            const columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'File', name: 'physicalFile.FileNumber', orderable: false, searchable: false}
            ];

            if (canOperateRegistry) {
                columns.push({data: 'Requester', name: 'requester.Name', orderable: false, searchable: false});
            }

            columns.push(
                {data: 'Period', name: 'NeededFrom', searchable: false},
                {data: 'Purpose', name: 'Purpose'},
                {data: 'Status', name: 'status.Description', orderable: false}
            );

            if (canOperateRegistry) {
                columns.push({data: 'action', name: 'action', orderable: false, searchable: false});
            }

            physicalFileRequestsTable = $('#physicalFileRequestsTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ajax: {
                    url: requestsUrl,
                    data: function (d) {
                        d.registry = currentRegistry();
                    },
                    error: function (jqXHR) {
                        codeNotify(jqXHR.status);
                    }
                },
                columns: columns,
                order: [[canOperateRegistry ? 3 : 2, 'desc']],
                oLanguage: {
                    sEmptyTable: canOperateRegistry
                        ? 'no physical file requests for this registry yet !'
                        : 'you have not requested a file from this registry yet !'
                }
            });
        }

        @if($canManagePermissions)
        function fetchRegistryPermissionsTable() {
            if ($.fn.DataTable.isDataTable('#registryPermissionsTable')) {
                $('#registryPermissionsTable').DataTable().ajax.reload(null, false);

                return;
            }

            $('#registryPermissionsTable').DataTable({
                processing: true,
                serverSide: true,
                order: [[3, 'desc']],
                ajax: {
                    url: registryPermissionsUrl,
                    error: jqXHR => codeNotify(jqXHR.status)
                },
                columns: [
                    {data: 'DT_RowIndex', name: 'DT_RowIndex', searchable: false, orderable: false},
                    {data: 'party', name: 'party'},
                    {data: 'Role', name: 'Role'},
                    {data: 'CreatedOn', name: 'CreatedOn'},
                    {data: 'action', name: 'action', orderable: false, searchable: false}
                ],
                oLanguage: {sEmptyTable: 'this registry has not been shared with anyone !'}
            }).on('error', function () {
                nWarning('an issue occurred while loading the shares.');
            });
        }
        @endif

        function fetchPhysicalFilesTable() {
            // Custody and visibility are an operator's concern; a requester
            // gets the file, what it holds, and a way in to look at it. The
            // rows themselves are scoped server-side to what they may see.
            const columns = [
                {data: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'FileNumber', name: 'FileNumber'},
                {data: 'Title', name: 'Title'}
            ];

            if (canOperateRegistry) {
                columns.push({data: 'CurrentCustodian', orderable: false, searchable: false});
            }

            columns.push(
                {data: 'documents_count', name: 'documents_count', orderable: false, searchable: false},
                {data: 'Status', orderable: false, searchable: false}
            );

            if (canOperateRegistry) {
                columns.push({data: 'Visibility', name: 'Visibility', searchable: false});
            }

            columns.push({data: 'action', name: 'action', orderable: false, searchable: false});

            physicalFilesTable = $('#physicalFilesTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                // The named filters replace the single search box.
                searching: false,
                ajax: {
                    url: physicalFilesUrl,
                    data: d => {
                        d.registry = currentRegistry();
                        d.fileNumber = $('#FilterFileNumber').val() || '';
                        d.title = $('#FilterTitle').val() || '';
                        d.documentTitle = $('#FilterDocumentTitle').val() || '';
                        d.documentRelation = $('#FilterDocumentRelation').val() || '';
                    },
                    error: jqXHR => codeNotify(jqXHR.status)
                },
                columns: columns,
                order: [[1, 'asc']],
                oLanguage: {
                    sEmptyTable: canOperateRegistry
                        ? 'no physical files registered here yet !'
                        : 'no physical files here are visible to you !'
                }
            });
        }
    </script>
@endsection
