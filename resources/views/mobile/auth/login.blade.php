<x-layouts.app :title="__('mobile.auth.login.title')" :site-config="$siteConfig">
    <x-slot:authFooter>
        <div class="grid gap-3 text-center text-sm">
            @if ($registrationEnabled ?? false)
                <a class="vault-btn vault-btn-secondary w-full" href="{{ route('signup') }}">{{ __('mobile.auth.login.signup') }}</a>
            @endif
        </div>
    </x-slot:authFooter>

    <section class="vault-card grid gap-6 rounded-3xl p-6" data-mobile-animate>
        <div class="grid gap-2">
            <h2 class="vault-title text-2xl">{{ __('mobile.auth.login.heading') }}</h2>
            <p class="text-base leading-7 vault-muted">{{ __('mobile.auth.login.subtitle') }}</p>
        </div>

        <form class="grid gap-5" method="POST" action="{{ route('login.store') }}">
            @csrf
            @include('mobile.partials.field', ['name' => 'email', 'label' => __('mobile.common.email'), 'type' => 'email', 'autocomplete' => 'email', 'placeholder' => 'name@email.com', 'required' => true, 'icon' => 'mail'])
            @include('mobile.partials.field', ['name' => 'password', 'label' => __('mobile.common.password'), 'type' => 'password', 'autocomplete' => 'current-password', 'placeholder' => __('mobile.auth.login.password_placeholder'), 'required' => true, 'icon' => 'key', 'actionLabel' => __('mobile.auth.login.forgot'), 'actionHref' => route('password.forgot'), 'togglePassword' => true])

            <button class="vault-btn vault-btn-primary mt-1 w-full" type="submit">
                <span>{{ __('mobile.auth.login.submit') }}</span>
                @include('mobile.partials.icon', ['name' => 'arrow-right', 'class' => 'size-5'])
            </button>
        </form>
    </section>
</x-layouts.app>
