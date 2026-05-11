<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Services\MobileApiClient;
use App\Services\MobileApiException;
use App\Services\MobileCredentialStore;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly MobileApiClient $api,
        private readonly MobileCredentialStore $credentials,
    ) {}

    public function __invoke(): View
    {
        $profile = $this->credentials->user();
        $access = $this->credentials->access();

        try {
            $profileResponse = $this->api->authenticated('get', '/profile');
            $profileData = is_array($profileResponse['data'] ?? null) ? $profileResponse['data'] : [];
            $profile = $profileData['user'] ?? $profileData ?: $profile;

            if (is_array($profile)) {
                $this->credentials->updateUser($profile);
            }

            $accessResponse = $this->api->authenticated('get', '/profile/access');
            $access = is_array($accessResponse['data'] ?? null) ? $accessResponse['data'] : [];
            $this->credentials->updateAccess($access);
        } catch (MobileApiException $exception) {
            report($exception);
        }

        $permissions = is_array($access['permissions'] ?? null) ? $access['permissions'] : [];

        return view('mobile.dashboard', [
            'profile' => is_array($profile) ? $profile : [],
            'role' => is_array($access['role'] ?? null) ? $access['role'] : [],
            'permissions' => $permissions,
        ]);
    }
}
