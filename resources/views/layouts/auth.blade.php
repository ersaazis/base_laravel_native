<section class="mobile-screen vault-pattern mx-auto grid h-[calc(100dvh-var(--inset-top,0px)-var(--inset-bottom,0px))] w-full max-w-md grid-rows-[minmax(0,1fr)] overflow-hidden px-5 pt-5" data-auth-shell data-page-shell>
    <div class="min-h-0 overflow-y-auto overscroll-contain py-6" data-page-content>
        <div class="grid min-h-full w-full content-center gap-8">
            <header class="grid justify-items-center gap-4 text-center" data-mobile-animate>
            @if ($siteConfig['logo_url'])
                <img class="size-16 rounded-2xl object-cover ring-1 ring-vault-border" src="{{ $siteConfig['logo_url'] }}" alt="{{ $siteConfig['site_name'] }}">
            @else
                <div class="vault-icon-tile size-16 rounded-2xl">
                    @include('mobile.partials.icon', ['name' => 'shield', 'class' => 'size-9'])
                </div>
            @endif
                <div class="grid gap-2">
                    <h1 class="vault-title text-4xl">{{ $siteConfig['site_name'] }}</h1>
                </div>
            </header>

            @include('layouts.partials.feedback')

            {{ $slot }}

            <div class="grid gap-5 text-center" data-mobile-animate>
                @isset($authFooter)
                    <div class="w-full">
                        {{ $authFooter }}
                    </div>
                @endisset
                @include('mobile.partials.language-selector', [
                    'action' => route('language.update'),
                    'languages' => app(\App\Services\MobileCredentialStore::class)->enabledLanguages(),
                    'activeLocale' => app(\App\Services\MobileCredentialStore::class)->activeLocale(),
                    'compact' => true,
                ])
            </div>
        </div>
    </div>
</section>
