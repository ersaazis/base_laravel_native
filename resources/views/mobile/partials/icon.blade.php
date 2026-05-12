@props([
    'name',
    'class' => 'size-5',
])

@php
    $stroke = 'round';
    $class = $class ?? 'size-5';
@endphp

<svg class="{{ $class }}" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="{{ $stroke }}" stroke-linejoin="{{ $stroke }}">
    @switch($name)
        @case('activity')
            <path d="M4 12h4l2.5-6 3 12L16 12h4" />
            @break

        @case('alert')
            <path d="M12 9v4" />
            <path d="M12 17h.01" />
            <path d="M10.3 3.9 2.4 17.6A1.6 1.6 0 0 0 3.8 20h16.4a1.6 1.6 0 0 0 1.4-2.4L13.7 3.9a1.6 1.6 0 0 0-2.8 0Z" />
            @break

        @case('arrow-right')
            <path d="M5 12h14" />
            <path d="m13 6 6 6-6 6" />
            @break

        @case('bell')
            <path d="M6 9a6 6 0 0 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9" />
            <path d="M10 21h4" />
            @break

        @case('block')
            <circle cx="12" cy="12" r="8.5" />
            <path d="m6 6 12 12" />
            @break

        @case('check-shield')
            <path d="M12 3 5 6v5.5c0 4.1 2.8 7.7 7 9.5 4.2-1.8 7-5.4 7-9.5V6l-7-3Z" />
            <path d="m9 12 2 2 4-5" />
            @break

        @case('chevron')
            <path d="m9 6 6 6-6 6" />
            @break

        @case('code')
            <path d="m8 9-3 3 3 3" />
            <path d="m16 9 3 3-3 3" />
            <path d="m14 5-4 14" />
            @break

        @case('copy')
            <rect width="12" height="12" x="8" y="8" rx="2" />
            <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" />
            @break

        @case('devices')
            <rect width="11" height="8" x="3" y="5" rx="1.5" />
            <rect width="6" height="10" x="15" y="9" rx="1.5" />
            <path d="M7 17h6" />
            @break

        @case('eye')
            <path d="M2.5 10.1a2 2 0 0 0 0 2C3.7 14.1 7 18 12 18s8.3-3.9 9.5-5.9a2 2 0 0 0 0-2C20.3 8.1 17 4 12 4S3.7 8.1 2.5 10.1Z" />
            <circle cx="12" cy="11" r="3" />
            @break

        @case('eye-off')
            <path d="M3 3l18 18" />
            <path d="M10.6 10.6A2 2 0 0 0 13.4 13.4" />
            <path d="M9.9 4.5A10.7 10.7 0 0 1 12 4c5 0 8.3 4.1 9.5 6.1a2 2 0 0 1 0 2c-.5.9-1.4 2.1-2.7 3.3" />
            <path d="M6.6 6.6A15.4 15.4 0 0 0 2.5 10.1a2 2 0 0 0 0 2C3.7 14.1 7 18 12 18c1.1 0 2.1-.2 3-.6" />
            @break

        @case('gear')
            <path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z" />
            <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2 3.4-.2-.1a1.7 1.7 0 0 0-1.9.2 1.7 1.7 0 0 0-.8 1.6v.2H11v-.2a1.7 1.7 0 0 0-.9-1.6 1.7 1.7 0 0 0-1.8-.2l-.2.1-2-3.4.1-.1A1.7 1.7 0 0 0 6.6 15 1.7 1.7 0 0 0 5 14H4.8v-4H5a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 2-3.4.2.1a1.7 1.7 0 0 0 1.8-.2A1.7 1.7 0 0 0 11 1.9v-.2h4v.2a1.7 1.7 0 0 0 .8 1.6 1.7 1.7 0 0 0 1.9.2l.2-.1 2 3.4-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4h-.2a1.7 1.7 0 0 0-1.7 1Z" />
            @break

        @case('globe')
            <circle cx="12" cy="12" r="9" />
            <path d="M3 12h18" />
            <path d="M12 3c2.2 2.5 3.3 5.5 3.3 9S14.2 18.5 12 21c-2.2-2.5-3.3-5.5-3.3-9S9.8 5.5 12 3Z" />
            @break

        @case('home')
            <path d="M3.5 11.5 12 4l8.5 7.5" />
            <path d="M5.5 10.5V20h5v-5.5h3V20h5v-9.5" />
            @break

        @case('key')
            <circle cx="7.5" cy="12.5" r="3.5" />
            <path d="M11 12.5h9" />
            <path d="M17 12.5v3" />
            <path d="M14 12.5v2" />
            @break

        @case('lock')
            <rect width="14" height="10" x="5" y="11" rx="2" />
            <path d="M8 11V8a4 4 0 1 1 8 0v3" />
            @break

        @case('login')
            <path d="M14 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
            <path d="M10 17l5-5-5-5" />
            <path d="M15 12H3" />
            @break

        @case('mail')
            <rect width="18" height="14" x="3" y="5" rx="2" />
            <path d="m3 7 9 6 9-6" />
            @break

        @case('qr')
            <path d="M4 4h6v6H4z" />
            <path d="M14 4h6v6h-6z" />
            <path d="M4 14h6v6H4z" />
            <path d="M14 14h2v2h-2z" />
            <path d="M18 14h2v6h-4v-2" />
            @break

        @case('shield')
            <path d="M12 3 5 6v5.5c0 4.1 2.8 7.7 7 9.5 4.2-1.8 7-5.4 7-9.5V6l-7-3Z" />
            @break

        @case('swap')
            <path d="M7 7h11" />
            <path d="m15 4 3 3-3 3" />
            <path d="M17 17H6" />
            <path d="m9 14-3 3 3 3" />
            @break

        @case('user')
            <circle cx="12" cy="8" r="4" />
            <path d="M4.5 21a7.5 7.5 0 0 1 15 0" />
            @break

        @case('wallet')
            <path d="M4 7h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h11" />
            <path d="M16 12h5v4h-5a2 2 0 0 1 0-4Z" />
            @break

        @case('x')
            <path d="M6 6l12 12" />
            <path d="M18 6 6 18" />
            @break

        @default
            <circle cx="12" cy="12" r="9" />
    @endswitch
</svg>
