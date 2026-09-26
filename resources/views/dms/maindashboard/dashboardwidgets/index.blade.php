@extends('layouts.app')

@section('title', $pageTitle ?? 'Document Management Dashboard')

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/gridstack@10.0.0/dist/gridstack.min.css"
          rel="stylesheet">
@endpush

@section('content')

<div class="container-fluid px-4 py-4">

    {{-- Selected Layout --}}
    <div class="row g-3 mb-4">

        <div class="col-12">

            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h5 class="fw-bold mb-1">
                                Document Management Dashboard
                            </h5>

                            <p class="text-muted mb-0">
                                {{ $hasSavedLayout ? 'Saved Layout' : 'Default Layout' }}
                            </p>
                        </div>

                        <a href="{{ route('documentdashboardwidgets.create') }}"
                           class="btn btn-primary">
                            Manage Widgets
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <div class="grid-stack">

        @forelse($widgetDefinitions as $widget)

            @php

                $config = is_string($widget->WidgetConfig)
                    ? json_decode($widget->WidgetConfig, true)
                    : (array) $widget->WidgetConfig;

                $sources = $config['data_source'] ?? [];

                if (!is_array($sources)) {
                    $sources = [$sources];
                }

                $chartData = [];

                foreach ($sources as $src) {
                    if (isset($widgetValues[$src])) {
                        $chartData[$src] = $widgetValues[$src];
                    }
                }

                $rawData = $chartData;

                $id = $widget->Id;
                $type = $widget->WidgetType;

            @endphp

            <div class="grid-stack-item"
                 gs-x="{{ $widget->x ?? 0 }}"
                 gs-y="{{ $widget->y ?? 0 }}"
                 gs-w="{{ $widget->w ?? 4 }}"
                 gs-h="{{ $widget->h ?? 2 }}"
                 data-id="{{ $widget->Id }}"
                 data-widget-key="{{ $widget->WidgetKey }}">

                <div class="grid-stack-item-content">

                    <div class="card shadow-sm border-0 h-100">

                        {{-- Header --}}
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">

                            <h6 class="mb-0 text-uppercase text-muted">
                                {{ $widget->WidgetTitle }}
                            </h6>

                            <button class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#widgetDetails{{ $id }}">
                                Details
                            </button>

                        </div>

                        {{-- Body --}}
                        <div class="card-body">

                             @switch($type)

                            {{-- ================= METRIC ================= --}}
                                @case('metric')

                                    @php
                                        // Normalize data
                                        $isArray = is_array($rawData);

                                        // Compute total if numeric dataset
                                        $total = 0;

                                        if ($isArray) {
                                            foreach ($rawData as $v) {
                                                if (is_numeric($v)) {
                                                    $total += $v;
                                                }
                                            }
                                        } elseif (is_numeric($rawData)) {
                                            $total = $rawData;
                                        }
                                    @endphp

                                    {{-- ================= TOP SUMMARY ================= --}}
                                    <div class="mb-3 p-3 bg-light rounded border">

                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted fw-bold">TOTAL</span>
                                            <span class="fw-bold text-primary fs-5">
                                                {{ number_format($total) }}
                                            </span>
                                        </div>

                                        @if($isArray && $total > 0)
                                            <div class="small text-muted">
                                                Breakdown:
                                            </div>

                                            @foreach($rawData as $label => $value)
                                                @php
                                                    $percentage = is_numeric($value) ? round(($value / $total) * 100, 2) : 0;
                                                @endphp

                                                <div class="d-flex justify-content-between small">
                                                    <span>{{ $label }}</span>
                                                    <span>
                                                        {{ $percentage }}%
                                                    </span>
                                                </div>
                                            @endforeach
                                        @endif

                                    </div>

                                    {{-- ================= ORIGINAL DISPLAY ================= --}}
                                    @if($isArray)
                                        @foreach($rawData as $label => $value)
                                            <div class="mb-2">
                                                <strong>{{ $label }}:</strong>
                                                {{ is_numeric($value) ? number_format($value) : $value }}
                                            </div>
                                        @endforeach
                                    @else
                                        <h1 class="fw-bold text-primary">
                                            {{ number_format($rawData) }}
                                        </h1>
                                    @endif

                                    {{-- ================= DETAILS (UNCHANGED) ================= --}}
                                    <div class="collapse mt-4" id="widgetDetails{{ $id }}">
                                        <table class="table table-bordered table-sm">
                                            <tr>
                                                <th>Value</th>
                                                <td>
                                                    @if($isArray)
                                                        <ul class="mb-0">
                                                            @foreach($rawData as $label => $value)
                                                                <li>
                                                                    <strong>{{ $label }}:</strong>
                                                                    {{ is_numeric($value) ? number_format($value) : $value }}
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    @else
                                                        {{ is_numeric($rawData) ? number_format($rawData) : $rawData }}
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </div>

                                @break

                            {{-- ================= BAR CHART ================= --}}
                                @case('barChart')

                                    @php
                                        $labels = array_keys($rawData ?? []);
                                        $values = array_values($rawData ?? []);
                                    @endphp

                                <div style="height:300px; width:100%; position:relative;">
                                <canvas id="chart_{{ $id }}" style="width:100%; height:100%;"></canvas>
                                    </div>

                                    <div class="collapse mt-4" id="widgetDetails{{ $id }}">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Label</th>
                                                        <th>Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($rawData ?? [] as $label => $value)
                                                        <tr>
                                                            <td>{{ $label }}</td>
                                                            <td>{{ $value }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    {{-- ONLY PUSH DATA (NO CHART CREATION HERE) --}}
                                    <script>
                                        window.__BAR_CHARTS = window.__BAR_CHARTS || [];
                                        window.__BAR_CHARTS.push({
                                            id: "chart_{{ $id }}",
                                            title: @json($widget->WidgetTitle),
                                            labels: @json($labels),
                                            values: @json($values)
                                        });
                                    </script>

                                @break

                            {{-- ================= LINE CHART ================= --}}
                            @case('lineChart')

                                <div style="height:300px; width:100%; position:relative;">
                                <canvas id="chart_{{ $id }}" style="width:100%; height:100%;"></canvas>
                                </div>

                                <div class="collapse mt-4" id="widgetDetails{{ $id }}">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Label</th>
                                                    <th>Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($rawData ?? [] as $label => $value)
                                                    <tr>
                                                        <td>{{ $label }}</td>
                                                        <td>{{ $value }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            @break

                            {{-- ================= COMBINED LINE BAR ================= --}}
                            @case('combinedLineBar')

                                <div style="height:300px; width:100%; position:relative;">
                                <canvas id="chart_{{ $id }}" style="width:100%; height:100%;"></canvas>
                                </div>

                                <div class="collapse mt-4" id="widgetDetails{{ $id }}">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Label</th>
                                                    <th>Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($rawData ?? [] as $label => $value)
                                                    <tr>
                                                        <td>{{ $label }}</td>
                                                        <td>{{ $value }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            @break

                            {{-- ================= PIE CHART ================= --}}
                            @case('pieChart')

                                @php
                                    $total = is_array($rawData) ? collect($rawData)->sum() : 0;
                                @endphp

                                <div style="height:300px; width:100%; position:relative;">
                                <canvas id="chart_{{ $id }}" style="width:100%; height:100%;"></canvas>
                                </div>

                                <div class="collapse mt-4" id="widgetDetails{{ $id }}">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Category</th>
                                                    <th>Value</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>

                                                @foreach($rawData ?? [] as $label => $value)
                                                    @php
                                                        $percentage = $total > 0
                                                            ? round(($value / $total) * 100, 2)
                                                            : 0;
                                                    @endphp
                                                    <tr>
                                                        <td>{{ $label }}</td>
                                                        <td>{{ $value }}</td>
                                                        <td>{{ $percentage }}%</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            @break

                            {{-- ================= DONUT CHART ================= --}}
                            @case('donutChart')

                                @php
                                    $total = is_array($rawData) ? collect($rawData)->sum() : 0;
                                @endphp

                                <div style="height:300px; width:100%; position:relative;">
                                <canvas id="chart_{{ $id }}" style="width:100%; height:100%;"></canvas>
                                </div>

                                <div class="collapse mt-4" id="widgetDetails{{ $id }}">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Category</th>
                                                    <th>Value</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($rawData ?? [] as $label => $value)
                                                    @php
                                                        $percentage = $total > 0
                                                            ? round(($value / $total) * 100, 2)
                                                            : 0;
                                                    @endphp
                                                    <tr>
                                                        <td>{{ $label }}</td>
                                                        <td>{{ $value }}</td>
                                                        <td>{{ $percentage }}%</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            @break

                            @default
                                <div class="alert alert-warning mb-0">
                                    Unsupported widget type: {{ $type }}
                                </div>

                        @endswitch

                        </div>

                    </div>

                </div>

            </div>

        @empty

            <div class="col-12">

                <div class="alert alert-info">

                    No Document Management dashboard widgets have been configured.

                    <a href="{{ route('documentdashboardwidgets.create') }}"
                       class="alert-link">
                        Create Widget
                    </a>

                </div>

            </div>

        @endforelse

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gridstack@10.0.0/dist/gridstack-all.js"></script>

<script>
function initializeDmsDashboard() {
    const gridElement = document.querySelector('.grid-stack');
    if (!gridElement || gridElement.dataset.gridstackInitialized === 'true') {
        return;
    }

    if (typeof window.GridStack === 'undefined') {
        console.error('GridStack failed to load.');
        return;
    }

    const grid = window.GridStack.init({
        cellHeight: 80,
        draggable: {
            handle: '.card-header'
        },
        resizable: {
            handles: 'all'
        },
        margin: 10,
        staticGrid: true
    }, gridElement);

    grid.setStatic(true);
    gridElement.dataset.gridstackInitialized = 'true';

    const widgetValues = @json($widgetValues);

    @foreach($widgetDefinitions as $widget)

        @php
            $config = is_string($widget->WidgetConfig)
                ? json_decode($widget->WidgetConfig, true)
                : (array) $widget->WidgetConfig;

            $sources = $config['data_source'] ?? [];

            if (!is_array($sources)) {
                $sources = [$sources];
            }

            $chartData = [];

            foreach ($sources as $src) {
                if (isset($widgetValues[$src])) {
                    $chartData[$src] = $widgetValues[$src];
                }
            }

            $type = $widget->WidgetType;
            $id = $widget->Id;
        @endphp

        @if(in_array($type, ['barChart','lineChart','pieChart','donutChart','combinedLineBar']))

        (function () {

            const canvas = document.getElementById('chart_{{ $id }}');
            if (!canvas || canvas.dataset.chartInitialized === 'true') return;

            if (typeof window.Chart === 'undefined') {
                console.error('Chart.js failed to load.');
                return;
            }

            const rawData = @json($chartData ?? []);

            let labels = [];
            let values = [];

            if (rawData && typeof rawData === 'object' && !Array.isArray(rawData)) {
                labels = Object.keys(rawData);
                values = Object.values(rawData).map(v => Number(v) || 0);
            }

            if (Array.isArray(rawData)) {
                labels = rawData.map((_, i) => `Item ${i + 1}`);
                values = rawData.map(v => Number(v) || 0);
            }

            if (!labels.length || !values.length) {
                console.warn("Empty chart:", {{ $id }});
                return;
            }

            if ("{{ $type }}" === "combinedLineBar") {

                new window.Chart(canvas, {
                    data: {
                        labels,
                        datasets: [
                            {
                                type: 'bar',
                                label: @json($widget->WidgetTitle),
                                data: values,
                                backgroundColor: '#0d6efd'
                            },
                            {
                                type: 'line',
                                label: @json($widget->WidgetTitle . ' Trend'),
                                data: values,
                                borderColor: '#dc3545',
                                fill: false
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });

                canvas.dataset.chartInitialized = 'true';
                return;
            }

            let chartType = {
                donutChart: "doughnut",
                pieChart: "pie",
                barChart: "bar",
                lineChart: "line"
            }["{{ $type }}"] || "bar";

            new window.Chart(canvas, {
                type: chartType,
                data: {
                    labels,
                    datasets: [{
                        label: @json($widget->WidgetTitle),
                        data: values,
                        backgroundColor: ['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#20c997'],
                        borderWidth: chartType === 'bar' ? 1 : 0,
                        fill: chartType === 'line' ? false : true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: chartType === "doughnut" ? "60%" : 0
                }
            });
            canvas.dataset.chartInitialized = 'true';

        })();

        @endif

    @endforeach

}

// On a full request this script can be parsed before DOMContentLoaded. During
// sidebar navigation it is injected after that event, so initialize at once.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDmsDashboard, { once: true });
} else {
    initializeDmsDashboard();
}
</script>

@endsection
