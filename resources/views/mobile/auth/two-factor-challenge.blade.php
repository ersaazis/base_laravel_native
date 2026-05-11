<x-layouts.app :title="__('mobile.auth.two_factor.title')">
    <x-slot:authFooter>
        <a class="vault-btn vault-btn-secondary w-full" href="{{ route('login') }}">
            {{ __('mobile.auth.two_factor.back') }}
        </a>
    </x-slot:authFooter>

    <section class="vault-card grid gap-6 rounded-3xl p-6" data-mobile-animate>
        <div class="grid gap-3">
            <div class="vault-icon-tile size-14 rounded-2xl">
                @include('mobile.partials.icon', ['name' => 'shield', 'class' => 'size-7'])
            </div>
            <div class="grid gap-2">
                <h2 class="vault-title text-2xl">{{ __('mobile.auth.two_factor.heading') }}</h2>
                <p class="text-base leading-7 vault-muted">{{ __('mobile.auth.two_factor.subtitle') }}</p>
            </div>
        </div>

        <form class="grid gap-5" method="POST" action="{{ route('two-factor.challenge.store') }}">
            @csrf
            @include('mobile.partials.field', ['name' => 'code', 'label' => __('mobile.auth.two_factor.code'), 'autocomplete' => 'one-time-code', 'placeholder' => '123456', 'inputmode' => 'numeric', 'icon' => 'code'])

            <details class="rounded-2xl border border-vault-border/60 bg-vault-bg-deep/40 p-4">
                <summary class="flex min-h-10 cursor-pointer list-none items-center justify-between gap-3 text-sm font-black text-vault-text">
                    {{ __('mobile.auth.two_factor.use_recovery_code') }}
                    @include('mobile.partials.icon', ['name' => 'chevron', 'class' => 'size-5'])
                </summary>
                <div class="mt-4 grid gap-3">
                    <p class="text-sm leading-6 vault-muted">{{ __('mobile.auth.two_factor.recovery_code_help') }}</p>
                    @include('mobile.partials.field', ['name' => 'recovery_code', 'label' => __('mobile.auth.two_factor.recovery_code'), 'autocomplete' => 'one-time-code', 'placeholder' => 'xxxx-xxxx', 'icon' => 'key'])
                </div>
            </details>

            <button class="vault-btn vault-btn-primary w-full" type="submit">
                {{ __('mobile.auth.two_factor.submit') }}
            </button>
        </form>
    </section>
</x-layouts.app>
