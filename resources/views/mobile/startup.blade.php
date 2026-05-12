<x-layouts.app :title="$siteConfig['site_name']" :site-config="$siteConfig">
    <div class="flex min-h-full flex-1 flex-col items-center justify-center gap-8 text-center" data-startup-screen data-mobile-animate>
        <div class="grid justify-items-center gap-5">
            @if ($siteConfig['logo_url'])
                <img class="size-24 rounded-3xl object-cover ring-1 ring-vault-border" src="{{ $siteConfig['logo_url'] }}" alt="{{ $siteConfig['site_name'] }}">
            @else
                <div class="vault-icon-tile size-24 rounded-3xl">
                    @include('mobile.partials.icon', ['name' => 'shield', 'class' => 'size-12'])
                </div>
            @endif

            <div class="grid gap-2">
                <h1 class="vault-title text-3xl">{{ $siteConfig['site_name'] }}</h1>
                <p class="text-base font-medium vault-muted">{{ __('mobile.startup.checking') }}</p>
            </div>
        </div>

        <div class="grid justify-items-center gap-4">
            <div class="size-8 animate-spin rounded-full border-2 border-vault-primary/15 border-t-vault-primary"></div>
            <form method="GET" action="{{ route('startup.check') }}" data-startup-check data-startup-check-url="{{ route('startup.check') }}"></form>
            <noscript>
                <meta http-equiv="refresh" content="0;url={{ route('startup.check') }}">
                <a class="vault-btn vault-btn-primary" href="{{ route('startup.check') }}">
                    {{ __('mobile.common.continue') }}
                </a>
            </noscript>
        </div>
    </div>

    <script>
        window.setTimeout(() => {
            const startupForm = document.querySelector('[data-startup-check]');

            if (!(startupForm instanceof HTMLFormElement) || startupForm.dataset.autoSubmitted === 'true') {
                return;
            }

            startupForm.dataset.autoSubmitted = 'true';
            window.location.replace(startupForm.dataset.startupCheckUrl || startupForm.action);
        }, 1600);
    </script>
</x-layouts.app>
