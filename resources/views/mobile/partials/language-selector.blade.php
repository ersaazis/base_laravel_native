@props([
    'action',
    'languages',
    'activeLocale',
    'compact' => false,
])

<form class="flex items-center justify-center gap-2 text-center" method="POST" action="{{ $action }}">
    @csrf
    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
    <label class="sr-only" for="locale">{{ __('mobile.common.language') }}</label>
    @if (! $compact)
        <span class="text-vault-dim">
            @include('mobile.partials.icon', ['name' => 'globe', 'class' => 'size-5'])
        </span>
    @endif
    <select
        class="{{ $compact ? 'min-h-10 rounded-full px-3 text-xs' : 'min-h-12 rounded-xl px-4 text-sm' }} border border-vault-border/60 bg-vault-card-high/50 font-bold text-vault-text outline-none"
        id="locale"
        name="locale"
        onchange="this.form.requestSubmit()"
    >
        @foreach ($languages as $language)
            <option class="bg-vault-bg text-vault-text" value="{{ $language['locale'] }}" @selected($activeLocale === $language['locale'])>
                {{ $language['native_name'] }}
            </option>
        @endforeach
    </select>
</form>
