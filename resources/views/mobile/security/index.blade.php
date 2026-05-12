<x-layouts.app :title="__('mobile.security.title')">
    @php
        $nestedQr = is_array($qrCode['qr_code'] ?? null) ? $qrCode['qr_code'] : [];
        $nestedQrCode = is_array($qrCode['qrCode'] ?? null) ? $qrCode['qrCode'] : [];
        $nestedTwoFactor = is_array($qrCode['two_factor'] ?? null) ? $qrCode['two_factor'] : [];
        $nestedTwoFactorCamel = is_array($qrCode['twoFactor'] ?? null) ? $qrCode['twoFactor'] : [];
        $setupPayloads = [$qrCode, $nestedQr, $nestedQrCode, $nestedTwoFactor, $nestedTwoFactorCamel];
        $setupValue = static function (array $keys) use ($setupPayloads): ?string {
            foreach ($setupPayloads as $payload) {
                foreach ($keys as $key) {
                    $value = $payload[$key] ?? null;

                    if (is_string($value) && filled($value)) {
                        return $value;
                    }
                }
            }

            return null;
        };

        $qrSvg = $setupValue(['svg', 'qr_code_svg', 'qrCodeSvg', 'qr_code', 'qrCode']);
        $qrImageUrl = $setupValue(['qr_code_url', 'qrCodeUrl', 'image_url', 'imageUrl']);
        $genericUrl = $setupValue(['url']);
        if ((! is_string($qrImageUrl) || blank($qrImageUrl)) && is_string($genericUrl) && (str_starts_with($genericUrl, 'data:image') || str_starts_with($genericUrl, 'http'))) {
            $qrImageUrl = $genericUrl;
        }
        $qrImageUrl = is_string($qrImageUrl) && filled($qrImageUrl)
            ? $qrImageUrl
            : (is_string($qrSvg) && (str_starts_with($qrSvg, 'data:image') || str_starts_with($qrSvg, 'http')) ? $qrSvg : null);
        $setupUrl = $setupValue(['otpauth_url', 'otpauthUrl', 'setup_url', 'setupUrl', 'url']);
        $setupKey = $setupValue(['setup_key', 'setupKey', 'secret_key', 'secretKey', 'secret', 'key', 'manual_entry_key', 'manualEntryKey', 'manual_key', 'manualKey', 'shared_secret', 'sharedSecret']);
        if ((! is_string($setupKey) || blank($setupKey)) && is_string($setupUrl) && filled($setupUrl)) {
            parse_str(parse_url($setupUrl, PHP_URL_QUERY) ?: '', $setupQuery);
            $setupKey = is_string($setupQuery['secret'] ?? null) ? $setupQuery['secret'] : $setupKey;
        }
        $codes = array_is_list($recoveryCodes) ? $recoveryCodes : ($recoveryCodes['recovery_codes'] ?? $recoveryCodes['codes'] ?? []);
        $codes = is_array($codes) ? $codes : [];
    @endphp

    <div class="grid gap-6">
        @include('mobile.profile.partials.header', ['profile' => $profile, 'role' => $role])
        @include('mobile.profile.partials.tabs', ['active' => 'security'])

        <section class="grid gap-5" data-mobile-animate>
            <h2 class="text-2xl font-black text-vault-text">{{ __('mobile.security.update_password') }}</h2>
            <form class="mt-5 grid gap-5" method="POST" action="{{ route('security.password.update') }}">
                @csrf
                @include('mobile.partials.field', ['name' => 'current_password', 'label' => __('mobile.security.current_password'), 'type' => 'password', 'autocomplete' => 'current-password', 'required' => true, 'icon' => 'key', 'togglePassword' => true])
                @include('mobile.partials.field', ['name' => 'password', 'label' => __('mobile.security.new_password'), 'type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'icon' => 'lock', 'togglePassword' => true])
                @include('mobile.partials.field', ['name' => 'password_confirmation', 'label' => __('mobile.security.confirm_password'), 'type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'icon' => 'lock', 'togglePassword' => true])
                <button class="vault-btn vault-btn-primary w-full" type="submit">{{ __('mobile.security.update_password') }}</button>
            </form>
        </section>

        <section id="two-factor-setup" class="vault-card scroll-mt-6 rounded-3xl p-5" data-mobile-animate>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-vault-text">{{ __('mobile.security.authenticator_setup') }}</h2>
                    <p class="mt-1 text-sm leading-6 vault-muted">{{ __('mobile.security.authenticator_help') }}</p>
                </div>
                <form method="POST" action="{{ route('security.two-factor.enable') }}" data-spa-preserve-scroll>
                    @csrf
                    <button class="vault-btn vault-btn-primary min-h-11 px-3 text-xs" type="submit">
                        {{ $twoFactorEnabled ? __('mobile.security.refresh') : __('mobile.security.enable') }}
                    </button>
                </form>
            </div>

            @if ($setupRequested)
                @if (is_string($qrSvg) && str_contains($qrSvg, '<svg'))
                    <div class="two-factor-qr mt-5 flex min-h-64 items-center justify-center overflow-hidden rounded-2xl bg-white p-4">
                        {!! $qrSvg !!}
                    </div>
                @elseif (is_string($qrImageUrl) && filled($qrImageUrl))
                    <div class="mt-5 flex min-h-64 items-center justify-center overflow-hidden rounded-2xl bg-white p-4">
                        <img class="mx-auto size-56 object-contain" src="{{ $qrImageUrl }}" alt="{{ __('mobile.security.authenticator_setup') }}">
                    </div>
                @endif

                @if (is_string($setupKey) && filled($setupKey))
                    <div class="mt-5 rounded-2xl border border-vault-border/60 bg-vault-bg-deep/40 p-4">
                        <label class="grid gap-2">
                            <span class="text-xs font-extrabold uppercase text-vault-dim">{{ __('mobile.security.setup_key') }}</span>
                            <input class="vault-input px-3 font-mono text-sm" type="text" value="{{ $setupKey }}" readonly data-copy-source>
                            <button class="vault-btn vault-btn-primary w-full text-sm" type="button" data-copy-value="{{ $setupKey }}" data-copy-label="{{ __('mobile.security.copy_setup_key') }}" data-copied-label="{{ __('mobile.security.copied') }}">
                                @include('mobile.partials.icon', ['name' => 'copy', 'class' => 'size-4'])
                                {{ __('mobile.security.copy_setup_key') }}
                            </button>
                        </label>
                    </div>
                @endif

                <div class="mt-5 grid gap-3">
                    <form class="grid gap-5" method="POST" action="{{ route('security.two-factor.confirm') }}">
                        @csrf
                        @include('mobile.partials.field', ['name' => 'code', 'label' => __('mobile.security.code'), 'autocomplete' => 'one-time-code', 'placeholder' => '123456', 'inputmode' => 'numeric', 'icon' => 'code'])
                        <button class="vault-btn vault-btn-primary w-full" type="submit">{{ __('mobile.security.confirm_2fa') }}</button>
                    </form>

                    <form method="POST" action="{{ route('security.two-factor.cancel') }}" data-spa-preserve-scroll>
                        @csrf
                        <button class="vault-btn vault-btn-secondary w-full" type="submit">{{ __('mobile.common.cancel') }}</button>
                    </form>
                </div>
            @endif

            @if ($twoFactorEnabled)
                <details class="mt-6 border-t border-vault-border/60 pt-5">
                    <summary class="vault-btn vault-btn-danger flex cursor-pointer list-none justify-between text-sm">
                        {{ __('mobile.security.disable_2fa') }}
                        @include('mobile.partials.icon', ['name' => 'chevron', 'class' => 'size-5'])
                    </summary>
                    <form class="mt-5 grid gap-5" method="POST" action="{{ route('security.two-factor.disable') }}">
                        @csrf
                        @include('mobile.partials.field', ['name' => 'two_factor_password', 'label' => __('mobile.security.disable_password'), 'type' => 'password', 'autocomplete' => 'current-password', 'icon' => 'key', 'togglePassword' => true])
                        <button class="vault-btn vault-btn-danger w-full" type="submit">{{ __('mobile.security.disable_2fa') }}</button>
                    </form>
                </details>
            @endif
        </section>

        @if ($twoFactorEnabled)
        <section id="recovery-codes" class="vault-card scroll-mt-6 rounded-3xl p-5" data-mobile-animate>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-vault-text">{{ __('mobile.security.backup_access') }}</h2>
                    <p class="mt-1 text-sm vault-muted">{{ __('mobile.security.backup_help') }}</p>
                </div>
                <form method="POST" action="{{ route('security.two-factor.recovery-codes') }}" data-spa-preserve-scroll>
                    @csrf
                    <button class="vault-btn vault-btn-secondary min-h-11 px-3 text-xs" type="submit">{{ __('mobile.security.regenerate') }}</button>
                </form>
            </div>

            <div class="mt-5 grid gap-2">
                @forelse ($codes as $code)
                    <div class="rounded-xl border border-vault-border/60 bg-vault-bg-deep/40 px-4 py-3 font-mono text-sm text-vault-text">
                        {{ $code }}
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-vault-border/60 p-5 text-sm vault-dim">
                        {{ __('mobile.security.no_backup') }}
                    </div>
                @endforelse
            </div>
        </section>
        @endif
    </div>
</x-layouts.app>
