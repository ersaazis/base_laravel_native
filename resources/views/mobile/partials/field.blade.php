@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'autocomplete' => null,
    'required' => false,
    'placeholder' => null,
    'inputmode' => null,
    'icon' => null,
    'actionLabel' => null,
    'actionHref' => null,
    'togglePassword' => false,
])

@php
    $hasError = $errors->has($name);
@endphp

<label class="grid gap-2">
    <span class="flex items-center justify-between gap-3">
        <span class="vault-label {{ $hasError ? 'text-vault-danger' : '' }}">{{ $label }}</span>
        @if ($actionLabel && $actionHref)
            <a class="min-h-8 rounded-lg px-1 text-sm font-bold text-vault-primary underline-offset-4 hover:underline" href="{{ $actionHref }}">
                {{ $actionLabel }}
            </a>
        @endif
    </span>
    <span class="relative block">
        @if ($icon)
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-vault-muted">
                @include('mobile.partials.icon', ['name' => $icon, 'class' => 'size-5'])
            </span>
        @endif
    <input
        class="vault-input {{ $icon ? 'pl-11' : 'px-4' }} {{ $togglePassword ? 'pr-11' : 'pr-4' }}"
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($required) required @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($inputmode) inputmode="{{ $inputmode }}" @endif
    >
        @if ($togglePassword)
            <button class="absolute right-2 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-lg text-vault-muted transition hover:text-vault-text" type="button" data-password-toggle="{{ $name }}" aria-label="{{ __('mobile.common.toggle_password') }}" aria-pressed="false">
                <span data-password-icon-show>
                    @include('mobile.partials.icon', ['name' => 'eye', 'class' => 'size-5'])
                </span>
                <span hidden data-password-icon-hide>
                    @include('mobile.partials.icon', ['name' => 'eye-off', 'class' => 'size-5'])
                </span>
            </button>
        @endif
    </span>
    @error($name)
        <span id="{{ $name }}-error" class="text-sm font-semibold text-vault-danger" role="alert">{{ $message }}</span>
    @enderror
</label>
