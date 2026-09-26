{{-- The documents pane of a file's page, fetched over ajax.

     A paper either has a counterpart in t_Documents or it does not. Where it
     does, the soft copy opens in the shared preview modal; where it does not,
     a custodian can scan it in and the two are linked from then on. --}}
<div class="list-group" data-documents-count="{{ $documents->count() }}">
    @forelse($documents as $index => $document)
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <span class="fw-semibold">{{ $index + 1 }}. {{ $document['title'] }}</span>
                    @if($document['reference'])
                        <span class="text-muted small">({{ $document['reference'] }})</span>
                    @endif
                    @if($document['description'])
                        <div class="text-muted small">{{ $document['description'] }}</div>
                    @endif
                    @foreach($document['links'] as $link)
                        <span class="badge bg-light text-dark border mt-1">{{ $link }}</span>
                    @endforeach
                </div>
                <div class="text-end text-nowrap">
                    <div class="text-muted small mb-1">{{ $document['date'] ?: '—' }}</div>
                    @if($document['softCopy'])
                        @can('view', $document['softCopy'])
                            <button type="button" class="btn btn-sm btn-outline-info modal-preview-document"
                                    title="{{ $document['softCopy']->Name }}"
                                    data-url="{{ route('file.embed-preview', [$document['softCopy']->DocumentId]) }}">
                                <i class="fas fa-eye me-1"></i> Soft copy
                            </button>
                        @else
                            <span class="badge bg-light text-dark border">soft copy restricted</span>
                        @endcan
                    @elseif($canManage)
                        <button type="button" class="btn btn-sm btn-outline-primary upload-soft-copy"
                                data-document-id="{{ $document['id'] }}"
                                data-document="{{ $document['title'] }}">
                            <i class="fas fa-upload me-1"></i> Upload scan
                        </button>
                    @else
                        <span class="text-muted small">paper only</span>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="list-group-item file-empty">
            No documents have been filed into this file yet.
        </div>
    @endforelse
</div>
