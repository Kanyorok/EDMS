<div class="w-100">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0">{{ $version->Name }}</h5>
            <small class="text-muted">
                v{{ $version->Version }} &middot; {{ $version->CreatedOn?->format('d M Y, H:i') }} &middot; {{ $version->creator->Name ?? 'System' }}
            </small>
        </div>
        <a href="{{ $downloadUrl }}" download="{{ $version->Name }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-file-download"></i> download this version
        </a>
    </div>

    @if($previewable)
        {!! $html !!}
    @else
        <div class="text-center py-5">
            <img src="{{ $type->getIcon('img') }}" alt="{{ $type->name }}" class="wid-75 mb-3">
            <h4 class="mb-1">No Preview Available</h4>
            <p class="text-muted mb-0">This file type cannot be previewed inline. Use the download button above to view its contents.</p>
        </div>
    @endif
</div>
