<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\BillingCredentialRequest;
use App\Http\Resources\BillingCredentialResource;
use App\Models\BillingCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CRUD para credenciales de facturación electrónica del tenant.
 *
 * El certificado digital se guarda en el disk privado `local`, que el
 * `FilesystemTenancyBootstrapper` redirige a `storage/{tenant}/app/private/`.
 * `cert_path` almacena la ruta relativa dentro de ese disk.
 */
final class BillingCredentialController extends ApiController
{
    private const CERT_DIRECTORY = 'billing/certs';

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive,all',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = BillingCredential::query()->orderBy('id', 'asc');

        if (! empty($validated['q'])) {
            $term = $validated['q'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('sol_user', 'like', "%{$term}%");
            });
        }

        if (! empty($validated['status']) && $validated['status'] !== 'all') {
            $query->where('is_active', $validated['status'] === 'active');
        }

        $perPage = (int) ($validated['per_page'] ?? 25);
        $paginator = $query->paginate($perPage);

        return BillingCredentialResource::collection($paginator);
    }

    public function show(BillingCredential $billingCredential): BillingCredentialResource
    {
        return new BillingCredentialResource($billingCredential);
    }

    public function store(BillingCredentialRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $credential = DB::transaction(function () use ($request, $validated) {
            $certPath = $this->storeCertificate($request);

            return BillingCredential::create([
                'name' => $validated['name'],
                'sol_user' => $validated['sol_user'],
                'sol_pass' => $validated['sol_pass'],
                'cert_path' => $certPath,
                'client_id' => $validated['client_id'] ?? null,
                'client_secret' => $validated['client_secret'] ?? null,
                'production' => (bool) ($validated['production'] ?? false),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);
        });

        return response()->json([
            'message' => 'Credenciales de facturación creadas exitosamente.',
            'data' => new BillingCredentialResource($credential),
        ], 201);
    }

    public function update(BillingCredentialRequest $request, BillingCredential $billingCredential): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $billingCredential) {
            // Si vino un certificado nuevo, lo subimos y descartamos el anterior.
            if ($request->hasFile('cert_file')) {
                $oldPath = $billingCredential->cert_path;
                $billingCredential->cert_path = $this->storeCertificate($request);

                if ($oldPath) {
                    Storage::disk('local')->delete($oldPath);
                }
            }

            $billingCredential->fill([
                'name' => $validated['name'] ?? $billingCredential->name,
                'sol_user' => $validated['sol_user'] ?? $billingCredential->sol_user,
                'client_id' => array_key_exists('client_id', $validated)
                    ? $validated['client_id']
                    : $billingCredential->client_id,
                'production' => array_key_exists('production', $validated)
                    ? (bool) $validated['production']
                    : $billingCredential->production,
                'is_active' => array_key_exists('is_active', $validated)
                    ? (bool) $validated['is_active']
                    : $billingCredential->is_active,
            ]);

            // Sólo actualiza secretos si vienen en el payload (no se sobreescriben con null).
            if (! empty($validated['sol_pass'])) {
                $billingCredential->sol_pass = $validated['sol_pass'];
            }
            if (array_key_exists('client_secret', $validated)) {
                $billingCredential->client_secret = $validated['client_secret'];
            }

            $billingCredential->save();
        });

        return response()->json([
            'message' => 'Credenciales de facturación actualizadas exitosamente.',
            'data' => new BillingCredentialResource($billingCredential->fresh()),
        ]);
    }

    public function destroy(BillingCredential $billingCredential): JsonResponse
    {
        DB::transaction(function () use ($billingCredential) {
            if ($billingCredential->cert_path) {
                Storage::disk('local')->delete($billingCredential->cert_path);
            }
            $billingCredential->delete();
        });

        return response()->json([
            'message' => 'Credenciales de facturación eliminadas exitosamente.',
        ]);
    }

    public function toggleStatus(BillingCredential $billingCredential): JsonResponse
    {
        $billingCredential->update([
            'is_active' => ! $billingCredential->is_active,
        ]);

        return $this->success(new BillingCredentialResource($billingCredential->fresh()));
    }

    /**
     * Sube el archivo del certificado al disk privado del tenant y devuelve
     * la ruta relativa para almacenar en `cert_path`. Lanza si no hay archivo.
     */
    private function storeCertificate(Request $request): string
    {
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('cert_file');

        $extension = $file->getClientOriginalExtension() ?: 'pem';
        $filename = Str::uuid()->toString().'.'.$extension;

        // putFileAs devuelve la ruta relativa dentro del disk.
        return (string) Storage::disk('local')->putFileAs(
            self::CERT_DIRECTORY,
            $file,
            $filename,
        );
    }
}
