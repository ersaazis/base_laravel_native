<x-layouts.app :title="__('mobile.auth.signup.title')">
    <x-slot:authFooter>
        <a class="vault-btn vault-btn-secondary w-full" href="{{ route('login') }}">{{ __('mobile.auth.signup.login') }}</a>
    </x-slot:authFooter>

    <section class="vault-card grid gap-6 rounded-3xl p-6" data-mobile-animate>
        <div class="grid gap-2">
            <h2 class="vault-title text-2xl">{{ __('mobile.auth.signup.heading') }}</h2>
            <p class="text-base leading-7 vault-muted">{{ __('mobile.auth.signup.subtitle') }}</p>
        </div>

        <form class="grid gap-5" method="POST" action="{{ route('signup.store') }}">
            @csrf
            @include('mobile.partials.field', ['name' => 'name', 'label' => __('mobile.auth.signup.name'), 'autocomplete' => 'name', 'placeholder' => __('mobile.auth.signup.name_placeholder'), 'required' => true, 'icon' => 'user'])
            @include('mobile.partials.field', ['name' => 'email', 'label' => __('mobile.common.email'), 'type' => 'email', 'autocomplete' => 'email', 'placeholder' => 'name@email.com', 'required' => true, 'icon' => 'mail'])
            @include('mobile.partials.field', ['name' => 'password', 'label' => __('mobile.common.password'), 'type' => 'password', 'autocomplete' => 'new-password', 'placeholder' => __('mobile.auth.signup.password_placeholder'), 'required' => true, 'icon' => 'key', 'togglePassword' => true])
            @include('mobile.partials.field', ['name' => 'password_confirmation', 'label' => __('mobile.auth.signup.confirm'), 'type' => 'password', 'autocomplete' => 'new-password', 'placeholder' => __('mobile.auth.signup.confirm_placeholder'), 'required' => true, 'icon' => 'lock', 'togglePassword' => true])

            <button class="vault-btn vault-btn-primary w-full" type="submit">
                {{ __('mobile.auth.signup.submit') }}
            </button>
        </form>
    </section>
</x-layouts.app>
