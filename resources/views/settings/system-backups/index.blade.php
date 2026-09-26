@extends('layouts.app')

@section('title', 'System Backup')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">System Backup</h3>
            <p class="text-muted mb-0">Configure database backup instructions and schedule daily backup runs.</p>
        </div>
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
            <strong>Please fix the highlighted backup settings.</strong>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Backup Configuration</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info" role="alert">
                        The backup path must exist on the database server. The ERP sends the backup command to the database; it does not upload the file from your browser.
                    </div>

                    <form method="POST" action="{{ route('settings.system-backups.update') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="BackupPath" class="form-label">Backup File Path</label>
                            <input type="text"
                                   class="form-control @error('BackupPath') is-invalid @enderror"
                                   id="BackupPath"
                                   name="BackupPath"
                                   value="{{ old('BackupPath', $setting->BackupPath) }}"
                                   placeholder="D:\SQLBackups">
                            <div class="form-text">Enter a folder path accessible by SQL Server, for example <code>D:\SQLBackups</code>.</div>
                            @error('BackupPath')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="FileNamePrefix" class="form-label">Backup File Prefix</label>
                            <input type="text"
                                   class="form-control @error('FileNamePrefix') is-invalid @enderror"
                                   id="FileNamePrefix"
                                   name="FileNamePrefix"
                                   value="{{ old('FileNamePrefix', $setting->FileNamePrefix ?: 'BRERP') }}"
                                   placeholder="BRERP">
                            <div class="form-text">The system will append the database name, timestamp, and <code>.bak</code>.</div>
                            @error('FileNamePrefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="border rounded p-3 mb-3">
                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="ScheduleEnabled" value="0">
                                <input class="form-check-input"
                                       type="checkbox"
                                       role="switch"
                                       id="ScheduleEnabled"
                                       name="ScheduleEnabled"
                                       value="1"
                                       @checked(old('ScheduleEnabled', $setting->ScheduleEnabled))>
                                <label class="form-check-label" for="ScheduleEnabled">Enable Daily Scheduled Backup</label>
                            </div>

                            <label for="ScheduleTime" class="form-label">Daily Backup Time</label>
                            <input type="time"
                                   class="form-control @error('ScheduleTime') is-invalid @enderror"
                                   id="ScheduleTime"
                                   name="ScheduleTime"
                                   value="{{ old('ScheduleTime', $setting->ScheduleTime ? substr((string) $setting->ScheduleTime, 0, 5) : '02:00') }}">
                            <div class="form-text">The Laravel scheduler must be running on the server for scheduled backups.</div>
                            @error('ScheduleTime')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save me-1"></i> Save Backup Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Run Backup Now</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">This immediately sends a backup instruction to the database using the configured path.</p>
                    <form method="POST" action="{{ route('settings.system-backups.run-now') }}"
                          onsubmit="return confirm('Start a database backup now?');">
                        @csrf
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fa fa-database me-1"></i> Run Backup Now
                        </button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Current Schedule</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <span class="text-muted">Scheduled:</span>
                        <strong>{{ $setting->ScheduleEnabled ? 'Yes' : 'No' }}</strong>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted">Time:</span>
                        <strong>{{ $setting->ScheduleTime ? substr((string) $setting->ScheduleTime, 0, 5) : 'Not set' }}</strong>
                    </div>
                    <div>
                        <span class="text-muted">Last Run:</span>
                        <strong>{{ $setting->LastRunAt ? $setting->LastRunAt->format('d/m/Y H:i') : 'Never' }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-header">
            <h5 class="mb-0">Recent Backup Runs</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Started</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Backup File</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($runs as $run)
                        <tr>
                            <td>{{ $run->StartedOn?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td>{{ ucfirst($run->BackupType) }}</td>
                            <td>
                                @php
                                    $badge = match($run->Status) {
                                        'succeeded' => 'bg-success',
                                        'failed' => 'bg-danger',
                                        'running' => 'bg-info',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $badge }}">{{ ucfirst($run->Status) }}</span>
                            </td>
                            <td><code>{{ $run->BackupFile }}</code></td>
                            <td>{{ $run->Message ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No backup runs recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
