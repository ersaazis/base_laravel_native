<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

test('native site icon command imports nativephp assets from site config', function () {
    if (! native_brand_gd_is_available()) {
        $this->markTestSkipped('The GD extension is required for NativePHP image asset tests.');
    }

    config(['services.golf_api.base_url' => 'https://api.example.test']);

    $root = storage_path('framework/testing/native-brand-assets');
    $publicPath = $root.DIRECTORY_SEPARATOR.'public';
    $androidResPath = $root.DIRECTORY_SEPARATOR.'android-res';

    File::deleteDirectory($root);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.example.test/site-config*' => Http::response([
            'message' => 'Success.',
            'data' => [
                'config' => native_brand_site_config_payload([
                    'logo_url' => 'https://cdn.example.test/logo.png',
                ]),
            ],
        ]),
        'https://cdn.example.test/logo.png' => Http::response(native_brand_test_png(), 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

    $this->artisan('native:import-site-icon', [
        '--public-path' => $publicPath,
        '--android-res-path' => $androidResPath,
        '--background' => '111111',
    ])->assertSuccessful();

    expect($publicPath.DIRECTORY_SEPARATOR.'icon.png')->toBeFile()
        ->and(getimagesize($publicPath.DIRECTORY_SEPARATOR.'icon.png'))->toMatchArray([1024, 1024])
        ->and($publicPath.DIRECTORY_SEPARATOR.'native-logo.png')->toBeFile()
        ->and(getimagesize($publicPath.DIRECTORY_SEPARATOR.'native-logo.png'))->toMatchArray([1024, 1024])
        ->and($publicPath.DIRECTORY_SEPARATOR.'splash.png')->toBeFile()
        ->and(getimagesize($publicPath.DIRECTORY_SEPARATOR.'splash.png'))->toMatchArray([1080, 1920])
        ->and($publicPath.DIRECTORY_SEPARATOR.'splash-dark.png')->toBeFile()
        ->and(getimagesize($publicPath.DIRECTORY_SEPARATOR.'splash-dark.png'))->toMatchArray([1080, 1920])
        ->and($androidResPath.DIRECTORY_SEPARATOR.'drawable'.DIRECTORY_SEPARATOR.'startup_logo.png')->toBeFile()
        ->and(getimagesize($androidResPath.DIRECTORY_SEPARATOR.'drawable'.DIRECTORY_SEPARATOR.'startup_logo.png'))->toMatchArray([512, 512]);

    Http::assertSent(fn ($request): bool => str_starts_with((string) $request->url(), 'https://api.example.test/site-config'));
    Http::assertSent(fn ($request): bool => (string) $request->url() === 'https://cdn.example.test/logo.png');
});

test('native site icon command can render the splash logo as a circle', function () {
    if (! native_brand_gd_is_available()) {
        $this->markTestSkipped('The GD extension is required for NativePHP image asset tests.');
    }

    config(['services.golf_api.base_url' => 'https://api.example.test']);

    $root = storage_path('framework/testing/native-brand-assets-circle');
    $publicPath = $root.DIRECTORY_SEPARATOR.'public';

    File::deleteDirectory($root);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.example.test/site-config*' => Http::response([
            'message' => 'Success.',
            'data' => [
                'config' => native_brand_site_config_payload([
                    'logo_url' => 'https://cdn.example.test/logo.png',
                ]),
            ],
        ]),
        'https://cdn.example.test/logo.png' => Http::response(native_brand_test_png(), 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

    $this->artisan('native:import-site-icon', [
        '--public-path' => $publicPath,
        '--background' => '111111',
        '--no-android' => true,
        '--circle-splash-logo' => true,
    ])->assertSuccessful();

    $splashPath = $publicPath.DIRECTORY_SEPARATOR.'splash.png';

    expect($splashPath)->toBeFile()
        ->and(getimagesize($splashPath))->toMatchArray([1080, 1920])
        ->and(native_brand_png_rgb_at($splashPath, 540, 700))->toBe([17, 17, 17])
        ->and(native_brand_png_rgb_at($splashPath, 540, 780))->toBe([255, 255, 255]);
});

test('native site icon command fails clearly when site config has no logo url', function () {
    config(['services.golf_api.base_url' => 'https://api.example.test']);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.example.test/site-config*' => Http::response([
            'message' => 'Success.',
            'data' => [
                'config' => native_brand_site_config_payload(['logo_url' => null]),
            ],
        ]),
    ]);

    $this->artisan('native:import-site-icon', [
        '--public-path' => storage_path('framework/testing/native-brand-assets/no-logo'),
        '--no-android' => true,
    ])
        ->expectsOutputToContain('Site config does not contain a logo_url')
        ->assertFailed();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function native_brand_site_config_payload(array $overrides = []): array
{
    return array_replace([
        'site_name' => 'Golf Web Specialist Test',
        'logo_url' => null,
        'favicon_url' => null,
        'apple_touch_icon_url' => null,
        'registration_enabled' => false,
        'languages' => [
            'current' => 'en',
            'default' => 'en',
            'enabled' => [
                ['locale' => 'en', 'name' => 'English', 'native_name' => 'English'],
            ],
        ],
    ], $overrides);
}

function native_brand_test_png(): string
{
    $image = imagecreatetruecolor(640, 360);

    if ($image === false) {
        throw new RuntimeException('Unable to create test image.');
    }

    imagesavealpha($image, true);

    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    $green = imagecolorallocate($image, 88, 185, 134);
    $white = imagecolorallocate($image, 255, 255, 255);

    if ($transparent === false || $green === false || $white === false) {
        throw new RuntimeException('Unable to allocate test image colors.');
    }

    imagefill($image, 0, 0, $transparent);
    imagefilledrectangle($image, 70, 70, 570, 290, $green);
    imagefilledellipse($image, 320, 180, 180, 180, $white);

    ob_start();
    imagepng($image);
    $contents = ob_get_clean();

    imagedestroy($image);

    if (! is_string($contents)) {
        throw new RuntimeException('Unable to encode test image.');
    }

    return $contents;
}

function native_brand_gd_is_available(): bool
{
    return extension_loaded('gd') && function_exists('imagecreatetruecolor');
}

/**
 * @return array{0: int, 1: int, 2: int}
 */
function native_brand_png_rgb_at(string $path, int $x, int $y): array
{
    $image = imagecreatefrompng($path);

    if (! $image instanceof GdImage) {
        throw new RuntimeException("Unable to read PNG image [{$path}].");
    }

    $color = imagecolorat($image, $x, $y);
    imagedestroy($image);

    return [
        ($color >> 16) & 0xFF,
        ($color >> 8) & 0xFF,
        $color & 0xFF,
    ];
}
