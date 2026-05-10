<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SiteAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

final class ProcessSiteAsset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    private const array THUMBNAIL_SIZES = [
        'sm' => 320,
        'md' => 768,
        'lg' => 1280,
    ];

    public function __construct(
        private readonly SiteAsset $asset,
    ) {}

    public function handle(): void
    {
        if (! $this->asset->isImage()) {
            return;
        }

        // Skip SVGs — they don't need thumbnails
        if ($this->asset->mime_type === 'image/svg+xml') {
            return;
        }

        $disk = Storage::disk('public');
        $sourcePath = $this->asset->path;

        if (! $disk->exists($sourcePath)) {
            return;
        }

        $sourceContent = $disk->get($sourcePath);
        $sourceImage = @imagecreatefromstring($sourceContent);

        if (! $sourceImage) {
            return;
        }

        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);
        $variants = [];

        foreach (self::THUMBNAIL_SIZES as $key => $maxWidth) {
            // Skip if original is smaller than target
            if ($originalWidth <= $maxWidth) {
                continue;
            }

            $ratio = $maxWidth / $originalWidth;
            $newHeight = (int) round($originalHeight * $ratio);

            $thumb = imagecreatetruecolor($maxWidth, $newHeight);

            // Preserve transparency for PNG
            if ($this->asset->mime_type === 'image/png') {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
            }

            imagecopyresampled($thumb, $sourceImage, 0, 0, 0, 0, $maxWidth, $newHeight, $originalWidth, $originalHeight);

            // Generate WebP variant
            $pathInfo = pathinfo($sourcePath);
            $variantPath = "{$pathInfo['dirname']}/{$pathInfo['filename']}_{$key}.webp";

            ob_start();
            imagewebp($thumb, null, 82);
            $webpContent = ob_get_clean();

            $disk->put($variantPath, $webpContent);

            $variants[$key] = [
                'path' => $variantPath,
                'width' => $maxWidth,
                'height' => $newHeight,
                'mime_type' => 'image/webp',
                'size_bytes' => strlen($webpContent),
            ];

            imagedestroy($thumb);
        }

        // Generate WebP version of original size
        $pathInfo = pathinfo($sourcePath);
        $webpOriginalPath = "{$pathInfo['dirname']}/{$pathInfo['filename']}_original.webp";

        ob_start();
        imagewebp($sourceImage, null, 85);
        $webpOriginal = ob_get_clean();

        $disk->put($webpOriginalPath, $webpOriginal);

        $variants['original_webp'] = [
            'path' => $webpOriginalPath,
            'width' => $originalWidth,
            'height' => $originalHeight,
            'mime_type' => 'image/webp',
            'size_bytes' => strlen($webpOriginal),
        ];

        // Extract dominant color
        $dominantColor = $this->extractDominantColor($sourceImage);

        imagedestroy($sourceImage);

        $this->asset->update([
            'variants' => $variants,
            'dominant_color' => $dominantColor,
        ]);
    }

    private function extractDominantColor(\GdImage $image): ?string
    {
        $resized = imagecreatetruecolor(1, 1);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));

        $rgb = imagecolorat($resized, 0, 0);
        imagedestroy($resized);

        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
