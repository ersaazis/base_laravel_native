<?php

namespace App\Services;

use App\Models\MobileCredential;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class MobileCredentialStore
{
    private const ClientCookie = 'mobile_client_id';

    private const ClientCookieMinutes = 2_628_000;

    private static int $credentialRevision = 0;

    private ?MobileCredential $cachedCredential = null;

    private bool $credentialLoaded = false;

    private int $loadedRevision = -1;

    private ?string $clientId = null;

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
        if (! $this->credentialLoaded || $this->loadedRevision !== self::$credentialRevision) {
            $this->cachedCredential = MobileCredential::query()
                ->where('client_id', $this->clientId())
                ->latest('id')
                ->first();
            $this->credentialLoaded = true;
            $this->loadedRevision = self::$credentialRevision;
        }

        return $this->cachedCredential;
    }

    public function clientId(): string
    {
        $cookie = request()->cookie(self::ClientCookie);

        if (is_string($cookie) && Str::isUuid($cookie)) {
            if ($this->clientId !== null && $this->clientId !== $cookie) {
                $this->credentialLoaded = false;
            }

            return $this->clientId = $cookie;
        }

        if ($this->clientId !== null) {
            return $this->clientId;
        }

        if (app()->runningUnitTests()) {
            return $this->clientId = 'test-client';
        }

        $this->clientId = (string) Str::uuid();

        Cookie::queue(Cookie::make(
            self::ClientCookie,
            $this->clientId,
            self::ClientCookieMinutes,
            '/',
            null,
            null,
            true,
            false,
            'Lax',
        ));

        return $this->clientId;
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

    public function siteConfigFetchedAt(): ?Carbon
    {
        $fetchedAt = $this->credential()?->site_config_fetched_at;

        return $fetchedAt instanceof Carbon ? $fetchedAt : null;
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
        $clientId = $this->clientId();
        $existing = $this->credential();
        $locale = $existing?->locale;
        $siteConfig = $existing?->site_config;
        $siteConfigFetchedAt = $existing?->site_config_fetched_at;

        MobileCredential::query()
            ->where('client_id', $clientId)
            ->delete();

        return $this->setChangedCredential(MobileCredential::query()->create([
            'client_id' => $clientId,
            'plain_text_token' => $token,
            'user' => $user,
            'access' => [],
            'locale' => is_string($locale) ? $locale : null,
            'site_config' => is_array($siteConfig) ? $siteConfig : null,
            'site_config_fetched_at' => $siteConfigFetchedAt,
            'last_validated_at' => now(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function updateUser(array $user): void
    {
        $credential = $this->credential();

        if (! $credential instanceof MobileCredential) {
            return;
        }

        $credential->forceFill(['user' => $user])->save();
        $this->setChangedCredential($credential);
    }

    /**
     * @param  array<string, mixed>  $access
     */
    public function updateAccess(array $access): void
    {
        $credential = $this->credential();

        if (! $credential instanceof MobileCredential) {
            return;
        }

        $credential->forceFill(['access' => $access])->save();
        $this->setChangedCredential($credential);
    }

    public function updateLocale(string $locale): void
    {
        $credential = $this->ensureCredential();

        $credential->forceFill(['locale' => $locale])->save();
        $this->setChangedCredential($credential);
    }

    /**
     * @param  array<string, mixed>  $siteConfig
     */
    public function updateSiteConfig(array $siteConfig): void
    {
        $credential = $this->ensureCredential();

        $credential->forceFill([
            'site_config' => $siteConfig,
            'site_config_fetched_at' => now(),
        ])->save();

        $this->setChangedCredential($credential);
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
        $credential = $this->credential();

        if (! $credential instanceof MobileCredential) {
            return;
        }

        $credential->forceFill(['last_validated_at' => now()])->save();
        $this->setChangedCredential($credential);
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
            'last_validated_at' => null,
        ])->save();

        $this->setChangedCredential($credential);
    }

    private function ensureCredential(): MobileCredential
    {
        return $this->credential() ?? $this->setChangedCredential(MobileCredential::query()->create([
            'client_id' => $this->clientId(),
            'access' => [],
            'site_config' => $this->fallbackSiteConfig(),
            'site_config_fetched_at' => null,
        ]));
    }

    private function setCredential(?MobileCredential $credential): ?MobileCredential
    {
        $this->cachedCredential = $credential;
        $this->credentialLoaded = true;
        $this->loadedRevision = self::$credentialRevision;

        return $credential;
    }

    private function setChangedCredential(?MobileCredential $credential): ?MobileCredential
    {
        self::$credentialRevision++;

        return $this->setCredential($credential);
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
