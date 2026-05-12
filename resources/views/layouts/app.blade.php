@php
    $credentials = app(\App\Services\MobileCredentialStore::class);
    $siteConfig = $siteConfig ?? app(\App\Services\SiteConfig::class)->get();
    $isAuthenticated = $credentials->isAuthenticated();
    $isAuthScreen = request()->routeIs('login', 'signup', 'password.*', 'two-factor.challenge');
    $showsAuthBrand = ! $isAuthenticated && $isAuthScreen;
    $showsMobileLayout = $isAuthenticated && ! request()->routeIs('startup');
    $showsPlainHeader = $isAuthenticated && ! request()->routeIs('startup');
    $title = $title ?? $siteConfig['site_name'];
    $mobileMessages = [
        'copyFailed' => __('mobile.errors.copy_failed'),
        'doubleBackToClose' => __('mobile.common.double_back_to_close'),
    ];
    $mobileSecurity = [
        'csrfToken' => csrf_token(),
        'doubleBackToClose' => $showsMobileLayout,
        'performanceMode' => true,
        'spaTimeout' => 10000,
    ];
    $mobileRuntimeConfig = [
        'messages' => $mobileMessages,
        'security' => $mobileSecurity,
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script>
            window.mobileMessages = @js($mobileMessages);
            window.mobileSecurity = @js($mobileSecurity);
        </script>
    </head>
    <body class="nativephp-safe-area mobile-performance-mode min-h-screen bg-vault-bg text-vault-text antialiased">
        <main class="min-h-full bg-vault-bg" data-mobile-spa-root>
            <script type="application/json" data-mobile-runtime-config>{!! json_encode($mobileRuntimeConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
            @if ($showsAuthBrand)
                @include('layouts.auth', ['siteConfig' => $siteConfig, 'slot' => $slot, 'authFooter' => $authFooter ?? null])
            @elseif ($showsMobileLayout)
                @include('layouts.mobile', ['siteConfig' => $siteConfig, 'slot' => $slot, 'profile' => $credentials->user()])
            @else
                @include('layouts.plain', ['siteConfig' => $siteConfig, 'slot' => $slot, 'isAuthenticated' => $isAuthenticated, 'showHeader' => $showsPlainHeader])
            @endif
        </main>
        @if (session('clear_native_nav') === true)
            <native:bottom-nav label-visibility="unlabeled" dark></native:bottom-nav>
        @endif
        <div data-mobile-spa-controller hidden></div>
    </body>
</html>
