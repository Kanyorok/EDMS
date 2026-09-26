@php
    $uiThemes = config('ui_themes', []);
    $themeOptions = $uiThemes['themes'] ?? [];
    $layoutOptions = $uiThemes['layouts'] ?? [];
    $activeThemeId = $activeThemeId ?? ($uiThemes['default_theme'] ?? 'preset-1');
    $activeLayoutId = $activeLayoutId ?? ($uiThemes['default_layout'] ?? 'vertical');
@endphp
<button type="button" class="customizer-trigger" id="customizer-trigger" data-bs-toggle="offcanvas"
    data-bs-target="#customizerPanel" aria-controls="customizerPanel"
    aria-label="Open layout and theme settings">
    <i data-feather="settings"></i>
</button>

{{--
    Deliberately NOT using the vendor's own "pct-offcanvas" class here: theme.js's
    preset_change() checks for that class and, if present, tries to manage active-state via
    ".preset-color > a[data-value]" elements — markup this panel never uses (it has its own
    customizer-theme-option buttons, active-state handled by customizer.js's own
    markSelected()). Adding that class was tried in an earlier version and caused a null-
    reference crash inside preset_change() the first time a theme was actually clicked (since
    only "Current" was selectable before Phase C, this went unnoticed until now) — the crash
    aborted the rest of the click handler before it could reach the save request.
--}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="customizerPanel"
    aria-labelledby="customizerPanelLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="customizerPanelLabel">Layout &amp; Theme</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body pct-body">
        <h6 class="customizer-section-title">Sidebar Layout</h6>
        <div class="customizer-layout-options" role="list" aria-label="Layout choices">
            @foreach ($layoutOptions as $layout)
                <button type="button"
                    class="customizer-option customizer-layout-option{{ $activeLayoutId === $layout['id'] ? ' selected' : '' }}{{ empty($layout['available']) ? ' upcoming' : '' }}"
                    data-layout-id="{{ $layout['id'] }}"
                    @if (empty($layout['available'])) disabled @endif
                    aria-pressed="{{ $activeLayoutId === $layout['id'] ? 'true' : 'false' }}"
                    title="{{ $layout['description'] ?? '' }}">
                    <span class="customizer-layout-preview customizer-layout-preview-{{ $layout['id'] }}" aria-hidden="true"></span>
                    <span class="customizer-option-label">{{ $layout['name'] }}</span>
                    @if (empty($layout['available']))
                        <em class="customizer-badge">Coming soon</em>
                    @endif
                </button>
            @endforeach
        </div>

        <h6 class="customizer-section-title">Theme Colors</h6>
        <div class="customizer-theme-options" role="listbox" aria-label="Theme choices">
            @foreach ($themeOptions as $theme)
                <button type="button"
                    class="customizer-option customizer-theme-option{{ $activeThemeId === $theme['id'] ? ' selected' : '' }}{{ empty($theme['available']) ? ' upcoming' : '' }}"
                    data-theme-id="{{ $theme['id'] }}"
                    data-theme-variables="{{ json_encode($theme['variables'] ?? []) }}"
                    @if (empty($theme['available'])) disabled @endif
                    role="option"
                    aria-selected="{{ $activeThemeId === $theme['id'] ? 'true' : 'false' }}">
                    <span class="customizer-swatches" aria-hidden="true">
                        @foreach (($theme['swatches'] ?? []) as $swatch)
                            <span style="background: {{ $swatch }}"></span>
                        @endforeach
                    </span>
                    <span class="customizer-option-text">
                        <strong>{{ $theme['name'] }}</strong>
                        <small>{{ $theme['description'] ?? '' }}</small>
                    </span>
                    @if (empty($theme['available']))
                        <em class="customizer-badge">Coming soon</em>
                    @endif
                </button>
            @endforeach
        </div>
    </div>
</div>
