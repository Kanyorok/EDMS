@php $hasChildren = $module['children']->isNotEmpty(); @endphp
<div class="module-tree-node">
    <div class="d-flex align-items-center justify-content-between py-2 px-2 border-bottom" style="padding-left: {{ 0.5 + $depth * 1.5 }}rem !important;">
        <div class="d-flex align-items-center gap-2">
            @if($hasChildren)
                <button type="button" class="btn btn-sm btn-link p-0 module-tree-toggle" data-bs-toggle="collapse" data-bs-target="#module-children-{{ $module['id'] }}" aria-expanded="false" aria-controls="module-children-{{ $module['id'] }}">
                    <i data-feather="chevron-right"></i>
                </button>
            @else
                <span style="display:inline-block; width:1rem;"></span>
            @endif
            @if($module['icon'])
                <span class="text-muted">{!! $module['icon'] !!}</span>
            @endif
            <span class="{{ $depth === 0 ? 'fw-semibold' : '' }}">{{ $module['name'] }}</span>
        </div>
        <form method="POST" action="{{ route('settings.modules.toggle', $module['id']) }}" class="d-inline-flex align-items-center justify-content-end gap-2 mb-0">
            @csrf
            <span class="badge {{ $module['is_active'] ? 'bg-success' : 'bg-secondary' }}">
                {{ $module['is_active'] ? 'Enabled' : 'Disabled' }}
            </span>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch"
                       onchange="this.form.submit()"
                       @checked($module['is_active'])>
            </div>
        </form>
    </div>
    @if($hasChildren)
        <div class="collapse" id="module-children-{{ $module['id'] }}">
            @foreach($module['children'] as $child)
                @include('settings.modules.partials.tree-node', ['module' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
