<x-layouts.app :title="__('mobile.home.title')">
    @php
        $displayName = $profile['name'] ?? __('mobile.common.user');
        $roleName = $role['name'] ?? __('mobile.common.no_role');
    @endphp

    <div class="grid gap-7 py-1">
        <section class="vault-card relative overflow-hidden rounded-3xl p-6" data-mobile-animate>
            <div class="absolute right-5 top-5 opacity-10">
                @include('mobile.partials.icon', ['name' => 'wallet', 'class' => 'size-20'])
            </div>
            <p class="text-xs font-extrabold uppercase text-vault-muted">{{ __('mobile.home.account_overview') }}</p>
            <h1 class="mt-3 truncate text-4xl font-black text-vault-text">{{ $displayName }}</h1>
            <p class="mt-2 truncate text-base font-bold text-vault-primary">{{ $roleName }}</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <span class="vault-chip">
                    @include('mobile.partials.icon', ['name' => 'check-shield', 'class' => 'size-4'])
                    {{ __('mobile.home.verified') }}
                </span>
            </div>
        </section>
    </div>
</x-layouts.app>
