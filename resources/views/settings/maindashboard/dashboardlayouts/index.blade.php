@extends('layouts.app')

@section('title','Dashboard')

@section('content')

<div class="container-fluid px-4 py-4">

    {{-- ================= HEADER ================= --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h4 class="fw-bold mb-0">
                Dashboard
            </h4>

            <small class="text-muted">
                {{ $layout ? 'Saved Layout' : 'Default Layout' }}
            </small>
        </div>

        <div class="d-flex gap-2">
            <button id="editLayout"
                    class="btn btn-warning btn-sm">
                Edit Layout
            </button>

            <button id="saveLayout"
                    class="btn btn-success btn-sm d-none">
                Save Layout
            </button>

        </div>
        <div class="d-flex gap-2">
                <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                </a>
        </div>
    </div>


    {{-- ================= WIDGET PICKER ================= --}}
    <div id="widget-picker"
         class="card shadow-sm mb-3 d-none">

        <div class="card-header">
            Available Widgets
        </div>

        <div class="card-body">

            <div id="available-widgets"
                 style="max-height:300px;
                        overflow:auto;">
            </div>

            <button id="add-selected"
                    class="btn btn-success btn-sm mt-3">
                Add Selected Widget
            </button>

        </div>

    </div>


    {{-- ================= GRID CONTAINER ================= --}}
    <link href="https://cdn.jsdelivr.net/npm/gridstack@10.0.0/dist/gridstack.min.css"
          rel="stylesheet"/>

    <div class="grid-stack">

        @php
            $widgetsByKey =
                collect($availableWidgets ?? [])
                ->keyBy('key');
        @endphp

        @forelse($layout ?? [] as $slot)

            @php
                $def =
                    $widgetsByKey[$slot->widget_key]
                    ?? null;
            @endphp

            <div class="grid-stack-item"
                 gs-x="{{ $slot->x ?? 0 }}"
                 gs-y="{{ $slot->y ?? 0 }}"
                 gs-w="{{ $slot->w ?? 4 }}"
                 gs-h="{{ $slot->h ?? 2 }}"
                 data-key="{{ $slot->widget_key }}">

                <div class="grid-stack-item-content">

                    {{-- ================= CARD ================= --}}
                    <div class="card shadow-sm border-0 h-100">

                        {{-- CARD HEADER --}}
                        <div class="card-header
                                    d-flex
                                    justify-content-between
                                    align-items-center">

                            <strong>
                                {{ $def->name ?? $slot->widget_key }}
                            </strong>

                            <div class="d-flex gap-2">

                                <span class="badge bg-secondary">
                                    {{ strtoupper($def->type ?? 'Widget') }}
                                </span>

                                <form action="{{ route('dashboardwidgets.cancel',$slot->id) }}"
                                      method="POST"
                                      onsubmit="return confirm('Remove widget?')">

                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                            class="btn btn-sm btn-outline-danger">
                                        ✕
                                    </button>

                                </form>

                            </div>

                        </div>


                        {{-- CARD BODY --}}
                        <div class="card-body">
                           
                            @if($def &&
                                !empty($def->view) &&
                                view()->exists($def->view))

                                @include($def->view,[
                                    'widget'=>$def,
                                    'stats'=>$stats ?? []
                                ])

                            @elseif($def)

                                @switch(strtoupper($def->type))

                            @case('KPI')

                                @php

                                    // Get data from stats
                                    $kpiData = $stats[$def->view]
                                        ?? $stats[$def->key]
                                        ?? [];

                                    // Convert single values into array format
                                    if (!is_array($kpiData)) {
                                        $kpiData = [
                                            $def->name ?? 'Value' => $kpiData
                                        ];
                                    }

                                    $total = array_sum($kpiData);

                                @endphp

                                <div class="mb-2">

                                    <strong>{{ $def->name ?? $def->key }}</strong>

                                    <hr>

                                    @if(!empty($kpiData))

                                        @foreach($kpiData as $label => $count)

                                            <div class="d-flex justify-content-between border-bottom py-1">
                                                <span>
                                                    {{ ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $label)) }}
                                                </span>

                                                <strong>{{ $count }}</strong>
                                            </div>

                                        @endforeach

                                        <div class="mt-2 text-end">
                                            <strong>Total: {{ $total }}</strong>
                                        </div>

                                    @else

                                        <div class="text-center text-muted">
                                            No KPI data available
                                        </div>

                                    @endif

                                </div>

                            @break

                            @case('METRIC')

                                    @php

                                        $metricData = $stats[$def->view] ?? [];

                                        $metricData = is_array($metricData)
                                            ? $metricData
                                            : [];

                                        $total = array_sum($metricData) ?: 1;

                                    @endphp

                                    <div class="mb-3">

                                        <strong>{{ $def->name }}</strong>

                                        <small class="text-muted d-block">
                                            Source: {{ $def->view }}
                                        </small>

                                        <hr>

                                        @if(count($metricData))

                                            @foreach($metricData as $label => $count)

                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span>{{ $label }}</span>
                                                    <strong>{{ $count }}</strong>
                                                </div>

                                            @endforeach

                                        @else

                                            <div class="alert alert-warning mb-0">
                                                No data found for source:
                                                <strong>{{ $def->view }}</strong>
                                            </div>

                                        @endif

                                    </div>

                                    @if(count($metricData))

                                        <div class="table-responsive">

                                            <table class="table table-sm table-bordered">

                                                <thead>
                                                    <tr>
                                                        <th>Category</th>
                                                        <th>Value</th>
                                                        <th>Percentage</th>
                                                    </tr>
                                                </thead>

                                                <tbody>

                                                    @foreach($metricData as $label => $count)

                                                        @php
                                                            $percentage = ($count / $total) * 100;
                                                        @endphp

                                                        <tr>
                                                            <td>{{ $label }}</td>
                                                            <td>{{ $count }}</td>
                                                            <td>{{ number_format($percentage, 1) }}%</td>
                                                        </tr>

                                                    @endforeach

                                                </tbody>

                                                <tfoot>
                                                    <tr>
                                                        <th>Total</th>
                                                        <th>{{ $total }}</th>
                                                        <th>100%</th>
                                                    </tr>
                                                </tfoot>

                                            </table>

                                        </div>

                                    @endif

                                @break

                                   @case('CHART')

                                @php

                                    // Get chart data directly from stats
                                    $chartData = $stats[$def->view] ?? [];

                                    // Ensure array
                                    $chartData = is_array($chartData)
                                        ? $chartData
                                        : [];

                                    // Build chart labels and values
                                    $labels = array_keys($chartData);
                                    $values = array_values($chartData);

                                    // Unique chart id
                                    $chartId = 'chart_' . md5($def->key);

                                @endphp

                                <div class="mb-3">

                                    <strong>{{ $def->name }}</strong>

                                    <small class="text-muted d-block">
                                        Source: {{ $def->view }}
                                    </small>

                                    <hr>

                                    @if(count($chartData))

                                        {{-- Data Preview --}}
                                        @foreach($chartData as $label => $value)

                                            <div class="d-flex justify-content-between border-bottom py-1">
                                                <span>{{ $label }}</span>
                                                <strong>{{ $value }}</strong>
                                            </div>

                                        @endforeach

                                        {{-- Donut Chart --}}
                                        <div style="height:350px;" class="mt-3">
                                            <canvas id="{{ $chartId }}"></canvas>
                                        </div>

                                                @once
                                                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

                                                <script>
                                                    window.dashboardCharts = window.dashboardCharts || [];
                                                </script>
                                                @endonce

                                                <script>
                                                window.dashboardCharts.push({
                                                    id: "{{ $chartId }}",
                                                    type: "doughnut",
                                                    labels: @json($labels),
                                                    values: @json($values),
                                                    title: @json($def->name)
                                                });
                                                </script>

                                    @else

                                        <div class="alert alert-warning mb-0">
                                            No chart data found for:
                                            <strong>{{ $def->view }}</strong>
                                        </div>

                                    @endif

                                </div>

                            @break

                                     @case('INBOX')
                                    <h4 class="text-center">{{ $value }}</h4>
                                @break

                                    @case('WORKFLOW')
                                    @foreach(($stats['pending_approvals'] ?? []) as $item)
                                        <a href="{{ $item['url'] }}" class="d-flex justify-content-between align-items-center gap-2 py-2 border-bottom text-decoration-none">
                                            <span>
                                                <strong class="d-block">{{ $item['title'] }} #{{ $item['reference'] }}</strong>
                                                <small class="text-muted">{{ $item['stage'] }}</small>
                                            </span>
                                            <span class="badge {{ ($item['type'] ?? null) === 'contract' ? 'bg-primary' : 'bg-warning text-dark' }}">Review</span>
                                        </a>
                                    @endforeach
                                    @break

                                    @default
                                        <div class="text-muted">
                                            Unsupported Widget
                                        </div>
                                @endswitch

                            @else

                                <div class="alert alert-danger">
                                    Widget not found.
                                </div>

                            @endif

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

                    <div class="alert alert-info mb-0">
                        No widgets found.
                    </div>

                </div>

            </div>

        @endforelse

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gridstack@10.0.0/dist/gridstack-all.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {

    if (!window.dashboardCharts) return;

    window.dashboardCharts.forEach(chart => {

        const canvas = document.getElementById(chart.id);

        if (!canvas) return;

        new Chart(canvas, {
            type: chart.type,
            data: {
                labels: chart.labels,
                datasets: [{
                    label: chart.title,
                    data: chart.values,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

    });

});
</script>
<script>

document.addEventListener('DOMContentLoaded', function () {

    const grid = GridStack.init({
        cellHeight: 90,
        margin: 10,
        draggable: {
            handle: '.card-header'
        },
        resizable: {
            handles: 'all'
        }
    });

    grid.setStatic(true);

    let editMode = false;

    /*
    |--------------------------------------------------------------------------
    | EDIT MODE
    |--------------------------------------------------------------------------
    */
    document.getElementById('editLayout')
        ?.addEventListener('click', function () {

            editMode = true;

            grid.setStatic(false);
            grid.enableMove(true);
            grid.enableResize(true);

            document.getElementById('saveLayout')
                .classList.remove('d-none');
        });

    /*
    |--------------------------------------------------------------------------
    | SAVE LAYOUT
    |--------------------------------------------------------------------------
    */
    document.getElementById('saveLayout')
    ?.addEventListener('click', async () => {

        const widgets = [...document.querySelectorAll('.grid-stack-item')]
            .sort((a, b) => {
                const na = a.gridstackNode;
                const nb = b.gridstackNode;

                // Sort by row first, then column
                return na.y - nb.y || na.x - nb.x;
            })
            .map((el, index) => {
                const node = el.gridstackNode;

                return {
                    widget_key: el.dataset.key,
                    x: node.x,
                    y: node.y,
                    w: node.w,
                    h: node.h,
                    sort_order: index
                };
            });

        console.table(widgets);
        document.querySelectorAll('.grid-stack-item').forEach(el => {
       console.log(
        el.dataset.key,
        el.gridstackNode?.x,
        el.gridstackNode?.y,
        el.style.transform
    );
    });

        const res = await fetch(
            "{{ route('user-dashboard.widgets.save') }}",
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ widgets })
            }
        );

        if (!res.ok) {
            return alert('Failed to save layout');
        }

        grid.setStatic(true);
        grid.enableMove(false);
        grid.enableResize(false);

        document.getElementById('saveLayout')
            ?.classList.add('d-none');

        alert('Layout saved successfully');
    });

});

</script>
@endsection
