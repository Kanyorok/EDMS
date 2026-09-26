@php use Illuminate\Support\Number; @endphp
<div class="d-flex flex-column" style="height: 85%; overflow-y: auto;">
    <h2 class="text-center">{{ $file->Name }}</h2>
    <p class="text-center text-muted mb-4">{{ $versions->count() }} {{ Str::plural('version', $versions->count()) }}, newest first</p>

    @if($versions->isEmpty())
        <div class="alert alert-info text-center">
            <i class="fas fa-info-circle fa-2x mb-2"></i>
            <p class="mb-0">No versions found for this document.</p>
        </div>
    @else
        <div class="version-timeline">
            @foreach($versions as $index => $version)
                <div class="version-timeline-item">
                    <div class="version-timeline-marker {{ $index === 0 ? 'bg-primary' : 'bg-secondary' }}"></div>
                    <div class="version-timeline-content">
                        <div class="card mb-3 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0">
                                        <span class="badge {{ $index === 0 ? 'bg-primary' : 'bg-secondary' }}">v{{ $version->Version }}</span>
                                        @if($index === 0)
                                            <span class="badge bg-success">Latest</span>
                                        @endif
                                    </h6>
                                    <small class="text-muted">{{ Number::fileSize($version->Size, 2) }}</small>
                                </div>

                                <a href="javascript:void(0)" class="modal-preview-document d-block text-truncate fw-semibold mb-2"
                                   title="{{ $version->Name }}"
                                   data-url="{{ route('document-versions.preview',[$file->DocumentId, $version->Id]) }}">
                                    <i class="fas fa-eye text-muted"></i> {{ $version->Name }}
                                </a>

                                <p class="mb-1">
                                    <i class="fas fa-clock text-muted"></i>
                                    {{ $version->CreatedOn?->format('d M Y, H:i') }}
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-user text-muted"></i>
                                    {{ $version->creator->Name ?? 'System' }}
                                </p>

                                @if($version->checkoutCycle)
                                    <div class="mt-2 p-2 bg-light rounded mb-1">
                                        <i class="fas fa-arrow-up-from-bracket text-muted"></i> <b>Check-out note:</b>
                                        {{ filled($version->checkoutCycle->CheckOutRemark) ? $version->checkoutCycle->CheckOutRemark : 'no note provided' }}
                                    </div>
                                    <div class="p-2 bg-light rounded">
                                        <i class="fas fa-arrow-down-to-bracket text-muted"></i> <b>Check-in note:</b>
                                        {{ filled($version->checkoutCycle->CheckInRemark) ? $version->checkoutCycle->CheckInRemark : 'no note provided' }}
                                    </div>
                                @elseif(filled($version->Description))
                                    <div class="mt-2 p-2 bg-light rounded">
                                        <i class="fas fa-info-circle text-muted"></i> {{ $version->Description }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-2">
        <button type="button" class="btn btn-secondary w-100" onclick="window.bsOffcanvas.hide();">close</button>
    </div>
</div>
<style>
    .version-timeline {
        position: relative;
        padding: 10px 0;
    }

    .version-timeline::before {
        content: '';
        position: absolute;
        left: 9px;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(180deg, rgba(var(--bs-primary-rgb), .8) 0%, rgba(var(--bs-secondary-rgb), .25) 100%);
    }

    .version-timeline-item {
        position: relative;
        padding-left: 35px;
        margin-bottom: 10px;
    }

    .version-timeline-marker {
        position: absolute;
        left: 0;
        top: 6px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 3px solid #fff;
        box-shadow: 0 0 0 2px rgba(var(--bs-secondary-rgb), .25);
        z-index: 1;
    }
</style>
