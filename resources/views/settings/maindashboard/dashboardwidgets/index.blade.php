@extends('layouts.app')

@section('title', 'Dashboard Widgets')

@section('content')
<div class="container-fluid px-4 py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h1 class="fw-bold mb-1">Dashboard Widgets View</h1>
            <p class="text-muted mb-0">
                Manage dashboard widget definitions.
            </p>
        </div>
        
        <div class="d-flex gap-2">
                <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                </a>
        </div>

        <a href="{{ route('dashboardwidgets.create') }}"
           class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>
            Create Widget
        </a>

    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card shadow-sm border-0">

        <div class="card-header bg-white">
            <h5 class="mb-0 fw-bold">
                Registered Widgets
            </h5>
        </div>

        <div class="card-body">

            @if($widgets->count())

                <div class="table-responsive">

                    <table class="table table-striped align-middle">

                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Name</th>
                                <th>Module</th>
                                <th>Type</th>
                                <th>Width</th>
                                <th>Height</th>
                                <th>Status</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                        @foreach($widgets as $widget)

                            <tr>

                                <td>{{ $widget->Key }}</td>

                                <td>{{ $widget->Name }}</td>

                                <td>{{ ucwords(strtolower($widget->Module)) }}</td>

                                <td>
                                    <span class="badge bg-info">
                                        {{ $widget->Type }}
                                    </span>
                                </td>

                                <td>{{ $widget->DefaultW }}</td>

                                <td>{{ $widget->DefaultH }}</td>

                                <td>
                                    @if($widget->IsActive)
                                        <span class="badge bg-success">
                                            Active
                                        </span>
                                    @else
                                        <span class="badge bg-danger">
                                            Inactive
                                        </span>
                                    @endif
                                </td>

                                <td>

                                    <div class="btn-group">

                                        <a href="{{ route('dashboardwidgets.edit', $widget->Id) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            Edit
                                        </a>

                                        <form method="POST"
                                              action="{{ route('dashboardwidgets.destroy', $widget->Id) }}"
                                              onsubmit="return confirm('Delete this widget?')">

                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger">
                                                Delete
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        @endforeach

                        </tbody>

                    </table>

                </div>

            @else

                <div class="text-center py-5">

                    <i class="bi bi-grid-3x3-gap fs-1 text-muted"></i>

                    <h5 class="mt-3">
                        No Widgets Found
                    </h5>

                    <p class="text-muted">
                        Create your first dashboard widget.
                    </p>

                    <a href="{{ route('dashboardwidgets.create') }}"
                       class="btn btn-primary">
                        Create Widget
                    </a>

                </div>

            @endif

        </div>

    </div>

</div>
@endsection
