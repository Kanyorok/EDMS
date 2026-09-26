@extends('layouts.app')

@section('title', 'Branch Sync')

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/css/select2.min.css') }}">
    <style>
        .branch-modal-header {
            background: linear-gradient(135deg, #0dcaf0, #0d6efd);
            color: #fff;
        }

        .branch-detail-card {
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            background: #f8f9fa;
            height: 100%;
        }

        .branch-detail-card .card-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #495057;
            margin-bottom: 0.75rem;
        }

        .branch-detail-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            border-bottom: 1px dashed #dee2e6;
            padding: 0.35rem 0;
            font-size: 0.875rem;
        }

        .branch-detail-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .branch-detail-key {
            color: #6c757d;
            white-space: nowrap;
        }

        .branch-detail-val {
            color: #212529;
            font-weight: 600;
            text-align: right;
            word-break: break-word;
        }
    </style>
@endsection

@section('content')
    @php
        $initialSyncId = session('active_sync_id') ?? optional($activeSync)->Id;
        $latestStatus = optional($latestSync)->Status;
        $sourceName = 'CBS';
        $statusClass = match ($latestStatus) {
            'completed' => 'bg-success',
            'cancelled' => 'bg-secondary',
            'failed' => 'bg-danger',
            'running', 'pending' => 'bg-warning text-dark',
            default => 'bg-secondary',
        };
    @endphp

    <div class="container my-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-1 text-info"><i class="fas fa-sync-alt me-2"></i>Sync Branches ({{ $sourceName }})</h4>
                <p class="text-muted mb-0">Pull branches from {{ $sourceName }} using a background job.</p>
            </div>
            <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>{{ __('common.back') }}</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted">Synced branches</span>
                            <i class="fas fa-database text-info"></i>
                        </div>
                        <h3 class="fw-bold mb-1">{{ number_format($syncCount ?? 0) }}</h3>
                        <small class="text-muted">Synced branches available</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted">Last synced at</span>
                            <i class="fas fa-clock text-info"></i>
                        </div>
                        <h6 class="fw-bold mb-1">{{ $lastSyncedAt ? \Carbon\Carbon::parse($lastSyncedAt)->format('M d, Y h:i A') : 'Never' }}</h6>
                        <small class="text-muted">{{ $lastSyncedAt ? \Carbon\Carbon::parse($lastSyncedAt)->diffForHumans() : 'No sync run yet' }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted">Latest run status</span>
                            <i class="fas fa-tasks text-info"></i>
                        </div>
                        <h6 class="fw-bold mb-1 text-uppercase">{{ $latestStatus ?? 'none' }}</h6>
                        <span class="badge {{ $statusClass }}">{{ $latestStatus ? ucfirst($latestStatus) : 'No runs yet' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div id="syncProgressCard"
             class="card shadow-sm mb-3 {{ $initialSyncId ? '' : 'd-none' }}"
             data-sync-id="{{ $initialSyncId }}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 fw-bold">Sync Progress</h6>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" id="cancelSyncBtn" class="btn btn-sm btn-outline-danger d-none">
                            <i class="fas fa-stop-circle me-1"></i>Cancel Sync
                        </button>
                        <span id="syncStatusBadge" class="badge bg-warning text-dark">{{ __('common.status_pending') }}</span>
                    </div>
                </div>
                <div class="small text-muted mb-2" id="syncStatusMessage">Waiting for updates...</div>
                <div class="progress mb-2" style="height: 10px;">
                    <div id="syncProgressBar" class="progress-bar bg-info" style="width: 0%"></div>
                </div>
                <div class="row small text-muted">
                    <div class="col-md-3">Synced: <span id="syncRecordsSynced">0</span></div>
                    <div class="col-md-3">Failed: <span id="syncRecordsFailed">0</span></div>
                    <div class="col-md-3">Total: <span id="syncTotalRecords">0</span></div>
                    <div class="col-md-3">Page: <span id="syncCurrentPage">0</span></div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Start Sync</h6>
                        <small class="text-muted">Pulls from {{ $sourceName }} endpoint and processes all pages until completion.</small>
                    </div>
                    <button type="button"
                            class="btn btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#confirmSyncModal">
                        <i class="fas fa-sync-alt me-2"></i>Start Sync
                    </button>
                </div>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Request body uses <code>BankID</code>, <code>Source={{ $sourceName }}</code>, and <code>PageSize</code> (default 1000).
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Synced Branches</h6>
                <small class="text-muted">Showing {{ $syncedBranches->count() }} of {{ number_format($syncedBranches->total()) }}</small>
            </div>
            <div class="card-body">
                <form id="branchSearchForm" method="GET" action="{{ route('branches.sync') }}" class="row g-2 mb-3">
                    <div class="col-md-8">
                        <label for="branch_account" class="form-label small text-muted mb-1">Search Branch</label>
                        <select id="branch_account" name="branch_account" class="form-select form-select-sm">
                            @if(!empty($selectedBranchOption))
                                <option value="{{ $selectedBranchOption['id'] }}" selected>{{ $selectedBranchOption['text'] }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-search me-1"></i>{{ __('common.search') }}</button>
                        <a href="{{ route('branches.sync') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i>{{ __('common.reset') }}</a>
                    </div>
                </form>

                @if($syncedBranches->count())
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle">
                            <thead class="table-light">
                            <tr>
                                <th style="width: 80px;">#</th>
                                <th>Branch ID</th>
                                <th>Name</th>
                                <th>{{ __('common.address') }}</th>
                                <th>{{ __('common.status') }}</th>
                                <th class="text-center" style="width: 120px;">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($syncedBranches as $branch)
                                <tr>
                                    <td>{{ $syncedBranches->firstItem() + $loop->index }}</td>
                                    <td>{{ $branch->BranchID ?? '-' }}</td>
                                    <td>{{ $branch->Name ?? '-' }}</td>
                                    <td>{{ $branch->Address ?? '-' }}</td>
                                    <td>
                                        @if($branch->IsActive)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                        @if($branch->IsHQ)
                                            <span class="badge bg-info">HQ</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary view-branch-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#branchDetailsModal"
                                            data-branch-id="{{ $branch->BranchID ?? '' }}"
                                            data-name="{{ $branch->Name ?? '' }}"
                                            data-address="{{ $branch->Address ?? '' }}"
                                            data-address2="{{ $branch->Address2 ?? '' }}"
                                            data-city="{{ $branch->City ?? '' }}"
                                            data-state="{{ $branch->State ?? '' }}"
                                            data-zip="{{ $branch->Zip ?? '' }}"
                                            data-country="{{ $branch->Country ?? '' }}"
                                            data-phone="{{ $branch->Phone ?? '' }}"
                                            data-fax="{{ $branch->Fax ?? '' }}"
                                            data-email="{{ $branch->Email ?? '' }}"
                                            data-is-hq="{{ (int) (bool) $branch->IsHQ }}"
                                            data-is-active="{{ (int) (bool) $branch->IsActive }}"
                                            data-source="{{ $branch->Source ?? '' }}"
                                            data-source-table="{{ $branch->SourceTable ?? '' }}"
                                            data-created-on="{{ optional($branch->CreatedOn)->toDateTimeString() }}"
                                            data-modified-on="{{ optional($branch->ModifiedOn)->toDateTimeString() }}"
                                        >
                                            <i class="fas fa-eye me-1"></i>{{ __('common.view') }}</button>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-2">
                        {{ $syncedBranches->links() }}
                    </div>
                @else
                    <div class="alert alert-secondary mb-0">
                        No synced branch records found yet. Run sync first to populate this table.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmSyncModal" tabindex="-1" aria-labelledby="confirmSyncModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmSyncModalLabel">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>Confirm Sync
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="startSyncForm" action="{{ route('branches.sync.start') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p class="mb-3">This will queue a background sync and replace the current synced branches.</p>
                        <label for="page_size" class="form-label fw-semibold">Page size</label>
                        <select name="page_size" id="page_size" class="form-select">
                            @foreach([50, 100, 500, 1000, 2000, 5000] as $size)
                                <option value="{{ $size }}" {{ $size === 1000 ? 'selected' : '' }}>{{ number_format($size) }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Default is 1000.</small>
                        <div id="startSyncLoader" class="alert alert-info d-none mt-3 mb-0" role="status" aria-live="polite">
                            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                            Sync is starting. Please wait...
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                        <button id="startSyncSubmitBtn" type="submit" class="btn btn-primary">
                            <i class="fas fa-sync-alt me-1"></i>Yes, Start Sync
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="branchDetailsModal" tabindex="-1" aria-labelledby="branchDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header branch-modal-header">
                    <h5 class="modal-title fw-bold" id="branchDetailsModalLabel">
                        <i class="fas fa-code-branch me-2"></i>Branch Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="branch-detail-card card p-3">
                                <div class="card-title">Branch Identity</div>
                                <div class="branch-detail-row">
                                    <span class="branch-detail-key">Branch ID</span>
                                    <span class="branch-detail-val" id="detailBranchID">-</span>
                                </div>
                                <div class="branch-detail-row">
                                    <span class="branch-detail-key">Name</span>
                                    <span class="branch-detail-val" id="detailName">-</span>
                                </div>
                                <div class="branch-detail-row">
                                    <span class="branch-detail-key">{{ __('common.address') }}</span>
                                    <span class="branch-detail-val" id="detailAddress">-</span>
                                </div>
                                <div class="branch-detail-row">
                                    <span class="branch-detail-key">{{ __('common.address') }} 2</span>
                                    <span class="branch-detail-val" id="detailAddress2">-</span>
                                </div>
                                <div class="branch-detail-row">
                                    <span class="branch-detail-key">City / State</span>
                                    <span class="branch-detail-val" id="detailCityState">-</span>
                                </div>
                                <div class="branch-detail-row">
                                    <span class="branch-detail-key">Zip / Country</span>
                                    <span class="branch-detail-val" id="detailZipCountry">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="branch-detail-card card p-3">
                                <div class="card-title">Contact & Status</div>
                                <div class="branch-detail-row">
                                    <span class="branch-detail-key">Phone</span>
                                    <span class="branch-detail-val" id="detailPhone">-</span>
                                </div>
                                <div class="branch-detail-row">
                                    <span class="branch-detail-key">Fax</span>
                                    <span class="branch-detail-val" id="detailFax">-</span>
                                </div>
                                <div class="branch-detail-row">
                                    <span class="branch-detail-key">{{ __('common.email') }}</span>
                                    <span class="branch-detail-val" id="detailEmail">-</span>
                                </div>
                                <div class="branch-detail-row">
                                    <span class="branch-detail-key">{{ __('common.status') }}</span>
                                    <span class="branch-detail-val" id="detailIsActive">-</span>
                                </div>
                                <div class="branch-detail-row">
                                    <span class="branch-detail-key">{{ __('legal.created_on') }}</span>
                                    <span class="branch-detail-val" id="detailCreatedOn">-</span>
                                </div>
                                <div class="branch-detail-row">
                                    <span class="branch-detail-key">Modified On</span>
                                    <span class="branch-detail-val" id="detailModifiedOn">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="branch-detail-card card p-3">
                                <div class="card-title">Source</div>
                                <div class="branch-detail-row">
                                    <span class="branch-detail-key">{{ __('common.source') }}</span>
                                    <span class="branch-detail-val" id="detailSource">-</span>
                                </div>
                                <div class="branch-detail-row">
                                    <span class="branch-detail-key">Source Table</span>
                                    <span class="branch-detail-val" id="detailSourceTable">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.close') }}</button>
                    <button type="button" class="btn btn-primary" id="copyBranchIdBtn">
                        <i class="fas fa-copy me-1"></i>Copy Branch ID
                    </button>
                </div>
                <input type="hidden" id="detailBranchIDRaw" value="">
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('assets/libs/select2/js/select2.full.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const progressCard = document.getElementById('syncProgressCard');
            const progressBaseUrl = @json(url('/settings/branches-sync/progress'));
            const cancelBaseUrl = @json(url('/settings/branches-sync/cancel'));
            const branchSearchSelect = document.getElementById('branch_account');
            const branchSearchForm = document.getElementById('branchSearchForm');

            if (window.jQuery && $.fn.select2 && branchSearchSelect) {
                $('#branch_account').select2({
                    placeholder: 'Search by branch ID or name',
                    allowClear: true,
                    width: '100%',
                    ajax: {
                        url: @json(route('branches.sync.search')),
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return { q: params.term || '' };
                        },
                        processResults: function (data) {
                            return { results: data.results || [] };
                        },
                        cache: true,
                    },
                    templateResult: function (data) {
                        if (!data.id) return data.text;
                        const code = data.code || data.id || '';
                        const name = data.name || '';
                        return $('<div><strong>' + code + '</strong> <small class="text-muted">(' + name + ')</small></div>');
                    },
                    templateSelection: function (data) {
                        if (!data.id) return data.text;
                        const code = data.code || data.id || '';
                        const name = data.name || '';
                        return code && name ? (code + ' (' + name + ')') : (data.text || code);
                    }
                });

                $('#branch_account').on('select2:select', function () {
                    if (branchSearchForm) branchSearchForm.submit();
                });

                $('#branch_account').on('select2:clear', function () {
                    if (branchSearchForm) branchSearchForm.submit();
                });
            }

            if (!progressCard) {
                return;
            }

            let syncId = progressCard.dataset.syncId || null;
            let timer = null;

            const statusBadge = document.getElementById('syncStatusBadge');
            const statusMessage = document.getElementById('syncStatusMessage');
            const progressBar = document.getElementById('syncProgressBar');
            const recordsSynced = document.getElementById('syncRecordsSynced');
            const recordsFailed = document.getElementById('syncRecordsFailed');
            const totalRecords = document.getElementById('syncTotalRecords');
            const currentPage = document.getElementById('syncCurrentPage');
            const cancelSyncBtn = document.getElementById('cancelSyncBtn');
            const startSyncForm = document.getElementById('startSyncForm');
            const startSyncSubmitBtn = document.getElementById('startSyncSubmitBtn');
            const startSyncLoader = document.getElementById('startSyncLoader');
            const confirmSyncModalEl = document.getElementById('confirmSyncModal');
            const copyBranchIdBtn = document.getElementById('copyBranchIdBtn');
            const detailBranchIDRaw = document.getElementById('detailBranchIDRaw');
            const startSyncBtnDefaultHtml = startSyncSubmitBtn ? startSyncSubmitBtn.innerHTML : '';

            const statusBadgeClass = function (status) {
                if (status === 'completed') return 'badge bg-success';
                if (status === 'cancelled') return 'badge bg-secondary';
                if (status === 'failed') return 'badge bg-danger';
                if (status === 'running' || status === 'pending') return 'badge bg-warning text-dark';
                return 'badge bg-secondary';
            };

            const updateCancelButton = function (status, disabled = false) {
                if (!cancelSyncBtn) {
                    return;
                }

                const cancellable = status === 'pending' || status === 'running';
                cancelSyncBtn.classList.toggle('d-none', !cancellable);
                cancelSyncBtn.disabled = disabled || !cancellable;
            };

            const updateProgressUi = function (payload) {
                const status = payload.status || 'unknown';
                const percentage = payload.percentage ?? 0;
                const errorText = payload.isFailed && payload.error ? ` (${payload.error})` : '';

                statusBadge.className = statusBadgeClass(status);
                statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                statusMessage.textContent = (payload.message || 'Processing...') + errorText;
                progressBar.style.width = Math.max(0, Math.min(100, percentage)) + '%';
                recordsSynced.textContent = payload.recordsSynced ?? 0;
                recordsFailed.textContent = payload.recordsFailed ?? 0;
                totalRecords.textContent = payload.totalRecords ?? 0;
                currentPage.textContent = payload.currentPage ?? 0;
                updateCancelButton(status);
            };

            const stopPolling = function () {
                if (timer) {
                    clearInterval(timer);
                    timer = null;
                }
            };

            const poll = async function () {
                if (!syncId) {
                    return;
                }

                try {
                    const response = await fetch(progressBaseUrl + '/' + syncId, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        return;
                    }

                    const payload = await response.json();
                    if (!payload || !payload.success) {
                        return;
                    }

                    progressCard.classList.remove('d-none');
                    updateProgressUi(payload);

                    if (payload.isComplete || payload.isFailed || payload.isCancelled) {
                        stopPolling();
                    }
                } catch (error) {
                    console.error('Failed to poll branch sync progress', error);
                }
            };

            const startPolling = function () {
                stopPolling();
                if (syncId) {
                    progressCard.dataset.syncId = syncId;
                    progressCard.classList.remove('d-none');
                    poll();
                    timer = setInterval(poll, 3000);
                }
            };

            if (startSyncForm) {
                startSyncForm.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    if (startSyncSubmitBtn) {
                        startSyncSubmitBtn.disabled = true;
                        startSyncSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Starting Sync...';
                    }

                    if (startSyncLoader) {
                        startSyncLoader.classList.remove('d-none');
                    }

                    if (progressCard) {
                        progressCard.classList.remove('d-none');
                    }

                    updateCancelButton('pending', true);

                    if (statusMessage) {
                        statusMessage.textContent = 'Sync is starting. Please wait...';
                    }

                    if (statusBadge) {
                        statusBadge.className = 'badge bg-warning text-dark';
                        statusBadge.textContent = 'Starting';
                    }

                    if (progressBar) {
                        progressBar.classList.add('progress-bar-striped', 'progress-bar-animated');
                        progressBar.style.width = '5%';
                    }

                    const formData = new FormData(startSyncForm);
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                    try {
                        const response = await fetch(startSyncForm.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf || formData.get('_token'),
                            },
                            credentials: 'same-origin',
                            body: formData,
                        });

                        let payload = null;
                        try {
                            payload = await response.json();
                        } catch (e) {
                            payload = null;
                        }

                        if (progressBar) {
                            progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
                        }

                        if (!response.ok || !payload || !payload.success) {
                            if (payload?.syncRunId) {
                                syncId = payload.syncRunId;
                                statusMessage.textContent = payload.message || 'Sync is already running.';
                                statusBadge.className = 'badge bg-warning text-dark';
                                statusBadge.textContent = 'Running';
                                updateCancelButton('running');
                                startPolling();
                                const modal = bootstrap.Modal.getInstance(confirmSyncModalEl);
                                if (modal) {
                                    modal.hide();
                                }
                                return;
                            }

                            statusMessage.textContent = payload?.message || 'Failed to start sync.';
                            statusBadge.className = 'badge bg-danger';
                            statusBadge.textContent = 'Failed';
                            progressBar.style.width = '0%';
                            updateCancelButton('failed');
                            return;
                        }

                        syncId = payload.syncRunId;
                        statusMessage.textContent = payload.message || 'Sync started.';
                        statusBadge.className = 'badge bg-warning text-dark';
                        statusBadge.textContent = 'Pending';
                        updateCancelButton('pending');

                        const modal = bootstrap.Modal.getInstance(confirmSyncModalEl);
                        if (modal) {
                            modal.hide();
                        }

                        startPolling();
                    } catch (error) {
                        console.error('Failed to start sync', error);
                        statusMessage.textContent = 'Failed to start sync due to a network error.';
                        statusBadge.className = 'badge bg-danger';
                        statusBadge.textContent = 'Failed';
                        progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
                        progressBar.style.width = '0%';
                        updateCancelButton('failed');
                    } finally {
                        if (startSyncLoader) {
                            startSyncLoader.classList.add('d-none');
                        }

                        if (startSyncSubmitBtn) {
                            startSyncSubmitBtn.disabled = false;
                            startSyncSubmitBtn.innerHTML = startSyncBtnDefaultHtml;
                        }
                    }
                });
            }

            if (cancelSyncBtn) {
                cancelSyncBtn.addEventListener('click', async function () {
                    if (!syncId || !window.confirm('Cancel the current branch sync?')) {
                        return;
                    }

                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    cancelSyncBtn.disabled = true;
                    cancelSyncBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Cancelling...';

                    try {
                        const response = await fetch(cancelBaseUrl + '/' + syncId, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf || '',
                            },
                            credentials: 'same-origin',
                        });

                        let payload = null;
                        try {
                            payload = await response.json();
                        } catch (e) {
                            payload = null;
                        }

                        if (!response.ok || !payload || !payload.success) {
                            statusMessage.textContent = payload?.message || 'Failed to cancel sync.';
                            updateCancelButton('running');
                            return;
                        }

                        statusBadge.className = statusBadgeClass('cancelled');
                        statusBadge.textContent = 'Cancelled';
                        statusMessage.textContent = payload.message || 'Branch sync cancelled.';
                        progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
                        updateCancelButton('cancelled');
                        stopPolling();
                    } catch (error) {
                        console.error('Failed to cancel sync', error);
                        statusMessage.textContent = 'Failed to cancel sync due to a network error.';
                        updateCancelButton('running');
                    } finally {
                        cancelSyncBtn.innerHTML = '<i class="fas fa-stop-circle me-1"></i>Cancel Sync';
                    }
                });
            }

            if (syncId) {
                startPolling();
            }

            document.querySelectorAll('.view-branch-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    const d = button.dataset;
                    const setText = function (id, value) {
                        const el = document.getElementById(id);
                        if (el) el.textContent = value || '-';
                    };

                    setText('detailBranchID', d.branchId);
                    setText('detailName', d.name);
                    setText('detailAddress', d.address);
                    setText('detailAddress2', d.address2);
                    setText('detailCityState', [d.city, d.state].filter(Boolean).join(', '));
                    setText('detailZipCountry', [d.zip, d.country].filter(Boolean).join(', '));
                    setText('detailPhone', d.phone);
                    setText('detailFax', d.fax);
                    setText('detailEmail', d.email);
                    setText('detailSource', d.source);
                    setText('detailSourceTable', d.sourceTable);
                    setText('detailCreatedOn', d.createdOn);
                    setText('detailModifiedOn', d.modifiedOn);

                    const activeEl = document.getElementById('detailIsActive');
                    if (activeEl) {
                        const badges = [];
                        badges.push(d.isActive === '1'
                            ? '<span class="badge bg-success">Active</span>'
                            : '<span class="badge bg-danger">Inactive</span>');
                        if (d.isHq === '1') {
                            badges.push('<span class="badge bg-info ms-1">HQ</span>');
                        }
                        activeEl.innerHTML = badges.join('');
                    }

                    if (detailBranchIDRaw) {
                        detailBranchIDRaw.value = d.branchId || '';
                    }
                });
            });

            if (copyBranchIdBtn && detailBranchIDRaw) {
                copyBranchIdBtn.addEventListener('click', async function () {
                    const code = detailBranchIDRaw.value || '';
                    if (!code) {
                        return;
                    }

                    try {
                        await navigator.clipboard.writeText(code);
                        copyBranchIdBtn.innerHTML = '<i class="fas fa-check me-1"></i>Copied';
                        setTimeout(function () {
                            copyBranchIdBtn.innerHTML = '<i class="fas fa-copy me-1"></i>Copy Branch ID';
                        }, 1200);
                    } catch (error) {
                        console.error('Copy failed', error);
                    }
                });
            }
        });
    </script>
@endsection
