<x-layouts.app :title="__('mobile.unlock.title')">
    <div class="flex min-h-[calc(100vh-8rem)] flex-col justify-between gap-8 text-center" data-mobile-animate>
        <div class="grid justify-items-center gap-8 pt-20">
            <div class="biometric-ring">
                @include('mobile.partials.icon', ['name' => 'fingerprint', 'class' => 'size-20'])
            </div>
            <div class="grid gap-2">
                <h2 class="vault-title text-3xl">{{ __('mobile.unlock.heading') }}</h2>
                <p class="mx-auto max-w-80 text-base leading-7 vault-muted">{{ __('mobile.unlock.subtitle') }}</p>
            </div>
        </div>

        <div class="grid gap-3">
            <form method="POST" action="{{ route('settings.unlock.store') }}" data-biometric-form data-biometric-auto-submit>
                @csrf
                <input type="hidden" name="biometric_verified" value="0" data-biometric-verified>
                <button class="vault-btn vault-btn-primary w-full" type="submit">
                    @include('mobile.partials.icon', ['name' => 'fingerprint', 'class' => 'size-5'])
                    {{ __('mobile.unlock.submit') }}
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="vault-btn vault-btn-secondary w-full" type="submit">
                    {{ __('mobile.common.logout') }}
                </button>
            </form>
        </div>
    </div>
</x-layouts.app>
