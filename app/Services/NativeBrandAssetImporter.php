<?php

namespace App\Services;

use GdImage;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

class NativeBrandAssetImporter
{
    /**
     * @return array<string, string>
     */
    public function import(
        string $imageContents,
        string $publicPath,
        string $backgroundHex = '050505',
        ?string $androidResPath = null,
        bool $circleSplashLogo = false,
    ): array {
        $this->ensureGdIsAvailable();

        $source = @imagecreatefromstring($imageContents);

        if (! $source instanceof GdImage) {
            throw new InvalidArgumentException('The downloaded site logo is not a valid image.');
        }

        $background = $this->hexToRgb($backgroundHex);

        File::ensureDirectoryExists($publicPath);

        $files = [
            'icon' => $publicPath.DIRECTORY_SEPARATOR.'icon.png',
            'native_logo' => $publicPath.DIRECTORY_SEPARATOR.'native-logo.png',
            'splash' => $publicPath.DIRECTORY_SEPARATOR.'splash.png',
            'splash_dark' => $publicPath.DIRECTORY_SEPARATOR.'splash-dark.png',
        ];

        $this->writeContainedPng($source, $files['icon'], 1024, 1024, $background, 0.78);
        $this->writeContainedPng($source, $files['native_logo'], 1024, 1024, null, 0.78);

        if ($circleSplashLogo) {
            $this->writeCircleSplashPng($source, $files['splash'], 1080, 1920, $background);
            $this->writeCircleSplashPng($source, $files['splash_dark'], 1080, 1920, $background);
        } else {
            $this->writeContainedPng($source, $files['splash'], 1080, 1920, $background, 0.42);
            $this->writeContainedPng($source, $files['splash_dark'], 1080, 1920, $background, 0.42);
        }

        if ($androidResPath !== null) {
            $drawablePath = $androidResPath.DIRECTORY_SEPARATOR.'drawable';

            File::ensureDirectoryExists($drawablePath);

            $files['android_startup_logo'] = $drawablePath.DIRECTORY_SEPARATOR.'startup_logo.png';

            $this->writeContainedPng($source, $files['android_startup_logo'], 512, 512, null, 0.82);
        }

        imagedestroy($source);

        return $files;
    }

    private function ensureGdIsAvailable(): void
    {
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            $ini = php_ini_loaded_file() ?: 'unknown php.ini';

            throw new RuntimeException("The PHP GD extension is required to generate NativePHP icons. Enable extension=gd in {$ini}.");
        }
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $background
     */
    private function writeCircleSplashPng(GdImage $source, string $path, int $width, int $height, array $background): void
    {
        $canvas = $this->makeCanvas($width, $height, $background);
        $diameter = (int) round(min($width, $height) * 0.4);
        $tile = $this->makeCircleLogoTile($source, $diameter);
        $targetX = (int) round(($width - $diameter) / 2);
        $targetY = (int) round(($height - $diameter) / 2);

        imagealphablending($canvas, true);
        imagecopy($canvas, $tile, $targetX, $targetY, 0, 0, $diameter, $diameter);
        imagedestroy($tile);

        $this->writePng($canvas, $path);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}|null  $background
     */
    private function writeContainedPng(GdImage $source, string $path, int $width, int $height, ?array $background, float $scale): void
    {
        $canvas = $this->makeCanvas($width, $height, $background);

        $this->copyContained($canvas, $source, $width, $height, $scale);
        $this->writePng($canvas, $path);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}|null  $background
     */
    private function makeCanvas(int $width, int $height, ?array $background): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            throw new RuntimeException('Unable to create a GD image canvas.');
        }

        if ($background === null) {
            imagesavealpha($canvas, true);
            imagealphablending($canvas, false);

            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);

            if ($transparent === false) {
                throw new RuntimeException('Unable to allocate a transparent image color.');
            }

            imagefill($canvas, 0, 0, $transparent);
            imagealphablending($canvas, true);
        } else {
            [$red, $green, $blue] = $background;
            $color = imagecolorallocate($canvas, $red, $green, $blue);

            if ($color === false) {
                throw new RuntimeException('Unable to allocate an image background color.');
            }

            imagefill($canvas, 0, 0, $color);
        }

        return $canvas;
    }

    private function makeCircleLogoTile(GdImage $source, int $diameter): GdImage
    {
        $scale = 4;
        $largeDiameter = $diameter * $scale;
        $largeTile = $this->makeCanvas($largeDiameter, $largeDiameter, null);
        $circleColor = imagecolorallocate($largeTile, 255, 255, 255);

        if ($circleColor === false) {
            throw new RuntimeException('Unable to allocate the splash logo circle color.');
        }

        imagefilledellipse($largeTile, (int) ($largeDiameter / 2), (int) ($largeDiameter / 2), $largeDiameter, $largeDiameter, $circleColor);
        $this->copyContained($largeTile, $source, $largeDiameter, $largeDiameter, 0.72);

        $tile = $this->makeCanvas($diameter, $diameter, null);
        imagecopyresampled($tile, $largeTile, 0, 0, 0, 0, $diameter, $diameter, $largeDiameter, $largeDiameter);
        imagedestroy($largeTile);

        return $tile;
    }

    private function writePng(GdImage $canvas, string $path): void
    {
        File::ensureDirectoryExists(dirname($path));

        if (! imagepng($canvas, $path, 9)) {
            imagedestroy($canvas);

            throw new RuntimeException("Unable to write PNG asset to [{$path}].");
        }

        imagedestroy($canvas);
    }

    private function copyContained(GdImage $canvas, GdImage $source, int $canvasWidth, int $canvasHeight, float $scale): void
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $maxWidth = max(1, (int) round($canvasWidth * $scale));
        $maxHeight = max(1, (int) round($canvasHeight * $scale));
        $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $targetWidth = max(1, (int) round($sourceWidth * $ratio));
        $targetHeight = max(1, (int) round($sourceHeight * $ratio));
        $targetX = (int) round(($canvasWidth - $targetWidth) / 2);
        $targetY = (int) round(($canvasHeight - $targetHeight) / 2);

        imagecopyresampled(
            $canvas,
            $source,
            $targetX,
            $targetY,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $normalized = ltrim(trim($hex), '#');

        if (strlen($normalized) === 3) {
            $normalized = $normalized[0].$normalized[0].$normalized[1].$normalized[1].$normalized[2].$normalized[2];
        }

        if (! preg_match('/\A[0-9a-fA-F]{6}\z/', $normalized)) {
            throw new InvalidArgumentException('The background color must be a 3 or 6 character hex color.');
        }

        return [
            hexdec(substr($normalized, 0, 2)),
            hexdec(substr($normalized, 2, 2)),
            hexdec(substr($normalized, 4, 2)),
        ];
    }
}
