<x-layouts.app :title="__('mobile.auth.forgot.title')">
    <x-slot:authFooter>
        <a class="vault-btn vault-btn-secondary w-full" href="{{ route('login') }}">{{ __('mobile.auth.forgot.back') }}</a>
    </x-slot:authFooter>

    <section class="vault-card grid gap-6 rounded-3xl p-6" data-mobile-animate>
        <div class="grid gap-2">
            <h2 class="vault-title text-2xl">{{ __('mobile.auth.forgot.heading') }}</h2>
            <p class="text-base leading-7 vault-muted">{{ __('mobile.auth.forgot.subtitle') }}</p>
        </div>

        <form class="grid gap-5" method="POST" action="{{ route('password.email') }}">
            @csrf
            @include('mobile.partials.field', ['name' => 'email', 'label' => __('mobile.common.email'), 'type' => 'email', 'autocomplete' => 'email', 'placeholder' => 'name@email.com', 'required' => true, 'icon' => 'mail'])

            <button class="vault-btn vault-btn-primary w-full" type="submit">
                {{ __('mobile.auth.forgot.submit') }}
            </button>
        </form>
    </section>
</x-layouts.app>
