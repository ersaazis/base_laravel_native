<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MobileApiClient
{
    public function __construct(
        private readonly MobileCredentialStore $credentials,
        private readonly OpenApiSpec $openApi,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function guest(string $method, string $path, array $data = [], ?int $timeout = null, ?int $connectTimeout = null): array
    {
        return $this->send($method, $path, $data, false, $timeout, $connectTimeout);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function authenticated(string $method, string $path, array $data = [], ?int $timeout = null, ?int $connectTimeout = null): array
    {
        return $this->send($method, $path, $data, true, $timeout, $connectTimeout);
    }

    public function checkToken(?int $timeout = null, ?int $connectTimeout = null): bool
    {
        try {
            $this->authenticated('get', '/auth/check-token', timeout: $timeout, connectTimeout: $connectTimeout);
            $this->credentials->markValidated();

            return true;
        } catch (MobileApiException $exception) {
            if ($exception->status === 401) {
                $this->credentials->forget();

                return false;
            }

            return true;
        }
    }

    public function baseUrl(): string
    {
        $configuredUrl = config('services.golf_api.base_url');

        if (is_string($configuredUrl) && filled($configuredUrl)) {
            return rtrim($configuredUrl, '/');
        }

        return rtrim($this->openApi->serverUrl(), '/');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $data, bool $authenticated, ?int $timeout, ?int $connectTimeout): array
    {
        $this->openApi->assertOperation($method, $path);

        $request = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->withQueryParameters(['lang' => $this->credentials->activeLocale()])
            ->timeout($this->seconds($timeout, 'timeout', 8))
            ->connectTimeout($this->seconds($connectTimeout, 'connect_timeout', 2));

        if ($authenticated) {
            $token = $this->credentials->token();

            if (! filled($token)) {
                throw new MobileApiException(__('mobile.errors.session_unavailable'), 401);
            }

            $request = $request->withToken($token);
        }

        try {
            $response = match (strtolower($method)) {
                'get' => $request->get($path, $data),
                'post' => $request->post($path, $data),
                'patch' => $request->patch($path, $data),
                'put' => $request->put($path, $data),
                'delete' => $request->delete($path, $data),
                default => throw new MobileApiException("Unsupported API method [{$method}]."),
            };
        } catch (ConnectionException) {
            $message = __('mobile.errors.api_unavailable');

            throw new MobileApiException($message, 0, ['api' => [$message]]);
        }

        return $this->handleResponse($response);
    }

    private function seconds(?int $override, string $configKey, int $fallback): int
    {
        return max(1, $override ?? (int) config("services.golf_api.{$configKey}", $fallback));
    }

    /**
     * @return array<string, mixed>
     */
    private function handleResponse(Response $response): array
    {
        $payload = $response->json() ?? [];

        if ($response->successful()) {
            return is_array($payload) ? $payload : [];
        }

        if ($response->status() === 401) {
            $this->credentials->forget();
        }

        $message = is_array($payload) && is_string($payload['message'] ?? null)
            ? $payload['message']
            : __('mobile.errors.api_failed');

        $errors = is_array($payload) && is_array($payload['errors'] ?? null)
            ? $payload['errors']
            : ['api' => [$message]];

        throw new MobileApiException($message, $response->status(), $errors);
    }
}
