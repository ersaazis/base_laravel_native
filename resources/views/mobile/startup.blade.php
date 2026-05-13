<x-layouts.app :title="$siteConfig['site_name']" :site-config="$siteConfig">
    <div class="flex min-h-full flex-1 flex-col items-center justify-center gap-8 px-8 text-center" data-startup-screen data-mobile-animate>
        <div class="grid justify-items-center gap-5">
            <img
                class="size-40 rounded-3xl object-contain p-2"
                src="{{ $siteConfig['logo_url'] ?: asset('native-logo.png') }}"
                alt="{{ $siteConfig['site_name'] }}"
            >

            <h1 class="vault-title text-3xl">{{ $siteConfig['site_name'] }}</h1>
        </div>

        <div>
            <form
                method="GET"
                action="{{ route('startup.check') }}"
                data-startup-check
                data-startup-check-url="{{ route('startup.check') }}"
                data-startup-check-delay="120"
                data-startup-fallback-delay="450"
            ></form>
            <noscript>
                <meta http-equiv="refresh" content="0;url={{ route('startup.check') }}">
                <a class="vault-btn vault-btn-primary" href="{{ route('startup.check') }}">
                    {{ __('mobile.common.continue') }}
                </a>
            </noscript>
        </div>
    </div>

    <script>
        const fallbackDelay = Number(document.querySelector('[data-startup-check]')?.dataset.startupFallbackDelay || 450);

        window.setTimeout(() => {
            const startupForm = document.querySelector('[data-startup-check]');

            if (!(startupForm instanceof HTMLFormElement) || startupForm.dataset.autoSubmitted === 'true') {
                return;
            }

            startupForm.dataset.autoSubmitted = 'true';
            window.location.replace(startupForm.dataset.startupCheckUrl || startupForm.action);
        }, fallbackDelay);
    </script>
</x-layouts.app>
