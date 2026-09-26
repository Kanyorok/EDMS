@extends('layouts.app')

@section('title', 'Edit Dashboard Widget')

@section('content')
<div class="container-fluid px-4 py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1">Widget Registry</h1>
            <p class="text-muted mb-0">
                Edit dashboard widget definition.
            </p>
        </div>

        <a href="{{ route('dashboardwidgets.index') }}"
           class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>
            Back
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

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
                Edit Widget Definition
            </h5>
        </div>

        <div class="card-body">

            <form method="POST"
                  action="{{ route('dashboardwidgets.update', $widget->Id) }}">

                @csrf
                @method('PUT')

                <div class="row g-3">

                    {{-- Key --}}
                    <div class="col-md-6">
                        <label class="form-label">Key</label>
                        <input type="text"
                               name="Key"
                               class="form-control"
                               value="{{ old('Key', $widget->Key) }}"
                               required>
                    </div>

                    {{-- Name --}}
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text"
                               name="Name"
                               class="form-control"
                               value="{{ old('Name', $widget->Name) }}"
                               required>
                    </div>

                    {{-- Module --}}
                    <div class="col-md-6">
                        <label class="form-label">Module</label>

                        <select name="Module"
                                id="moduleSelect"
                                class="form-select"
                                required>

                            <option value="">Select Module</option>

                            @foreach($modules as $module)
                                <option value="{{ $module->ModuleKey }}"
                                    {{ old('Module', $widget->Module) == $module->ModuleKey ? 'selected' : '' }}>
                                    {{ ucwords(strtolower($module->ModuleKey)) }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                    {{-- View --}}
                    <div class="col-md-6">
                        <label class="form-label">View</label>

                        <select name="View"
                                id="viewSelect"
                                class="form-select"
                                required>

                            <option value="">Select View</option>

                        </select>
                    </div>

                    {{-- Type --}}
                    <div class="col-md-6">

                        <label class="form-label">Type</label>

                        @php
                            $types = [
                                'KPI' => 'KPI - Key Performance Indicator',
                                'Metric' => 'Metric - Comparative Value',
                                'Chart' => 'Chart - Graphical Data',
                                'Workflow' => 'Workflow - Approval Process',
                                'Inbox' => 'Inbox - Messages/Notifications',
                                'Report' => 'Report - Tabular Data',
                            ];
                        @endphp

                        <select name="Type" class="form-select">

                            <option value="">
                                -- Select Type --
                            </option>

                            @foreach($types as $value => $label)

                                <option value="{{ $value }}"
                                    {{ old('Type', $widget->Type) == $value ? 'selected' : '' }}>

                                    {{ $label }}

                                </option>

                            @endforeach

                        </select>

                    </div>

                    {{-- Description --}}
                    <div class="col-12">
                        <label class="form-label">Description</label>

                        <textarea name="Description"
                                  rows="3"
                                  class="form-control">{{ old('Description', $widget->Description) }}</textarea>
                    </div>

                    {{-- Width --}}
                    <div class="col-md-3">
                        <label class="form-label">Default Width</label>

                        <input type="number"
                               name="DefaultW"
                               class="form-control"
                               value="{{ old('DefaultW', $widget->DefaultW) }}">
                    </div>

                    {{-- Height --}}
                    <div class="col-md-3">
                        <label class="form-label">Default Height</label>

                        <input type="number"
                               name="DefaultH"
                               class="form-control"
                               value="{{ old('DefaultH', $widget->DefaultH) }}">
                    </div>

                    {{-- Status --}}
                    <div class="col-md-3">
                        <label class="form-label">Status</label>

                        <select name="IsActive"
                                class="form-select">

                            <option value="1"
                                {{ old('IsActive', $widget->IsActive) == 1 ? 'selected' : '' }}>
                                Active
                            </option>

                            <option value="0"
                                {{ old('IsActive', $widget->IsActive) == 0 ? 'selected' : '' }}>
                                Inactive
                            </option>

                        </select>
                    </div>

                </div>

                <div class="mt-4">

                    <button type="submit"
                            class="btn btn-primary">

                        Update Widget

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<script>

const moduleViews = @json($moduleViews);

const selectedModule = "{{ old('Module', $widget->Module) }}";
const selectedView = "{{ old('View', $widget->View) }}";

function loadViews(moduleKey)
{
    const viewSelect = document.getElementById('viewSelect');

    viewSelect.innerHTML =
        '<option value="">Select View</option>';

    let views = null;

    Object.keys(moduleViews).forEach(function(key) {

        if (key.toUpperCase() === moduleKey.toUpperCase()) {
            views = moduleViews[key];
        }

    });

    if (!views) return;

    Object.entries(views).forEach(([key, label]) => {

        const option = document.createElement('option');

        option.value = key;
        option.textContent = label;

        if (key === selectedView) {
            option.selected = true;
        }

        viewSelect.appendChild(option);

    });
}

document.getElementById('moduleSelect')
    .addEventListener('change', function () {

        loadViews(this.value);

    });

// Load existing views on page load
if (selectedModule) {
    loadViews(selectedModule);
}

</script>

@endsection
