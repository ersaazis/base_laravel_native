<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Mobile\Concerns\HandlesMobileApiErrors;
use App\Http\Requests\Mobile\ForgotPasswordRequest;
use App\Http\Requests\Mobile\LocaleUpdateRequest;
use App\Http\Requests\Mobile\LoginRequest;
use App\Http\Requests\Mobile\ResetPasswordRequest;
use App\Http\Requests\Mobile\SignupRequest;
use App\Http\Requests\Mobile\TwoFactorChallengeRequest;
use App\Services\MobileApiClient;
use App\Services\MobileApiException;
use App\Services\MobileCredentialStore;
use App\Services\SiteConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AuthController extends Controller
{
    use HandlesMobileApiErrors;

    public function __construct(
        private readonly MobileApiClient $api,
        private readonly MobileCredentialStore $credentials,
        private readonly SiteConfig $siteConfig,
    ) {}

    public function loginForm(): View
    {
        return view('mobile.auth.login', [
            'siteConfig' => $this->siteConfig->get(),
            'registrationEnabled' => $this->siteConfig->registrationEnabled(),
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $payload = $request->validated() + ['device_name' => 'NativePHP Mobile'];

        try {
            $response = $this->api->guest('post', '/auth/login', $payload);

            if ($this->requiresTwoFactorChallenge($response)) {
                session(['mobile_two_factor_challenge_token' => $response['data']['challenge_token']]);

                return redirect()->route('two-factor.challenge');
            }

            $this->storeAuthenticatedResponse($response);
            session()->forget('mobile_two_factor_challenge_token');

            return redirect()->route('dashboard');
        } catch (MobileApiException $exception) {
            return $this->backWithLoginError($exception);
        }
    }

    public function twoFactorChallengeForm(): View|RedirectResponse
    {
        if (! is_string(session('mobile_two_factor_challenge_token'))) {
            return redirect()->route('login')->withErrors([
                'api' => __('mobile.auth.two_factor.restart'),
            ]);
        }

        return view('mobile.auth.two-factor-challenge');
    }

    public function twoFactorChallenge(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $challengeToken = session('mobile_two_factor_challenge_token');

        if (! is_string($challengeToken) || blank($challengeToken)) {
            return redirect()->route('login')->withErrors([
                'api' => __('mobile.auth.two_factor.expired'),
            ]);
        }

        try {
            $validated = $request->validated();
            $usesRecoveryCode = filled($validated['recovery_code'] ?? null);
            $payload = ['challenge_token' => $challengeToken];
            $payload[$usesRecoveryCode ? 'recovery_code' : 'code'] = $usesRecoveryCode
                ? $validated['recovery_code']
                : $validated['code'];

            $response = $this->api->guest(
                'post',
                $usesRecoveryCode ? '/auth/two-factor-recovery-code' : '/auth/two-factor-challenge',
                $payload,
            );

            $this->storeAuthenticatedResponse($response);
            session()->forget('mobile_two_factor_challenge_token');

            return redirect()->route('dashboard');
        } catch (MobileApiException $exception) {
            return $this->backWithApiError($exception);
        }
    }

    public function signupForm(): View|RedirectResponse
    {
        if (! $this->siteConfig->registrationEnabled()) {
            return redirect()->route('login')->withErrors([
                'api' => __('mobile.auth.registration_disabled'),
            ]);
        }

        return view('mobile.auth.signup');
    }

    public function signup(SignupRequest $request): RedirectResponse
    {
        if (! $this->siteConfig->registrationEnabled()) {
            return redirect()->route('login')->withErrors([
                'api' => __('mobile.auth.registration_disabled'),
            ]);
        }

        $payload = $request->validated() + ['device_name' => 'NativePHP Mobile'];

        try {
            $response = $this->api->guest('post', '/auth/signup', $payload);
            $this->storeAuthenticatedResponse($response);

            return redirect()->route('dashboard');
        } catch (MobileApiException $exception) {
            return $this->backWithApiError($exception);
        }
    }

    public function forgotPasswordForm(): View
    {
        return view('mobile.auth.forgot-password');
    }

    public function forgotPassword(ForgotPasswordRequest $request): RedirectResponse
    {
        try {
            $this->api->guest('post', '/auth/forgot-password', $request->validated());

            return back()->with('status', __('mobile.auth.forgot.sent'));
        } catch (MobileApiException $exception) {
            return $this->backWithApiError($exception);
        }
    }

    public function resetPasswordForm(): View
    {
        return view('mobile.auth.reset-password');
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        try {
            $this->api->guest('post', '/auth/reset-password', $request->validated());

            return redirect()->route('login')->with('status', __('mobile.auth.reset.done'));
        } catch (MobileApiException $exception) {
            return $this->backWithApiError($exception);
        }
    }

    public function updateLocale(LocaleUpdateRequest $request): RedirectResponse
    {
        $this->credentials->updateLocale((string) $request->validated('locale'));

        return redirect()->to($request->safeRedirectUrl('login'));
    }

    public function logout(): RedirectResponse
    {
        try {
            $this->api->authenticated('post', '/auth/logout');
        } catch (MobileApiException) {
            //
        }

        $this->credentials->forget();

        return redirect()->route('login')
            ->with('status', __('mobile.auth.signed_out'))
            ->with('clear_native_nav', true);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function storeAuthenticatedResponse(array $response): void
    {
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $token = $data['plain_text_token'] ?? null;
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];

        if (! is_string($token) || blank($token)) {
            throw new MobileApiException(__('mobile.auth.missing_token'));
        }

        $this->credentials->storeToken($token, $user);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function requiresTwoFactorChallenge(array $response): bool
    {
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        return ($data['two_factor'] ?? false) === true
            && is_string($data['challenge_token'] ?? null)
            && filled($data['challenge_token']);
    }

    private function backWithLoginError(MobileApiException $exception): RedirectResponse
    {
        $errors = $exception->errors;

        if (! array_key_exists('email', $errors) && ! array_key_exists('password', $errors)) {
            $message = $errors['api'] ?? $exception->getMessage();

            if (is_array($message)) {
                $message = $message[0] ?? $exception->getMessage();
            }

            $errors = [
                'email' => __('mobile.auth.email_hint'),
                'password' => is_string($message) && filled($message) ? $message : __('mobile.auth.password_mismatch'),
            ];
        }

        return back()
            ->withInput(request()->except('password'))
            ->withErrors($errors);
    }
}
