@extends('layouts.app')

@section('title', 'Edit Document Dashboard Widget')

@section('content')

<div class="container-fluid px-4 py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h1 class="fw-bold mb-1">
                Edit Document Dashboard Widget
            </h1>

            <p class="text-muted mb-0">
                Modify the selected Document Management dashboard widget configuration.
            </p>
        </div>

        <a href="{{ route('documentdashboardwidgets.index') }}"
           class="btn btn-outline-secondary">
            Back
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

    @php
        $config = is_string($widget->WidgetConfig)
            ? json_decode($widget->WidgetConfig, true)
            : (array) $widget->WidgetConfig;

        $sources = $config['data_source'] ?? [];

        if (!is_array($sources)) {
            $sources = [$sources];
        }

        if (empty($sources)) {
            $sources = [''];
        }
    @endphp

    <div class="card shadow-sm border-0">

        <div class="card-header bg-white">
            <h5 class="mb-0 fw-bold">
                Widget Details
            </h5>
        </div>

        <div class="card-body">

            <form method="POST"
                  action="{{ route('documentdashboardwidgets.update', $widget->Id) }}">

                @csrf
                @method('PUT')

                <div class="row g-3">

                    {{-- Widget Key --}}
                    <div class="col-md-6">

                        <label class="form-label">
                            Widget Key
                        </label>

                        <input type="text"
                               name="WidgetKey"
                               class="form-control"
                               value="{{ old('WidgetKey', $widget->WidgetKey) }}"
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
                               value="{{ old('WidgetTitle', $widget->WidgetTitle) }}"
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

                                <option value="{{ $type }}"
                                    @selected(old('WidgetType', $widget->WidgetType) == $type)>
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

                                <option value="{{ $key }}"
                                    @selected(old('WidgetSize', $widget->WidgetSize) == $key)>
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

                                <option value="{{ $view }}"
                                    @selected(old('WidgetView', $widget->WidgetView) == $view)>
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

                            @foreach($sources as $source)

                                <div class="input-group mb-2">

                                    <select name="WidgetConfig[data_source][]"
                                            class="form-select"
                                            required>

                                        <option value="">
                                            Select Source
                                        </option>

                                        @foreach($widgetConfigs as $config)

                                            <option value="{{ $config }}"
                                                @selected($source == $config)>
                                                {{ $config }}
                                            </option>

                                        @endforeach

                                    </select>

                                    <button type="button"
                                            class="btn btn-outline-danger remove-source">
                                        Remove
                                    </button>

                                </div>

                            @endforeach

                        </div>

                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                id="add-source">
                            Add Data Source
                        </button>

                    </div>

                </div>

                <div class="mt-4 d-flex gap-2">

                    <button type="submit"
                            class="btn btn-primary">
                        Update Widget
                    </button>

                    <a href="{{ route('documentdashboardwidgets.create') }}"
                       class="btn btn-outline-secondary">
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection

@push('scripts')
<script>

document.getElementById('add-source')
?.addEventListener('click', function () {

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

        const rows = document.querySelectorAll(
            '#data-source-wrapper .input-group'
        );

        if (rows.length > 1) {
            e.target.parentElement.remove();
        }
    }

});

</script>
@endpush