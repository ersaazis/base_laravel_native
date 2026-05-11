@php
    $displayName = $profile['name'] ?? __('mobile.common.user');
    $initial = mb_substr((string) $displayName, 0, 1);
@endphp

<section class="vault-card grid justify-items-center gap-3 rounded-3xl p-5 text-center" data-mobile-animate>
    <div class="vault-icon-tile size-20 rounded-full text-3xl font-black">{{ $initial }}</div>
    <div class="min-w-0">
        <h1 class="truncate text-2xl font-extrabold text-vault-text">{{ $displayName }}</h1>
        <p class="mt-1 truncate text-sm vault-dim">{{ $profile['email'] ?? __('mobile.profile.email_missing') }}</p>
        <p class="mx-auto mt-3 w-fit vault-chip">{{ $role['name'] ?? __('mobile.common.no_role') }}</p>
    </div>
</section>
