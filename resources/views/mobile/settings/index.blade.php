<x-layouts.app :title="__('mobile.settings.title')">
    <div class="grid gap-5">
        <section class="vault-card rounded-3xl p-5" data-mobile-animate>
            <div class="flex items-start justify-between gap-4">
                <div>
            <p class="text-sm font-bold vault-muted">{{ __('mobile.settings.local_security') }}</p>
            <h1 class="mt-2 text-2xl font-black text-vault-text">{{ $biometricsEnabled ? __('mobile.settings.biometrics_active') : __('mobile.settings.biometrics_off') }}</h1>
                </div>
                <div class="vault-icon-tile size-12 rounded-full">
                    @include('mobile.partials.icon', ['name' => 'fingerprint', 'class' => 'size-7'])
                </div>
            </div>
            <p class="mt-3 text-sm leading-6 vault-muted">
                {{ $biometricsEnabled ? __('mobile.settings.biometrics_active_help') : __('mobile.settings.biometrics_off_help') }}
            </p>
        </section>

        <section class="grid gap-3">
            @if ($biometricsEnabled)
                <form method="POST" action="{{ route('settings.lock') }}">
                    @csrf
                    <button class="vault-card-muted flex min-h-16 w-full items-center justify-between gap-4 rounded-2xl p-4 text-left" type="submit">
                        <span>
                            <span class="block font-extrabold text-vault-text">{{ __('mobile.settings.lock_app') }}</span>
                            <span class="mt-1 block text-sm vault-muted">{{ __('mobile.settings.lock_help') }}</span>
                        </span>
                        @include('mobile.partials.icon', ['name' => 'chevron', 'class' => 'size-5 text-vault-dim'])
                    </button>
                </form>

                <form method="POST" action="{{ route('settings.biometrics.disable') }}" data-biometric-form>
                    @csrf
                    <input type="hidden" name="biometric_verified" value="0" data-biometric-verified>
                    <button class="flex min-h-16 w-full items-center justify-between gap-4 rounded-2xl border border-vault-danger/35 bg-vault-danger/10 p-4 text-left text-vault-danger" type="submit">
                        <span>
                            <span class="block font-extrabold">{{ __('mobile.settings.disable_biometrics') }}</span>
                            <span class="mt-1 block text-sm">{{ __('mobile.settings.disable_help') }}</span>
                        </span>
                        @include('mobile.partials.icon', ['name' => 'chevron', 'class' => 'size-5'])
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('settings.biometrics.enable') }}" data-biometric-form>
                    @csrf
                    <input type="hidden" name="biometric_verified" value="0" data-biometric-verified>
                    <button class="vault-btn vault-btn-primary flex min-h-16 w-full justify-between p-4 text-left" type="submit">
                        <span>
                            <span class="block font-bold">{{ __('mobile.settings.enable_biometrics') }}</span>
                            <span class="mt-1 block text-sm opacity-80">{{ __('mobile.settings.enable_help') }}</span>
                        </span>
                        @include('mobile.partials.icon', ['name' => 'fingerprint', 'class' => 'size-6'])
                    </button>
                </form>
            @endif
        </section>

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
