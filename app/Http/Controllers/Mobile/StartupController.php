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
        $this->siteConfig->refresh();

        if (! $this->credentials->isAuthenticated()) {
            return redirect()->route('login');
        }

        if (! $this->api->checkToken()) {
            return redirect()->route('login')->withErrors([
                'api' => __('mobile.errors.session_expired'),
            ]);
        }

        if ($this->credentials->biometricsEnabled() || $this->credentials->shouldRequireUnlock()) {
            $this->credentials->lock();

            return redirect()->route('settings.unlock');
        }

        return redirect()->route('dashboard');
    }
}
