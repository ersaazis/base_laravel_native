<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Mobile\Concerns\HandlesMobileApiErrors;
use App\Http\Requests\Mobile\PasswordUpdateRequest;
use App\Http\Requests\Mobile\TwoFactorConfirmRequest;
use App\Http\Requests\Mobile\TwoFactorDisableRequest;
use App\Services\MobileApiClient;
use App\Services\MobileApiException;
use App\Services\MobileCredentialStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityController extends Controller
{
    use HandlesMobileApiErrors;

    public function __construct(
        private readonly MobileApiClient $api,
        private readonly MobileCredentialStore $credentials,
    ) {}

    public function index(Request $request): View
    {
        $twoFactor = $this->safeData('/profile/security/two-factor');
        $twoFactorEnabled = $this->twoFactorEnabled($twoFactor) || session('two_factor_recently_confirmed') === true;
        $setupRequested = $request->boolean('setup') || session('two_factor_setup_requested') === true;

        return view('mobile.security.index', [
            'profile' => $this->credentials->user(),
            'role' => is_array($this->credentials->access()['role'] ?? null) ? $this->credentials->access()['role'] : [],
            'twoFactor' => $twoFactor,
            'twoFactorEnabled' => $twoFactorEnabled,
            'setupRequested' => $setupRequested,
            'qrCode' => $setupRequested ? $this->setupData() : [],
            'recoveryCodes' => $twoFactorEnabled ? $this->safeData('/profile/security/two-factor/recovery-codes') : [],
        ]);
    }

    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        try {
            $this->api->authenticated('put', '/profile/security/password', $request->safe()->only([
                'current_password',
                'password',
                'password_confirmation',
            ]));

            return back()->with('status', __('mobile.security.password_updated'));
        } catch (MobileApiException $exception) {
            return $this->backWithApiError($exception);
        }
    }

    public function enableTwoFactor(): RedirectResponse
    {
        try {
            $response = $this->api->authenticated('post', '/profile/security/two-factor', ['force' => false]);
            session([
                'two_factor_setup_requested' => true,
                'two_factor_setup_data' => $this->payloadData($response),
            ]);

            return redirect()->route('security.index', ['setup' => 1])
                ->withFragment('two-factor-setup')
                ->with('status', __('mobile.security.two_factor_requested'));
        } catch (MobileApiException $exception) {
            return $this->backWithApiError($exception);
        }
    }

    public function confirmTwoFactor(TwoFactorConfirmRequest $request): RedirectResponse
    {
        try {
            $this->api->authenticated('post', '/profile/security/two-factor/confirm', $request->validated());
            session()->forget(['two_factor_setup_requested', 'two_factor_setup_data']);

            return redirect()
                ->route('security.index')
                ->with('two_factor_recently_confirmed', true)
                ->with('status', __('mobile.security.two_factor_confirmed'));
        } catch (MobileApiException $exception) {
            return $this->backWithApiError($exception)->with('two_factor_setup_requested', true);
        }
    }

    public function cancelTwoFactorSetup(): RedirectResponse
    {
        session()->forget(['two_factor_setup_requested', 'two_factor_setup_data']);

        return redirect()->route('security.index');
    }

    public function disableTwoFactor(TwoFactorDisableRequest $request): RedirectResponse
    {
        try {
            $this->api->authenticated('delete', '/profile/security/two-factor', [
                'current_password' => $request->validated('two_factor_password'),
            ]);
            session()->forget(['two_factor_setup_requested', 'two_factor_setup_data']);

            return back()->with('status', __('mobile.security.two_factor_disabled'));
        } catch (MobileApiException $exception) {
            return $this->backWithApiError($exception);
        }
    }

    public function regenerateRecoveryCodes(): RedirectResponse
    {
        try {
            $this->api->authenticated('post', '/profile/security/two-factor/recovery-codes');

            return back()->with('status', __('mobile.security.recovery_regenerated'));
        } catch (MobileApiException $exception) {
            return $this->backWithApiError($exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function safeData(string $path): array
    {
        try {
            $response = $this->api->authenticated('get', $path);

            return $this->payloadData($response);
        } catch (MobileApiException) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function setupData(): array
    {
        $flashed = session('two_factor_setup_data');
        $flashed = is_array($flashed) ? $flashed : [];
        $fetched = $this->safeData('/profile/security/two-factor/qr-code');

        return array_replace_recursive($flashed, $fetched);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function payloadData(array $response): array
    {
        $data = $response['data'] ?? $response;

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $twoFactor
     */
    private function twoFactorEnabled(array $twoFactor): bool
    {
        $nestedTwoFactor = is_array($twoFactor['two_factor'] ?? null) ? $twoFactor['two_factor'] : [];

        return (bool) (
            $twoFactor['enabled']
            ?? $twoFactor['two_factor_enabled']
            ?? $twoFactor['confirmed']
            ?? $twoFactor['enabled_at']
            ?? $nestedTwoFactor['enabled']
            ?? $nestedTwoFactor['two_factor_enabled']
            ?? $nestedTwoFactor['confirmed']
            ?? $nestedTwoFactor['enabled_at']
            ?? false
        );
    }
}
