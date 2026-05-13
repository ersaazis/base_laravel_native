<?php

namespace App\Console\Commands;

use App\Services\NativeBrandAssetImporter;
use App\Services\SiteConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

class NativeImportSiteIconCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'native:import-site-icon
        {--url= : Override the site config logo URL}
        {--no-refresh : Use cached site config instead of refreshing from the API}
        {--background=050505 : Solid hex background for generated icon and splash assets}
        {--public-path= : Directory for icon.png, native-logo.png, splash.png, and splash-dark.png}
        {--android-res-path= : Android res directory for drawable/startup_logo.png}
        {--no-android : Skip writing drawable/startup_logo.png when the Android project exists}
        {--circle-splash-logo : Render the logo inside a circle on splash.png and splash-dark.png}
        {--sync-name : Update APP_NAME in the local .env file from site_name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import NativePHP icon and splash assets from the current site config logo.';

    /**
     * Execute the console command.
     */
    public function handle(SiteConfig $siteConfig, NativeBrandAssetImporter $importer): int
    {
        $config = $this->option('no-refresh')
            ? $siteConfig->get()
            : $siteConfig->refresh(force: true);

        $sourceUrl = $this->stringOption('url') ?: $this->siteConfigString($config, 'logo_url');

        if ($sourceUrl === null) {
            $this->error('Site config does not contain a logo_url. Provide one with --url if needed.');

            return self::FAILURE;
        }

        $imageContents = $this->downloadImage($sourceUrl);

        if ($imageContents === null) {
            return self::FAILURE;
        }

        try {
            $files = $importer->import(
                imageContents: $imageContents,
                publicPath: $this->stringOption('public-path') ?: public_path(),
                backgroundHex: $this->stringOption('background') ?: '050505',
                androidResPath: $this->androidResPath(),
                circleSplashLogo: (bool) $this->option('circle-splash-logo'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('sync-name')) {
            $this->syncAppName($this->siteConfigString($config, 'site_name') ?: (string) config('app.name', 'Laravel'));
        }

        $this->info('NativePHP site icon assets imported.');
        $this->line("Source: {$sourceUrl}");

        foreach ($files as $label => $path) {
            $this->line("{$label}: {$path}");
        }

        if (! $this->option('sync-name')) {
            $this->line('Tip: add --sync-name to update APP_NAME from site_name before native:run.');
        }

        return self::SUCCESS;
    }

    private function downloadImage(string $sourceUrl): ?string
    {
        try {
            $response = Http::timeout((int) config('services.golf_api.timeout', 8))
                ->connectTimeout((int) config('services.golf_api.connect_timeout', 2))
                ->withHeaders(['Accept' => 'image/*,*/*'])
                ->get($sourceUrl);
        } catch (Throwable $exception) {
            $this->error("Unable to download site logo from [{$sourceUrl}]: {$exception->getMessage()}");

            return null;
        }

        if (! $response->successful()) {
            $this->error("Unable to download site logo from [{$sourceUrl}]. HTTP status: {$response->status()}.");

            return null;
        }

        $contents = $response->body();

        if ($contents === '') {
            $this->error("The site logo response from [{$sourceUrl}] was empty.");

            return null;
        }

        return $contents;
    }

    private function androidResPath(): ?string
    {
        if ($this->option('no-android')) {
            return null;
        }

        $configuredPath = $this->stringOption('android-res-path');

        if ($configuredPath !== null) {
            return $configuredPath;
        }

        $defaultPath = base_path('nativephp/android/app/src/main/res');

        return is_dir($defaultPath) ? $defaultPath : null;
    }

    private function syncAppName(string $siteName): void
    {
        $envPath = base_path('.env');
        $line = 'APP_NAME="'.$this->escapeEnvValue($siteName).'"';

        if (! File::exists($envPath)) {
            File::put($envPath, $line.PHP_EOL);
            $this->line("app_name: wrote {$envPath}");

            return;
        }

        $contents = File::get($envPath);
        $updated = preg_replace('/^APP_NAME=.*$/m', $line, $contents, 1, $count);

        if ($updated === null) {
            $this->warn('Unable to update APP_NAME in .env.');

            return;
        }

        if ($count === 0) {
            $updated = rtrim($updated).PHP_EOL.$line.PHP_EOL;
        }

        File::put($envPath, $updated);

        $this->line("app_name: {$siteName}");
    }

    private function escapeEnvValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function siteConfigString(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        return is_string($value) && filled($value) ? $value : null;
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && filled($value) ? $value : null;
    }
}
