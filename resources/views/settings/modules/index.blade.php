@extends('layouts.app')

@section('title', 'Disable Modules')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Disable Modules</h3>
            <p class="text-muted mb-0">Turn a module, or any of its sub-items, off to hide it from the navigation menu and block direct access to its pages for every user. Disabling a module also disables everything nested under it.</p>
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

    @if(!$licenseValid)
        <div class="alert alert-warning" role="alert">
            No valid license was found, so no modules can be managed here. Contact your vendor to install a license.
        </div>
    @elseif($modules->isEmpty())
        <div class="alert alert-warning" role="alert">
            Your current license does not include any modules that can be disabled.
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Modules</h5>
            <p class="text-muted small mb-0">Only modules included in your current license are listed here. Click the arrow next to a module to expand its sub-items.</p>
        </div>
        <div class="card-body p-0">
            @forelse($modules as $module)
                @include('settings.modules.partials.tree-node', ['module' => $module, 'depth' => 0])
            @empty
                <p class="text-center text-muted py-4 mb-0">No modules found.</p>
            @endforelse
        </div>
    </div>
</div>

<style>
    .module-tree-toggle {
        color: inherit;
        line-height: 0;
    }
    .module-tree-toggle svg {
        width: 16px;
        height: 16px;
        transition: transform 0.15s ease-in-out;
    }
    .module-tree-toggle[aria-expanded="true"] svg {
        transform: rotate(90deg);
    }
</style>
@endsection
