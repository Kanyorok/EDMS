@extends('layouts.app')

@section('title', $pageTitle ?? 'Document Management Dashboard')

@section('content')

<div class="container-fluid px-4 py-4">

    {{-- ================= HEADER ================= --}}
    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="fw-bold mb-0">
                Document Management Dashboard
            </h4>

            <small class="text-muted">
                {{ $hasSavedLayout ? 'Saved Layout' : 'Default Layout' }}
            </small>
        </div>

        <div class="d-flex gap-2">

            <button id="editLayout"
                    class="btn btn-primary btn-sm">
                Edit Layout
            </button>

            <button id="saveLayout"
                    class="btn btn-success btn-sm d-none">
                Save Layout
            </button>

        </div>

        <div class="d-flex gap-2">

            <a href="{{ route('documentdashboardwidgets.index') }}"
               class="btn btn-outline-secondary">

                <i class="bi bi-arrow-left me-1"></i>
                Back to Dashboard

            </a>

        </div>

    </div>

    {{-- ================= GRIDSTACK CSS ================= --}}
    <link href="https://cdn.jsdelivr.net/npm/gridstack@10.0.0/dist/gridstack.min.css"
          rel="stylesheet"/>

    {{-- ================= GRID ================= --}}
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

                $type = $widget->WidgetType;
                $id   = $widget->Id;

                $rawData = [];

                foreach ($sources as $src) {
                    if (isset($widgetValues[$src])) {
                        $rawData[$src] = $widgetValues[$src];
                    }
                }
            @endphp

            <div class="grid-stack-item"
                 gs-x="{{ $widget->x ?? 0 }}"
                 gs-y="{{ $widget->y ?? 0 }}"
                 gs-w="{{ $widget->w ?? 4 }}"
                 gs-h="{{ $widget->h ?? 2 }}"
                 data-widget-key="{{ $widget->WidgetKey }}"
                 data-id="{{ $id }}">

                <div class="grid-stack-item-content">

                    <div class="card shadow-sm border-0 h-100">

                        {{-- ================= CARD HEADER ================= --}}
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

                        {{-- ================= CARD BODY ================= --}}
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

                        {{-- ================= DETAILS ================= --}}
                        <div class="collapse p-3"
                             id="widgetDetails{{ $id }}">

                            <pre class="small bg-light p-2 border rounded">{{ json_encode($rawData, JSON_PRETTY_PRINT) }}</pre>

                        </div>

                    </div>

                </div>

            </div>

        @empty

            <div class="grid-stack-item"
                 gs-x="0"
                 gs-y="0"
                 gs-w="12"
                 gs-h="2">

                <div class="grid-stack-item-content">

                    <div class="alert alert-info">
                        No Document Management widgets found for this layout.
                    </div>

                </div>

            </div>

        @endforelse

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/gridstack@10.0.0/dist/gridstack-all.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const grid = GridStack.init({
        cellHeight: 80,
        draggable: {
            handle: '.card-header'
        },
        resizable: {
            handles: 'all'
        },
        margin: 10
    });

    grid.setStatic(true);

    document.getElementById('editLayout')
        ?.addEventListener('click', function () {

            grid.setStatic(false);
            grid.enableMove(true);
            grid.enableResize(true);

            document.getElementById('saveLayout')
                .classList.remove('d-none');

        });

    document.getElementById('saveLayout')
        ?.addEventListener('click', function () {

            const payload = [];

            document.querySelectorAll('.grid-stack-item')
                .forEach((el, index) => {

                    const node = el.gridstackNode;

                    payload.push({
                        WidgetKey: el.dataset.widgetKey,
                        x: node.x,
                        y: node.y,
                        w: node.w,
                        h: node.h,
                        SortOrder: index,
                        Config: {
                            x: node.x,
                            y: node.y,
                            w: node.w,
                            h: node.h
                        }
                    });

                });

            fetch("{{ route('documentdashboardlayouts.saveLayout') }}", {
                method: "PUT",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                },
                body: JSON.stringify({
                    layout: payload
                })
            })
            .then(res => res.json())
            .then(response => {

                grid.setStatic(true);
                grid.enableMove(false);
                grid.enableResize(false);

                document.getElementById('saveLayout')
                    .classList.add('d-none');

                alert('Document Management layout saved successfully.');

            })
            .catch(error => {
                console.error(error);
                alert('Failed to save Document Management layout.');
            });

        });

});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {

    console.log("Dashboard JS loaded");

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
            if (!canvas) return;

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

                new Chart(canvas, {
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

                return;
            }

            let chartType = {
                donutChart: "doughnut",
                pieChart: "pie",
                barChart: "bar",
                lineChart: "line"
            }["{{ $type }}"] || "bar";

            new Chart(canvas, {
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

        })();

        @endif

    @endforeach

});
</script>

@endsection

