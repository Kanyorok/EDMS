{{-- Offcanvas body: the registries this user may work in, for switching. --}}
<div>
    @if($registries->count() > 1)
        <input type="search" class="form-control mb-3" id="RegistrySwitchFilter"
               placeholder="Filter registries by code or name" autocomplete="off">
    @endif

    <div class="list-group" id="RegistrySwitchList">
        @forelse($registries as $option)
            @php $isCurrent = $currentId !== null && (int) $option->Id === (int) $currentId; @endphp
            <button type="button"
                    class="list-group-item list-group-item-action switch-registry @if($isCurrent) active @endif"
                    data-registry-id="{{ $option->Id }}"
                    data-search="{{ strtolower($option->Code . ' ' . $option->Name) }}"
                    @disabled($isCurrent)>
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="fw-semibold">{{ $option->Code }} - {{ $option->Name }}</div>
                        <small class="@if($isCurrent) text-white-50 @else text-muted @endif">
                            Head of registry: {{ $option->hod?->Name ?: 'not assigned' }}
                        </small>
                    </div>
                    <div class="text-end">
                        @if($isCurrent)
                            <span class="badge bg-light text-dark">Current</span>
                        @elseif($option->IsArchive)
                            <span class="badge bg-secondary">Archive</span>
                        @else
                            <span class="badge bg-light text-dark">{{ $option->status?->Description ?: 'Active' }}</span>
                        @endif
                    </div>
                </div>
                <small class="d-block mt-2 @if($isCurrent) text-white-50 @else text-muted @endif">
                    {{ number_format($option->locations_count) }} location(s)
                    <span class="mx-1">&bull;</span>
                    {{ number_format($option->physical_files_count) }} physical file(s)
                </small>
            </button>
        @empty
            <div class="text-center text-muted py-4">
                No registry is available in your branch.
            </div>
        @endforelse
    </div>

    <p class="text-muted small mt-3 mb-0">
        Switching reloads the workspace around the registry you pick.
    </p>
</div>
