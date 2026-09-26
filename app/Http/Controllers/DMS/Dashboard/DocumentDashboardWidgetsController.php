<?php

namespace App\Http\Controllers\DMS\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Enums\Core\PermissionEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\DMS\DocumentDashboardLayouts;
use App\Models\DMS\DocumentDashboardWidgets;
use App\Services\DMS\DocumentDashboardLayoutsService;
use App\Services\DMS\DocumentDashboardWidgetsService;


class DocumentDashboardWidgetsController extends Controller
{
    //
public function create(){
$this->authorize(PermissionEnum::DMSWidgetsCreate, DocumentDashboardWidgets::class);  
      $services = new DocumentDashboardWidgetsService();
      $widgetDefinitions = DocumentDashboardWidgets::all();
        return view('dms.maindashboard.dashboardwidgets.create', [
           'widgetSizeOptions' => $services->getWidgetSizeOptions(),
            'widgetTypes' => $services->getWidgetTypes(),
            'widgetViews'   => $services->getWidgetViews(),
            'widgetConfigs' => $services->getWidgetConfigs(),
            'widgetDefinitions' => $widgetDefinitions,
        ]);
    }

public function index(Request $request){
$this->authorize(PermissionEnum::DMSWidgetsView, DocumentDashboardWidgets::class);
    $service = new DocumentDashboardWidgetsService();
    $layoutService = new DocumentDashboardLayoutsService();
    $widgetConfigs = array_keys($service->getWidgetValues());
    $widgetDefinitions = DocumentDashboardWidgets::all();
    $availableLayouts = $layoutService->availableLayouts();

    $layoutKey = $request->query(
        'layout',
        session('customer_engagement', 'lead_overview')

     );   
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


    $selectedLayout = $layoutService->getLayout($layoutKey);
    $selectedLayout['cards'] = $layoutService->getLayoutCards($layoutKey);

    $chartType = session('dms_dashboard_chart_type', 'none');
        return view('dms.maindashboard.dashboardwidgets.index', [
       'widgetValues' => $service->getWidgetValues(),
               'mode' => 'dashboard',
        'pageTitle' => 'Property Dashboard',

        'availableLayouts' => $availableLayouts,
        'selectedLayoutKey' => $layoutKey,
        'selectedLayout' => $selectedLayout,
        'hasSavedLayout' => $hasSavedLayout,
        'chartType' => $chartType,

        'widgetDefinitions' => $widgetDefinitions,

        'widgetConfigs' => $widgetConfigs,

        'widgetDisplayOptions' => $layoutService->getWidgetDisplayOptions(),
        ]);
    }


        public function store(Request $request)
    {
    $this->authorize(PermissionEnum::DMSWidgetsUpdate, DocumentDashboardWidgets::class);
        $validated = $request->validate([
            'WidgetTitle' => 'required|string|max:255',
            'WidgetKey' => 'nullable|string|max:100',
            'WidgetType' => 'required|string|max:100',
            'WidgetSize' => 'required|string|max:50',
            'WidgetView' => 'nullable|string|max:255',

            'WidgetConfig.data_source' => 'required|array|min:1',
            'WidgetConfig.data_source.*' => 'required|string',

            'cards' => 'nullable|array',
            'cards.*.title' => 'required_with:cards|string|max:255',
            'cards.*.dataKey' => 'required_with:cards|string|max:255',
            'cards.*.meta' => 'nullable|string|max:255',
        ]);

        $widgetKey = $validated['WidgetKey']
            ? Str::slug($validated['WidgetKey'])
            : Str::slug($validated['WidgetTitle']);

        $exists = DocumentDashboardWidgets::where(
            'WidgetKey',
            $widgetKey
        )->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors([
                    'WidgetKey' => 'A widget with this key already exists.'
                ]);
        }

        DocumentDashboardWidgets::create([
            'WidgetTitle' => $validated['WidgetTitle'],
            'WidgetKey' => $widgetKey,
            'WidgetType' => $validated['WidgetType'],
            'WidgetSize' => $validated['WidgetSize'],
            'WidgetView' => $validated['WidgetView'] ?? null,

            'WidgetConfig' => [
                'data_source' => $validated['WidgetConfig']['data_source'],
            ],

            'CreatedBy' => Auth::id(),
            'ModifiedBy' => Auth::id(),
        ]);

        return redirect()
            ->route('documentdashboardwidgets.create')
            ->with(
                'success',
                'Dashboard widget created successfully.'
            );
    }

  public function edit($Id){
    $this->authorize(PermissionEnum::DMSWidgetsUpdate, DocumentDashboardWidgets::class);  
      $services = new DocumentDashboardWidgetsService();
      $widgetDefinitions = DocumentDashboardWidgets::all();
     $widget = DocumentDashboardWidgets::findOrFail($Id);
        return view('dms.maindashboard.dashboardwidgets.edit', [
           'widgetSizeOptions' => $services->getWidgetSizeOptions(),
            'widgetTypes' => $services->getWidgetTypes(),
            'widgetViews'   => $services->getWidgetViews(),
            'widgetConfigs' => $services->getWidgetConfigs(),
            'widgetDefinitions' => $widgetDefinitions,
            'widget' => $widget,
        ]);
    }
    public function updateWidget(Request $request,$Id)
    {
    $this->authorize(PermissionEnum::DMSWidgetsUpdate, DocumentDashboardWidgets::class);

    try{

        $widget = DocumentDashboardWidgets::findOrFail($Id);

        $validated = $request->validate([
            'WidgetTitle' => 'required|string|max:255',
            'WidgetKey' => 'nullable|string|max:100',
            'WidgetType' => 'required|string|max:100',
            'WidgetSize' => 'required|string|max:50',
            'WidgetView' => 'nullable|string|max:255',

            'WidgetConfig.data_source' => 'required|array|min:1',
            'WidgetConfig.data_source.*' => 'required|string',
        ]);

        $widgetKey = $validated['WidgetKey']
            ? Str::slug($validated['WidgetKey'])
            : Str::slug($validated['WidgetTitle']);

        $exists = DocumentDashboardWidgets::where(
            'WidgetKey',
            $widgetKey
        )->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors([
                    'WidgetKey' => 'A widget with this key already exists.'
                ]);
        }

            $actorId = $request->user()?->Id;
            $now = now();

             $widget->update([
            'WidgetTitle' => $validated['WidgetTitle'],
            'WidgetKey' => $widgetKey,
            'WidgetType' => $validated['WidgetType'],
            'WidgetSize' => $validated['WidgetSize'],
            'WidgetView' => $validated['WidgetView'] ?? null,

            'WidgetConfig' => [
                'data_source' => $validated['WidgetConfig']['data_source'],
            ],

            'ModifiedBy' => $actorId,
            'ModifiedOn' => $now,

        ]);

        return redirect()->route('documentdashboardwidgets.create')->with('success', 'Widget updated successfully.');

    } catch (\Throwable $th) {

        Log::error('Widget update failed: ' . $th->getMessage());

        return back()
            ->withErrors(['error' => $th->getMessage()])
            ->withInput();
    }
  }

    public function deletewidget($Id)
{
    $this->authorize(PermissionEnum::DMSWidgetsDelete, DocumentDashboardWidgets::class);
    try {
        $widget =DocumentDashboardWidgets::findOrFail($Id);

        $widget->forceDelete();

        return redirect()
            ->route('documentdashboardwidgets.create')
            ->with('success', 'Widget  deleted successfully!');
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return redirect()->back()
            ->withErrors(['error' => 'Widget does not exist.']);
    } catch (\Throwable $th) {
        Log::error('Error deleting widget: ' . $th->getMessage());

        return redirect()->back()
            ->withErrors(['error' => 'Failed to delete widget. Please try again.']);
    }
}

}
