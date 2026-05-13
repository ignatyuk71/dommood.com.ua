<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductImageOptimizer
{
    private const MAX_WIDTH = 2400;

    private const MAX_HEIGHT = 2400;

    private const RESPONSIVE_VARIANTS = [
        'card' => [900, 900],
        'thumb' => [420, 420],
    ];

    private const WEBP_QUALITY = 92;

    public function storeAsWebp(UploadedFile $image, string $directory, string $filename, ?int $maxWidth = null, ?int $maxHeight = null): string
    {
        $source = $this->imageResource($image);

        if (! $source || ! function_exists('imagewebp')) {
            return $image->storeAs($directory, $filename.'.'.$this->fallbackExtension($image), 'public');
        }

        $source = $this->applyJpegOrientation($image, $source);
        $binary = $this->webpBinary($source, $maxWidth ?? self::MAX_WIDTH, $maxHeight ?? self::MAX_HEIGHT);

        imagedestroy($source);

        if (! is_string($binary) || $binary === '') {
            return $image->storeAs($directory, $filename.'.'.$this->fallbackExtension($image), 'public');
        }

        $path = $directory.'/'.$filename.'.webp';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    public function storeResponsiveVersions(string $sourcePath, string $directory, string $baseFilename, string $disk = 'public'): array
    {
        if (! function_exists('imagewebp')) {
            return [];
        }

        $sourcePath = $this->normalizeStoragePath($sourcePath);

        if ($sourcePath === null || ! Storage::disk($disk)->exists($sourcePath)) {
            return [];
        }

        $source = $this->imageResourceFromPath(Storage::disk($disk)->path($sourcePath));

        if (! $source) {
            return [];
        }

        $storedPaths = [];

        foreach (self::RESPONSIVE_VARIANTS as $variant => [$maxWidth, $maxHeight]) {
            $binary = $this->webpBinary($source, $maxWidth, $maxHeight);

            if (! is_string($binary) || $binary === '') {
                continue;
            }

            $path = $directory.'/'.$baseFilename.'-'.$variant.'.webp';
            Storage::disk($disk)->put($path, $binary);
            $storedPaths[$variant] = $path;
        }

        imagedestroy($source);

        return $storedPaths;
    }

    private function imageResource(UploadedFile $image): mixed
    {
        return $this->imageResourceFromPath($image->getRealPath(), $image->getMimeType());
    }

    private function imageResourceFromPath(?string $path, ?string $mimeType = null): mixed
    {
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        $mimeType = $mimeType ?: $this->mimeType($path);

        return match ($mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };
    }

    private function applyJpegOrientation(UploadedFile $image, mixed $source): mixed
    {
        if ($image->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $source;
        }

        $exif = @exif_read_data($image->getRealPath());
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($source, 180, 0),
            6 => imagerotate($source, -90, 0),
            8 => imagerotate($source, 90, 0),
            default => $source,
        };

        if (! $rotated) {
            return $source;
        }

        if ($rotated !== $source) {
            imagedestroy($source);
        }

        return $rotated;
    }

    private function webpBinary(mixed $source, int $maxWidth, int $maxHeight): ?string
    {
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width < 1 || $height < 1) {
            return null;
        }

        [$targetWidth, $targetHeight] = $this->targetSize($width, $height, $maxWidth, $maxHeight);
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        imagealphablending($target, false);
        imagesavealpha($target, true);

        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $stored = imagewebp($target, null, self::WEBP_QUALITY);
        $binary = ob_get_clean();

        imagedestroy($target);

        return $stored && is_string($binary) && $binary !== '' ? $binary : null;
    }

    private function targetSize(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    private function fallbackExtension(UploadedFile $image): string
    {
        $extension = strtolower($image->getClientOriginalExtension() ?: $image->extension() ?: 'jpg');

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
    }

    private function mimeType(string $path): ?string
    {
        $info = @getimagesize($path);

        return is_array($info) ? ($info['mime'] ?? null) : null;
    }

    private function normalizeStoragePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, 9);
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        return ltrim($path, '/');
    }
}
