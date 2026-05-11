<?php

namespace App\Http\Requests\Mobile;

use App\Services\MobileCredentialStore;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LocaleUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(MobileCredentialStore $credentials): array
    {
        return [
            'locale' => ['required', 'string', Rule::in($credentials->enabledLocaleCodes())],
        ];
    }

    public function safeRedirectUrl(string $fallbackRoute): string
    {
        $redirectTo = $this->input('redirect_to');

        if (is_string($redirectTo) && $this->isLocalUrl($redirectTo) && $this->supportsGet($redirectTo)) {
            return $redirectTo;
        }

        return route($fallbackRoute);
    }

    protected function getRedirectUrl(): string
    {
        return $this->safeRedirectUrl(
            $this->routeIs('profile.language.update') ? 'profile.edit' : 'login',
        );
    }

    private function isLocalUrl(string $url): bool
    {
        $baseUrl = rtrim(url('/'), '/');

        return $url === $baseUrl
            || str_starts_with($url, "{$baseUrl}/")
            || str_starts_with($url, "{$baseUrl}?");
    }

    private function supportsGet(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($path) || $path === '') {
            $path = '/';
        }

        $uri = is_string($query) && $query !== '' ? "{$path}?{$query}" : $path;

        try {
            app('router')->getRoutes()->match(HttpRequest::create($uri, 'GET'));

            return true;
        } catch (MethodNotAllowedHttpException|NotFoundHttpException) {
            return false;
        }
    }
}
