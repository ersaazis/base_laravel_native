<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Services\MobileApiClient;
use App\Services\MobileCredentialStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly MobileApiClient $api,
        private readonly MobileCredentialStore $credentials,
    ) {}

    public function index(): View
    {
        return view('mobile.settings.index', [
            'baseUrl' => $this->api->baseUrl(),
            'biometricsEnabled' => $this->credentials->biometricsEnabled(),
        ]);
    }

    public function unlockForm(): View
    {
        return view('mobile.settings.unlock');
    }

    public function enableBiometrics(Request $request): RedirectResponse
    {
        $request->validate([
            'biometric_verified' => ['accepted'],
        ]);

        $this->credentials->enableBiometrics();

        return back()->with('status', __('mobile.settings.biometrics_enabled'));
    }

    public function disableBiometrics(Request $request): RedirectResponse
    {
        $request->validate([
            'biometric_verified' => ['accepted'],
        ]);

        $this->credentials->disableBiometrics();

        return back()->with('status', __('mobile.settings.biometrics_disabled'));
    }

    public function unlock(Request $request): RedirectResponse
    {
        $request->validate([
            'biometric_verified' => ['accepted'],
        ]);

        $this->credentials->unlock();

        return redirect()->route('dashboard');
    }

    public function lock(): RedirectResponse
    {
        $this->credentials->lock();

        return redirect()->route('settings.unlock');
    }
}
