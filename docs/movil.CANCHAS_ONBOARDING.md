# Onboarding sin fricción para canchas — análisis (no implementado)

> Estrategia para atraer dueños de canchas deportivas a la plataforma sin pagar
> el costo de infraestructura de un tenant completo hasta que decidan usar la
> gestión real. Este documento es un análisis de arquitectura/producto, no
> refleja código existente salvo donde se indica explícitamente.

---

## El problema

Hoy, todo registro de tenant — incluso alguien que "solo quiere probar" —
provisiona una base de datos MySQL completa y aislada:

- `TenantRegistrationController::register()` ([app/Http/Controllers/Api/Central/V1/TenantRegistrationController.php](../app/Http/Controllers/Api/Central/V1/TenantRegistrationController.php))
  crea el `Tenant` inmediatamente, sin importar si el plan elegido es de pago o
  gratis.
- El evento `TenantCreated` dispara en cadena `Jobs\CreateDatabase` →
  `Jobs\MigrateDatabase` → `Jobs\SeedDatabase`
  ([app/Providers/TenancyServiceProvider.php:27-31](../app/Providers/TenancyServiceProvider.php#L27-L31)).
- El manager configurado es `MySQLDatabaseManager`
  ([config/tenancy.php:63](../config/tenancy.php#L63)): cada tenant es una BD
  MySQL física distinta, con todo el esquema migrado y sembrado.

Ya existe un plan `free-trial` (precio S/ 0, 14 días,
[database/seeders/PlanSeeder.php](../database/seeders/PlanSeeder.php)) que
evita el checkout de Mercado Pago — pero de todas formas crea la BD completa.
Al vencer el trial, `subscriptions:expire`
([app/Console/Commands/ExpireSubscriptionsCommand.php](../app/Console/Commands/ExpireSubscriptionsCommand.php))
marca la suscripción `expired`, y `CheckTenantSubscription`
([app/Http/Middleware/CheckTenantSubscription.php](../app/Http/Middleware/CheckTenantSubscription.php))
bloquea con 403 todo el acceso — es acceso total o corte total, sin zona
intermedia.

**Costo real**: cada curioso, cada "solo quiero ver de qué se trata", cada
comunidad que nunca termina de adoptar el sistema completo, deja una BD
huérfana ocupando espacio.

---

## Estrategia propuesta: dos fases

```
Fase 1 (central, sin tenant)          Fase 2 (tenant real)
┌─────────────────────────┐           ┌──────────────────────────┐
│ BusinessProfile (central)│  activar  │ Company (tenant DB)      │
│ CourtListing (central)   │ ────────► │ Court (tenant DB)        │
│ — CRUD multi-cancha      │  negocio  │ + motor de reservas real │
│ — disponibilidad         │           │ + BD dedicada            │
│   informativa (texto)    │           │ + plan (free u otro)     │
└─────────────────────────┘           └──────────────────────────┘
        │                                        │
        └──────────────┬─────────────────────────┘
                        ▼
              /canchas (marketplace central)
              unifica ambas fuentes por lat/lng
```

### Fase 1 — Perfil de negocio + canchas (central, sin tenant)

El dueño crea su cuenta central (esto **ya existe** vía
`AuthController::register`, es solo un `User`, costo cero) y con esa cuenta
administra su negocio directamente en tablas centrales — sin BD nueva:

- **Perfil de negocio** (equivalente central a `Company`): dueño (`user_id`),
  nombre del negocio, dirección/ubigeo, **latitud/longitud** (mismo par que ya
  tiene `Company` desde
  [database/migrations/tenant/2026_09_14_083656_add_coordinates_to_companies_table.php](../database/migrations/tenant/2026_09_14_083656_add_coordinates_to_companies_table.php)),
  contacto (WhatsApp/teléfono).
- **Canchas del perfil** (equivalente central a `Court`): CRUD real —
  nombre, deporte, superficie, capacidad, activa/inactiva. El dueño puede
  tener varias, no una sola ficha estática.

**Deliberadamente NO incluye** un motor de reservas transaccional. La
disponibilidad es informativa (texto libre o una grilla simple de
abierto/cerrado por franja horaria) — sin bloqueo de horarios, sin prevenir
dobles reservas, sin pagos online. Un visitante que ve la cancha la contacta
por WhatsApp y coordina directamente con el dueño.

Esto no es una limitación menor: es la razón de negocio para que alguien
decida pasar a Fase 2. Si Fase 1 ya resolviera reservas sin conflictos, nadie
tendría motivo para "activar su negocio", ni siquiera con un plan gratis.

### Compatibilidad con `/canchas` (marketplace central)

El endpoint agregador actual (`PublicMarketplaceController::courts()`) itera
tenants en vivo y arma un array `court` uniforme (ver
[app/Http/Controllers/Api/Central/V1/PublicMarketplaceController.php](../app/Http/Controllers/Api/Central/V1/PublicMarketplaceController.php)).
Ya fue extendido para incluir `company_latitude`/`company_longitude` y
`distance_km` (fórmula de Haversine) cuando el visitante manda su ubicación —
ver sección "Ya construido" abajo.

La propuesta es que ese mismo endpoint una **dos fuentes** en un solo listado:

1. Canchas reales de tenants provisionados (como hoy).
2. `CourtListing` de Fase 1 (una consulta simple a central, sin tocar
   tenancy).

Ambas se normalizan al mismo shape porque comparten los mismos campos (deporte,
superficie, capacidad, lat/lng), con un flag adicional `bookable_online:
true/false` que cambia el CTA en el frontend:

- Tenant real → "Ver disponibilidad" (flujo de reserva real, ya implementado
  vía `reserveUrl()` en
  [resources/js/central/views/Marketplace/index.vue](../resources/js/central/views/Marketplace/index.vue)).
- Ficha central → "Contactar por WhatsApp" (sin reserva online).

El filtro **"Cerca de mí"** (ya implementado, ver abajo) funciona igual para
ambas fuentes sin cambios, porque el cálculo de distancia solo necesita
lat/lng — no le importa si detrás hay un tenant o una ficha central.

### Fase 2 — Activar mi negocio

Cuando el dueño decide pasar a gestión real, un botón explícito ("Activar mi
negocio") dispara lo que hoy hace `TenantRegistrationController`: crea el
`Tenant` (BD real), y el dueño elige plan (el `free-trial` ya existente u
otro). La diferencia es que ahora **pre-llena** `Company`/`Court` del tenant
nuevo con los datos de su `BusinessProfile`/`CourtListing` de Fase 1 — como
los campos son literalmente los mismos, el traspaso es una copia directa, sin
transformación.

Las filas centrales de Fase 1 se marcan como "promovidas a tenant" para no
duplicarse en el listado de `/canchas`.

---

## Por qué esto encaja con la app móvil

El backend ya es una API pura (Sanctum + JSON resources) — una futura app
móvil no necesita backend nuevo, solo un cliente que hable con los mismos
endpoints que ya usa el SPA de Vue.

- **Fase 1 es un alcance ideal para una primera versión de app**: CRUD simple
  (agregar cancha, editar nombre/horario, ver mis canchas), sin la
  complejidad de sincronizar un calendario transaccional en tiempo real.
- **Lado deportista**: buscar/ver canchas cercanas (ya construido en central,
  incluyendo "Cerca de mí"), contactar o reservar según si la cancha es de
  Fase 1 (WhatsApp) o de un tenant activado (reserva real).

---

## Decisiones abiertas (sin resolver)

- **Fichas abandonadas**: sin sistema real detrás, una ficha puede quedar
  desactualizada. ¿Recordatorio periódico? ¿Moderación manual?
- **Trigger de "Activar mi negocio"**: ¿botón explícito en el panel del
  dueño, o se lo ofrecemos automáticamente después de cierto tráfico/tiempo
  en su ficha?
- **Duplicados**: si alguien ya tiene una ficha y crea un tenant por otra
  vía, ¿el alta de tenant absorbe la ficha existente o quedan ambas?
- **Verificación**: ¿la cuenta central necesita verificar email/teléfono
  antes de poder publicar una ficha pública, para evitar spam en
  `/canchas`?

---

## Estado actual vs. por construir

| Pieza | Estado |
|---|---|
| `latitude`/`longitude` en `Company` (tenant) | ✅ Implementado |
| Selector de mapa (Leaflet + OSM) en el form de sede | ✅ Implementado |
| `/api/v1/marketplace/courts` devuelve `company_latitude`/`company_longitude` | ✅ Implementado |
| Filtro `lat`/`lng` + orden por distancia (Haversine) + `distance_km` | ✅ Implementado |
| Botón "Cerca de mí" en `/canchas` (geolocalización del navegador) | ✅ Implementado |
| Plan `free-trial` sin checkout de Mercado Pago | ✅ Implementado (ya existía) |
| Modelo central `BusinessProfile` + `CourtListing` (Fase 1) | ⬜ Por construir |
| Unificación de fuentes en `PublicMarketplaceController` (tenant + Fase 1) | ⬜ Por construir |
| Flujo "Activar mi negocio" (Fase 1 → Fase 2, pre-llenado) | ⬜ Por construir |
| App móvil (gestión Fase 1 + búsqueda para deportistas) | ⬜ Por construir |
