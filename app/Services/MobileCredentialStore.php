<?php

namespace App\Services;

use App\Models\MobileCredential;
use Illuminate\Support\Carbon;

class MobileCredentialStore
{
    /**
     * @return array<string, mixed>
     */
    public function fallbackSiteConfig(): array
    {
        return [
            'site_name' => config('app.name', 'Mobile'),
            'logo_url' => null,
            'favicon_url' => null,
            'apple_touch_icon_url' => null,
            'registration_enabled' => false,
            'languages' => [
                'current' => $this->fallbackLocale(),
                'default' => $this->fallbackLocale(),
                'enabled' => [
                    $this->languageOption($this->fallbackLocale()),
                ],
            ],
        ];
    }

    public function credential(): ?MobileCredential
    {
        return MobileCredential::query()->latest('id')->first();
    }

    public function token(): ?string
    {
        return $this->credential()?->plain_text_token;
    }

    /**
     * @return array<string, mixed>
     */
    public function user(): array
    {
        return $this->credential()?->user ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function access(): array
    {
        return $this->credential()?->access ?? [];
    }

    public function locale(): ?string
    {
        $locale = $this->credential()?->locale;

        return is_string($locale) && filled($locale) ? $locale : null;
    }

    public function activeLocale(): string
    {
        $locale = $this->locale();

        if (is_string($locale) && in_array($locale, $this->enabledLocaleCodes(), true)) {
            return $locale;
        }

        $default = $this->siteConfigDefaultLocale();

        return in_array($default, $this->enabledLocaleCodes(), true) ? $default : $this->fallbackLocale();
    }

    /**
     * @return array<string, mixed>
     */
    public function siteConfig(): array
    {
        $config = $this->credential()?->site_config;

        return is_array($config) ? $config : $this->fallbackSiteConfig();
    }

    /**
     * @return array<int, array{locale: string, name: string, native_name: string}>
     */
    public function enabledLanguages(): array
    {
        $languages = $this->siteConfig()['languages']['enabled'] ?? null;

        if (! is_array($languages)) {
            return [$this->languageOption($this->fallbackLocale())];
        }

        $enabled = [];

        foreach ($languages as $language) {
            if (! is_array($language)) {
                continue;
            }

            $locale = $language['locale'] ?? null;

            if (is_string($locale) && $this->isSupportedLocale($locale)) {
                $enabled[$locale] = $this->languageOption($locale, $language);
            }
        }

        return array_values($enabled ?: [$this->languageOption($this->fallbackLocale())]);
    }

    /**
     * @return array<int, string>
     */
    public function enabledLocaleCodes(): array
    {
        return array_values(array_map(
            fn (array $language): string => $language['locale'],
            $this->enabledLanguages(),
        ));
    }

    public function isAuthenticated(): bool
    {
        return filled($this->token());
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function storeToken(string $token, array $user = []): MobileCredential
    {
        $existing = $this->credential();
        $locale = $existing?->locale;
        $siteConfig = $existing?->site_config;
        $siteConfigFetchedAt = $existing?->site_config_fetched_at;

        MobileCredential::query()->delete();

        return MobileCredential::query()->create([
            'plain_text_token' => $token,
            'user' => $user,
            'access' => [],
            'locale' => is_string($locale) ? $locale : null,
            'site_config' => is_array($siteConfig) ? $siteConfig : null,
            'site_config_fetched_at' => $siteConfigFetchedAt,
            'locked' => false,
            'last_validated_at' => now(),
            'unlocked_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function updateUser(array $user): void
    {
        $this->credential()?->forceFill(['user' => $user])->save();
    }

    /**
     * @param  array<string, mixed>  $access
     */
    public function updateAccess(array $access): void
    {
        $this->credential()?->forceFill(['access' => $access])->save();
    }

    public function updateLocale(string $locale): void
    {
        $this->ensureCredential()->forceFill(['locale' => $locale])->save();
    }

    /**
     * @param  array<string, mixed>  $siteConfig
     */
    public function updateSiteConfig(array $siteConfig): void
    {
        $this->ensureCredential()->forceFill([
            'site_config' => $siteConfig,
            'site_config_fetched_at' => now(),
        ])->save();

        $this->ensureValidLocale();
    }

    public function ensureValidLocale(): void
    {
        $locale = $this->locale();

        if ($locale === null || in_array($locale, $this->enabledLocaleCodes(), true)) {
            return;
        }

        $this->updateLocale($this->siteConfigDefaultLocale());
    }

    public function markValidated(): void
    {
        $this->credential()?->forceFill(['last_validated_at' => now()])->save();
    }

    public function needsTokenCheck(): bool
    {
        $validatedAt = $this->credential()?->last_validated_at;

        return ! $validatedAt instanceof Carbon || $validatedAt->lt(now()->subMinutes(5));
    }

    public function forget(): void
    {
        $credential = $this->credential();

        if (! $credential instanceof MobileCredential) {
            return;
        }

        $credential->forceFill([
            'plain_text_token' => null,
            'user' => [],
            'access' => [],
            'biometrics_enabled' => false,
            'locked' => false,
            'last_validated_at' => null,
            'unlocked_at' => null,
        ])->save();
    }

    public function biometricsEnabled(): bool
    {
        return (bool) $this->credential()?->biometrics_enabled;
    }

    public function enableBiometrics(): void
    {
        $this->credential()?->forceFill([
            'biometrics_enabled' => true,
            'locked' => false,
            'unlocked_at' => now(),
        ])->save();
    }

    public function disableBiometrics(): void
    {
        $this->credential()?->forceFill([
            'biometrics_enabled' => false,
            'locked' => false,
            'unlocked_at' => now(),
        ])->save();
    }

    public function lock(): void
    {
        $this->credential()?->forceFill(['locked' => true])->save();
    }

    public function unlock(): void
    {
        $this->credential()?->forceFill([
            'locked' => false,
            'unlocked_at' => now(),
        ])->save();
    }

    public function shouldRequireUnlock(): bool
    {
        $credential = $this->credential();

        if (! $credential instanceof MobileCredential || ! $credential->biometrics_enabled) {
            return false;
        }

        if ($credential->locked) {
            return true;
        }

        return ! $credential->unlocked_at instanceof Carbon
            || $credential->unlocked_at->lt(now()->subMinutes(15));
    }

    private function ensureCredential(): MobileCredential
    {
        return $this->credential() ?? MobileCredential::query()->create([
            'access' => [],
            'site_config' => $this->fallbackSiteConfig(),
            'site_config_fetched_at' => null,
        ]);
    }

    private function siteConfigDefaultLocale(): string
    {
        $default = $this->siteConfig()['languages']['default'] ?? null;

        return is_string($default) && $this->isSupportedLocale($default) ? $default : $this->fallbackLocale();
    }

    private function fallbackLocale(): string
    {
        return (string) config('languages.fallback', 'en');
    }

    /**
     * @param  array<string, mixed>  $language
     * @return array{locale: string, name: string, native_name: string}
     */
    private function languageOption(string $locale, array $language = []): array
    {
        $supported = config("languages.supported.{$locale}", []);

        return [
            'locale' => $locale,
            'name' => is_string($language['name'] ?? null) ? $language['name'] : (string) ($supported['name'] ?? strtoupper($locale)),
            'native_name' => is_string($language['native_name'] ?? null) ? $language['native_name'] : (string) ($supported['native_name'] ?? strtoupper($locale)),
        ];
    }

    private function isSupportedLocale(string $locale): bool
    {
        return array_key_exists($locale, (array) config('languages.supported', []));
    }
}
