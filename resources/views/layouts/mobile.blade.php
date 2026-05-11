@php
    $profile = app(\App\Services\MobileCredentialStore::class)->user();
    $displayName = is_string($profile['name'] ?? null) && filled($profile['name']) ? $profile['name'] : $siteConfig['site_name'];
    $initial = mb_substr((string) $displayName, 0, 1);
@endphp

<section class="mobile-screen vault-pattern mx-auto grid h-[calc(100dvh-var(--inset-top,0px)-var(--inset-bottom,0px))] w-full max-w-md grid-rows-[auto_minmax(0,1fr)_auto] overflow-hidden px-5 pt-4" data-authenticated-shell data-page-shell>
    <header class="z-20 -mx-5 flex min-h-16 shrink-0 items-center justify-between gap-4 border-b border-vault-border/40 bg-vault-bg/82 px-5 pb-3 backdrop-blur-xl">
        <div class="flex min-w-0 items-center gap-3">
            @if ($siteConfig['logo_url'])
                <img class="size-11 shrink-0 rounded-full object-cover ring-1 ring-vault-border" src="{{ $siteConfig['logo_url'] }}" alt="{{ $siteConfig['site_name'] }}">
            @else
                <div class="vault-icon-tile size-11 shrink-0 rounded-full text-sm font-black">
                    {{ $initial }}
                </div>
            @endif
            <div class="min-w-0">
                <p class="truncate text-xl font-extrabold text-vault-primary">{{ $siteConfig['site_name'] }}</p>
            </div>
        </div>
        <a class="vault-icon-tile relative size-11 shrink-0 rounded-full transition" href="{{ route('notifications.index') }}" aria-label="{{ __('mobile.notifications.title') }}" data-notification-trigger data-notification-label="{{ __('mobile.notifications.title') }}">
            @include('mobile.partials.icon', ['name' => 'bell', 'class' => 'size-6'])
            <span class="pointer-events-none absolute -right-1 -top-1 z-30 hidden min-w-5 rounded-full bg-vault-warning px-1.5 py-0.5 text-center text-[10px] font-black leading-none text-vault-bg-deep ring-2 ring-vault-bg" data-notification-badge></span>
        </a>
    </header>

    <div class="min-h-0 overflow-y-auto overscroll-contain" data-mobile-scroll data-page-content>
        @include('layouts.partials.feedback')

        {{ $slot }}
    </div>

    <div class="relative z-20 shrink-0" data-notification-status-url="{{ route('notifications.status') }}" data-notification-refresh="10000">
        <div class="sr-only">
            <span>{{ __('mobile.nav.home') }}</span>
            <span>{{ __('mobile.nav.profile') }}</span>
        </div>
        <native:bottom-nav label-visibility="labeled" dark>
            <native:bottom-nav-item id="home" icon="home" label="{{ __('mobile.nav.home') }}" url="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')" />
            <native:bottom-nav-item id="profile" icon="person" label="{{ __('mobile.nav.profile') }}" url="{{ route('profile.edit') }}" :active="request()->routeIs('profile.*') || request()->routeIs('settings.*')" />
        </native:bottom-nav>
    </div>
</section>
