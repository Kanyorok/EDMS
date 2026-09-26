<div class="dms-secure-preview">
    <div class="dms-secure-preview__meta mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">
            <i class="ti ti-shield-lock me-1" aria-hidden="true"></i>{{ $accessContext }}
        </div>
        <a href="{{ $downloadUrl }}" class="btn app-button app-button--outline-primary app-button--sm">
            <i class="ti ti-download me-1" aria-hidden="true"></i>Download
        </a>
    </div>

    @if($service->isPrevieable())
        <div class="dms-secure-preview__frame">
            {!! $service->preview('width="100%" height="100" style="min-height:70vh; border:0;"') !!}
        </div>
    @else
        <div class="text-center py-5">
            <i class="ti ti-file-off" style="font-size:3rem;" aria-hidden="true"></i>
            <h5 class="mt-3 mb-1">No preview available</h5>
            <p class="text-muted mb-3">This file type cannot be previewed inline. Download it to view its contents.</p>
            <a href="{{ $downloadUrl }}" class="btn app-button app-button--primary">
                <i class="ti ti-download me-1" aria-hidden="true"></i>Download {{ $document->Name }}
            </a>
        </div>
    @endif
</div>
