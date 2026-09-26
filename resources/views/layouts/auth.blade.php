<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ __('ui.login_meta_description') }}">
    <meta name="theme-color" content="#315f25">
    <title>{{ $authOrganizationName }} ERP · @yield('title')</title>
    <link rel="icon" href="{{ asset($authOrganizationLogo) }}">
    @vite('resources/css/auth.css')
</head>
<body>
    <a class="auth-skip-link" href="#auth-content">{{ __('ui.skip_to_login') }}</a>
    @yield('content')
    @yield('scripts')
</body>
</html>
