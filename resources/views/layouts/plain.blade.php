<section class="mobile-screen vault-pattern relative mx-auto flex h-[calc(100dvh-var(--inset-top,0px)-var(--inset-bottom,0px))] w-full max-w-md flex-col overflow-hidden px-5 pb-5 pt-4" data-page-shell>
    @if ($isAuthenticated)
        <header class="z-20 mb-5 flex shrink-0 items-center justify-between gap-4 border-b border-vault-border/40 bg-vault-bg/85 pb-3 backdrop-blur-xl">
            <div class="flex min-w-0 items-center gap-3">
                @if ($siteConfig['logo_url'])
                    <img class="size-10 shrink-0 rounded-full object-cover ring-1 ring-vault-border" src="{{ $siteConfig['logo_url'] }}" alt="{{ $siteConfig['site_name'] }}">
                @else
                    <div class="vault-icon-tile size-10 shrink-0 rounded-full">
                        @include('mobile.partials.icon', ['name' => 'shield', 'class' => 'size-5'])
                    </div>
                @endif
                <p class="truncate text-lg font-extrabold text-vault-primary">{{ $siteConfig['site_name'] }}</p>
            </div>
        </header>
    @endif

    @include('layouts.partials.feedback')

    {{ $slot }}
</section>
