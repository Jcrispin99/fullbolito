<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\ReceiptTemplateRequest;
use App\Http\Resources\ReceiptTemplateResource;
use App\Models\ReceiptTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class ReceiptTemplateController extends ApiController
{
    public function show(): ReceiptTemplateResource
    {
        return new ReceiptTemplateResource(ReceiptTemplate::current());
    }

    public function update(ReceiptTemplateRequest $request): JsonResponse
    {
        $template = ReceiptTemplate::current();
        $template->update($request->validated());

        return response()->json([
            'message' => 'Plantilla actualizada exitosamente.',
            'data' => new ReceiptTemplateResource($template->fresh()),
        ]);
    }

    public function reset(): JsonResponse
    {
        $template = ReceiptTemplate::current();

        if ($template->logo_path) {
            Storage::disk('public')->delete($template->logo_path);
        }

        $template->update([
            'layout' => ReceiptTemplate::defaultLayout(),
            'logo_path' => null,
        ]);

        return response()->json([
            'message' => 'Plantilla restablecida a valores por defecto.',
            'data' => new ReceiptTemplateResource($template->fresh()),
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,svg,webp|max:1024',
        ]);

        $template = ReceiptTemplate::current();

        if ($template->logo_path) {
            Storage::disk('public')->delete($template->logo_path);
        }

        $path = $request->file('logo')->store('receipt-template', 'public');
        $template->update(['logo_path' => $path]);

        return response()->json([
            'message' => 'Logo actualizado.',
            'data' => new ReceiptTemplateResource($template->fresh()),
        ]);
    }

    public function deleteLogo(): JsonResponse
    {
        $template = ReceiptTemplate::current();

        if ($template->logo_path) {
            Storage::disk('public')->delete($template->logo_path);
            $template->update(['logo_path' => null]);
        }

        return response()->json([
            'message' => 'Logo eliminado.',
            'data' => new ReceiptTemplateResource($template->fresh()),
        ]);
    }
}
