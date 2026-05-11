@props(['active' => 'profile'])

<nav class="grid grid-cols-2 rounded-2xl border border-vault-border/60 bg-vault-surface/70 p-1 text-center text-sm font-bold" data-mobile-animate>
    <a class="min-h-11 rounded-xl px-4 py-3 {{ $active === 'profile' ? 'bg-vault-primary text-vault-bg-deep shadow-[0_0_18px_rgba(255,255,255,0.14)]' : 'text-vault-muted' }}" href="{{ route('profile.edit') }}">
        {{ __('mobile.profile.tab_profile') }}
    </a>
    <a class="min-h-11 rounded-xl px-4 py-3 {{ $active === 'security' ? 'bg-vault-primary text-vault-bg-deep shadow-[0_0_18px_rgba(255,255,255,0.14)]' : 'text-vault-muted' }}" href="{{ route('security.index') }}">
        {{ __('mobile.profile.tab_security') }}
    </a>
</nav>
