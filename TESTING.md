# Testing Guide — saas_base

Plan completo de testing para la API Laravel 12 multi-tenant (stancl/tenancy v3) con Pest 4.
Este documento describe la estrategia, la fundación de la suite y el checklist por módulo
para llegar a una cobertura razonable sin romperse contra SUNAT, tenancy o el kardex FIFO.

---

## 1. Stack & convenciones

- **Framework de tests**: Pest 4 + `pest-plugin-laravel` (`composer.json`).
- **Runner**: `composer test` corre `lint` (pint+rector dry-run) → `types` (phpstan) → `unit` (pest).
- **Solo Pest**: `vendor/bin/pest` o `php artisan test`.
- **DB en tests**: SQLite `:memory:` (ver `phpunit.xml`). Para tenancy se usa SQLite por archivo en directorio temporal del test (la conexión central queda en memoria).
- **Estilo**: archivos `*Test.php` en `tests/Feature/...` o `tests/Unit/...`. Estructura `describe()` + `it()` (no `test()`), strict types arriba.

### Layout

```
tests/
├── Pest.php                       # binding global, helpers, expectations
├── TestCase.php                   # base abstracta (central / sin tenancy)
├── TenantTestCase.php             # base para tests que corren dentro de un tenant
├── Concerns/
│   ├── InteractsWithTenancy.php   # bootstrap tenant, login, headers
│   ├── MocksGreenter.php          # fakes para SUNAT (invoice + GRE)
│   └── BuildsSaleScenario.php     # builders para venta/POS de extremo a extremo
├── Unit/
│   ├── Services/
│   │   ├── KardexServiceTest.php
│   │   ├── SequenceServiceTest.php
│   │   └── ... (ver §6)
│   └── Models/                    # invariantes de modelos (scopes, casts)
└── Feature/
    ├── Api/
    │   ├── V1/                    # central API (auth global)
    │   │   └── AuthTest.php       # ya existe
    │   └── Tenant/V1/             # tenant API (todo lo demás)
    │       ├── SaleTest.php
    │       ├── TransferTest.php
    │       ├── PurchaseTest.php
    │       ├── MovementTest.php
    │       ├── PosCheckoutTest.php
    │       ├── PosSessionTest.php
    │       ├── CompanyTest.php
    │       ├── WarehouseTest.php
    │       ├── PartnerTest.php
    │       ├── ProductTemplateTest.php
    │       ├── LotTest.php
    │       ├── BillingCredentialTest.php
    │       ├── LoyaltyProgramTest.php
    │       ├── PosCheckoutLoyaltyTest.php
    │       ├── Builder/            # 10+ archivos del Website Builder
    │       └── ...
    └── Middleware/
        ├── ParseCompanyFilterTest.php
        └── CheckTenantSubscriptionTest.php
```

---

## 2. Tenancy en tests — qué hace falta

`stancl/tenancy` necesita 3 cosas para funcionar en tests:

1. **Base de datos central** con migraciones de `database/migrations/` (tenants, domains, users, billing).
2. **Crear el tenant** (`Tenant::create([...])`) que dispara el `TenantDatabaseManager` y crea su propia BD.
3. **Inicializar la tenancy** (`tenancy()->initialize($tenant)`) para que las queries de Eloquent vayan a la BD del tenant.

### Decisiones tomadas

- **Conexión central**: SQLite `:memory:` (ya configurado en `phpunit.xml`). Cada proceso de tests recrea el esquema central con `RefreshDatabase`.
- **Conexión tenant**: SQLite por archivo en `storage/framework/testing/tenants/<tenant_id>.sqlite`. **Razón**: `:memory:` no se comparte entre conexiones; tenancy abre una conexión nueva al inicializar y perdería el esquema.
- **Limpieza**: cada test que use tenant ejecuta `tenancy()->end()` y borra el archivo en el `afterEach`.
- **Migraciones tenant**: corren con `php artisan tenants:migrate --tenants=<id>` o programáticamente con `Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]])`.

### `TenantTestCase`

Hace tres cosas en `setUp`:
1. Migra la BD central (vía `RefreshDatabase`).
2. Crea un `Tenant` + `Domain` + `User` propietario.
3. Inicializa la tenancy y migra la BD tenant.

Todo test que herede de `TenantTestCase` ya está dentro del tenant: `User::factory()->create()` crea en la BD del tenant.

### Helpers globales (`Pest.php`)

- `actingAsTenantUser($user = null, ?array $companies = null)` — crea/usa user, le pega Sanctum, opcionalmente setea header `X-Company-Ids`.
- `tenantPost($uri, $data)` / `tenantGetJson($uri)` — wrappers que ya cargan el header.
- `mockGreenterInvoice(bool $success = true)` — bindea un fake de `GreenterInvoiceService`.
- `mockGreenterDespatch(bool $success = true, string $ticket = 'TICKET-1')` — fake de `GreenterDespatchService`.

---

## 3. Mocking de SUNAT (Greenter)

SUNAT no se debe golpear ni en tests ni en CI. Los servicios `GreenterInvoiceService` y `GreenterDespatchService` se sustituyen vía el contenedor:

```php
$this->app->bind(GreenterInvoiceService::class, fn () => new FakeGreenterInvoiceService());
```

Los fakes (en `tests/Doubles/`) implementan los mismos métodos públicos pero:
- Persisten `sunat_status = 'accepted'` (o `'error'` cuando se pide) sin tocar la red.
- Generan archivos vacíos en `storage_path('app/billing/...')` para que `downloadXml` / `downloadCdr` no fallen.
- No firman; no requieren `BillingCredential` real.

Esto permite probar los flujos de controlador (estados, persistencia, respuesta JSON) sin certificados.

---

## 4. Factories — qué crear

Hoy solo existe `UserFactory`. Para llegar a la suite se crea (en `database/factories/`):

| Factory | Estados extra |
|---|---|
| `CompanyFactory` | `active()`, `branch()` |
| `WarehouseFactory` | `withCompany(Company $c)` |
| `PartnerFactory` | `customer()`, `supplier()`, `dual()` |
| `ProductTemplateFactory` | `withLotTracking()`, `service()` |
| `ProductProductFactory` | `withTemplate()`, `principal()` |
| `TaxFactory` | `igv()`, `exempt()`, `default()` |
| `UnitOfMeasureFactory` | `unit()` |
| `PaymentMethodFactory` | `cash()`, `card()` |
| `JournalFactory` | `boleta()`, `factura()`, `gre()` |
| `SequenceFactory` | `withJournal(Journal $j)` |
| `BillingCredentialFactory` | `sandbox()`, `production()` |
| `PosConfigFactory` | `withWarehouse()`, `applyTax()` |
| `PosSessionFactory` | `opened()`, `closed()` |
| `SaleFactory` | `draft()`, `posted()`, `cancelled()`, `paid()` |
| `PurchaseFactory` | `draft()`, `posted()`, `cancelled()` |
| `TransferFactory` | `draft()`, `inTransit()`, `completed()`, `withGre()` |
| `MovementFactory` | `entry()`, `exit()`, `posted()` |
| `LotFactory` | `expired()`, `expiringSoon()`, `active()` |

Cada factory genera datos coherentes (no inventa FKs); los tests que necesiten una venta completa usan **builders** en `tests/Concerns/BuildsSaleScenario.php` para no repetir el setup.

---

## 5. Convenciones de tests Feature (tenant API)

Cada `*Test.php` de un controller tenant sigue el mismo patrón:

```php
uses(TenantTestCase::class);

beforeEach(function () {
    [$this->company, $this->warehouse] = setupCompanyAndWarehouse();
    actingAsTenantUser(companies: [$this->company->id]);
});

describe('index', function () {
    it('lista paginado', /* ... */);
    it('filtra por company_id via header', /* ... */);
    it('rechaza sin sanctum', /* ... */);
});

describe('store', function () { /* validaciones + happy path */ });
describe('update', function () { /* ... */ });
describe('destroy', function () { /* soft-delete o cascade */ });
describe('formOptions', function () { /* ... */ });
```

Para acciones de estado (`post`, `cancel`, `pay`, `send`, `receive`, etc.) un `describe` por acción con:
- transición válida
- transición inválida (estado origen no permitido)
- efecto colateral (kardex, sunat_status, lealtad — según corresponda)

---

## 6. Checklist por módulo

> **Leyenda**: ✅ entregado en esta primera ronda · ⏳ documentado con plantilla, pendiente

### Auth & central
- ✅ `Feature/Api/V1/AuthTest.php` (ya existía, no se toca)
- ⏳ `Feature/Api/V1/EmailVerificationTest.php` (existe)
- ⏳ `Feature/Api/V1/PasswordResetTest.php` (existe)
- ✅ `Feature/Api/Tenant/V1/AuthTenantTest.php` — login/me bajo subdominio

### Catálogos (CRUD + toggle-status + form-options + batch-delete)
- ⏳ CompanyTest, WarehouseTest, CategoryTest, AttributeTest, UnitOfMeasureTest, TaxTest
- ⏳ SupplierTest, CustomerTest, ProductTemplateTest, ProductProductTest
- ⏳ PaymentMethodTest, PosConfigTest

### Inventario
- ⏳ LotTest (CRUD + expiring + expired + toggle-status)
- ⏳ LotAlertTest (badge, mark-read, mark-all-read)
- ✅ `Unit/Services/KardexServiceTest.php` — entradas, salidas FIFO/FEFO, lotes, no-lot remainder

### Movimientos & transferencias
- ⏳ MovementTest (CRUD + submit + post + reject + cancel + reopen)
- ✅ `Feature/Api/Tenant/V1/TransferTest.php` (CRUD + send + receive + cancel + GRE mocked)

### Compras
- ✅ `Feature/Api/Tenant/V1/PurchaseTest.php` (CRUD + post + cancel + draft + pay + lots + updateLots)

### Ventas & POS
- ✅ `Feature/Api/Tenant/V1/SaleTest.php` (CRUD + post + cancel + pay + creditNote + sendToSunat fake)
- ✅ `Feature/Api/Tenant/V1/PosCheckoutTest.php` (open session + checkout + close)
- ⏳ PosSessionTest (open/close por separado, edge cases)
- ⏳ PosLoyaltyTest (preview)

### Facturación electrónica
- ⏳ BillingCredentialTest (CRUD + toggle-status, sin enviar a SUNAT)
- ✅ Tests de `sendToSunat` y GRE viven dentro de SaleTest / TransferTest con fakes

### Loyalty
- ⏳ LoyaltyProgramTest (CRUD + toggle + simulate + validateCode)
- ⏳ LoyaltyCardTest (CRUD + find-by-code)
- ⏳ LoyaltyTransactionTest (index + adjust)

### Website Builder (10+ controllers)
- ⏳ Una sola sesión para builder; recomendación: archivo `Builder/SiteTest.php`, `Builder/PageTest.php`, `Builder/SectionTest.php`, `Builder/MediaTest.php`, `Builder/NavTest.php`, `Builder/RedirectTest.php`, `Builder/TemplateTest.php`, `Builder/FormSubmissionTest.php`, `Builder/GlobalSectionTest.php`.
- Validaciones de schema → `Unit/Services/BlockSchemaValidatorTest.php`.

### Middleware
- ⏳ `ParseCompanyFilterTest` (header CSV, ids inválidos, sin header)
- ⏳ `CheckTenantSubscriptionTest` (sin suscripción → 402, etc.)

### Catálogo central read-only
- ⏳ UbigeoTest (departments / provinces / districts / resolve)

---

## 7. Cómo correr la suite

```bash
# todo
composer test

# solo pest
vendor/bin/pest

# un archivo / un describe
vendor/bin/pest tests/Feature/Api/Tenant/V1/SaleTest.php
vendor/bin/pest --filter="post a draft sale"

# con cobertura (requiere xdebug/pcov)
vendor/bin/pest --coverage --min=70
```

---

## 8. Anti-patrones a evitar

- **No mockear KardexService**: la lógica FIFO/FEFO + lotes es el corazón del sistema. Tests de Sale/Purchase/Transfer corren contra el servicio real con factories.
- **No golpear SUNAT**: siempre `mockGreenterInvoice()` / `mockGreenterDespatch()`.
- **No usar `:memory:` para tenants**: crear archivo SQLite por tenant en `storage/framework/testing/tenants/`.
- **No reutilizar tenants entre tests**: cada test crea/destruye su tenant para aislamiento total.
- **No olvidar `X-Company-Ids`**: si un endpoint hace `companyFiltered()`, el test debe enviar el header (helper `actingAsTenantUser` lo hace).

---

## 9. Roadmap

1. **Ronda 1 (esta entrega)** — fundación + tests críticos (Sale, Transfer, Purchase, POS, Kardex, Auth tenant).
2. **Ronda 2** — catálogos (Company, Warehouse, Partner, Product, Tax, UOM, etc.).
3. **Ronda 3** — Loyalty (Programs/Cards/Transactions) + Movement + Lot + BillingCredential + Ubigeo + middleware.
4. **Ronda 4** — Website Builder completo (10+ archivos) + BlockSchemaValidator.
5. **Ronda 5** — meta tests (rendimiento de queries N+1, cobertura mínima, snapshot de respuestas SUNAT).
