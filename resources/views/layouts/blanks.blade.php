<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts._partials._head')
    <title>{{ config('app.name') }} - @yield('title')</title>
    <style>
        /* background overlay + fixed cover */
        .auth-main {
            background: linear-gradient(rgba(255, 253, 253, 0.95), rgba(255, 253, 253, 0.95)),
            url({{ asset('assets/img/BRERP_Logo_small.png') }}) left/cover no-repeat fixed;
            height: 95vh;
        }

        /* simple footer styling */
        .site-footer {
            background: #f8f9fa;
        }
    </style>
</head>
<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr"
      data-pc-theme_contrast="" data-pc-theme="light">
<div class="loader-bg">
    <div class="loader-track">
        <div class="loader-fill"></div>
    </div>
</div>
{{-- Language switcher for auth pages --}}
<div class="position-fixed top-0 end-0 p-3" style="z-index:1050;">
    <div class="dropdown">
        <a class="btn btn-sm btn-outline-secondary dropdown-toggle fw-semibold" href="#"
           data-bs-toggle="dropdown" role="button" aria-expanded="false" style="letter-spacing:.5px;">
            {{ strtoupper(app()->getLocale()) }}
        </a>
        <ul class="dropdown-menu dropdown-menu-end" style="min-width:130px;">
            <li>
                <a class="dropdown-item{{ app()->getLocale() === 'en' ? ' active' : '' }}"
                   href="{{ route('lang.switch', 'en') }}">
                    <span class="fw-semibold me-2">EN</span> {{ __('ui.english') }}
                </a>
            </li>
            <li>
                <a class="dropdown-item{{ app()->getLocale() === 'fr' ? ' active' : '' }}"
                   href="{{ route('lang.switch', 'fr') }}">
                    <span class="fw-semibold me-2">FR</span> {{ __('ui.french') }}
                </a>
            </li>
        </ul>
    </div>
</div>
<main class="auth-main flex-fill d-flex align-items-center justify-content-center">
    @yield('content')
</main>
<footer class="site-footer mt-auto text-center py-3 text-black">
    &copy; {{ date('Y') }} Bankers Realm. {{ __('ui.all_rights_reserved') }} Beyond The Eye Ltd
</footer>
@include('layouts._partials._scripts')
</body>
</html>
