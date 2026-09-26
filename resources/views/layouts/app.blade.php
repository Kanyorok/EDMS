<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

@php
    $uiThemes = config('ui_themes', []);
    $uiPrefs = auth()->check() ? (auth()->user()->UIPreferences ?? []) : [];
    // Theme is intentionally session-scoped, not read from the persisted user record: a
    // fresh login always starts from the org's Color Scheme (Settings > Organization
    // Profile), and an in-session customizer pick only overrides it until logout.
    $activeThemeId = session('ui_theme_id') ?? ($uiThemes['default_theme'] ?? 'preset-1');
    $activeLayoutId = $uiPrefs['layout_id'] ?? ($uiThemes['default_layout'] ?? 'vertical');
    if (! ($uiThemes['themes'][$activeThemeId]['available'] ?? false)) {
        $activeThemeId = $uiThemes['default_theme'] ?? 'preset-1';
    }
    if (! ($uiThemes['layouts'][$activeLayoutId]['available'] ?? false)) {
        $activeLayoutId = $uiThemes['default_layout'] ?? 'vertical';
    }
@endphp

<head>
    @include('layouts._partials._head')
    <title>{{ config('app.name') }} - @yield('title')</title>
</head>

<body data-pc-preset="{{ $activeThemeId }}" data-pc-sidebar-caption="true" data-pc-layout="{{ $activeLayoutId }}" data-pc-direction="ltr"
    data-pc-theme_contrast="" data-pc-theme="light">
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <nav class="pc-sidebar">
        <div class="navbar-wrapper">
            <div class="m-header">
                @php
                $org = \App\Models\Settings\OrganizationProfile::current();
                $branding = $org?->Configuration;
                $logo = is_object($branding) && isset($branding->logo) ? $branding->logo : 'assets/img/carft.png';
                $name = is_object($branding) && isset($branding->name) ? $branding->name : config('app.name');
                $shortName = is_object($branding) && !empty($branding->short_name) ? $branding->short_name : $name;
                $motto = is_object($branding) && isset($branding->motto) ? $branding->motto : 'Thinking.Crafting.Transforming';
                @endphp
                <a href="{{ route('home') }}" class="b-brand text-primary d-flex align-items-center app-sidebar-brand" title="{{ $name }}">
                    <img src="{{ asset($logo) }}" class="img-fluid app-brand-logo" alt="logo">
                    <div class="ms-3 app-brand-text">
                        <div class="app-brand-name text-decoration-none text-center">
                            {{ $shortName }}
                        </div>
                        <div class="small text-center app-brand-motto">{{ $motto }}</div>
                    </div>
                </a>
                @if ($activeLayoutId === 'layout-3')
                    @include('layouts._partials._layout-3')
                @endif
            </div>

            <div class="navbar-content">
                <div class="card pc-user-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 ">
                                @auth
                                {!! auth()->user()->getImage('class="avatar-1 user-avtar wid-45 hei-45 rounded-circle"
                                alt="user-image"') !!}
                                @else
                                <img src="{{ asset('assets/img/user/avatar-1.jpg') }}" class="avatar-1 user-avtar wid-45 hei-45 rounded-circle" alt="guest-image">
                                @endauth
                            </div>
                            <div class="flex-grow-1 ms-3 me-2">
                                @auth
                                <h6 class="mb-0">{{ auth()->user()->UserID }}</h6>
                                <small>{{ session('LoginRoleName')??'?' }}</small><br>
                                <small>{{ session('LoginBranchName') ?: __('ui.no_branch') }}</small>
                                @else
                                <h6 class="mb-0">{{ __('ui.guest') }}</h6>
                                <small>{{ __('ui.visitor') }}</small>
                                @endauth
                            </div>
                            <a class="btn btn-icon btn-link-secondary avtar collapsed" data-bs-toggle="collapse"
                                href="#pc_sidebar_userlink" aria-expanded="false">
                                <svg class="pc-icon">
                                    <use xlink:href="#custom-sort-outline"></use>
                                </svg>
                            </a>
                        </div>
                        <div class="pc-user-links collapse" id="pc_sidebar_userlink">
                            <div class="pt-3">
                                <a href="{{ route('profile') }}"><i class="ti ti-user"></i> <span>{{ __('ui.my_account') }}</span>
                                </a>
                                <a href="{{ route('logout') }}"
                                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="ti ti-power"></i> <span>{{ __('ui.logout') }}</span>
                                </a>

                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Skip rebuilding the full module tree (578 modules / 5k+ routes) on AJAX
                     partial navigations -- the client only swaps #mainBodyContent and never
                     reads this markup from a partial response (see ajaxNavigate() below). --}}
                @unless (request()->header('X-Partial') === '1')
                    @include('layouts._partials._navbar')
                @endunless
            </div>
        </div>
        @if ($activeLayoutId === 'layout-1')
            @include('layouts._partials._layout-1')
        @elseif ($activeLayoutId === 'layout-2')
            @include('layouts._partials._layout-2')
        @endif
        {{-- Layouts 3 and 4 need no secondary panel here: layout-3.js/layout-4.js work
             entirely off the existing .pc-navbar markup above (layout-3 also has its own
             small toggle-switch partial included in the brand row, further up). --}}
    </nav>
    <header class="pc-header">
        <div class="header-wrapper">
            <div class="me-auto pc-mob-drp">
                <ul class="list-unstyled">
                    <li class="pc-h-item pc-sidebar-collapse"><a href="#" class="pc-head-link ms-0" id="sidebar-hide"><i
                                class="ti ti-menu-2"></i></a></li>
                    <li class="pc-h-item pc-sidebar-popup"><a href="#" class="pc-head-link ms-0" id="mobile-collapse"><i
                                class="ti ti-menu-2"></i></a></li>
                    <li class="pc-h-item d-none d-md-inline-flex">
                        @yield('search-form')
                    </li>

                </ul>
            </div>
            <div class="ms-auto">
                <ul class="list-unstyled">
                    <li class="dropdown pc-h-item">
                        <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                            role="button" aria-haspopup="false" aria-expanded="false">
                            <svg class="pc-icon">
                                <use xlink:href="#custom-sun-1"></use>
                            </svg>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end pc-h-dropdown"><a href="javascript:void(0)"
                                class="dropdown-item"
                                onclick="layout_change('dark')">
                                <svg class="pc-icon">
                                    <use xlink:href="#custom-moon"></use>
                                </svg>
                                <span>{{ __('ui.theme_dark') }}</span> </a><a href="javascript:void(0)" class="dropdown-item"
                                onclick="layout_change('light')">
                                <svg class="pc-icon">
                                    <use xlink:href="#custom-sun-1"></use>
                                </svg>
                                <span>{{ __('ui.theme_light') }}</span> </a><a href="javascript:void(0)" class="dropdown-item"
                                onclick="layout_change_default()">
                                <svg class="pc-icon">
                                    <use xlink:href="#custom-setting-2"></use>
                                </svg>
                                <span>{{ __('ui.theme_default') }}</span></a></div>
                    </li>
                    <li class="pc-h-item">
                        <a href="#" class="pc-head-link me-0" data-bs-toggle="offcanvas" data-bs-target="#announcement"
                            aria-controls="announcement">
                            <svg class="pc-icon">
                                <use xlink:href="#custom-flash"></use>
                            </svg>
                        </a>
                    </li>
                    <li class="dropdown pc-h-item">
                        @php
                            $notifUnread = 0;
                            $notifItems = collect();
                            if (auth()->check()) {
                                $notifItems = \App\Models\Communication\UserNotification::where('UserID', auth()->id())
                                    ->orderByDesc('CreatedOn')
                                    ->limit(5)
                                    ->get();
                                $notifUnread = \App\Models\Communication\UserNotification::where('UserID', auth()->id())
                                    ->whereNull('ReadOn')
                                    ->count();
                            }
                        @endphp
                        <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                            role="button" aria-haspopup="false" aria-expanded="false">
                            <svg class="pc-icon">
                                <use xlink:href="#custom-notification"></use>
                            </svg>
                            @if($notifUnread > 0)
                                <span class="badge bg-danger pc-h-badge">{{ $notifUnread }}</span>
                            @endif
                        </a>
                        <div class="dropdown-menu dropdown-notification dropdown-menu-end pc-h-dropdown">
                            <div class="dropdown-header d-flex align-items-center justify-content-between">
                                <h5 class="m-0">{{ __('ui.notifications') }}</h5>
                            </div>
                            <div class="dropdown-body text-wrap header-notification-scroll position-relative">
                                @if($notifItems->isEmpty())
                                    <p class="text-span text-center my-3">{{ __('ui.no_notifications') }}</p>
                                @else
                                    @foreach($notifItems as $n)
                                        <div class="d-flex align-items-start p-2 border-bottom">
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold">{{ $n->Title }}</div>
                                                <div class="text-muted small">{{ \Illuminate\Support\Str::limit((string) $n->Body, 80) }}</div>
                                                <div class="text-muted small">{{ $n->CreatedOn }}</div>
                                            </div>
                                            @if(is_null($n->ReadOn))
                                                <span class="badge bg-warning text-dark ms-2">{{ __('ui.badge_new') }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                            <div class="text-center py-2">
                                <a href="{{ route('notifications.index') }}" class="link-primary">{{ __('ui.view_all') }}</a>
                            </div>
                        </div>
                    </li>
                    <li class="dropdown pc-h-item">
                        <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                            role="button" aria-haspopup="false" aria-expanded="false">
                            @auth
                            {!! auth()->user()->getImage('class="avatar-1 user-avtar" alt="user-image"') !!}
                            @else
                            <img src="{{ asset('assets/img/user/avatar-1.jpg') }}" class="avatar-1 user-avtar" alt="guest-image">
                            @endauth
                            <svg class="pc-icon">
                                <use xlink:href="#custom-setting-2"></use>
                            </svg>

                        </a>
                        <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                            <a href="{{ route('profile') }}" class="dropdown-item">
                                <i class="ti ti-user"></i> <span>{{ __('ui.my_account') }}</span>
                            </a>
                            <a href="javascript:void(0)" class="dropdown-item"><i class="ti ti-headset"></i>
                                <span>{{ __('ui.support') }}</span>
                            </a>

                            <a href="{{ route('logout') }}"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                                class="dropdown-item">
                                <i class="ti ti-power"></i> <span>{{ __('ui.logout') }}</span>
                            </a>
                        </div>
                    </li>
                    {{-- Language Switcher --}}
                    <li class="dropdown pc-h-item">
                        <a class="pc-head-link dropdown-toggle arrow-none me-0 fw-semibold" data-bs-toggle="dropdown"
                            href="#" role="button" aria-haspopup="false" aria-expanded="false"
                            style="font-size:13px; letter-spacing:.5px;">
                            {{ strtoupper(app()->getLocale()) }}
                        </a>
                        <div class="dropdown-menu dropdown-menu-end pc-h-dropdown" style="min-width:140px;">
                            <a href="{{ route('lang.switch', 'en') }}"
                               class="dropdown-item{{ app()->getLocale() === 'en' ? ' active' : '' }}">
                                <span class="fw-semibold me-2">EN</span> {{ __('ui.english') }}
                            </a>
                            <a href="{{ route('lang.switch', 'fr') }}"
                               class="dropdown-item{{ app()->getLocale() === 'fr' ? ' active' : '' }}">
                                <span class="fw-semibold me-2">FR</span> {{ __('ui.french') }}
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    @include('layouts._partials._customizer')

    <div class="pc-container">
        <div class="pc-content" id="mainBodyContent">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-12 col-sm-6">
                            <div class="page-header-title">
                                <h3 class="mb-0">@yield('title')</h3>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <ul class="breadcrumb float-end">
                                <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('ui.home_breadcrumb') }}</a></li>
                                @yield('breadcrumbs')
                                <li class="breadcrumb-item" aria-current="page">@yield('title')</li>
                            </ul>
                        </div>

                    </div>
                </div>
            </div>

            @php
                $flashHasPageError = session()->has('page_error');
                $flashSuccess = session()->pull('success');
                $flashStatus = session()->pull('status');
                $flashFail = session()->pull('fail');
                $flashError = session()->pull('error');
                $flashWarning = session()->pull('warning');

                if ($flashHasPageError) {
                    $flashFail = null;
                    $flashError = null;
                }
            @endphp

            @yield('page-alerts')

	            @sectionMissing('suppressGlobalValidationErrors')
	                @if ($errors->any())
		                    <div class="alert alert-danger alert-dismissible fade show" role="alert" data-global-validation-errors hidden>
	                        <div class="fw-semibold mb-1">{{ __('ui.please_fix_following') }}</div>
	                        <ul class="mb-0 ps-3">
	                            @foreach ($errors->all() as $error)
	                                <li>{{ $error }}</li>
	                            @endforeach
	                        </ul>
	                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	                    </div>
	                @endif
	            @endif

            @if($flashFail)
                <div class="alert alert-danger alert-dismissible fade show" role="alert" data-global-flash-alert>
                    {{ $flashFail }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($flashError)
                <div class="alert alert-danger alert-dismissible fade show" role="alert" data-global-flash-alert>
                    {{ $flashError }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($flashWarning)
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    {{ $flashWarning }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
    <footer class="pc-footer">
        <div class="footer-wrapper container-fluid">
            <div class="row">
                <div class="col my-1">
                    <p class="m-0">@include('layouts._partials._copyright')</p>
                </div>
                <div class="col-auto my-1 d-flex align-items-center gap-3">
                    <span id="footer-datetime" class="text-muted small"></span>
                    <span class="text-muted small">|</span>
                    <span class="text-muted small">version v1.0.0</span>
                </div>
            </div>
        </div>
    </footer>
    @include('layouts._partials._scripts')

    {{-- Partial navigation init registry --}}
    <script src="{{ asset('js/partial-init.js') }}" defer></script>
    <script src="{{ asset('js/partial-widgets.js') }}" defer></script>
    <script src="{{ asset('js/partial-forms.js') }}" defer></script>

    {{--
        @push('scripts') content (unlike @section('scripts')) isn't captured by
        any named section, so the AJAX partial-navigation fragment extractor
        (ReturnContentFragment.php) can't forward it unless it's wrapped in an
        identifiable container it knows to look for.
    --}}
    <div id="page-stack-scripts" style="display:none">
        @stack('scripts')
    </div>

    <script>
        (function () {
            const removeDuplicateGlobalValidationErrors = () => {
                const globalAlert = document.querySelector('[data-global-validation-errors]');
                if (!globalAlert) return;

                const alertRoot = globalAlert.closest('.pc-content') || document;
                const hasLocalErrorManagement = Array.from(alertRoot.querySelectorAll('.alert.alert-danger'))
                    .some((alert) => {
                        if (alert === globalAlert) return false;
                        if (alert.hasAttribute('data-global-flash-alert')) return false;

                        return alert.textContent.trim().length > 0;
                    });

                if (hasLocalErrorManagement) {
                    globalAlert.remove();
                } else {
                    globalAlert.hidden = false;
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', removeDuplicateGlobalValidationErrors);
            } else {
                removeDuplicateGlobalValidationErrors();
            }
        })();
    </script>

    <script>
        (function () {
            const formatValue = (value) => {
                let raw = String(value).replace(/,/g, '');
                if (raw === '' || raw === '.' || raw === '-.') {
                    return raw;
                }
                const negative = raw.startsWith('-');
                if (negative) raw = raw.slice(1);
                raw = raw.replace(/[^0-9.]/g, '');
                const parts = raw.split('.');
                const integerPart = parts.shift() || '';
                const decimalPart = parts.length ? parts.join('') : '';
                const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                return negative
                    ? `-${formattedInteger}${decimalPart ? '.' + decimalPart : ''}`
                    : `${formattedInteger}${decimalPart ? '.' + decimalPart : ''}`;
            };

            const formatInput = (input) => {
                if (!input || input.disabled) return;
                input.value = formatValue(input.value);
            };

            document.addEventListener('input', function (event) {
                const target = event.target;
                if (target.matches('.js-thousand-sep')) {
                    formatInput(target);
                }
            });

            document.addEventListener('blur', function (event) {
                const target = event.target;
                if (target.matches('.js-thousand-sep')) {
                    formatInput(target);
                }
            }, true);

            document.addEventListener('submit', function (event) {
                const form = event.target;
                if (!form || !form.querySelectorAll) return;
                form.querySelectorAll('.js-thousand-sep').forEach((input) => {
                    if (input && input.value) {
                        input.value = String(input.value).replace(/,/g, '');
                    }
                });
            });

            document.addEventListener('partial:loaded', function () {
                document.querySelectorAll('.js-thousand-sep').forEach(formatInput);
            });

            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.js-thousand-sep').forEach(formatInput);
            });
        })();
    </script>

    <script>
        // Refresh Feather icons after partial content loads
        document.addEventListener('partial:loaded', function() {
            if (window.feather && typeof window.feather.replace === 'function') {
                try {
                    window.feather.replace();
                } catch (e) {}
            }
        });
    </script>
    <script type="module">
        import {
            SidebarState
        } from '/js/sidebarState.js';

        SidebarState.init({
            rootSelector: 'nav.pc-sidebar',
            itemSelector: 'li.pc-item',
            linkSelector: 'a.pc-link',
            submenuSelector: '.pc-submenu',
            activeItemClass: 'active',
            expandedItemClass: 'pc-trigger',
            userKey: '{{ auth()->id() ?? "guest" }}'
        });
        document.addEventListener('partial:loaded', () => SidebarState.restore());
    </script>

    <script>
        // Footer DateTime (user timezone in browser)
        (function updateFooterDateTime() {
            const el = document.getElementById('footer-datetime');
            if (!el) return;
            const now = new Date();
            // Format: YYYY-MM-DD HH:MM:SS (24h)
            const pad = n => n.toString().padStart(2, '0');
            const formatted = `${pad(now.getDate())}-${pad(now.getMonth() + 1)}-${now.getFullYear()} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
            el.textContent = `System Time: ${formatted}`;
            setTimeout(updateFooterDateTime, 1000);
        })();

        // Keep sidebar static: load only #mainBodyContent for internal sidebar navigation
        (function() {
            const sidebar = document.querySelector('nav.pc-sidebar');
            const contentId = 'mainBodyContent';

            function sameOrigin(url) {
                try {
                    const u = new URL(url, location.href);
                    return u.origin === location.origin;
                } catch (e) {
                    return false;
                }
            }

            function runScripts(container) {
                if (!container) return;
                // Execute inline and external scripts found inside the new content,
                // strictly in document order. A normal page load blocks on each
                // <script src> before running the next tag, so inline scripts can
                // safely assume earlier external scripts have already run. Firing
                // every script off in a plain forEach loses that guarantee (the
                // external script is still downloading when the next, often
                // inline, script executes) -- so each script here waits for the
                // previous external one to finish loading before it runs.
                const scripts = Array.from(container.querySelectorAll('script'));

                function run(index) {
                    if (index >= scripts.length) return;
                    const old = scripts[index];
                    const s = document.createElement('script');
                    Array.from(old.attributes).forEach(attr => s.setAttribute(attr.name, attr.value));
                    old.parentNode && old.parentNode.removeChild(old);

                    if (old.src) {
                        s.onload = s.onerror = () => run(index + 1);
                        document.body.appendChild(s);
                    } else {
                        s.textContent = old.textContent;
                        document.body.appendChild(s);
                        run(index + 1);
                    }
                }

                run(0);
            }

            async function ajaxNavigate(url, addToHistory = true) {
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]');
                    const res = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Partial': '1',
                            'X-CSRF-TOKEN': csrfToken ? csrfToken.content : ''
                        }
                    });
                    
                    // Handle expired or unauthenticated sessions.
                    if (res.status === 401 || res.status === 419) {
                        console.warn('Session expired, redirecting to login');
                        window.location.href = '/login?expired=1';
                        return;
                    }
                    
                    if (!res.ok) {
                        console.warn('AJAX navigate failed with status:', res.status);
                        window.location.href = url;
                        return;
                    }
                    const text = await res.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(text, 'text/html');
                    const newContent = doc.getElementById(contentId);
                    if (!newContent) {
                        window.location.href = url;
                        return;
                    }
                    const target = document.getElementById(contentId);
                    if (!target) {
                        window.location.href = url;
                        return;
                    }
                    target.innerHTML = newContent.innerHTML;
                    const newTitle = doc.querySelector('title');
                    if (newTitle) document.title = newTitle.innerText;
                    if (addToHistory) history.pushState({
                        url: url
                    }, '', url);
                    window.scrollTo(0, 0);
                    runScripts(target);
                    // Page-specific scripts (@section('scripts')) render outside
                    // #mainBodyContent via the shared layout, so the fragment
                    // swap above never picks them up -- re-run them explicitly
                    // from the freshly fetched document or the new page's JS
                    // (add-button handlers, table init, etc.) never binds.
                    const newPageScripts = doc.getElementById('page-scripts');
                    if (newPageScripts) {
                        runScripts(newPageScripts);
                    }
                    // Same problem for @push('scripts') content (see page-stack-scripts
                    // in layouts/app.blade.php) -- it renders outside #mainBodyContent too.
                    const newStackScripts = doc.getElementById('page-stack-scripts');
                    if (newStackScripts) {
                        runScripts(newStackScripts);
                    }
                    // Same problem for @section('styles')/@push('styles') content
                    // (see page-styles in layouts/_partials/_head.blade.php) -- it
                    // renders in <head>, which the fragment swap above never touches,
                    // so page-specific CSS never applies until a full reload.
                    document.querySelectorAll('link[data-page-style], style[data-page-style]').forEach(el => el.remove());
                    const newPageStyles = doc.getElementById('page-styles');
                    if (newPageStyles) {
                        Array.from(newPageStyles.querySelectorAll('link, style')).forEach(node => {
                            node.setAttribute('data-page-style', '1');
                            document.head.appendChild(node);
                        });
                    }
                    try {
                        sessionStorage.setItem('activeSidebarRoute', new URL(url, location.href).pathname);
                    } catch (e) {}
                    document.dispatchEvent(new CustomEvent('partial:loaded', {
                        detail: {
                            url
                        }
                    }));
                } catch (err) {
                    console.error('AJAX navigate failed, falling back', err);
                    window.location.href = url;
                }
            }

            function hideSidebarTooltips() {
                document.querySelectorAll('nav.pc-sidebar [data-bs-toggle="tooltip"]').forEach(function (trigger) {
                    const instance = bootstrap.Tooltip.getInstance(trigger);
                    if (instance) {
                        instance.hide();
                    }
                });
            }

            if (sidebar) {
                sidebar.addEventListener('click', function(ev) {
                    const a = ev.target.closest && ev.target.closest('a');
                    if (!a) return;
                    const href = a.getAttribute('href');
                    if (!href) return;
                    if (href.startsWith('#') || href.startsWith('javascript:')) return;
                    if (a.target && a.target !== '_self') return;
                    const isAjaxMarked = a.hasAttribute('data-ajax') && a.getAttribute('data-ajax') === '1';
                    const inGeneratedNavbar = !!a.closest('.pc-navbar');
                    if (!isAjaxMarked && !inGeneratedNavbar) return;
                    if (a.hasAttribute('data-no-ajax')) return;
                    if (a.getAttribute('onclick')) return;
                    if (a.getAttribute('href') && a.getAttribute('href').includes('/logout')) return;
                    if (!sameOrigin(href)) return;
                    if ((ev.button && ev.button !== 0) || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;

                    hideSidebarTooltips();
                    ev.preventDefault();
                    // Handle leaf navigation before the theme's ancestor menu
                    // handlers stop bubbling or toggle an unrelated submenu.
                    ev.stopPropagation();
                    try {
                        sessionStorage.setItem('activeSidebarRoute', new URL(href, location.href).pathname);
                    } catch (e) {}
                    ajaxNavigate(href);
                }, true);
            }

            window.addEventListener('popstate', function(ev) {
                ajaxNavigate(location.href, false);
            });
        })();

        // Highlight and scroll active sidebar item
        (function() {
            function normalizePath(p) {
                try {
                    return new URL(p, location.href).pathname.replace(/\/+$|^\/+/g, '/');
                } catch (e) {
                    return ('' + p).replace(/\/+$/, '');
                }
            }

            function highlightAndScrollActive() {
                const sidebar = document.querySelector('nav.pc-sidebar');
                if (!sidebar) return;
                const navRoot = sidebar.querySelector('.pc-navbar') || sidebar;
                navRoot.querySelectorAll('a.pc-link:not([data-route])').forEach(a => {
                    try {
                        a.setAttribute('data-route', new URL(a.getAttribute('href'), location.href).pathname);
                    } catch (e) {}
                });
                const anchors = Array.from(navRoot.querySelectorAll('a[data-route]'));
                const current = normalizePath(location.pathname);
                let match = anchors.find(a => normalizePath(a.dataset.route) === current);

                if (!match) {
                    let best = null,
                        bestLen = 0;
                    anchors.forEach(a => {
                        const p = normalizePath(a.dataset.route);
                        if (current.startsWith(p) && p.length > bestLen && p !== '/') {
                            best = a;
                            bestLen = p.length;
                        }
                    });
                    match = best;
                }

                if (!match) {
                    const stored = sessionStorage.getItem('activeSidebarRoute');
                    if (stored) match = anchors.find(a => normalizePath(a.dataset.route) === normalizePath(stored));
                }

                if (!match) return;

                match.classList.add('active');
                let el = match.closest('.pc-item') || match.parentElement;
                while (el && el !== navRoot) {
                    if (el.classList && el.classList.contains('pc-item')) {
                        el.classList.add('active');
                        if (el.classList.contains('pc-hasmenu')) el.classList.add('pc-trigger');
                    }
                    el = el.parentElement;
                }

                try {
                    const rect = match.getBoundingClientRect();
                    const vpH = window.innerHeight || document.documentElement.clientHeight;
                    if (rect.top < 80 || rect.bottom > vpH - 40) {
                        match.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }
                } catch (e) {}
            }
            highlightAndScrollActive();
            document.addEventListener('partial:loaded', highlightAndScrollActive);
        })();

        // Global DataTable Protection
        (function() {
            'use strict';
            var checkJQuery = setInterval(function() {
                if (typeof jQuery === 'undefined') return;
                clearInterval(checkJQuery);
                var $ = jQuery;

                function setupDataTableObserver() {
                    var sidebarToggleButtons = document.querySelectorAll('#sidebar-hide, #mobile-collapse');
                    if (sidebarToggleButtons.length) {
                        sidebarToggleButtons.forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                setTimeout(function() {
                                    if ($.fn.DataTable) $.fn.DataTable.tables({
                                        visible: true,
                                        api: true
                                    }).columns.adjust();
                                }, 400);
                            });
                        });
                    }
                    var resizeTimer;
                    $(window).on('resize', function() {
                        clearTimeout(resizeTimer);
                        resizeTimer = setTimeout(function() {
                            if ($.fn.DataTable) $.fn.DataTable.tables({
                                visible: true,
                                api: true
                            }).columns.adjust();
                        }, 250);
                    });
                }
                $(document).ready(setupDataTableObserver);
                document.addEventListener('partial:loaded', function() {
                    setTimeout(setupDataTableObserver, 100);
                    setTimeout(function() {
                        if ($.fn.DataTable) $.fn.DataTable.tables({
                            visible: true,
                            api: true
                        }).columns.adjust();
                    }, 500);
                });
            }, 100);
        })();
    </script>

    @include('partials.session-timeout')
    <script>
        (function() {
            // Re-init partials logic listener if needed
        })();
    </script>
</body>

</html>
