<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\SiteAssetResource;
use App\Jobs\ProcessSiteAsset;
use App\Models\Site;
use App\Models\SiteAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class BuilderMediaController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $query = $site->assets()->orderBy('created_at', 'desc');

        if ($request->has('folder')) {
            $query->inFolder($request->input('folder'));
        }

        if ($request->input('type') === 'images') {
            $query->images();
        } elseif ($request->input('type') === 'videos') {
            $query->videos();
        }

        $perPage = $request->input('per_page', 30);
        $assets = $query->paginate((int) $perPage);

        return $this->success(
            SiteAssetResource::collection($assets)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:jpg,jpeg,png,webp,gif,svg,mp4,webm,mov'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'folder' => ['nullable', 'string', 'max:50'],
        ]);

        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $file = $request->file('file');
        $uuid = Str::uuid()->toString();
        $extension = $file->getClientOriginalExtension();
        $path = "sites/{$site->id}/media/{$uuid}.{$extension}";

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $width = null;
        $height = null;
        $dominantColor = null;

        if (str_starts_with($file->getMimeType(), 'image/') && $file->getMimeType() !== 'image/svg+xml') {
            $imageSize = @getimagesize($file->getRealPath());
            if ($imageSize) {
                $width = $imageSize[0];
                $height = $imageSize[1];
            }
        }

        $asset = SiteAsset::create([
            'site_id' => $site->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'dominant_color' => $dominantColor,
            'alt_text' => $request->input('alt_text'),
            'folder' => $request->input('folder', 'general'),
        ]);

        // Dispatch thumbnail generation job for images
        if ($asset->isImage() && $asset->mime_type !== 'image/svg+xml') {
            ProcessSiteAsset::dispatch($asset);
        }

        return $this->created(new SiteAssetResource($asset));
    }

    public function show(SiteAsset $asset): JsonResponse
    {
        return $this->success(new SiteAssetResource($asset));
    }

    public function update(Request $request, SiteAsset $asset): JsonResponse
    {
        $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
            'folder' => ['nullable', 'string', 'max:50'],
        ]);

        $asset->update($request->only(['alt_text', 'folder']));

        return $this->success(new SiteAssetResource($asset));
    }

    public function destroy(SiteAsset $asset): JsonResponse
    {
        $disk = Storage::disk('public');

        // Delete original file
        $disk->delete($asset->path);

        // Delete variant files (thumbnails)
        if ($asset->variants) {
            foreach ($asset->variants as $variant) {
                if (isset($variant['path'])) {
                    $disk->delete($variant['path']);
                }
            }
        }

        $asset->delete();

        return $this->noContent();
    }
}
