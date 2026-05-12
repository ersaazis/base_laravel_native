<x-layouts.app :title="__('mobile.notifications.title')">
    <div class="grid gap-5" data-notifications-list>
        <div class="flex items-center justify-between gap-3" data-mobile-animate>
            <div>
                <h1 class="text-3xl font-black text-vault-text">{{ __('mobile.notifications.title') }}</h1>
            </div>
        </div>

        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="vault-btn vault-btn-primary w-full" type="submit">
                {{ __('mobile.notifications.mark_all') }}
            </button>
        </form>

        @forelse ($notifications as $notification)
            @php
                $level = $notification['level_key'] ?? 'info';
                $icon = [
                    'success' => 'check-shield',
                    'warning' => 'alert',
                    'error' => 'block',
                ][$level] ?? 'bell';
            @endphp
            <article class="vault-card rounded-3xl p-4" data-mobile-animate>
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 gap-3">
                        <span class="vault-icon-tile size-11 shrink-0 rounded-full {{ $level === 'error' ? 'text-vault-danger' : '' }}">
                            @include('mobile.partials.icon', ['name' => $icon, 'class' => 'size-5'])
                        </span>
                        <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            @if (! ($notification['read'] ?? false))
                                <span class="size-2 rounded-full bg-vault-primary"></span>
                            @endif
                            <p class="truncate font-extrabold text-vault-text">{{ $notification['title'] ?? __('mobile.notifications.fallback_title') }}</p>
                        </div>
                        <span class="mt-2 vault-chip">
                            {{ __("mobile.notifications.levels.{$level}") }}
                        </span>
                        <p class="mt-2 text-sm leading-6 text-vault-muted">{{ $notification['message'] ?? '' }}</p>
                        <p class="mt-2 text-xs font-bold vault-dim">{{ $notification['created_at_human'] ?? $notification['created_at'] ?? '' }}</p>
                        </div>
                    </div>
                    @if (! ($notification['read'] ?? false) && isset($notification['id']))
                        <form method="POST" action="{{ route('notifications.read', $notification['id']) }}">
                            @csrf
                            <button class="vault-btn vault-btn-secondary min-h-11 px-3 text-xs" type="submit">{{ __('mobile.notifications.read') }}</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="vault-card grid justify-items-center gap-3 rounded-3xl border-dashed p-8 text-center" data-mobile-animate>
                <div class="vault-icon-tile size-14 rounded-full">
                    @include('mobile.partials.icon', ['name' => 'check-shield', 'class' => 'size-7'])
                </div>
                <p class="font-bold text-vault-text">{{ __('mobile.notifications.empty') }}</p>
            </div>
        @endforelse
    </div>
</x-layouts.app>
