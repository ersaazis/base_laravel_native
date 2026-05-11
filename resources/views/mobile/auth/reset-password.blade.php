<x-layouts.app :title="__('mobile.auth.reset.title')">
    <section class="vault-card grid gap-6 rounded-3xl p-6" data-mobile-animate>
        <div class="grid gap-2">
            <h2 class="vault-title text-2xl">{{ __('mobile.auth.reset.heading') }}</h2>
            <p class="text-base leading-7 vault-muted">{{ __('mobile.auth.reset.subtitle') }}</p>
        </div>

        <form class="grid gap-5" method="POST" action="{{ route('password.update') }}">
            @csrf
            @include('mobile.partials.field', ['name' => 'token', 'label' => __('mobile.auth.reset.token'), 'placeholder' => __('mobile.auth.reset.token_placeholder'), 'required' => true, 'icon' => 'code'])
            @include('mobile.partials.field', ['name' => 'email', 'label' => __('mobile.common.email'), 'type' => 'email', 'autocomplete' => 'email', 'placeholder' => 'name@email.com', 'required' => true, 'icon' => 'mail'])
            @include('mobile.partials.field', ['name' => 'password', 'label' => __('mobile.auth.reset.password'), 'type' => 'password', 'autocomplete' => 'new-password', 'placeholder' => __('mobile.auth.signup.password_placeholder'), 'required' => true, 'icon' => 'key', 'togglePassword' => true])
            @include('mobile.partials.field', ['name' => 'password_confirmation', 'label' => __('mobile.auth.signup.confirm'), 'type' => 'password', 'autocomplete' => 'new-password', 'placeholder' => __('mobile.auth.signup.confirm_placeholder'), 'required' => true, 'icon' => 'lock', 'togglePassword' => true])

            <button class="vault-btn vault-btn-primary w-full" type="submit">
                {{ __('mobile.auth.reset.submit') }}
            </button>
        </form>
    </section>
</x-layouts.app>
