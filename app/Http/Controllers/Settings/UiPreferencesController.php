<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UiPreferencesController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $preferences = $request->user()->UIPreferences ?? [];
        $preferences['theme_id'] = session('ui_theme_id', $preferences['theme_id'] ?? null);

        return response()->json([
            'preferences' => $preferences,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'theme_id' => ['nullable', 'string'],
            'layout_id' => ['nullable', 'string'],
        ]);

        $themes = config('ui_themes.themes', []);
        $layouts = config('ui_themes.layouts', []);

        $themeId = $data['theme_id'] ?? null;
        $layoutId = $data['layout_id'] ?? null;

        if ($themeId !== null && ! ($themes[$themeId]['available'] ?? false)) {
            abort(422, 'Unknown or unavailable theme.');
        }

        if ($layoutId !== null && ! ($layouts[$layoutId]['available'] ?? false)) {
            abort(422, 'Unknown or unavailable layout.');
        }

        $user = $request->user();
        $preferences = $user->UIPreferences ?? [];

        // Theme choice is deliberately session-only (not saved to the user record): it
        // should override the org Color Scheme for the rest of this login, then fall back
        // to the org default again on the next login. Session is cleared on logout in
        // AuthenticatedSessionController::destroy().
        if ($themeId !== null) {
            $request->session()->put('ui_theme_id', $themeId);
        }

        if ($layoutId !== null) {
            $preferences['layout_id'] = $layoutId;
            $user->UIPreferences = $preferences;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'preferences' => array_merge($preferences, [
                'theme_id' => $themeId ?? session('ui_theme_id'),
            ]),
        ]);
    }
}
