<?php

namespace App\Services\DMS;

use App\Models\DMS\DMSTags;
use App\Models\DMS\Repository;
use App\Models\DMS\DocumentVersion;
use App\Models\DMS\DocumentLegalHold;

class DocumentDashboardWidgetsService
{
    /**
     * Widget blade views.
     */
    public function getWidgetViews(): array
    {
        return [

            'dashboard.widgets.metric-card' => 'Metric Card',
            'dashboard.widgets.chart-card'  => 'Chart Widget',
            'dashboard.widgets.table-card'  => 'Table Widget',
            'dashboard.widgets.list-card'   => 'List Widget',
            'dashboard.widgets.report-card' => 'Report Widget',
            'dashboard.widgets.donut-card' => 'Donut Widget',

        ];
    }
    public function getWidgetTypes(): array
    {
        return [
            'metric' => 'metric',
            'barChart' => 'barChart',
            'pieChart' => 'pieChart',
            'lineChart' => 'lineChart',
            'combinedLineBar' => 'combinedLineBar',
            'donutChart' => 'donutChart',
        ];
    }
public function getWidgetSizeOptions(): array
{
    return [
        '1x1'  => 'Small KPI (col-md-3 col-sm-6)',
        '2x1'  => 'Medium Chart (col-md-6 col-sm-12)',
        '1x2'  => 'Small Wide Widget (col-md-4 col-sm-6)',
        '2x2'  => 'Large Chart (col-md-6 col-sm-12)',
        'full' => 'Full Width Table (col-md-12)',
    ];
}

    /**
     * Available dashboard metrics/data sources.
     */
 public function getWidgetConfigs(): array
{
    return [

        // DMS Tags
        'totalDMSTags',

        // Repositories
        'totalRepositories',

        // Document Versions
        'totalDocumentVersions',

        // Document Legal Hold
        'totalDocumentLegalHolds',
    ];
}

/**
 * Actual values displayed on dashboard.
 */
public function getWidgetValues(): array
{
    return [

        // DMS Tags
        'totalDMSTags' => DMSTags::count(),

        // Repositories
        'totalRepositories' => Repository::count(),

        // Document Versions
        'totalDocumentVersions' => DocumentVersion::count(),

        // Document Legal Hold
        'totalDocumentLegalHolds' => DocumentLegalHold::count(),
    ];
}
}

