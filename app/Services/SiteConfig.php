<?php

namespace App\Services;

use Throwable;

class SiteConfig
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $config = null;

    public function __construct(
        private readonly MobileApiClient $api,
        private readonly MobileCredentialStore $credentials,
    ) {}

    /**
     * @return array{
     *     site_name: string,
     *     logo_url: string|null,
     *     favicon_url: string|null,
     *     apple_touch_icon_url: string|null,
     *     registration_enabled: bool,
     *     languages: array{current: string, default: string, enabled: array<int, array{locale: string, name: string, native_name: string}>}
     * }
     */
    public function get(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        return $this->config = $this->credentials->siteConfig();
    }

    /**
     * @return array<string, mixed>
     */
    public function refresh(bool $force = false, ?int $timeout = null, ?int $connectTimeout = null): array
    {
        if (! $force && ! $this->shouldRefresh()) {
            return $this->config = $this->credentials->siteConfig();
        }

        try {
            $response = $this->api->guest('get', '/site-config', timeout: $timeout, connectTimeout: $connectTimeout);
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            $config = is_array($data['config'] ?? null) ? $data['config'] : [];
        } catch (Throwable) {
            return $this->config = $this->credentials->siteConfig();
        }

        $normalized = [
            'site_name' => $this->stringValue($config['site_name'] ?? null, config('app.name', 'Mobile')),
            'logo_url' => $this->nullableString($config['logo_url'] ?? null),
            'favicon_url' => $this->nullableString($config['favicon_url'] ?? null),
            'apple_touch_icon_url' => $this->nullableString($config['apple_touch_icon_url'] ?? null),
            'registration_enabled' => ($config['registration_enabled'] ?? false) === true,
            'languages' => $this->normalizeLanguages(is_array($config['languages'] ?? null) ? $config['languages'] : []),
        ];

        $this->credentials->updateSiteConfig($normalized);

        return $this->config = $this->credentials->siteConfig();
    }

    public function registrationEnabled(): bool
    {
        return $this->get()['registration_enabled'];
    }

    private function shouldRefresh(): bool
    {
        $fetchedAt = $this->credentials->siteConfigFetchedAt();

        return $fetchedAt === null || $fetchedAt->lt(now()->subMinutes(30));
    }

    private function stringValue(mixed $value, string $fallback): string
    {
        return is_string($value) && filled($value) ? $value : $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && filled($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $languages
     * @return array{current: string, default: string, enabled: array<int, array{locale: string, name: string, native_name: string}>}
     */
    private function normalizeLanguages(array $languages): array
    {
        $supported = (array) config('languages.supported', []);
        $enabled = [];
        $apiEnabled = is_array($languages['enabled'] ?? null) ? $languages['enabled'] : [];

        foreach ($apiEnabled as $language) {
            if (! is_array($language)) {
                continue;
            }

            $locale = $language['locale'] ?? null;

            if (! is_string($locale) || ! array_key_exists($locale, $supported)) {
                continue;
            }

            $enabled[$locale] = [
                'locale' => $locale,
                'name' => $this->stringValue($language['name'] ?? null, (string) ($supported[$locale]['name'] ?? strtoupper($locale))),
                'native_name' => $this->stringValue($language['native_name'] ?? null, (string) ($supported[$locale]['native_name'] ?? strtoupper($locale))),
            ];
        }

        if ($enabled === []) {
            $fallback = (string) config('languages.fallback', 'en');
            $enabled[$fallback] = [
                'locale' => $fallback,
                'name' => (string) ($supported[$fallback]['name'] ?? 'English'),
                'native_name' => (string) ($supported[$fallback]['native_name'] ?? 'English'),
            ];
        }

        $default = $languages['default'] ?? null;
        $current = $languages['current'] ?? null;
        $enabledLocales = array_keys($enabled);

        if (! is_string($default) || ! in_array($default, $enabledLocales, true)) {
            $default = $enabledLocales[0];
        }

        if (! is_string($current) || ! in_array($current, $enabledLocales, true)) {
            $current = $default;
        }

        return [
            'current' => $current,
            'default' => $default,
            'enabled' => array_values($enabled),
        ];
    }
}
