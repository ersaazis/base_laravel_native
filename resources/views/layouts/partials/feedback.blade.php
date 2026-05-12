@php
    $status = session('status');
    $toastMessages = [];

    if (is_string($status) && filled($status)) {
        $toastMessages[] = $status;
    }

    foreach ($errors->get('api') as $error) {
        if (is_string($error) && filled($error)) {
            $toastMessages[] = $error;
        }
    }

    $userAgent = (string) request()->userAgent();
    $shouldUseNativeToast = $toastMessages !== []
        && function_exists('nativephp_call')
        && ! app()->runningUnitTests()
        && (getenv('JUMP_BRIDGE_PORT') !== false
            || str_contains($userAgent, 'NativePHP')
            || str_contains($userAgent, ' wv)'));

    if ($shouldUseNativeToast) {
        foreach ($toastMessages as $toastMessage) {
            \Native\Mobile\Facades\Dialog::toast($toastMessage);
        }
    }
@endphp

@if ($toastMessages !== [] && ! $shouldUseNativeToast)
    <div class="mobile-toast-region" aria-live="polite" data-mobile-toast-region>
        @if (is_string($status) && filled($status))
            <div class="mobile-toast mobile-toast-success" role="status" tabindex="0" data-mobile-toast>
                @include('mobile.partials.icon', ['name' => 'check-shield', 'class' => 'size-5 shrink-0'])
                <span class="min-w-0">{{ $status }}</span>
                <button class="mobile-toast-close" type="button" aria-label="{{ __('mobile.common.dismiss') }}">
                    @include('mobile.partials.icon', ['name' => 'x', 'class' => 'size-4'])
                </button>
            </div>
        @endif

        @if ($errors->has('api'))
            <div class="mobile-toast mobile-toast-danger" role="alert" tabindex="0" data-mobile-toast>
                @include('mobile.partials.icon', ['name' => 'alert', 'class' => 'size-5 shrink-0'])
                <ul class="min-w-0 space-y-1">
                    @foreach ($errors->get('api') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button class="mobile-toast-close" type="button" aria-label="{{ __('mobile.common.dismiss') }}">
                    @include('mobile.partials.icon', ['name' => 'x', 'class' => 'size-4'])
                </button>
            </div>
        @endif
    </div>
@endif
