<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    (function () {
        // Keep the template's own layout-switcher (script.js setLayout(), reads this key on
        // every page load) in sync with the server-resolved layout, so it never clobbers
        // the data-pc-layout attribute this page was just rendered with.
        try { localStorage.setItem('layout', @json($activeLayoutId ?? 'vertical')); } catch (e) {}
    })();
</script>
<meta name="description" content="@yield('description')">
<meta name="keywords" content="">
<meta name="msapplication-TileColor" content="#3e17a0">
<meta name="msapplication-TileImage" content="{{ asset('assets/img/mood/kssco.jpg') }}">
<meta name="msapplication-config" content="{{ asset('browserconfig.xml') }}">
<meta name="theme-color" content="#2e3192">
<link rel="icon" href="{{ asset('assets/img/mood/kssco.jpg') }}" type="image/jpeg">
<link rel="shortcut icon" href="{{ asset('assets/img/mood/kssco.jpg') }}" type="image/jpeg"><!-- [Font] Family -->
<link rel="stylesheet" href="{{ asset('assets/fonts/inter/inter.css') }}" id="main-font-link">
<link rel="stylesheet" href="{{ asset('assets/fonts/phosphor/duotone/style.css') }}">
<link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}"><!-- [Feather Icons] https://feathericons.com -->
<link rel="stylesheet" href="{{ asset('assets/fonts/feather.css') }}">
<link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome.css') }}">
<link rel="stylesheet" href="{{ asset('assets/fonts/material.css') }}"><!-- [Template CSS Files] -->
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" id="main-style-link">
{{--<script src="{{ asset('assets/js/tech-stack.js') }}"></script> --}}
<link rel="stylesheet" href="{{ asset('assets/css/style-preset.css') }}">
{{-- Must load after style-preset.css: both use equal-specificity [data-pc-preset=...]
     selectors, so later source wins for any variable they'd otherwise both define. --}}
<link rel="stylesheet" href="{{ asset('assets/css/theme-presets.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/app-branding.css') }}">
@php
    $orgColorScheme = \App\Models\Settings\OrganizationProfile::current();
@endphp
@if ($orgColorScheme && (!empty($orgColorScheme->PrimaryColor) || !empty($orgColorScheme->SidebarBackgroundColor) || !empty($orgColorScheme->SidebarTextColor)))
    <style id="org-color-scheme">
        {{-- Targets body (not just :root) because the theme's preset CSS sets --bs-primary
             and --pc-sidebar-active-color directly on <body data-pc-preset="...">; a
             descendant with its own matching declaration doesn't inherit from :root. --}}
        :root, body {
            @if (!empty($orgColorScheme->SidebarBackgroundColor))
                --pc-sidebar-background: {{ $orgColorScheme->SidebarBackgroundColor }} !important;
            @endif
            @if (!empty($orgColorScheme->SidebarTextColor))
                --pc-sidebar-color: {{ $orgColorScheme->SidebarTextColor }} !important;
                --pc-sidebar-caption-color: {{ $orgColorScheme->SidebarTextColor }} !important;
                @php
                    [$orgTextR, $orgTextG, $orgTextB] = sscanf($orgColorScheme->SidebarTextColor, '#%02x%02x%02x');
                @endphp
                --pc-sidebar-color-rgb: {{ $orgTextR }}, {{ $orgTextG }}, {{ $orgTextB }} !important;
            @endif
            @if (!empty($orgColorScheme->PrimaryColor))
                --bs-primary: {{ $orgColorScheme->PrimaryColor }} !important;
                --bs-link-color: {{ $orgColorScheme->PrimaryColor }} !important;
                --pc-sidebar-active-color: {{ $orgColorScheme->PrimaryColor }} !important;
                @php
                    [$orgColorR, $orgColorG, $orgColorB] = sscanf($orgColorScheme->PrimaryColor, '#%02x%02x%02x');
                @endphp
                --bs-primary-rgb: {{ $orgColorR }}, {{ $orgColorG }}, {{ $orgColorB }} !important;
            @endif
        }
        @if (!empty($orgColorScheme->PrimaryColor))
            .btn-primary {
                --bs-btn-bg: {{ $orgColorScheme->PrimaryColor }} !important;
                --bs-btn-border-color: {{ $orgColorScheme->PrimaryColor }} !important;
                --bs-btn-disabled-bg: {{ $orgColorScheme->PrimaryColor }} !important;
                --bs-btn-disabled-border-color: {{ $orgColorScheme->PrimaryColor }} !important;
            }
        @endif
    </style>
@endif
@php
    $userThemeVariables = ($activeThemeId ?? null) && $activeThemeId !== (config('ui_themes.default_theme') ?? 'preset-1')
        ? (config('ui_themes.themes.' . $activeThemeId . '.variables') ?? [])
        : [];
@endphp
@if (!empty($userThemeVariables))
    {{-- Renders after org-color-scheme above (same file, later in source), so it naturally
         wins by cascade order without that block needing to know this one exists. This is
         what makes a personal theme choice take precedence over org branding, and render
         correctly on first paint (no flash) since it's server-side, unlike the customizer
         panel's client-side preset_change() which only handles the in-panel live preview. --}}
    <style id="user-theme-override">
        :root, body {
            @foreach ($userThemeVariables as $cssVar => $value)
                {{ $cssVar }}: {{ $value }} !important;
            @endforeach
        }
    </style>
@endif
<link rel="stylesheet" href="{{ asset('assets/css/sidebar-enhancements.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/app-components.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/customizer.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/layout-1.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/layout-2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/layout-3.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/layout-4.css') }}">
<link rel="stylesheet" href="{{ asset('assets/libs/dataTables/dataTables.bootstrap5.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/libs/flatpickr/flatpickr.min.css') }}">
{{-- Use CDN for Notyf CSS to match CDN JS --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">

{{-- Added as it is used in Property and Insurance beautification --}}
{{-- Include Bootstrap Icons --}}
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
{{-- Include DataTables CSS --}}
{{-- Removed duplicate DataTables CSS, using Bootstrap5 version above --}}
@if (request()->is('property*'))
	<style>
		.form-control:not(textarea):not([type="file"]):not(.form-control-sm),
		.form-select:not(.form-select-sm),
		select.form-control:not(.form-control-sm) {
			height: calc(1.5em + 0.75rem + 2px);
			min-height: calc(1.5em + 0.75rem + 2px);
			padding-top: 0.375rem;
			padding-bottom: 0.375rem;
		}

		.select2-container--default .select2-selection--single {
			display: flex;
			align-items: center;
			height: calc(1.5em + 0.75rem + 2px);
			min-height: calc(1.5em + 0.75rem + 2px);
			padding: 0.375rem 2.25rem 0.375rem 0.75rem;
			border: 1px solid #ced4da;
			border-radius: 0.375rem;
		}

		.select2-container--default .select2-selection--single .select2-selection__rendered {
			line-height: 1.5;
			color: #212529;
			padding-left: 0;
			padding-right: 0;
		}

		.select2-container--default .select2-selection--single .select2-selection__arrow {
			height: 100%;
			right: 0.5rem;
		}

		.select2-container {
			width: 100% !important;
		}
	</style>
@endif
<div id="page-styles">
    @yield('styles')
    @stack('styles')
</div>
