<?php

namespace App\Http\Controllers\DMS\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Enums\Core\PermissionEnum;
use App\Models\DMS\DocumentDashboardLayouts;
use App\Models\DMS\DocumentDashboardWidgets;
use App\Services\DMS\DocumentDashboardLayoutsService;
use App\Services\DMS\DocumentDashboardWidgetsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentDashboardLayoutsController extends Controller
{
    //

     public function create(Request $request)
    {
      $this->authorize(PermissionEnum::DMSLayoutsCreate, DocumentDashboardLayouts::class);

        $layoutService = new DocumentDashboardLayoutsService();
        $options = $layoutService->getWidgetDisplayOptions();
        $widgetDefinitions = DocumentDashboardWidgets::all();
        $layoutService = new DocumentDashboardLayoutsService();
        $service = new DocumentDashboardWidgetsService();
        $dashboardLayout = DocumentDashboardLayouts::orderBy('SortOrder')->get();
        $hasSavedLayout = $dashboardLayout->isNotEmpty();
        if ($hasSavedLayout) {

            $savedLayouts = $dashboardLayout->keyBy('WidgetKey');

            foreach ($widgetDefinitions as $widget) {

                if ($savedLayouts->has($widget->WidgetKey)) {

                    $saved = $savedLayouts[$widget->WidgetKey];

                    $widget->x = $saved->x;
                    $widget->y = $saved->y;
                    $widget->w = $saved->w;
                    $widget->h = $saved->h;
                }
            }
        }

       $widgetConfigs = array_keys($service->getWidgetValues());

        $layoutKey = $request->query(
        'layout',
        session('customer_engagement', 'lead_overview')
    );

    $selectedLayout = $layoutService->getLayout($layoutKey);
    $selectedLayout['cards'] = $layoutService->getLayoutCards($layoutKey);

        return view('dms.maindashboard.dashboardlayouts.create', [
            'widgetDefinitions'=> $widgetDefinitions,
            'widgetOptions' => $options,
            'widgetValues' => $service->getWidgetValues(),
            'widgetConfigs' => $widgetConfigs,
            'hasSavedLayout' =>$hasSavedLayout,
        ]);
    }



    public function saveLayout(Request $request)
  {
  $this->authorize(PermissionEnum::DMSLayoutsCreate, DocumentDashboardLayouts::class);
    $widgets = $request->input('layout'); // ✅ ONLY THIS

    if (!is_array($widgets)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid layout format'
        ], 422);
    }

    DB::beginTransaction();

    try {

        foreach ($widgets as $widget) {

            if (empty($widget['WidgetKey'])) {
                continue; // prevent SQL failure
            }

            DocumentDashboardLayouts::updateOrCreate(
                [
                    'WidgetKey' => $widget['WidgetKey'],
                ],
                [
                    'x' => $widget['x'] ?? 0,
                    'y' => $widget['y'] ?? 0,
                    'w' => $widget['w'] ?? 4,
                    'h' => $widget['h'] ?? 2,
                    'SortOrder' => $widget['SortOrder'] ?? 0,

                    'Config' => [
                        'x' => $widget['x'] ?? 0,
                        'y' => $widget['y'] ?? 0,
                        'w' => $widget['w'] ?? 4,
                        'h' => $widget['h'] ?? 2,
                    ],

                    'CreatedBy' => Auth::id(),
                    'ModifiedBy' => Auth::id(),
                ]
            );
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Layout saved  successfully'
        ]);

    } catch (\Exception $e) {

        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

}

