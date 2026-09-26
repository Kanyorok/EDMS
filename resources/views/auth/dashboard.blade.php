@extends('layouts.app')

@section('title', 'Dashboard')

@section('search-form')
    <div class="form-search">
        <i class="search-icon"><svg class="pc-icon"><use xlink:href="#custom-search-normal-1"></use></svg></i>
        <input type="search" name="q" class="form-control typeahead" id="SearchInput" style="width: 50vw;"
               placeholder="Search modules and menus" data-url="{{ route('modules.search') }}">
    </div>
@endsection

@section('content')
    <style>
        .hr-dashboard-hero { background: linear-gradient(125deg, #173f2b 0%, #2f6b2b 58%, #79a83b 100%); border-radius: 22px; color: #fff; overflow: hidden; padding: 26px 28px; position: relative; box-shadow: 0 18px 42px rgba(31, 77, 44, .18); }
        .hr-dashboard-hero::after { background: rgba(255,255,255,.08); border: 34px solid rgba(255,255,255,.08); border-radius: 50%; content: ''; height: 240px; position: absolute; right: -65px; top: -105px; width: 240px; }
        .hr-dashboard-hero .eyebrow { color: rgba(255,255,255,.72); font-size: .72rem; font-weight: 700; letter-spacing: .13em; text-transform: uppercase; }
        .hr-dashboard-hero .hero-pill { align-items: center; backdrop-filter: blur(6px); background: rgba(255,255,255,.13); border: 1px solid rgba(255,255,255,.18); border-radius: 999px; display: inline-flex; font-size: .78rem; gap: 7px; padding: 8px 12px; }
        .hr-dashboard-toolbar { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; margin: 20px 0 14px; }
        .hr-widget { border: 1px solid #e3ebdf !important; border-radius: 18px; box-shadow: 0 10px 28px rgba(34, 52, 40, .07); height: 100%; overflow: hidden; }
        .hr-widget .card-header { background: transparent; border-bottom: 0; padding: 20px 22px 4px; }
        .hr-widget .card-body { padding: 16px 22px 22px; }
        .hr-widget-kicker { color: #7d8a80; font-size: .7rem; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; }
        .hr-widget-title { color: #26312a; font-size: 1.02rem; font-weight: 700; margin: 3px 0 0; }
        .hr-kpi { background: linear-gradient(145deg, #f8fbf6, #f2f7ef); border: 1px solid #e5eee1; border-radius: 15px; min-height: 112px; padding: 17px; }
        .hr-kpi-icon { align-items: center; background: #e7f3e1; border-radius: 12px; color: #2f6b2b; display: inline-flex; font-size: 1.15rem; height: 38px; justify-content: center; width: 38px; }
        .hr-kpi-value { color: #1f2a23; font-size: 1.55rem; font-weight: 750; letter-spacing: -.03em; margin-top: 14px; }
        .hr-kpi-label { color: #778179; font-size: .78rem; }
        .hr-kpi-clickable { cursor: pointer; transition: box-shadow .15s, transform .15s; }
        .hr-kpi-clickable:hover { box-shadow: 0 6px 16px rgba(47,107,43,.14); transform: translateY(-1px); }
        .hr-metric-chip { background: #f5f8f3; border: 1px solid #e7ece4; border-radius: 12px; padding: 10px 12px; }
        .hr-widget-shell { height: 100%; position: relative; }
        .hr-widget-actions { display: flex; gap: 4px; opacity: 0; position: absolute; right: 14px; top: 14px; transition: opacity .18s; z-index: 5; }
        .hr-widget-shell:hover .hr-widget-actions { opacity: 1; }
        .hr-widget-actions button { align-items: center; background: rgba(255,255,255,.94); border: 1px solid #dce6d8; border-radius: 8px; color: #647168; display: inline-flex; height: 29px; justify-content: center; width: 29px; }
        .hr-widget-actions button:hover { color: #2f6b2b; }
        .hr-empty { background: #fff; border: 1px dashed #cad8c5; border-radius: 18px; color: #718075; padding: 50px 20px; text-align: center; }
        #widget-picker { background: #fff; border: 1px solid #dfe8db; border-radius: 14px; box-shadow: 0 10px 25px rgba(36,56,42,.08); padding: 10px; }
        @media (max-width: 767.98px) { .hr-dashboard-hero { padding: 22px 20px; } .hr-widget-actions { opacity: 1; } }
    </style>

    <section class="hr-dashboard-hero">
        <div class="position-relative" style="z-index:1">
            <div class="eyebrow">{{ config('app.name') }}</div>
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mt-2">
                <div>
                    <h2 class="text-white mb-1">Business insights, at a glance</h2>
                    <p class="mb-0 text-white-50">Live operations intelligence across the organization for {{ now()->format('F Y') }}.</p>
                </div>
                <div class="hero-pill"><i class="ti ti-shield-lock"></i> Permission-aware dashboard</div>
            </div>
        </div>
    </section>

    <div class="hr-dashboard-toolbar">
        <div><div class="fw-semibold text-dark">My dashboard</div><div class="small text-muted">Only information you are authorised to view is shown.</div></div>
        <div class="d-flex gap-2">
            <button id="add-widget" class="btn btn-sm btn-primary"><i class="ti ti-layout-grid-add me-1"></i>Add widget</button>
            <button id="save-layout" class="btn btn-sm btn-outline-secondary"><i class="ti ti-device-floppy me-1"></i>Save layout</button>
        </div>
    </div>

    <div id="widget-picker" class="mb-3" style="display:none">
        <select id="available-widgets" class="form-select form-select-sm" style="max-width:390px;display:inline-block"></select>
        <button id="add-selected" class="btn btn-sm btn-success ms-1">Add to dashboard</button>
    </div>

    <div id="dashboard-grid" class="row g-3">
        @php($widgetsByKey = collect($availableWidgets ?? [])->keyBy('key'))
        @forelse(($layout ?? []) as $slot)
            @php($def = $widgetsByKey[$slot->widget_key] ?? null)
            @if($def)
                <div class="col-12 col-xl-{{ $slot->w ?? ($def->default_w ?? 6) }}" data-key="{{ $slot->widget_key }}" data-w="{{ $slot->w ?? $def->default_w }}" data-h="{{ $slot->h ?? $def->default_h }}">
                    <div class="hr-widget-shell">
                        <div class="hr-widget-actions" aria-label="Widget controls">
                            <button type="button" data-widget-up title="Move up"><i class="ti ti-arrow-up"></i></button>
                            <button type="button" data-widget-down title="Move down"><i class="ti ti-arrow-down"></i></button>
                            <button type="button" data-widget-remove title="Remove"><i class="ti ti-x"></i></button>
                        </div>
                        @include($def->view, ['stats' => ($stats ?? []), 'hrDashboard' => ($hrDashboard ?? [])])
                    </div>
                </div>
            @endif
        @empty
            <div class="col-12"><div class="hr-empty"><i class="ti ti-layout-dashboard fs-2 d-block mb-2"></i>Add a permitted widget to build your dashboard.</div></div>
        @endforelse
    </div>

    @include('components.drilldown-modal')
@endsection

@section('scripts')
    <script src="{{ asset('assets/libs/typeahead/typeahead.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/search/modules.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/apexcharts.min.js') }}"></script>
    <script>
        (function () {
            const hr = @json($hrDashboard ?? []);
            const grid = document.getElementById('dashboard-grid');
            const picker = document.getElementById('widget-picker');
            const select = document.getElementById('available-widgets');
            const colors = ['#2f6b2b', '#8fbd4d', '#d9a441', '#6c8d78', '#b7685c'];
            const baseChart = { chart: { fontFamily: 'inherit', toolbar: { show: false }, animations: { speed: 500 } }, colors, dataLabels: { enabled: false }, grid: { borderColor: '#edf1eb', strokeDashArray: 4 }, noData: { text: 'No data available' } };
            const render = (selector, options) => { const element = document.querySelector(selector); if (element && window.ApexCharts) new ApexCharts(element, { ...baseChart, ...options }).render(); };

            if (hr.workforce) {
                render('#hr-department-chart', { chart: { ...baseChart.chart, type: 'bar', height: 285, events: { dataPointSelection: (e, ctx, config) => {
                    const label = hr.workforce.departments_chart.labels[config.dataPointIndex];
                    showDrilldown('Employees — ' + label, ['Employee', 'No.'], (hr.workforce.departments_chart.employees || {})[label] || []);
                } } }, series: [{ name: 'Employees', data: hr.workforce.departments_chart.series }], plotOptions: { bar: { borderRadius: 6, horizontal: true, barHeight: '55%' } }, xaxis: { categories: hr.workforce.departments_chart.labels, labels: { style: { colors: '#778179' } } }, yaxis: { labels: { maxWidth: 145 } }, legend: { show: false } });
                render('#hr-gender-chart', { chart: { ...baseChart.chart, type: 'donut', height: 250, events: { dataPointSelection: (e, ctx, config) => {
                    const label = hr.workforce.gender.labels[config.dataPointIndex];
                    showDrilldown('Employees — ' + label, ['Employee', 'No.'], (hr.workforce.gender.employees || {})[label] || []);
                } } }, series: hr.workforce.gender.series, labels: hr.workforce.gender.labels, stroke: { width: 3, colors: ['#fff'] }, plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'People', formatter: () => hr.workforce.total } } } } }, legend: { position: 'bottom' } });
                render('#hr-headcount-chart', { chart: { ...baseChart.chart, type: 'line', height: 305 }, series: [{ name: 'Headcount', type: 'area', data: hr.workforce.trend.headcount }, { name: 'New hires', type: 'column', data: hr.workforce.trend.hires }], stroke: { curve: 'smooth', width: [3, 0] }, fill: { type: ['gradient', 'solid'], gradient: { opacityFrom: .38, opacityTo: .04 } }, plotOptions: { bar: { borderRadius: 5, columnWidth: '38%' } }, xaxis: { categories: hr.workforce.trend.labels }, yaxis: { min: 0, forceNiceScale: true }, legend: { position: 'top', horizontalAlign: 'left' } });

                // Workforce pulse KPI tiles: each clickable tile drills into the list of
                // people (or departments) behind that number.
                const pulseDrilldowns = {
                    'active-employees': ['Active employees', ['Employee', 'No.'], hr.workforce.total_employees || []],
                    'departments': ['Employees by department', ['Department', 'Headcount'], hr.workforce.departments_list || []],
                    'new-hires': ['New hires this month', ['Employee', 'No.'], hr.workforce.new_hires_list || []],
                    'exits-ytd': ['Exits year to date', ['Employee', 'No.'], (hr.workforce.mobility || {}).exits_ytd_list || []],
                    'attrition': ['Voluntary exits year to date', ['Employee', 'No.'], (hr.workforce.mobility || {}).attrition_list || []],
                };
                document.querySelectorAll('[data-drilldown]').forEach(tile => {
                    tile.addEventListener('click', () => {
                        const entry = pulseDrilldowns[tile.dataset.drilldown];
                        if (entry) showDrilldown(...entry);
                    });
                });
            }
            if (hr.attendance) render('#hr-attendance-chart', { chart: { ...baseChart.chart, type: 'radialBar', height: 260 }, series: [hr.attendance.rate], labels: ['Attendance'], plotOptions: { radialBar: { hollow: { size: '62%' }, track: { background: '#edf3e9' }, dataLabels: { value: { fontSize: '28px', fontWeight: 700, formatter: value => `${value}%` } } } } });
            if (hr.leave) render('#hr-leave-chart', { chart: { ...baseChart.chart, type: 'donut', height: 260 }, series: hr.leave.series, labels: hr.leave.labels, stroke: { width: 3, colors: ['#fff'] }, plotOptions: { pie: { donut: { size: '70%' } } }, legend: { position: 'bottom' } });
            if (hr.payroll) render('#hr-payroll-chart', { chart: { ...baseChart.chart, type: 'line', height: 325 }, series: [{ name: 'Gross pay', type: 'area', data: hr.payroll.gross }, { name: 'Net pay', type: 'area', data: hr.payroll.net }, { name: 'Allowances', type: 'column', data: hr.payroll.allowances }, { name: 'Deductions', type: 'column', data: hr.payroll.deductions }], stroke: { curve: 'smooth', width: [3, 3, 0, 0] }, fill: { type: ['gradient', 'gradient', 'solid', 'solid'], gradient: { opacityFrom: .30, opacityTo: .03 } }, plotOptions: { bar: { borderRadius: 4, columnWidth: '42%' } }, xaxis: { categories: hr.payroll.labels }, yaxis: { labels: { formatter: value => value >= 1000000 ? `${(value / 1000000).toFixed(1)}m` : value >= 1000 ? `${(value / 1000).toFixed(0)}k` : value } }, tooltip: { y: { formatter: value => `KES ${Number(value).toLocaleString()}` } }, legend: { position: 'top', horizontalAlign: 'left' } });

            async function available() { const response = await fetch("{{ route('user-dashboard.widgets') }}"); const data = await response.json(); return Object.values(data.available || {}); }
            document.getElementById('add-widget')?.addEventListener('click', async () => {
                picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
                if (picker.style.display === 'block') {
                    const current = new Set(Array.from(grid.querySelectorAll('[data-key]')).map(item => item.dataset.key));
                    select.innerHTML = '';
                    (await available()).filter(widget => !current.has(widget.key)).forEach(widget => select.add(new Option(`${widget.name} · ${widget.type || 'Widget'}`, widget.key)));
                }
            });
            document.getElementById('add-selected')?.addEventListener('click', async () => {
                const widget = (await available()).find(item => item.key === select.value);
                if (!widget) return;
                const column = document.createElement('div');
                column.className = `col-12 col-xl-${widget.default_w || 6}`;
                column.dataset.key = widget.key; column.dataset.w = widget.default_w || 6; column.dataset.h = widget.default_h || 1;
                column.innerHTML = `<div class="hr-empty">${widget.name} added. Save the layout to load its live data.</div>`;
                grid.appendChild(column); select.selectedOptions[0]?.remove();
            });
            grid?.addEventListener('click', event => {
                const widget = event.target.closest('[data-key]');
                if (!widget) return;
                if (event.target.closest('[data-widget-remove]')) widget.remove();
                if (event.target.closest('[data-widget-up]') && widget.previousElementSibling) grid.insertBefore(widget, widget.previousElementSibling);
                if (event.target.closest('[data-widget-down]') && widget.nextElementSibling) grid.insertBefore(widget.nextElementSibling, widget);
            });
            document.getElementById('save-layout')?.addEventListener('click', async () => {
                const widgets = Array.from(grid.querySelectorAll('[data-key]')).map((element, index) => ({ widget_key: element.dataset.key, x: 0, y: index, w: Number(element.dataset.w || 6), h: Number(element.dataset.h || 1), sort_order: index }));
                const response = await fetch("{{ route('user-dashboard.widgets.save') }}", { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ widgets }) });
                if (response.ok) location.reload(); else alert('The dashboard layout could not be saved.');
            });
        })();
    </script>
@endsection
