<x-layouts.app :title="__('mobile.profile.title')">
    <div class="grid gap-6">
        @include('mobile.profile.partials.header', ['profile' => $profile, 'role' => $role])
        @include('mobile.profile.partials.tabs', ['active' => 'profile'])

        <section class="vault-card grid gap-1 rounded-3xl" data-mobile-animate>
            <div class="flex min-h-16 items-center justify-between gap-4 px-5 py-4">
                <div>
                    <p class="font-extrabold text-vault-text">{{ __('mobile.common.language') }}</p>
                    <p class="mt-1 text-sm vault-dim">{{ __('mobile.profile.language_subtitle') }}</p>
                </div>
                @include('mobile.partials.language-selector', [
                    'action' => route('profile.language.update'),
                    'languages' => $languages,
                    'activeLocale' => $activeLocale,
                    'compact' => true,
                ])
            </div>
        </section>

        <section class="grid gap-5" data-mobile-animate>
            <h2 class="text-2xl font-black text-vault-text">{{ __('mobile.profile.edit') }}</h2>
            <form class="grid gap-5" method="POST" action="{{ route('profile.update') }}">
                @csrf
                @include('mobile.partials.field', ['name' => 'name', 'label' => __('mobile.profile.name'), 'value' => $profile['name'] ?? '', 'autocomplete' => 'name', 'required' => true, 'icon' => 'user'])
                @include('mobile.partials.field', ['name' => 'email', 'label' => __('mobile.common.email'), 'type' => 'email', 'value' => $profile['email'] ?? '', 'autocomplete' => 'email', 'required' => true, 'icon' => 'mail'])

                <button class="vault-btn vault-btn-primary w-full" type="submit">
                    {{ __('mobile.profile.save') }}
                </button>
            </form>
        </section>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="vault-btn vault-btn-secondary w-full" type="submit">
                {{ __('mobile.common.logout') }}
            </button>
        </form>
    </div>
</x-layouts.app>
