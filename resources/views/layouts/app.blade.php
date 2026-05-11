@php
    $credentials = app(\App\Services\MobileCredentialStore::class);
    $siteConfig = $siteConfig ?? app(\App\Services\SiteConfig::class)->get();
    $isAuthenticated = $credentials->isAuthenticated();
    $isAuthScreen = request()->routeIs('login', 'signup', 'password.*', 'two-factor.challenge');
    $showsAuthBrand = ! $isAuthenticated && $isAuthScreen;
    $showsMobileLayout = $isAuthenticated && ! request()->routeIs('settings.unlock') && ! request()->routeIs('startup');
    $title = $title ?? $siteConfig['site_name'];
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
            window.mobileMessages = {
                biometricsUnavailable: @js(__('mobile.errors.biometrics_unavailable')),
                biometricsCancelled: @js(__('mobile.errors.biometrics_cancelled')),
                biometricsFailed: @js(__('mobile.errors.biometrics_failed')),
                copyFailed: @js(__('mobile.errors.copy_failed')),
            };
            window.mobileSecurity = {
                shouldLockOnHide: @js($showsMobileLayout && $credentials->biometricsEnabled()),
                lockUrl: @js(route('settings.lock')),
                unlockUrl: @js(route('settings.unlock')),
                csrfToken: @js(csrf_token()),
            };
        </script>
    </head>
    <body class="nativephp-safe-area min-h-screen bg-vault-bg text-vault-text antialiased">
        <main class="min-h-full bg-vault-bg">
            @if ($showsAuthBrand)
                @include('layouts.auth', ['siteConfig' => $siteConfig, 'slot' => $slot, 'authFooter' => $authFooter ?? null])
            @elseif ($showsMobileLayout)
                @include('layouts.mobile', ['siteConfig' => $siteConfig, 'slot' => $slot])
            @else
                @include('layouts.plain', ['siteConfig' => $siteConfig, 'slot' => $slot, 'isAuthenticated' => $isAuthenticated])
            @endif
        </main>
        @include('mobile.partials.biometric-overlay')
        @if (session('clear_native_nav') === true)
            <native:bottom-nav label-visibility="unlabeled" dark></native:bottom-nav>
        @endif
    </body>
</html>
