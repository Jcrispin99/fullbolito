<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transfer
 */
final class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Transfer $transfer */
        $transfer = $this->resource;
        /** @var \App\Models\Movement|null $exit */
        $exit = $transfer->exitMovement;
        /** @var \App\Models\Movement|null $entry */
        $entry = $transfer->entryMovement;

        return [
            'id' => $this->id,
            'serie' => $this->serie,
            'correlative' => $this->correlative,
            'sequence_code' => "{$this->serie}-{$this->correlative}",
            'date' => $this->date->toIso8601String(),
            // Estado derivado del par exit+entry. Mantiene el mismo nombre
            // de campo que la API anterior para minimizar fricción en FE.
            'status' => $transfer->derivedStatus(),
            'total' => (float) $this->total,
            'observation' => $this->observation,

            'company_id' => $this->company_id,
            'created_user_id' => $this->created_user_id,

            // GRE — Guía de Remisión Electrónica (fase 1: motivo 04 privado).
            // Devolvemos el bloque completo siempre para que el FE pueda
            // mostrar el formulario de transporte en cuanto exista el header.
            'gre' => [
                'motive_code' => $this->gre_motive_code,
                'modality' => $this->gre_modality,
                'transfer_start_date' => $this->gre_transfer_start_date?->format('Y-m-d'),
                'gross_weight' => $this->gre_gross_weight !== null ? (float) $this->gre_gross_weight : null,
                'packages' => $this->gre_packages,
                'vehicle_plate' => $this->gre_vehicle_plate,
                'driver_doc_type' => $this->gre_driver_doc_type,
                'driver_doc_number' => $this->gre_driver_doc_number,
                'driver_license' => $this->gre_driver_license,
                'driver_name' => $this->gre_driver_name,
                'status' => $this->gre_status,
                'ticket' => $this->gre_ticket,
                'sent_at' => $this->gre_sent_at?->toIso8601String(),
                'response' => $this->gre_response,
                'has_signed_xml' => filled($this->gre_signed_xml_path),
                'has_cdr_zip' => filled($this->gre_cdr_zip_path),
            ],

            // Atajos al par de movements (fuente de verdad)
            'from_warehouse_id' => $exit?->warehouse_id,
            'to_warehouse_id' => $entry?->warehouse_id,
            'sent_at' => $exit?->posted_at?->toIso8601String(),
            'received_at' => $entry?->posted_at?->toIso8601String(),
            'sent_by_user_id' => $exit?->posted_user_id,
            'received_by_user_id' => $entry?->posted_user_id,

            // Relaciones del header
            'company' => new CompanyResource($this->whenLoaded('company')),
            'created_user' => new UserResource($this->whenLoaded('createdUser')),

            // Bloques de cada movement (fuente de verdad)
            'exit_movement' => $exit ? new MovementResource($exit) : null,
            'entry_movement' => $entry ? new MovementResource($entry) : null,

            // Atajos compatibles con el front actual
            'from_warehouse' => $exit?->relationLoaded('warehouse')
                ? new WarehouseResource($exit->warehouse)
                : null,
            'to_warehouse' => $entry?->relationLoaded('warehouse')
                ? new WarehouseResource($entry->warehouse)
                : null,
            // Líneas: tomamos las del exit (origen). El entry tiene una copia
            // de las mismas líneas con sus propias cantidades recibidas.
            'lines' => $exit?->relationLoaded('productables')
                ? ProductableResource::collection($exit->productables)
                : [],

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
