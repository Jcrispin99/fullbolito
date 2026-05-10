# Refactor: Movimientos unificados + Transferencias como Header

> **Decisión arquitectónica**: las transferencias entre almacenes pasan a modelarse como
> **dos `Movement`s pareados** (un `exit` en origen + un `entry` en destino) bajo un
> `Transfer` que actúa como **header**. Cada movimiento tiene su propio workflow de
> aprobación independiente.

---

## Modelo conceptual

### Tablas

| Tabla | Rol |
|---|---|
| `transfers` | Header / agrupador del par. Slim: solo metadata común (serie, observación, fecha, company). |
| `movements` | Tabla nueva. Una fila por entrada/salida en un almacén. Soporta ciclo formal con aprobación. |
| `productables` | Líneas. Hoy `productable_type=Transfer`, pasa a `productable_type=Movement`. |
| `inventories` | Kardex. `inventoryable_type=Movement` cuando un movement se "postea". |

### Operaciones

| Operación | Entries | Exits | Header |
|---|---|---|---|
| Compra | – (sigue por su flujo) | – | – |
| Venta | – (sigue por su flujo) | – | – |
| Transferencia | 1 entry (warehouse destino) | 1 exit (warehouse origen) | **1 transfer** |
| Entrada manual (stock inicial / hallazgo / producción) | 1 entry | – | – |
| Salida manual (merma / muestra / donación) | – | 1 exit | – |

> Compras y Ventas **no se tocan** en este refactor — siguen escribiendo al kardex con
> sus modelos polimórficos actuales. La unificación queda lista para absorberlos en el
> futuro si se desea.

### Estados del Movement

```
draft → submitted → posted    (camino feliz)
              ↓
            rejected           (devuelto al solicitante)

posted → cancelled              (reversión post-aprobación, escribe contra-asiento al kardex)
```

| Estado | Significado | Impacto kardex |
|---|---|---|
| `draft` | Edita el creador | Ninguno |
| `submitted` | Esperando aprobación del jefe del almacén | Ninguno |
| `posted` | Aprobado y aplicado | **Sí** — escribe Inventory + actualiza LotInventory |
| `rejected` | Rechazado por el aprobador, vuelve a editar | Ninguno (rejection_reason explica) |
| `cancelled` | Anula un `posted` | **Sí** — escribe contra-asiento (entry↔exit invertido) |

### Estado lógico del Transfer (derivado del par)

| Exit estado | Entry estado | Estado lógico |
|---|---|---|
| draft | draft | `draft` (Borrador) |
| submitted | draft/submitted | `pending_exit` (Esperando aprobación origen) |
| posted | draft/submitted | `in_transit` (En tránsito) ✨ nuevo |
| posted | posted | `completed` (Completada) |
| posted | rejected | `with_observation` (Recibida con observación) |
| cancelled / cancelled | * | `cancelled` |

> El campo `status` desaparece de `transfers` — se calcula en el Resource a partir del
> par de movements. Esto evita drift entre la verdad (movements) y el header.

---

## Schema detallado

### `movements` (NUEVA)

```php
Schema::create('movements', function (Blueprint $table) {
    $table->id();

    $table->enum('type', ['entry', 'exit']);
    $table->string('serie');
    $table->string('correlative');

    $table->timestamp('date')->useCurrent();

    $table->decimal('total', 12, 4)->default(0);
    $table->string('observation')->nullable();
    $table->string('reason')->nullable();   // 'transfer_exit', 'transfer_entry', 'merma', 'inicial', 'produccion', etc.

    $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();

    // Pareado con el header (null para movements sueltos: entradas/salidas manuales)
    $table->foreignId('transfer_id')->nullable()->constrained('transfers')->cascadeOnDelete();

    $table->enum('status', ['draft', 'submitted', 'posted', 'rejected', 'cancelled'])
        ->default('draft');

    $table->timestamp('submitted_at')->nullable();
    $table->timestamp('posted_at')->nullable();
    $table->timestamp('rejected_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();

    $table->foreignId('created_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('submitted_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('posted_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('rejected_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('cancelled_user_id')->nullable()->constrained('users')->nullOnDelete();

    $table->string('rejection_reason')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->index(['type', 'status']);
    $table->index(['warehouse_id', 'date']);
    $table->index(['company_id', 'date']);
    $table->index('transfer_id');
    $table->unique(['company_id', 'serie', 'correlative'], 'movement_unique_company_serie_corr');
});
```

### `transfers` (SLIM)

Drop:
- `from_warehouse_id`, `to_warehouse_id` → ahora viven como `warehouse_id` en cada movement
- `status`, `sent_at`, `received_at` → ahora vive en cada movement
- `sent_by_user_id`, `received_by_user_id` → ahora vive en cada movement

Add:
- `created_user_id` (auditoría del creador)

Mantiene: `serie`, `correlative`, `date`, `company_id`, `total`, `observation`, `timestamps`, `softDeletes`.

---

## Comportamiento por flujo

### Crear transferencia (header + 2 movements en draft)

1. POST `/transfers` con `from_warehouse_id`, `to_warehouse_id`, `products[]`, `observation`.
2. Backend crea:
   - 1 row en `transfers` (header con serie/correlative del journal `transfer`).
   - 1 row en `movements` (`type=exit`, `warehouse_id=from`, `transfer_id=N`, `status=draft`, `reason='transfer_exit'`).
   - 1 row en `movements` (`type=entry`, `warehouse_id=to`, `transfer_id=N`, `status=draft`, `reason='transfer_entry'`).
   - Líneas en `productables` apuntando a **cada movement** (duplicadas: las del exit y las del entry).
3. Se calcula `total` del header como suma de líneas.

### Aprobación del exit (envío)

1. POST `/movements/{exit}/submit` → `submitted`.
2. POST `/movements/{exit}/post` (jefe origen) → `posted`:
   - Valida stock disponible (incluye lotes si aplica).
   - Llama `KardexService::registerExit` por cada línea del exit.
   - Actualiza `LotInventory` del origen.
3. Si rechaza: POST `/movements/{exit}/reject?rejection_reason=...` → `rejected`.

### Aprobación del entry (recepción)

1. POST `/movements/{entry}/submit` → `submitted` (puede el destino editar cantidades antes de submit).
2. POST `/movements/{entry}/post` (jefe destino) → `posted`:
   - Llama `KardexService::registerEntry` por cada línea.
   - Si la línea tiene lote del exit, **vincula el mismo `lot_id`** al destino (no crea Lot nuevo, solo `LotInventory` para el destino).
   - Si la cantidad recibida < enviada → diferencia queda como sugerencia para crear movimiento de merma adicional (no se hace automático, lo decide el aprobador).

### Cancelación

- **Cancelar un `posted`**: llama a una función de `MovementService::cancel` que:
  - Crea las líneas inversas (no nuevos rows, pero llama `registerExit/Entry` con la dirección opuesta).
  - Marca el movement como `cancelled` con `cancelled_at`/`cancelled_user_id`.
- **Cancelar un Transfer**: cancela ambos movements (si aplica) en su orden inverso.

---

## Implicaciones para Lotes

| Caso | Comportamiento |
|---|---|
| Entrada manual con producto trazado | Pide `lot_number` + `expires_at` → crea `Lot` con `purchase_id=null` y `LotInventory` del almacén destino. |
| Salida manual con producto trazado | Pide `lot_id` (FEFO o picker) → consume `LotInventory` del origen. |
| Transferencia exit (origen) | Consume `LotInventory` del origen del lote elegido. |
| Transferencia entry (destino) | **No crea lote nuevo** — vincula el mismo `lot_id` que vino del exit, solo crea/actualiza `LotInventory` del destino. |

> Esto resuelve el caso "lotes fuera de compra" que conversamos antes: con un Movement
> `type=entry` con `reason='inicial'` o `'produccion'`, podemos crear lotes sin pasar
> por el flujo de Compras.

---

## Plan de fases

### Fase 1 — Backend base ✅

- [x] **MD plan** (este archivo)
- [x] Migración `movements` table
- [x] Migración slim `transfers` table (drop columnas)
- [x] Modelo `Movement` con relaciones, casts, scopes, helpers de estado
- [x] Modelo `Transfer` actualizado (sin status/from/to, con relaciones a exit/entry movement)
- [x] Agregar Journals `movement_entry` y `movement_exit` al `JournalSeeder`
- [x] Migraciones aplicadas + JournalSeeder re-ejecutado

### Fase 2 — Servicios ✅

- [x] `MovementService` con: `submit`, `post`, `reject`, `cancel`, `reopen` (rejected→draft)
- [x] `MovementService::post` integra `KardexService::registerEntry|registerExit` según `type`
- [x] `MovementService::cancel` escribe contra-asiento al kardex (entry↔exit invertido)
- [x] `assertLinesValid` y `assertStockAvailable` movidas a `MovementService`
- [x] `resolveLineCost` movido a `MovementService`
- [x] Refactor `TransferService` a orquestador con `create`, `update`, `send`, `receive`, `cancel`
- [x] `TransferService::create` genera header + 2 movements en draft con líneas duplicadas
- [x] `TransferService::send/receive` delegan a `MovementService::submit + post`
- [x] `TransferService::cancel` cancela movements en orden inverso (entry primero, exit después)
- [x] Smoke test: ambos servicios bootean vía contenedor; journals SAL/ENT existen

### Fase 3 — API HTTP ✅

- [x] `MovementController` (Resource controller + acciones submit/post/reject/cancel/reopen)
- [x] `MovementRequest` (validación)
- [x] `MovementResource`
- [x] Refactor `TransferController`: store crea header+2 movements; `send/receive/cancel` delegan a `TransferService` (que orquesta `MovementService` por debajo). Filtros por estado derivado vía `whereHas` sobre movements + alias legacy (`sent`→`in_transit`, `received`→`completed`).
- [x] `TransferResource` expone bloques `exit_movement` y `entry_movement` + status derivado + fields legacy (`from_warehouse_id`, `to_warehouse_id`, `sent_at`, `received_at`, `lines`)
- [x] Routes: `Route::apiResource('movements', ...)` + acciones de workflow (submit/post/reject/cancel/reopen) + `formOptions`
- [x] `config/activity_subjects.php`: agregado `'movement' => Movement::class`
- [x] Smoke test: `php artisan route:list` muestra 11 rutas movements + 10 transfers

### Fase 4 — Frontend Movements ✅

- [x] `stores/movement.ts` (Pinia) — CRUD + workflow: `submit/post/reject/cancel/reopen`
- [x] `views/Movements/Index.vue` — lista filtrable por `type` (entry/exit), `status` (5 estados), toggle "solo sueltos" para excluir movimientos de transfers; contadores por estado en meta
- [x] `views/Movements/Form.vue` — selector entry/exit, picker de lote (sólo para `exit` con producto trazado vía TransferLotModal), columna de costo unitario, validación de lote requerido para entries trazadas (con guidance al usuario)
- [x] `views/Movements/FormPage.vue` — wrapper con DashboardLayout, ActivityLogPanel(`subject="movement"`), botones de workflow (submit/post/reject/cancel), badge de estado, banner de aviso si el movimiento pertenece a una Transferencia (con link al header)
- [x] Routes en `tenant/router/index.ts`: `/admin/movements`, `/admin/movements/create`, `/admin/movements/:id/edit`

### Fase 5 — Frontend Transfers refactor ✅

- [x] `stores/transfer.ts`: tipos actualizados — nuevo `TransferStatus` (draft / pending_exit / in_transit / completed / with_observation / cancelled) con aliases legacy (sent/received), `MovementStatus`, `TransferEmbeddedMovement`, meta con `in_transit_total` / `completed_total`
- [x] `views/Transfers/Index.vue`: filter dropdown con todos los estados derivados (Borradores / Pend. Salida / **En tránsito** / Completadas / Con observación / Canceladas) + contadores; row actions agrupadas por estado; eliminado `Restaurar a borrador` (controller devuelve 422)
- [x] `views/Transfers/FormPage.vue`: panel del par con dos secciones **Salida (Origen)** + **Entrada (Destino)** mostrando estado individual de cada movement, fechas (submitted_at/posted_at), aprobador, link al MovementController, motivo de rechazo si existe; botones contextuales `canSend` / `canReceive` / `canCancel` derivados del par; status badge usa la nueva tabla
- [x] El form de creación sigue siendo el mismo (un solo formulario con from/to/productos) ⬅ ESTAMOS AQUÍ

### Fase 6 — Navegación ✅

- [x] Reorganizar nav: grupo **Movimientos** con submenús **Transferencias**, **Entradas**, **Salidas**
- [x] `config/navigation.ts`: reemplazado app `Transferencias` por grupo `Movimientos` (icon `ArrowLeftRight`, default `/admin/transfers`) con 3 submenús — `Transferencias` (`/admin/transfers`), `Entradas` (`/admin/movements?type=entry`), `Salidas` (`/admin/movements?type=exit`)
- [x] `router/index.ts`: rutas de transfers ahora usan `defaultApp: 'Movimientos'` (alineado con rutas de movements)
- [x] `views/Movements/Index.vue`: lee `route.query.type` al montar y observa cambios para resincronizar el filtro `currentType` cuando se navega entre submenús Entradas/Salidas
- [x] `components/AppHeader.vue`: nuevo helper `matchesUrl()` que parsea path + query, así `isDirectActive` resalta correctamente el submenú activo cuando la URL incluye `?type=entry|exit` (chequea subset match de query params)

---

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Romper el único Transfer existente (id=1, draft) | Borrarlo manualmente antes del migrate (es draft, no escribió kardex). |
| Productables apuntando a Transfer ahora deben apuntar a Movement | El único transfer existente es draft, sus líneas no impactaron nada → recreables. |
| Sales/Purchases siguen escribiendo `inventoryable_type=Sale|Purchase` | Sin cambio. El kardex es agnóstico al tipo morph. |
| Frontend rompe momentáneamente | Hacemos backend completo antes de tocar front; mantenemos `TransferController` con los endpoints viejos como atajos compatibles para no congelar la UI durante el refactor. |

---

## Decisiones tomadas

1. **Header table (Opción 2)** sobre self-FK o polymorphic originable. Razón: header tiene su propia identidad (serie, observación común, total) y querying es trivial.
2. **Status del Transfer es derivado**, no almacenado. Razón: evita drift; la verdad vive en los movements.
3. **No tocar Sales/Purchases** en este refactor. Razón: ya tienen su flujo, agregarlos sería un refactor mucho mayor sin beneficio inmediato.
4. **Líneas duplicadas** (productables apuntando a cada movement por separado). Razón: permite registrar cantidad enviada ≠ cantidad recibida (mermas en tránsito naturalmente).
5. **Cancelación = contra-asiento**, no UPDATE del kardex. Razón: el kardex es append-only por diseño.

---

## Checklist de verificación post-migración

- [ ] `php artisan tenants:migrate --fresh` corre sin error
- [ ] Seeders corren sin error (Lots/Sales/Purchases siguen funcionando)
- [ ] Crear transferencia desde el front genera 1 row en `transfers` + 2 en `movements` + 2N en `productables`
- [ ] Submit + post del exit → crea Inventory y actualiza LotInventory
- [ ] Submit + post del entry → crea Inventory y vincula mismo Lot al destino
- [ ] Cancel del exit posted → genera contra-asiento (entry equivalente)
- [ ] Movimiento `entry` suelto con lote crea Lot nuevo
- [ ] Movimiento `exit` suelto consume Lot existente
- [ ] Compras y Ventas siguen funcionando idénticamente
