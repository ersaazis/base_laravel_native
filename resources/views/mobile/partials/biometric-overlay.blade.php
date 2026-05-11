<div class="biometric-overlay" data-biometric-overlay hidden aria-live="polite" aria-modal="true" role="dialog" aria-labelledby="biometric-overlay-title">
    <div class="grid h-full w-full max-w-md grid-rows-[1fr_auto]">
        <div class="grid place-items-center">
            <div class="grid justify-items-center gap-8 text-center">
                <div class="biometric-ring">
                    @include('mobile.partials.icon', ['name' => 'fingerprint', 'class' => 'size-20'])
                </div>

                <div class="grid gap-3">
                    <h2 id="biometric-overlay-title" class="vault-title text-3xl">
                        {{ __('mobile.biometrics.verifying') }}
                    </h2>
                    <p class="mx-auto max-w-72 text-base leading-7 vault-muted">
                        {{ __('mobile.biometrics.keep_sensor') }}
                    </p>
                </div>
            </div>
        </div>

        <button class="vault-btn vault-btn-secondary mx-auto min-w-32" type="button" data-biometric-cancel>
            {{ __('mobile.common.cancel') }}
        </button>
    </div>
</div>
