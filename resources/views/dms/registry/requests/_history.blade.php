{{-- Offcanvas body: one request, and everything that bears on deciding it. --}}
@php
    $statusValue = $request->status?->Value;
    $badge = match ($statusValue) {
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'pending' => 'bg-primary',
        default => 'bg-secondary',
    };
@endphp

<div>
    <div class="d-flex justify-content-between align-items-start gap-2">
        <div>
            <h5 class="mb-0">{{ $file->FileNumber }}</h5>
            <div class="text-muted">{{ $file->Title }}</div>
        </div>
        <span class="badge {{ $badge }}">{{ $request->status?->Description ?: 'unknown' }}</span>
    </div>

    <dl class="row small mt-3 mb-0">
        <dt class="col-sm-4 text-muted fw-normal">Requested by</dt>
        <dd class="col-sm-8">
            {{ $request->requester?->Name ?: '-' }}
            <span class="text-muted">on {{ $request->RequestedOn?->format('d M Y') ?: '-' }}</span>
        </dd>

        <dt class="col-sm-4 text-muted fw-normal">Needed</dt>
        <dd class="col-sm-8">
            {{ $request->NeededFrom?->format('d M Y') }}
            &ndash; {{ $request->RequestedUntil?->format('d M Y') }}
        </dd>

        <dt class="col-sm-4 text-muted fw-normal">Purpose</dt>
        <dd class="col-sm-8">{{ $request->Purpose }}</dd>

        @if($request->DecidedOn)
            <dt class="col-sm-4 text-muted fw-normal">Decided</dt>
            <dd class="col-sm-8">
                by {{ $request->decidedBy?->Name ?: 'someone' }}
                on {{ $request->DecidedOn->format('d M Y') }}
                @if($request->DecisionRemark)
                    <div class="text-muted">&ldquo;{{ $request->DecisionRemark }}&rdquo;</div>
                @endif
            </dd>
        @endif

        @if($request->CancelledOn)
            <dt class="col-sm-4 text-muted fw-normal">Cancelled</dt>
            <dd class="col-sm-8">
                by {{ $request->cancelledBy?->Name ?: 'someone' }}
                on {{ $request->CancelledOn->format('d M Y') }}
                @if($request->CancellationReason)
                    <div class="text-muted">&ldquo;{{ $request->CancellationReason }}&rdquo;</div>
                @endif
            </dd>
        @endif
    </dl>

    <hr>

    <h6 class="small text-uppercase text-muted">Where the file is now</h6>
    <dl class="row small mb-0">
        <dt class="col-sm-4 text-muted fw-normal">Status</dt>
        <dd class="col-sm-8">{{ $file->status?->Description ?: '-' }}</dd>

        <dt class="col-sm-4 text-muted fw-normal">Currently with</dt>
        <dd class="col-sm-8">
            {{ $file->currentHolder?->Name ?: (trim((string) $file->currentLocation?->Path, '/') ?: 'In transit') }}
        </dd>

        <dt class="col-sm-4 text-muted fw-normal">Home location</dt>
        <dd class="col-sm-8">{{ trim((string) $file->homeLocation?->Path, '/') ?: '—' }}</dd>
    </dl>

    <a href="{{ route('registry.physical-files.show', $file->Id) }}"
       class="btn btn-sm btn-outline-secondary mt-2">
        <i class="fas fa-folder-open me-1"></i> Open the file
    </a>

    <h6 class="small text-uppercase text-muted mt-4">Earlier requests</h6>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
            <tr>
                <th>Requester</th>
                <th>Period</th>
                <th>Outcome</th>
            </tr>
            </thead>
            <tbody>
            @forelse($earlier as $item)
                <tr>
                    <td>{{ $item->requester?->Name ?: '-' }}</td>
                    <td class="text-nowrap">
                        {{ $item->NeededFrom?->format('d M Y') }}
                        &ndash; {{ $item->RequestedUntil?->format('d M Y') }}
                    </td>
                    <td>
                        {{ $item->status?->Description ?: '-' }}
                        @if($item->decidedBy)
                            <br><small class="text-muted">by {{ $item->decidedBy->Name }}</small>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-muted">Nobody has asked for this file before.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <h6 class="small text-uppercase text-muted mt-4">Loan history</h6>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
            <tr>
                <th>Borrower</th>
                <th>Out</th>
                <th>Due</th>
                <th>Returned</th>
            </tr>
            </thead>
            <tbody>
            @forelse($loans as $loan)
                @php
                    $late = $loan->DueOn
                        && (($loan->ReturnedOn && $loan->ReturnedOn->greaterThan($loan->DueOn))
                            || (! $loan->ReturnedOn && $loan->DueOn->isPast()));
                @endphp
                <tr>
                    <td>{{ $loan->borrower?->Name ?: '-' }}</td>
                    <td class="text-nowrap">{{ $loan->CheckedOutOn?->format('d M Y') ?: '-' }}</td>
                    <td class="text-nowrap">{{ $loan->DueOn?->format('d M Y') ?: '-' }}</td>
                    <td class="text-nowrap {{ $late ? 'text-danger fw-semibold' : '' }}">
                        {{ $loan->ReturnedOn?->format('d M Y') ?: 'still out' }}
                        @if($late)
                            <br><small>late</small>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-muted">This file has never been lent out.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($canDecide && $statusValue === 'pending')
        <div class="d-flex gap-2 mt-4 pt-3 border-top">
            <button type="button" class="btn btn-success decide-request"
                    data-id="{{ $request->Id }}" data-decision="approve"
                    data-file="{{ $file->FileNumber }}">
                <i class="fas fa-check me-1"></i> Approve
            </button>
            <button type="button" class="btn btn-outline-danger decide-request"
                    data-id="{{ $request->Id }}" data-decision="reject"
                    data-file="{{ $file->FileNumber }}">
                <i class="fas fa-times me-1"></i> Reject
            </button>
        </div>
    @endif

    {{-- An approval still to be honoured: give the file out, or take the
         approval back. --}}
    @if($statusValue === 'approved' && ($canHandOver || $canCancel))
        <div class="d-flex gap-2 mt-4 pt-3 border-top">
            @if($canHandOver)
                <button type="button" class="btn btn-primary handover-request"
                        data-id="{{ $request->Id }}" data-file="{{ $file->FileNumber }}"
                        data-requester="{{ $request->requester?->Name ?: 'the requester' }}">
                    <i class="fas fa-hand-holding me-1"></i> Hand over
                </button>
            @endif
            @if($canCancel)
                <button type="button" class="btn btn-outline-danger cancel-request"
                        data-id="{{ $request->Id }}" data-file="{{ $file->FileNumber }}">
                    <i class="fas fa-ban me-1"></i> Cancel approval
                </button>
            @endif
        </div>
    @endif
</div>
