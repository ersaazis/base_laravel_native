<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Mobile\Concerns\HandlesMobileApiErrors;
use App\Http\Requests\Mobile\LocaleUpdateRequest;
use App\Http\Requests\Mobile\ProfileUpdateRequest;
use App\Services\MobileApiClient;
use App\Services\MobileApiException;
use App\Services\MobileCredentialStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use HandlesMobileApiErrors;

    public function __construct(
        private readonly MobileApiClient $api,
        private readonly MobileCredentialStore $credentials,
    ) {}

    public function edit(): View
    {
        $access = $this->credentials->access();

        return view('mobile.profile.edit', [
            'profile' => $this->credentials->user(),
            'role' => is_array($access['role'] ?? null) ? $access['role'] : [],
            'biometricsEnabled' => $this->credentials->biometricsEnabled(),
            'languages' => $this->credentials->enabledLanguages(),
            'activeLocale' => $this->credentials->activeLocale(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        try {
            $response = $this->api->authenticated('patch', '/profile', $request->validated());
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            $user = is_array($data['user'] ?? null) ? $data['user'] : $request->validated();

            $this->credentials->updateUser($user);

            return redirect()->route('profile.edit')->with('status', __('mobile.profile.updated'));
        } catch (MobileApiException $exception) {
            return $this->backWithApiError($exception);
        }
    }

    public function updateLanguage(LocaleUpdateRequest $request): RedirectResponse
    {
        $this->credentials->updateLocale((string) $request->validated('locale'));

        return redirect()
            ->to($request->safeRedirectUrl('profile.edit'))
            ->with('status', __('mobile.profile.language_saved'));
    }
}
