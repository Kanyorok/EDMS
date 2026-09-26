@extends('layouts.app')

@section('title', 'Historical Data Migration')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Historical Data Migration</h3>
            <p class="text-muted mb-0">
                Stage suppliers from a client's pre-BRERP records, then gradually sync as they finish registering -
                each matched supplier gets the prequalification &rarr; tender &rarr; contract trail this system
                requires before it can be sourced for a PO/LPO.
            </p>
        </div>
        <a href="{{ route('admin.historical-migration.template') }}" class="btn btn-outline-secondary">
            Download Template (.xlsx)
        </a>
    </div>

    <div class="alert alert-warning" role="alert">
        This writes directly to the connected database. Always run <strong>Preview</strong> before <strong>Save to
        Staging</strong>, and <strong>Preview Sync</strong> before <strong>Confirm Sync</strong>.
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Dashboard --}}
    <div class="d-flex flex-wrap gap-3 mb-4">
        <span class="badge bg-secondary fs-6">Staged total: {{ $stats['total'] }}</span>
        <span class="badge bg-success fs-6">Matched, pending sync: {{ $stats['matched_pending'] }}</span>
        <span class="badge bg-warning text-dark fs-6">Unmatched: {{ $stats['unmatched'] }}</span>
        <span class="badge bg-dark fs-6">Trail built: {{ $stats['built'] }}</span>
        <span class="badge bg-danger fs-6">Failed: {{ $stats['failed'] }}</span>
        <span class="badge bg-light text-dark fs-6">Invalid rows: {{ $stats['invalid'] }}</span>
    </div>

    {{-- Step 1: upload + preview --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">1. Upload &amp; Preview</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.historical-migration.preview') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="uploaded_file" class="form-label">Upload File</label>
                    <input type="file"
                           class="form-control @error('uploaded_file') is-invalid @enderror"
                           id="uploaded_file"
                           name="uploaded_file"
                           accept=".xlsx,.xls,.csv">
                    <div class="form-text">
                        Works no matter where this site is hosted - the file travels with the upload. Use this
                        instead of the path below when you're on the hosted site rather than running locally.
                    </div>
                    @error('uploaded_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="d-flex align-items-center gap-2 my-2">
                    <hr class="flex-grow-1">
                    <span class="text-muted small">OR</span>
                    <hr class="flex-grow-1">
                </div>

                <div class="mb-3">
                    <label for="file_path" class="form-label">Server File Path</label>
                    <input type="text"
                           class="form-control @error('file_path') is-invalid @enderror"
                           id="file_path"
                           name="file_path"
                           value="{{ old('file_path', $previewFilePath) }}"
                           placeholder="C:\Users\you\Documents\client-suppliers.xlsx"
                           autocomplete="off">
                    <div class="form-text">
                        Only works if this path exists on the machine actually running this web server - your
                        own computer if you're running locally, or the hosted server itself if not.
                    </div>
                    @error('file_path')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-text mb-3">
                    Columns (names flexible - see the downloadable template): Supplier ID, Supplier Tax PIN,
                    Supplier Name, Contract Filename. At least one of ID/PIN/Name is required per row - not
                    every row needs every field. A supplier must already exist in the system to match; rows
                    that don't match yet are staged anyway and retried automatically on every Sync.
                </div>

                <button type="submit" class="btn btn-outline-primary">Preview</button>
            </form>

            @if($previewResults !== null)
                <hr>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <span class="badge bg-secondary">Total: {{ $previewSummary['total'] }}</span>
                    <span class="badge bg-success">Matched: {{ $previewSummary['matched'] }}</span>
                    <span class="badge bg-warning text-dark">Unmatched (will retry on sync): {{ $previewSummary['unmatched'] }}</span>
                    <span class="badge bg-light text-dark">Invalid (no identity fields at all): {{ $previewSummary['invalid'] }}</span>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Supplier ID</th>
                                <th>Tax PIN</th>
                                <th>Supplier Name</th>
                                <th>Contract File</th>
                                <th>Status</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($previewResults as $row)
                                <tr>
                                    <td>{{ $row['row_number'] }}</td>
                                    <td>{{ $row['supplier_code'] ?? '—' }}</td>
                                    <td>{{ $row['registration_no'] ?? '—' }}</td>
                                    <td>{{ $row['supplier_identifier'] ?? '—' }}</td>
                                    <td>{{ $row['contract_file_path'] ? basename($row['contract_file_path']) : '—' }}</td>
                                    <td>
                                        @php
                                            $badgeClass = match($row['status']) {
                                                'matched' => 'bg-success',
                                                'unmatched' => 'bg-warning text-dark',
                                                default => 'bg-light text-dark',
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }}">{{ $row['status'] }}</span>
                                    </td>
                                    <td class="small text-muted">{{ $row['message'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <form method="POST" action="{{ route('admin.historical-migration.stage') }}"
                      onsubmit="return confirm('This will save {{ $previewSummary['total'] }} row(s) to the staging table. Continue?');">
                    @csrf
                    <input type="hidden" name="file_path" value="{{ $previewFilePath }}">
                    <button type="submit" class="btn btn-primary">Save {{ $previewSummary['total'] }} Row(s) to Staging</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Step 2: locate contract files --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">2. Locate Contract Files</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">
                The "Contract Filename" column only holds a filename (e.g. <code>CT-2023-001.pdf</code>), not a
                full path - the physical files live in one directory. Point at that directory to match each
                staged row's filename against what's actually there before Sync attaches them.
            </p>
            <form method="POST" action="{{ route('admin.historical-migration.locate-files-preview') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="contract_files" class="form-label">Upload Contract Files Folder</label>
                    <input type="file"
                           class="form-control @error('contract_files') is-invalid @enderror"
                           id="contract_files"
                           name="contract_files[]"
                           multiple
                           webkitdirectory
                           directory>
                    <div class="form-text">
                        Works no matter where this site is hosted. Click, then choose the whole folder of contract
                        files (not individual files) - your browser uploads everything inside it in one go.
                    </div>
                    @error('contract_files')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="d-flex align-items-center gap-2 my-2">
                    <hr class="flex-grow-1">
                    <span class="text-muted small">OR</span>
                    <hr class="flex-grow-1">
                </div>

                <div class="mb-3">
                    <label for="contract_files_directory" class="form-label">Server Directory Path</label>
                    <input type="text"
                           class="form-control @error('contract_files_directory') is-invalid @enderror"
                           id="contract_files_directory"
                           name="contract_files_directory"
                           value="{{ old('contract_files_directory', $locateDirectory) }}"
                           placeholder="C:\Contracts\Scanned"
                           autocomplete="off">
                    <div class="form-text">
                        Only works if this path exists on the machine actually running this web server.
                    </div>
                    @error('contract_files_directory')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-outline-primary">Scan for Files</button>
            </form>

            @if($locateResults !== null)
                <hr>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <span class="badge bg-secondary">Total pending files: {{ $locateSummary['total'] }}</span>
                    <span class="badge bg-success">Found: {{ $locateSummary['found'] }}</span>
                    <span class="badge bg-warning text-dark">Missing: {{ $locateSummary['missing'] }}</span>
                    <span class="badge bg-dark">Already resolved: {{ $locateSummary['already_resolved'] }}</span>
                </div>

                @if($locateSummary['total'] === 0)
                    <p class="text-muted mb-0">No staged rows are waiting on a contract file right now.</p>
                @else
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Staging #</th>
                                    <th>Filename</th>
                                    <th>Resolved Path</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($locateResults as $row)
                                    <tr>
                                        <td>{{ $row['id'] }}</td>
                                        <td>{{ $row['filename'] }}</td>
                                        <td class="small text-muted">{{ $row['resolved_path'] }}</td>
                                        <td>
                                            @if($row['already_resolved'])
                                                <span class="badge bg-dark">already resolved</span>
                                            @elseif($row['found'])
                                                <span class="badge bg-success">found</span>
                                            @else
                                                <span class="badge bg-warning text-dark">missing</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($locateSummary['found'] > 0)
                        <form method="POST" action="{{ route('admin.historical-migration.locate-files') }}"
                              onsubmit="return confirm('This will link {{ $locateSummary['found'] }} file(s) to their staged rows. Continue?');">
                            @csrf
                            <input type="hidden" name="contract_files_directory" value="{{ $locateDirectory }}">
                            <button type="submit" class="btn btn-primary">Link {{ $locateSummary['found'] }} Found File(s)</button>
                        </form>
                    @endif
                @endif
            @endif
        </div>
    </div>

    {{-- Step 3: sync --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">3. Sync (matches unmatched rows, builds the eligibility trail for matched ones)</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.historical-migration.sync-preview') }}">
                <button type="submit" class="btn btn-outline-primary">Preview Sync</button>
            </form>

            @if($syncPreviewResults !== null)
                <hr>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <span class="badge bg-secondary">Total pending: {{ $syncPreviewSummary['total'] }}</span>
                    <span class="badge bg-dark">Trail will be built: {{ $syncPreviewSummary['build_trail'] }}</span>
                    <span class="badge bg-success">Newly matched + built: {{ $syncPreviewSummary['match_and_build'] }}</span>
                    <span class="badge bg-warning text-dark">Still unmatched: {{ $syncPreviewSummary['still_unmatched'] }}</span>
                </div>

                @if($syncPreviewSummary['total'] === 0)
                    <p class="text-muted mb-0">Nothing pending - every staged row is either already built or has no match candidate change.</p>
                @else
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Staging #</th>
                                    <th>Source</th>
                                    <th>Matched Supplier</th>
                                    <th>Action</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($syncPreviewResults as $row)
                                    <tr>
                                        <td>{{ $row['id'] }}</td>
                                        <td>{{ $row['source'] ?? '—' }}</td>
                                        <td>{{ $row['supplier_display_name'] ?? '—' }}</td>
                                        <td>
                                            @php
                                                $actionClass = match($row['action']) {
                                                    'build_trail', 'match_and_build' => 'bg-success',
                                                    default => 'bg-warning text-dark',
                                                };
                                            @endphp
                                            <span class="badge {{ $actionClass }}">{{ str_replace('_', ' ', $row['action']) }}</span>
                                        </td>
                                        <td class="small text-muted">{{ $row['message'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" action="{{ route('admin.historical-migration.sync') }}"
                          onsubmit="return confirm('This will build the eligibility trail (prequalification, tender, award, contract) for matched rows and write to the database. Continue?');">
                        @csrf
                        <button type="submit" class="btn btn-danger">Confirm Sync</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    {{-- Staged rows --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Staged Rows (latest 200)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Batch</th>
                            <th>Supplier ID</th>
                            <th>Tax PIN</th>
                            <th>Supplier Name</th>
                            <th>Matched To</th>
                            <th>Match Status</th>
                            <th>Trail Status</th>
                            <th>Contract Ref</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stagedRows as $row)
                            <tr>
                                <td>{{ $row->Id }}</td>
                                <td class="small text-muted">{{ $row->BatchLabel }}</td>
                                <td>{{ $row->SourceSupplierCode ?? '—' }}</td>
                                <td>{{ $row->SourceTaxPin ?? '—' }}</td>
                                <td>{{ $row->SourceSupplierName ?? '—' }}</td>
                                <td>{{ $row->SupplierDisplayName ?? '—' }}</td>
                                <td>
                                    @php
                                        $matchClass = match($row->MatchStatus) {
                                            'matched' => 'bg-success',
                                            'unmatched' => 'bg-warning text-dark',
                                            default => 'bg-light text-dark',
                                        };
                                    @endphp
                                    <span class="badge {{ $matchClass }}">{{ $row->MatchStatus }}</span>
                                </td>
                                <td>
                                    @php
                                        $trailClass = match($row->TrailStatus) {
                                            'built' => 'bg-dark',
                                            'failed' => 'bg-danger',
                                            default => 'bg-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $trailClass }}">{{ $row->TrailStatus }}</span>
                                </td>
                                <td class="small">{{ $row->ContractRef ?? '—' }}</td>
                                <td class="small text-danger">{{ $row->LastError }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted">No rows staged yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
