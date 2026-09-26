@extends('dms.layout')

@section('title', $file->FileNumber)

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('storage-locations.index', ['registry' => $registry->Id]) }}">Registry</a>
    </li>
    <li class="breadcrumb-item active">{{ $file->FileNumber }}</li>
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/css/select2.min.css') }}">
    <style>
        .select2-container { width: 100% !important; }
        .physical-document-card { border-left: 3px solid var(--bs-primary); }
        .file-detail dt { font-weight: 500; color: var(--bs-secondary-color); }
        .file-compact-table th { font-size: .72rem; text-transform: uppercase; color: var(--bs-secondary-color); white-space: nowrap; }
        .file-compact-table td { vertical-align: middle; }
        .file-empty { padding: 2rem 1rem; text-align: center; color: var(--bs-secondary-color); }
    </style>
@endsection

@section('content')
    @php
        $isMissing = $file->status?->Value === 'missing';
        $custodian = $file->currentHolder?->Name
            ?: (trim((string) $file->currentLocation?->Path, '/') ?: 'In transit');
        $pendingRequests = $requests->filter(fn ($item) => $item->status?->Value === 'pending')->count();
    @endphp

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <h4 class="mb-1">{{ $file->FileNumber }}</h4>
                            <div class="text-muted">{{ $file->Title }}</div>
                            <div class="mt-2 d-flex gap-2 flex-wrap">
                                <span class="badge {{ $isMissing ? 'bg-danger' : 'bg-secondary' }}">
                                    {{ $file->status?->Description ?: 'unknown status' }}
                                </span>
                                <span class="badge bg-light text-dark border">
                                    {{ $file->Visibility->value === 'pub' ? 'Public' : 'Private' }}
                                </span>
                                <span class="badge bg-light text-dark border">
                                    <span class="documents-count">{{ $file->documents_count }}</span> document(s)
                                </span>
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('storage-locations.index', ['registry' => $registry->Id]) }}"
                               class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Registry
                            </a>
                            <button type="button" class="btn btn-outline-primary request-file"
                                    @disabled($isMissing)
                                    title="{{ $isMissing ? 'A missing file cannot be requested' : 'Request this file' }}">
                                <i class="fas fa-hand-paper me-1"></i> Request File
                            </button>
                            @if($canManage)
                                {{-- Filing means opening the folder, so it waits
                                     until the registry has the file back. --}}
                                <button type="button" class="btn btn-primary add-file-document"
                                        data-id="{{ $file->Id }}"
                                        @disabled(! $canFileDocuments)
                                        title="{{ $canFileDocuments
                                            ? 'File another document into this file'
                                            : 'This file is not in the registry, so nothing can be filed into it' }}"
                                        data-file="{{ $file->FileNumber }} - {{ $file->Title }}">
                                    <i class="fas fa-circle-plus me-1"></i> Add Document
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header"><h6 class="mb-0">Where this file lives</h6></div>
                <div class="card-body">
                    @if($file->activeCheckout)
                        <div class="alert alert-warning py-2 small">
                            Issued to <strong>{{ $file->activeCheckout->borrower?->Name ?: 'someone' }}</strong>,
                            due {{ $file->activeCheckout->DueOn?->format('d M Y') ?: '-' }}.
                            @if($file->activeCheckout->DueOn?->isPast())
                                <span class="fw-bold">Overdue.</span>
                            @endif
                            @if($canDecide)
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-success checkin-file"
                                            data-id="{{ $file->activeCheckout->Id }}"
                                            data-file="{{ $file->FileNumber }}"
                                            data-borrower="{{ $file->activeCheckout->borrower?->Name ?: 'the borrower' }}"
                                            data-registry="{{ $file->HomeRegistryId }}"
                                            data-home-location="{{ $file->HomeLocationId }}">
                                        <i class="fas fa-inbox me-1"></i> Check in
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endif

                    <dl class="row small file-detail mb-0">
                        <dt class="col-5">Registry</dt>
                        <dd class="col-7">{{ $registry->Code }} - {{ $registry->Name }}</dd>

                        <dt class="col-5">Home location</dt>
                        <dd class="col-7">{{ trim((string) $file->homeLocation?->Path, '/') ?: '—' }}</dd>

                        <dt class="col-5">Currently with</dt>
                        <dd class="col-7">{{ $custodian }}</dd>

                        <dt class="col-5">Barcode</dt>
                        <dd class="col-7">{{ $file->Barcode ?: '—' }}</dd>

                        <dt class="col-5">Registered</dt>
                        <dd class="col-7">{{ $file->CreatedOn?->format('d M Y') ?: '—' }}</dd>
                    </dl>

                    @if($file->Description)
                        <hr>
                        <div class="small text-muted">{{ $file->Description }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    {{-- Nav and panes are siblings, the arrangement Bootstrap's
                         tab plugin expects; card-header-tabs pulled the nav
                         under the card body, which swallowed the clicks. --}}
                    <ul class="nav nav-tabs mb-3" id="fileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="documents-tab" data-bs-toggle="tab"
                                    data-bs-target="#documents-pane" type="button" role="tab"
                                    aria-controls="documents-pane" aria-selected="true">
                                <i class="fas fa-file-lines me-1"></i> Documents
                                <span class="badge bg-light text-dark ms-1 documents-count">{{ $file->documents_count }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="requests-tab" data-bs-toggle="tab"
                                    data-bs-target="#requests-pane" type="button" role="tab"
                                    aria-controls="requests-pane" aria-selected="false">
                                <i class="fas fa-hand-paper me-1"></i> Requests
                                @if($pendingRequests > 0)
                                    <span class="badge bg-primary ms-1">{{ $pendingRequests }}</span>
                                @endif
                            </button>
                        </li>
                        @if($canOperate)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="movements-tab" data-bs-toggle="tab"
                                        data-bs-target="#movements-pane" type="button" role="tab"
                                        aria-controls="movements-pane" aria-selected="false">
                                    <i class="fas fa-exchange-alt me-1"></i> Custody history
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="sharing-tab" data-bs-toggle="tab"
                                        data-bs-target="#sharing-pane" type="button" role="tab"
                                        aria-controls="sharing-pane" aria-selected="false">
                                    <i class="fas fa-share-alt me-1"></i> Sharing
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="activities-tab" data-bs-toggle="tab"
                                        data-bs-target="#activities-pane" type="button" role="tab"
                                        aria-controls="activities-pane" aria-selected="false">
                                    <i class="fas fa-clock-rotate-left me-1"></i> {{ __('common.activities') }}
                                </button>
                            </li>
                        @endif
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="documents-pane" role="tabpanel"
                             aria-labelledby="documents-tab">
                            {{-- Filled from registry.physical-files.documents.panel. --}}
                            <div id="DocumentsPanel">
                                <div class="file-empty">
                                    <span class="spinner-border spinner-border-sm me-2"></span>loading documents…
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="requests-pane" role="tabpanel"
                             aria-labelledby="requests-tab">
                            <div class="table-responsive">
                                <table class="table file-compact-table mb-0">
                                    <thead>
                                    <tr>
                                        <th>Requester</th>
                                        <th>Required period</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($requests as $item)
                                        @php
                                            // Nobody answers their own ask, and an
                                            // approval is the approver's to withdraw.
                                            $requestStatus = $item->status?->Value;
                                            $ownRequest = (int) $item->RequestedByUserId === (int) auth()->id();
                                            $decidedByMe = (int) $item->DecidedByUserId === (int) auth()->id();
                                        @endphp
                                        <tr>
                                            <td>
                                                {{ $item->requester?->Name ?: '-' }}
                                                @if($item->decidedBy)
                                                    <br><small class="text-muted">decided by {{ $item->decidedBy->Name }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $item->NeededFrom?->format('d M Y') }}
                                                &ndash; {{ $item->RequestedUntil?->format('d M Y') }}
                                            </td>
                                            <td>{{ $item->Purpose }}</td>
                                            <td>{{ $item->status?->Description ?: '-' }}</td>
                                            <td class="text-end text-nowrap">
                                                <button type="button" class="btn btn-sm btn-outline-secondary request-history"
                                                        data-id="{{ $item->Id }}" data-file="{{ $file->FileNumber }}"
                                                        title="Request history">
                                                    <i class="fas fa-clock-rotate-left"></i>
                                                </button>
                                                @if($canDecide && $requestStatus === 'pending' && ! $ownRequest)
                                                    <button type="button" class="btn btn-sm btn-success decide-request"
                                                            data-id="{{ $item->Id }}" data-decision="approve"
                                                            data-file="{{ $file->FileNumber }}">Approve</button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger decide-request"
                                                            data-id="{{ $item->Id }}" data-decision="reject"
                                                            data-file="{{ $file->FileNumber }}">Reject</button>
                                                @endif
                                                @if($requestStatus === 'approved')
                                                    @if($canDecide)
                                                        <button type="button" class="btn btn-sm btn-primary handover-request"
                                                                data-id="{{ $item->Id }}" data-file="{{ $file->FileNumber }}"
                                                                data-requester="{{ $item->requester?->Name ?: 'the requester' }}">
                                                            <i class="fas fa-hand-holding"></i> Hand over
                                                        </button>
                                                    @endif
                                                    @if($canDecide || $decidedByMe)
                                                        <button type="button" class="btn btn-sm btn-outline-danger cancel-request"
                                                                data-id="{{ $item->Id }}" data-file="{{ $file->FileNumber }}">Cancel</button>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="file-empty">No requests for this file.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @if($canOperate)
                            <div class="tab-pane fade" id="movements-pane" role="tabpanel"
                                 aria-labelledby="movements-tab">
                                <div class="table-responsive">
                                    <table id="fileMovementsTable"
                                           class="table table-striped no-footer dtr-inline w-100">
                                        <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>Movement</th>
                                            <th>From / To</th>
                                            <th>Dispatched</th>
                                            <th>Received</th>
                                        </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="sharing-pane" role="tabpanel"
                                 aria-labelledby="sharing-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <p class="text-muted small mb-0">
                                        Who else may see this file, beyond the registry that keeps it.
                                    </p>
                                    @if($canShare)
                                        <button type="button" class="btn btn-primary btn-sm share-file-btn">
                                            <i class="fas fa-share-alt me-1"></i> Share
                                        </button>
                                    @endif
                                </div>
                                <div class="table-responsive">
                                    <table id="filePermissionsTable"
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

                            <div class="tab-pane fade" id="activities-pane" role="tabpanel"
                                 aria-labelledby="activities-tab">
                                <div class="table-responsive">
                                    <table id="fileActivitiesTable"
                                           class="table table-striped no-footer dtr-inline w-100">
                                        <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>Event</th>
                                            <th>{{ __('common.description') }}</th>
                                            <th>By</th>
                                            <th>{{ __('common.dated') }}</th>
                                        </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Opened by the soft-copy buttons above. --}}
    @include('snippets.actions.preview-files')

    @if($canManage)
        {{-- Scanning a paper in: the file goes to the DMS the usual way and the
             new document is linked to the paper it was scanned from. --}}
        <div class="modal fade" id="SoftCopyModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">Upload scan</h5>
                            <small class="text-muted" id="SoftCopyDocument"></small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" id="softCopyForm" action="" enctype="multipart/form-data"> @csrf
                            <div class="mb-3">
                                <label class="form-label" for="SoftCopyRepository">
                                    File it into <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="SoftCopyRepository" name="repository" required>
                                    @foreach($repositories as $repository)
                                        <option value="{{ $repository->Id }}">{{ $repository->Name }}</option>
                                    @endforeach
                                </select>
                                <p id="repository_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                @if($repositories->isEmpty())
                                    <small class="text-danger">
                                        You have no repository to file a scan into.
                                    </small>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="SoftCopyFile">
                                    Scanned file <span class="text-danger">*</span>
                                </label>
                                <input type="file" class="form-control" id="SoftCopyFile" name="file" required>
                                <p id="file_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                <small class="text-muted">
                                    Maximum {{ config('app.dms.file_size') }}MB.
                                </small>
                            </div>
                            <div class="d-flex justify-content-between pt-2">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="saveSoftCopy"
                                        @disabled($repositories->isEmpty())>
                                    <i class="fas fa-upload"></i> Upload &amp; link
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($canShare)
        {{-- Sharing a paper file grants the same special permissions a document
             uses, so the party picker and roles are the DMS's own. --}}
        <div class="modal fade" id="ShareFileModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Share {{ $file->FileNumber }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" id="shareFileForm"
                              action="{{ route('registry.physical-files.permissions.store', $file->Id) }}"> @csrf
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
                                <button type="submit" class="btn btn-primary" id="shareFileBtn">
                                    <i class="fas fa-share-alt"></i> share
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="UnshareFileModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Remove share</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <p class="mb-3">
                            Remove <b id="UnsharePartyName"></b>'s access to {{ $file->FileNumber }}?
                        </p>
                        <form method="post" id="unshareFileForm" action=""> @csrf
                            @method('delete')
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    no, keep
                                </button>
                                <button type="submit" class="btn btn-danger" id="unshareFileBtn">
                                    <i class="fas fa-trash"></i> yes, remove
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="modal fade" id="PhysicalFileRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request {{ $file->FileNumber }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="physicalFileRequestForm"
                          action="{{ route('registry.requests.store') }}"> @csrf
                        <input type="hidden" name="PhysicalFileId" value="{{ $file->Id }}">
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
    @if($canManage)
        @include('dms.registry.files._document-modal')
    @endif
@endsection

@section('scripts')
    <script src="{{ asset('assets/libs/select2/js/select2.full.min.js') }}"></script>
    @if($canManage)
        @include('dms.registry.files._document-form', ['documentModalMarkup' => false])
    @endif
    {{-- Request history in the offcanvas, the decision in a modal. --}}
    @include('dms.registry.requests._decide')
    @if($canDecide)
        {{-- The return counter, for a file that is currently out. --}}
        @include('dms.registry.checkouts._checkin')
    @endif
    <script>
        const requestsUrl = '{{ route('registry.requests.index') }}';
        const documentsPanelUrl = '{{ route('registry.physical-files.documents.panel', $file->Id) }}';
        @if($canOperate)
        const permissionsUrl = '{{ route('registry.physical-files.permissions.index', $file->Id) }}';
        const activitiesUrl = '{{ route('registry.physical-files.activities', $file->Id) }}';
        const movementsUrl = '{{ route('registry.physical-files.movements', $file->Id) }}';
        @endif
        @if($canManage)
        const softCopyUrlTemplate = '{{ route('registry.physical-documents.soft-copy', ['physicalDocument' => '__ID__']) }}';
        @endif

        // Called by the shared document form once papers have been filed.
        function afterDocumentsFiled() {
            loadDocumentsPanel();
        }

        // Called by the shared decision modal once a request is answered.
        function afterRequestDecided() {
            window.location.reload();
        }

        /** The documents pane arrives after the page, and again after a write. */
        function loadDocumentsPanel() {
            const $panel = $('#DocumentsPanel');
            $panel.html('<div class="file-empty">'
                + '<span class="spinner-border spinner-border-sm me-2"></span>loading documents…</div>');

            return $.get(documentsPanelUrl)
                .done(function (html) {
                    $panel.html(html);
                    $('.documents-count').text($panel.find('[data-documents-count]').data('documents-count'));
                })
                .fail(function (jqXHR) {
                    $panel.html('<div class="file-empty">loading documents failed</div>');
                    nWarning(jqXHR.responseJSON?.message || 'loading the documents failed');
                });
        }

        $(function () {
            loadDocumentsPanel();

            @if($canOperate)
            // Tables measure their columns wrong while hidden, so each one is
            // built the first time its tab is actually shown.
            $('#movements-tab').one('shown.bs.tab', fetchMovementsTable);
            $('#sharing-tab').one('shown.bs.tab', fetchPermissionsTable);
            $('#activities-tab').one('shown.bs.tab', fetchActivitiesTable);
            @endif

            @if($canShare)
            $('#share_party').select2({
                placeholder: 'Search a user or team (t:)',
                minimumInputLength: 2,
                dropdownParent: $('#ShareFileModal'),
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

            $(document).on('click', '.share-file-btn', function () {
                const $form = $('#shareFileForm');

                $form.find('.error').addClass('d-none').html('');
                $form.find('.is-invalid').removeClass('is-invalid');
                $('#share_role').val('');
                $('#share_party').val(null).trigger('change');
                $('#ShareFileModal').modal('show');
            });

            $('form#shareFileForm').submit(async function (e) {
                e.preventDefault();

                if (await saveForm($(this), $('#shareFileBtn'), false, false, true)) {
                    $('#ShareFileModal').modal('hide');
                    fetchPermissionsTable();
                }
            });

            // The remove button is rendered by the permissions table.
            $(document).on('click', '.share-permission-trash', function () {
                $('#unshareFileForm').attr('action', $(this).data('click_url'));
                $('#UnsharePartyName').text($(this).data('info') || 'this party');
                $('#UnshareFileModal').modal('show');
            });

            $('form#unshareFileForm').submit(async function (e) {
                e.preventDefault();

                if (await saveForm($(this), $('#unshareFileBtn'), false, false, true)) {
                    $('#UnshareFileModal').modal('hide');
                    fetchPermissionsTable();
                }
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
                    $('#PhysicalFileRequestModal').modal('hide');
                    window.location.reload();
                }
            });

            @if($canManage)
            $(document).on('click', '.upload-soft-copy', function () {
                const $form = $('#softCopyForm');

                $form[0].reset();
                $form.find('.error').addClass('d-none').html('');
                $form.find('.is-invalid').removeClass('is-invalid');
                $form.attr('action', softCopyUrlTemplate.replace('__ID__', $(this).data('document-id')));

                $('#SoftCopyDocument').text($(this).data('document') || '');
                $('#SoftCopyModal').modal('show');
            });

            // A file upload has to go as FormData, so this cannot use saveForm().
            $('form#softCopyForm').submit(function (e) {
                e.preventDefault();

                const $button = $('#saveSoftCopy');
                const original = $button.html();

                $(this).find('.error').addClass('d-none').html('');
                $(this).find('.is-invalid').removeClass('is-invalid');
                $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> uploading');

                $.ajax({
                    url: $(this).attr('action'),
                    method: 'POST',
                    data: new FormData(this),
                    processData: false,
                    contentType: false,
                    success: function (res) {
                        nSuccess(res.message);
                        $('#SoftCopyModal').modal('hide');
                        loadDocumentsPanel();
                    },
                    error: function (jqXHR) {
                        formRequest(jqXHR);
                    },
                    complete: function () {
                        $button.prop('disabled', false).html(original);
                    }
                });
            });
            @endif

        });

        @if($canOperate)
        function fetchMovementsTable() {
            if ($.fn.DataTable.isDataTable('#fileMovementsTable')) {
                $('#fileMovementsTable').DataTable().ajax.reload(null, false);

                return;
            }

            $('#fileMovementsTable').DataTable({
                processing: true,
                serverSide: true,
                searching: false,
                order: [[3, 'desc']],
                ajax: {
                    url: movementsUrl,
                    error: jqXHR => codeNotify(jqXHR.status)
                },
                columns: [
                    {data: 'DT_RowIndex', name: 'DT_RowIndex', searchable: false, orderable: false},
                    {data: 'Movement', name: 'Id'},
                    {data: 'Route', name: 'Route', orderable: false, searchable: false},
                    {data: 'Dispatched', name: 'DispatchedOn'},
                    {data: 'Received', name: 'ReceivedOn'}
                ],
                oLanguage: {sEmptyTable: 'this file has not moved yet !'}
            }).on('error', function () {
                nWarning('an issue occurred while loading the custody history.');
            });
        }

        function fetchPermissionsTable() {
            if ($.fn.DataTable.isDataTable('#filePermissionsTable')) {
                $('#filePermissionsTable').DataTable().ajax.reload(null, false);

                return;
            }

            $('#filePermissionsTable').DataTable({
                processing: true,
                serverSide: true,
                order: [[3, 'desc']],
                ajax: {
                    url: permissionsUrl,
                    error: jqXHR => codeNotify(jqXHR.status)
                },
                columns: [
                    {data: 'DT_RowIndex', name: 'DT_RowIndex', searchable: false, orderable: false},
                    {data: 'party', name: 'party'},
                    {data: 'Role', name: 'Role'},
                    {data: 'CreatedOn', name: 'CreatedOn'},
                    {data: 'action', name: 'action', orderable: false, searchable: false}
                ],
                oLanguage: {sEmptyTable: 'this file has not been shared with anyone !'}
            }).on('error', function () {
                nWarning('an issue occurred while loading the shares.');
            });
        }

        function fetchActivitiesTable() {
            if ($.fn.DataTable.isDataTable('#fileActivitiesTable')) {
                $('#fileActivitiesTable').DataTable().ajax.reload(null, false);

                return;
            }

            $('#fileActivitiesTable').DataTable({
                processing: true,
                serverSide: true,
                order: [[4, 'desc']],
                columnDefs: [
                    {
                        render: function (data, type, row) {
                            return '<p class="mb-0"><b>' + row.event + '</b><br>' + data + '</p>';
                        },
                        targets: 2
                    },
                    {visible: false, targets: [0, 1]}
                ],
                ajax: {
                    url: activitiesUrl,
                    error: jqXHR => codeNotify(jqXHR.status)
                },
                columns: [
                    {data: 'DT_RowIndex', name: 'DT_RowIndex', searchable: false, orderable: false},
                    {data: 'event', name: 'event'},
                    {data: 'description', name: 'description'},
                    {data: 'causer.Name', name: 'causer.Name'},
                    {data: 'created_at', name: 'created_at'}
                ],
                oLanguage: {sEmptyTable: 'nothing has been recorded against this file yet !'}
            }).on('error', function () {
                nWarning('an issue occurred while loading the activities.');
            });
        }
        @endif
    </script>
@endsection
