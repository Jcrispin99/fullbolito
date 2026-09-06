<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Tenant;
use App\Models\UbigeoDepartment;
use App\Models\UbigeoDistrict;
use App\Models\UbigeoProvince;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Endpoints públicos del marketplace central (fullbolito.com).
 *
 * Estrategia v1: iterar en vivo todos los tenants, queryear cada uno,
 * y agregar los resultados. Funcional hasta ~50 tenants. Cuando escale,
 * migrar a un cache de disponibilidad en central DB.
 */
final class PublicMarketplaceController extends ApiController
{
    /**
     * Lista paginada de canchas agregadas de todos los tenants.
     *
     * Filtros:
     *   - ubigeo  : código jerárquico ('15' = depto, '1501' = prov, '150130' = distrito)
     *   - sport   : exacto (futbol, voley, ...)
     *   - search  : busca en name/slug/code
     *   - date    : YYYY-MM-DD (si se manda con time, filtra a las que tienen ese slot libre)
     *   - time    : HH:MM
     *   - duration: minutos (default usa el slot_duration de cada cancha)
     *
     * Paginación:
     *   - page (default 1)
     *   - per_page (default 20, max 100)
     */
    public function courts(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'ubigeo' => ['nullable', 'string', 'max:6'],
            'sport' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i', 'required_with:date'],
            'duration' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);

        $hasTimeFilter = ! empty($filters['date']) && ! empty($filters['time']);
        $slotStart = $hasTimeFilter
            ? Carbon::createFromFormat('Y-m-d H:i', "{$filters['date']} {$filters['time']}")
            : null;

        $all = collect();

        // Itera todos los tenants. Cada uno se inicializa, se queryea, se cierra.
        Tenant::query()
            ->with('domains')
            ->chunk(50, function (Collection $tenants) use ($filters, $slotStart, $hasTimeFilter, &$all) {
                foreach ($tenants as $tenant) {
                    try {
                        tenancy()->initialize($tenant);

                        $tenantCourts = $this->queryCourtsInTenant($filters, $slotStart, $hasTimeFilter);

                        foreach ($tenantCourts as $court) {
                            $all->push([
                                'tenant_id' => (string) $tenant->id,
                                'tenant_domain' => optional($tenant->domains->first())->domain,
                                'court' => $court,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        // No reventamos si un tenant falla, lo logueamos y seguimos.
                        report($e);
                    } finally {
                        tenancy()->end();
                    }
                }
            });

        // Ordena por nombre. (Después podemos cambiar a randomize, popularidad, etc.)
        $sorted = $all->sortBy(fn ($row) => mb_strtolower($row['court']['name']))->values();

        $total = $sorted->count();
        $items = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        // Resuelve nombres legibles del ubigeo (depto/provincia/distrito) en
        // un batch contra la DB central, después de cerrar tenancy.
        $items = $this->attachUbigeoNames($items);

        return $this->success([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'filters_applied' => array_filter([
                    'ubigeo' => $filters['ubigeo'] ?? null,
                    'sport' => $filters['sport'] ?? null,
                    'search' => $filters['search'] ?? null,
                    'date' => $filters['date'] ?? null,
                    'time' => $filters['time'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''),
                'resolved_ubigeo' => ! empty($filters['ubigeo'])
                    ? $this->resolveUbigeoLabel($filters['ubigeo'])
                    : null,
                'iterated_tenants' => Tenant::query()->count(),
            ],
        ]);
    }

    /**
     * Recibe la lista de courts agregados y les pega los nombres del
     * ubigeo (departamento/provincia/distrito) + un label listo para mostrar
     * en una sola pasada (batch de queries a las tablas centrales).
     *
     * Maneja códigos parciales: 2 chars (depto solo), 4 (depto+provincia),
     * 6 (depto+provincia+distrito). Si la company no tiene ubigeo, deja
     * los campos en null.
     *
     * @param  Collection<int, array{tenant_id:string,tenant_domain:?string,court:array<string,mixed>}>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function attachUbigeoNames(Collection $items): Collection
    {
        $codes = $items
            ->pluck('court.ubigeo')
            ->filter(fn ($u) => is_string($u) && $u !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($codes)) {
            return $items;
        }

        $deptIds = collect($codes)->map(fn ($c) => substr($c, 0, 2))->unique()->values()->all();
        $provIds = collect($codes)->filter(fn ($c) => strlen($c) >= 4)->map(fn ($c) => substr($c, 0, 4))->unique()->values()->all();
        $distIds = collect($codes)->filter(fn ($c) => strlen($c) === 6)->unique()->values()->all();

        $depts = UbigeoDepartment::query()->whereIn('id', $deptIds)->pluck('name', 'id');
        $provs = empty($provIds) ? collect() : UbigeoProvince::query()->whereIn('id', $provIds)->pluck('name', 'id');
        $dists = empty($distIds) ? collect() : UbigeoDistrict::query()->whereIn('id', $distIds)->pluck('name', 'id');

        return $items->map(function (array $row) use ($depts, $provs, $dists): array {
            $code = $row['court']['ubigeo'] ?? null;
            if (! is_string($code) || $code === '') {
                $row['court']['ubigeo_names'] = null;
                $row['court']['ubigeo_label'] = null;

                return $row;
            }

            $deptId = substr($code, 0, 2);
            $provId = strlen($code) >= 4 ? substr($code, 0, 4) : null;
            $distId = strlen($code) === 6 ? $code : null;

            $names = [
                'department' => $depts->get($deptId),
                'province' => $provId ? $provs->get($provId) : null,
                'district' => $distId ? $dists->get($distId) : null,
            ];

            $row['court']['ubigeo_names'] = $names;
            $row['court']['ubigeo_label'] = $this->buildUbigeoLabel($names);

            return $row;
        });
    }

    /**
     * @return array{department:?string,province:?string,district:?string,label:?string}|null
     */
    private function resolveUbigeoLabel(string $code): ?array
    {
        if ($code === '') {
            return null;
        }

        $deptId = substr($code, 0, 2);
        $provId = strlen($code) >= 4 ? substr($code, 0, 4) : null;
        $distId = strlen($code) === 6 ? $code : null;

        $names = [
            'department' => UbigeoDepartment::query()->whereKey($deptId)->value('name'),
            'province' => $provId ? UbigeoProvince::query()->whereKey($provId)->value('name') : null,
            'district' => $distId ? UbigeoDistrict::query()->whereKey($distId)->value('name') : null,
        ];

        if ($names['department'] === null) {
            return null;
        }

        return $names + ['label' => $this->buildUbigeoLabel($names)];
    }

    /**
     * Construye "San Borja, Lima" (distrito, depto) o "Lima provincia, Lima"
     * según qué niveles haya. Evita repetir el nombre si distrito = provincia
     * = depto (común en deptos chicos donde la capital coincide).
     *
     * @param  array{department:?string,province:?string,district:?string}  $names
     */
    private function buildUbigeoLabel(array $names): ?string
    {
        $parts = [];
        if (! empty($names['district'])) {
            $parts[] = $names['district'];
        }
        if (! empty($names['province']) && $names['province'] !== ($names['district'] ?? null)) {
            $parts[] = $names['province'];
        }
        if (! empty($names['department'])
            && $names['department'] !== ($names['province'] ?? null)
            && $names['department'] !== ($names['district'] ?? null)
        ) {
            $parts[] = $names['department'];
        }

        return empty($parts) ? null : implode(', ', $parts);
    }

    /**
     * Queryea las canchas del tenant actual aplicando filtros y, opcionalmente,
     * checando disponibilidad para un slot específico.
     *
     * @return list<array<string, mixed>>
     */
    private function queryCourtsInTenant(array $filters, ?Carbon $slotStart, bool $hasTimeFilter): array
    {
        $query = DB::connection('tenant')
            ->table('courts as c')
            ->leftJoin('companies as co', 'co.id', '=', 'c.company_id')
            ->leftJoin('product_products as pp', 'pp.id', '=', 'c.product_product_id')
            ->where('c.is_active', true);

        if (! empty($filters['sport'])) {
            $query->where('c.sport', $filters['sport']);
        }

        if (! empty($filters['ubigeo'])) {
            // Match jerárquico: '15' matchea todo Lima, '1501' Lima prov, '150130' San Borja
            $query->where('co.ubigeo', 'like', $filters['ubigeo'] . '%');
        }

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s): void {
                $q->where('c.name', 'like', "%{$s}%")
                    ->orWhere('c.slug', 'like', "%{$s}%")
                    ->orWhere('c.code', 'like', "%{$s}%");
            });
        }

        $courts = $query
            ->select([
                'c.id', 'c.name', 'c.slug', 'c.sport', 'c.surface', 'c.capacity',
                'c.slot_duration_minutes', 'c.description',
                'pp.price as base_price',
                'co.id as company_id', 'co.business_name as company_name',
                'co.ubigeo', 'co.address as company_address',
            ])
            ->orderBy('c.name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        if (! $hasTimeFilter) {
            return $courts;
        }

        // Filtrar a las canchas que tienen el slot libre en el momento solicitado.
        $duration = (int) ($filters['duration'] ?? 0);
        $available = [];

        foreach ($courts as $court) {
            $slotDuration = $duration > 0 ? $duration : (int) $court['slot_duration_minutes'];
            $slotEnd = $slotStart->copy()->addMinutes($slotDuration);

            if ($this->isSlotAvailable((int) $court['id'], $slotStart, $slotEnd)) {
                $court['available_at_requested_time'] = true;
                $court['requested_slot'] = [
                    'start' => $slotStart->toIso8601String(),
                    'end' => $slotEnd->toIso8601String(),
                ];
                $available[] = $court;
            }
        }

        return $available;
    }

    /**
     * Verifica si un slot está dentro de un schedule activo del court Y no
     * solapa con ninguna reserva que esté bloqueando ahora mismo.
     */
    private function isSlotAvailable(int $courtId, Carbon $start, Carbon $end): bool
    {
        $dayOfWeek = (int) $start->dayOfWeek;
        $startTime = $start->format('H:i:s');
        $endTime = $end->format('H:i:s');

        // Hay schedule activo que cubre [start, end] ese día.
        $scheduleExists = DB::connection('tenant')
            ->table('court_schedules')
            ->where('court_id', $courtId)
            ->where('is_active', true)
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>=', $endTime)
            ->exists();

        if (! $scheduleExists) {
            return false;
        }

        // No hay reserva bloqueando el slot.
        $conflict = DB::connection('tenant')
            ->table('reservations')
            ->where('court_id', $courtId)
            ->where(function ($q) {
                $q->whereIn('status', ['confirmed', 'paid', 'played'])
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'held')
                            ->where('held_until', '>', now());
                    });
            })
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->exists();

        return ! $conflict;
    }
}
