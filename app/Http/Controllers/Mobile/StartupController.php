<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Services\MobileApiClient;
use App\Services\MobileCredentialStore;
use App\Services\SiteConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StartupController extends Controller
{
    public function __construct(
        private readonly MobileApiClient $api,
        private readonly MobileCredentialStore $credentials,
        private readonly SiteConfig $siteConfig,
    ) {}

    public function show(): View
    {
        return view('mobile.startup', [
            'siteConfig' => $this->siteConfig->get(),
        ]);
    }

    public function check(): RedirectResponse
    {
        $this->siteConfig->refresh(
            timeout: $this->startupTimeout(),
            connectTimeout: $this->startupConnectTimeout(),
        );

        if (! $this->credentials->isAuthenticated()) {
            return redirect()->route('login');
        }

        if (! $this->api->checkToken(
            timeout: $this->startupTimeout(),
            connectTimeout: $this->startupConnectTimeout(),
        )) {
            return redirect()->route('login')->withErrors([
                'api' => __('mobile.errors.session_expired'),
            ]);
        }

        return redirect()->route('dashboard');
    }

    private function startupTimeout(): int
    {
        return max(1, (int) config('services.golf_api.startup_timeout', 2));
    }

    private function startupConnectTimeout(): int
    {
        return max(1, (int) config('services.golf_api.startup_connect_timeout', 1));
    }
}
