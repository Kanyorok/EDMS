@extends('layouts.app')

@section('title', 'Create Document Dashboard Widget')

@section('content')

<div class="container-fluid px-4 py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1">
                Create Document Dashboard Widget
            </h1>

            <p class="text-muted mb-0">
                Register a new widget for use on the Document Management Dashboard.
            </p>
        </div>

        <a href="{{ route('documentdashboardwidgets.index') }}"
           class="btn btn-outline-secondary">
            Dashboard
        </a>
    </div>

    {{-- Success Message --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm border-0">

        <div class="card-header bg-white">
            <h5 class="mb-0 fw-bold">
                Widget Details
            </h5>
        </div>

        <div class="card-body">

            <form method="POST"
                  action="{{ route('documentdashboardwidgets.store') }}">

                @csrf

                <div class="row g-3">

                    {{-- Widget Key --}}
                    <div class="col-md-6">
                        <label class="form-label">
                            Widget Key
                        </label>

                        <input type="text"
                               name="WidgetKey"
                               class="form-control"
                               value="{{ old('WidgetKey') }}"
                               placeholder="totalRepositories"
                               required>
                    </div>

                    {{-- Widget Title --}}
                    <div class="col-md-6">
                        <label class="form-label">
                            Widget Title
                        </label>

                        <input type="text"
                               name="WidgetTitle"
                               class="form-control"
                               value="{{ old('WidgetTitle') }}"
                               placeholder="Total Repositories"
                               required>
                    </div>

                    {{-- Widget Type --}}
                    <div class="col-md-4">
                        <label class="form-label">
                            Widget Type
                        </label>

                        <select name="WidgetType"
                                class="form-select"
                                required>

                            <option value="">
                                Select Type
                            </option>

                            @foreach($widgetTypes as $type)
                                <option value="{{ $type }}">
                                    {{ ucfirst($type) }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                    {{-- Widget Size --}}
                    <div class="col-md-4">
                        <label class="form-label">
                            Widget Size
                        </label>

                        <select name="WidgetSize"
                                class="form-select"
                                required>

                            <option value="">
                                Select Size
                            </option>

                            @foreach($widgetSizeOptions as $key => $label)
                                <option value="{{ $key }}">
                                    {{ $label }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                    {{-- Widget View --}}
                    <div class="col-md-4">
                        <label class="form-label">
                            Widget View
                        </label>

                        <select name="WidgetView"
                                class="form-select">

                            <option value="">
                                Select View
                            </option>

                            @foreach($widgetViews as $view)
                                <option value="{{ $view }}">
                                    {{ ucfirst($view) }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                    {{-- Data Sources --}}
                    <div class="col-md-12">

                        <label class="form-label">
                            Widget Data Sources
                        </label>

                        <div id="data-source-wrapper">

                            <div class="input-group mb-2">

                                <select name="WidgetConfig[data_source][]"
                                        class="form-select"
                                        required>

                                    <option value="">
                                        Select Source
                                    </option>

                                    @foreach($widgetConfigs as $config)
                                        <option value="{{ $config }}">
                                            {{ $config }}
                                        </option>
                                    @endforeach

                                </select>

                                <button type="button"
                                        class="btn btn-outline-danger remove-source">
                                    Remove
                                </button>

                            </div>

                        </div>

                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                id="add-source">
                            Add Data Source
                        </button>

                    </div>

                </div>

                <div class="mt-4">
                    <button type="submit"
                            class="btn btn-primary">
                        Create Widget
                    </button>
                </div>

            </form>

        </div>

    </div>

    {{-- Existing Widgets --}}
    <div class="card shadow-sm border-0 mt-4">

        <div class="card-header bg-white">
            <h5 class="mb-0 fw-bold">
                Registered Document Dashboard Widgets
            </h5>
        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover table-bordered align-middle mb-0">

                    <thead class="table-light">

                        <tr>
                            <th>#</th>
                            <th>Widget Key</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>View</th>
                            <th>Data Sources</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>

                    </thead>

                    <tbody>

                        @forelse($widgetDefinitions as $index => $widget)

                            @php
                                $config = is_string($widget->WidgetConfig)
                                    ? json_decode($widget->WidgetConfig, true)
                                    : (array) $widget->WidgetConfig;

                                $sources = $config['data_source'] ?? [];
                            @endphp

                            <tr>

                                <td>{{ $index + 1 }}</td>

                                <td>{{ $widget->WidgetKey }}</td>

                                <td>{{ $widget->WidgetTitle }}</td>

                                <td>
                                    <span class="badge bg-primary">
                                        {{ $widget->WidgetType }}
                                    </span>
                                </td>

                                <td>{{ $widget->WidgetSize }}</td>

                                <td>{{ $widget->WidgetView }}</td>

                                <td>

                                    @if(count($sources))

                                        @foreach($sources as $source)
                                            <span class="badge bg-secondary me-1 mb-1">
                                                {{ $source }}
                                            </span>
                                        @endforeach

                                    @else

                                        <span class="text-muted">
                                            None
                                        </span>

                                    @endif

                                </td>

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

                                        <a href="{{ route('documentdashboardwidgets.edit', $widget->Id) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            Edit
                                        </a>

                                        <form method="POST"
                                              action="{{ route('documentdashboardwidgets.delete', $widget->Id) }}"
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

                        @empty

                            <tr>

                                <td colspan="9"
                                    class="text-center text-muted py-4">
                                    No document dashboard widgets have been created.
                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

@endsection

@push('scripts')
<script>

document.getElementById('add-source')
.addEventListener('click', function () {

    let wrapper = document.getElementById('data-source-wrapper');

    let field = document.createElement('div');

    field.classList.add('input-group', 'mb-2');

    field.innerHTML = `
        <select name="WidgetConfig[data_source][]"
                class="form-select"
                required>

            <option value="">
                Select Source
            </option>

            @foreach($widgetConfigs as $config)
                <option value="{{ $config }}">
                    {{ $config }}
                </option>
            @endforeach

        </select>

        <button type="button"
                class="btn btn-outline-danger remove-source">
            Remove
        </button>
    `;

    wrapper.appendChild(field);
});

document.addEventListener('click', function (e) {

    if (e.target.classList.contains('remove-source')) {
        e.target.parentElement.remove();
    }

});

</script>
@endpush