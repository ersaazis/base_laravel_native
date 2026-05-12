<x-layouts.app :title="__('mobile.settings.title')">
    <div class="grid gap-5">
        <section class="vault-card-muted rounded-2xl p-5" data-mobile-animate>
            <p class="text-xs font-extrabold uppercase text-vault-dim">{{ __('mobile.settings.developer') }}</p>
            <p class="mt-3 text-sm vault-muted">{{ __('mobile.settings.api_base_url') }}</p>
            <p class="mt-1 break-all text-sm font-extrabold text-vault-text">{{ $baseUrl }}</p>
        </section>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="vault-btn vault-btn-secondary w-full" type="submit">
                {{ __('mobile.common.logout') }}
            </button>
        </form>
    </div>
</x-layouts.app>
