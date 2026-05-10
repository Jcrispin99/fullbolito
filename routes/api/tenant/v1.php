<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\V1\ActivityController;
use App\Http\Controllers\Api\Tenant\V1\AppAddonController;
use App\Http\Controllers\Api\Tenant\V1\AttributeController;
use App\Http\Controllers\Api\Tenant\V1\BillingController;
use App\Http\Controllers\Api\Tenant\V1\BillingCredentialController;
use App\Http\Controllers\Api\Tenant\V1\BuilderBlockTypeController;
use App\Http\Controllers\Api\Tenant\V1\BuilderFormSubmissionController;
use App\Http\Controllers\Api\Tenant\V1\BuilderGlobalSectionController;
use App\Http\Controllers\Api\Tenant\V1\BuilderMediaController;
use App\Http\Controllers\Api\Tenant\V1\BuilderNavController;
use App\Http\Controllers\Api\Tenant\V1\BuilderPageController;
use App\Http\Controllers\Api\Tenant\V1\BuilderRedirectController;
use App\Http\Controllers\Api\Tenant\V1\BuilderSectionController;
use App\Http\Controllers\Api\Tenant\V1\BuilderSiteController;
use App\Http\Controllers\Api\Tenant\V1\BuilderTemplateController;
use App\Http\Controllers\Api\Tenant\V1\CategoryController;
use App\Http\Controllers\Api\Tenant\V1\CompanyController;
use App\Http\Controllers\Api\Tenant\V1\CustomerController;
use App\Http\Controllers\Api\Tenant\V1\ImportExportController;
use App\Http\Controllers\Api\Tenant\V1\ImportExportTemplateController;
use App\Http\Controllers\Api\Tenant\V1\ImportJobController;
use App\Http\Controllers\Api\Tenant\V1\LotAlertController;
use App\Http\Controllers\Api\Tenant\V1\LotController;
use App\Http\Controllers\Api\Tenant\V1\LoyaltyCardController;
use App\Http\Controllers\Api\Tenant\V1\LoyaltyProgramController;
use App\Http\Controllers\Api\Tenant\V1\LoyaltyTransactionController;
use App\Http\Controllers\Api\Tenant\V1\MovementController;
use App\Http\Controllers\Api\Tenant\V1\PosConfigController;
use App\Http\Controllers\Api\Tenant\V1\PaymentMethodController;
use App\Http\Controllers\Api\Tenant\V1\PermissionController;
use App\Http\Controllers\Api\Tenant\V1\PlanSwitchController;
use App\Http\Controllers\Api\Tenant\V1\PosCheckoutController;
use App\Http\Controllers\Api\Tenant\V1\PosLoyaltyController;
use App\Http\Controllers\Api\Tenant\V1\PosRefundController;
use App\Http\Controllers\Api\Tenant\V1\ProductProductController;
use App\Http\Controllers\Api\Tenant\V1\ProductTemplateController;
use App\Http\Controllers\Api\Tenant\V1\PosSessionController;
use App\Http\Controllers\Api\Tenant\V1\ReceiptTemplateController;
use App\Http\Controllers\Api\Tenant\V1\PublicSiteController;
use App\Http\Controllers\Api\Tenant\V1\RoleController;
use App\Http\Controllers\Api\Tenant\V1\PurchaseController;
use App\Http\Controllers\Api\Tenant\V1\SaleController;
use App\Http\Controllers\Api\Tenant\V1\SupplierController;
use App\Http\Controllers\Api\Tenant\V1\TaxController;
use App\Http\Controllers\Api\Tenant\V1\TenantAppController;
use App\Http\Controllers\Api\Tenant\V1\TransferController;
use App\Http\Controllers\Api\Tenant\V1\UbigeoController;
use App\Http\Controllers\Api\Tenant\V1\UnitOfMeasureController;
use App\Http\Controllers\Api\Tenant\V1\UserController;
use App\Http\Controllers\Api\Tenant\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant API V1 Routes
|--------------------------------------------------------------------------
|
| Rutas API versionadas SOLO para subdominios de tenants.
| Estas rutas NO están disponibles en el dominio central.
|
*/

Route::prefix('v1')->middleware(['auth:sanctum', 'company.filter'])->group(function () {

    Route::get('activity', [ActivityController::class, 'index'])->name('tenant.activity.index');

    // ─── Users / Roles / Permissions module ─────────────────────────────
    // TODO: re-añadir middleware('permission:X') cuando el frontend
    // maneje correctamente la ocultación de UI por permisos.
    Route::get('permissions', [PermissionController::class, 'index'])
        ->name('tenant.permissions.index');

    Route::get('roles/form-options', [RoleController::class, 'formOptions'])
        ->name('tenant.roles.form-options');
    Route::get('roles', [RoleController::class, 'index'])
        ->name('tenant.roles.index');
    Route::post('roles', [RoleController::class, 'store'])
        ->name('tenant.roles.store');
    Route::get('roles/{role}', [RoleController::class, 'show'])
        ->where('role', '[0-9]+')
        ->name('tenant.roles.show');
    Route::put('roles/{role}', [RoleController::class, 'update'])
        ->where('role', '[0-9]+')
        ->name('tenant.roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->where('role', '[0-9]+')
        ->name('tenant.roles.destroy');

    Route::get('users/form-options', [UserController::class, 'formOptions'])
        ->name('tenant.users.form-options');
    Route::get('users', [UserController::class, 'index'])
        ->name('tenant.users.index');
    Route::post('users', [UserController::class, 'store'])
        ->name('tenant.users.store');
    Route::get('users/{user}', [UserController::class, 'show'])
        ->where('user', '[0-9]+')
        ->name('tenant.users.show');
    Route::put('users/{user}', [UserController::class, 'update'])
        ->where('user', '[0-9]+')
        ->name('tenant.users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->where('user', '[0-9]+')
        ->name('tenant.users.destroy');

    // Apps catalog: always available (NOT gated by tenant.feature) so the
    // SPA can show what the tenant has + what they could upgrade to.
    Route::get('apps', [TenantAppController::class, 'index'])->name('tenant.apps.index');
    Route::post('apps/{key}/addon', [AppAddonController::class, 'attach'])->name('tenant.apps.addon.attach');
    Route::delete('apps/{key}/addon', [AppAddonController::class, 'detach'])->name('tenant.apps.addon.detach');
    Route::post('apps/plan', PlanSwitchController::class)->name('tenant.apps.plan.switch');

    // Billing self-service
    Route::get('billing', [BillingController::class, 'show'])->name('tenant.billing.show');
    Route::get('billing/plans', [BillingController::class, 'plans'])->name('tenant.billing.plans');
    Route::get('billing/invoices', [BillingController::class, 'invoices'])->name('tenant.billing.invoices');
    Route::post('billing/checkout', [BillingController::class, 'checkout'])->name('tenant.billing.checkout');
    Route::post('billing/portal', [BillingController::class, 'portal'])->name('tenant.billing.portal');

    Route::patch('companies/{company}/toggle-status', [CompanyController::class, 'toggleStatus'])->name('tenant.companies.toggle-status');
    Route::get('companies/form-options', [CompanyController::class, 'formOptions'])->name('tenant.companies.form-options');
    Route::post('companies/batch-delete', [CompanyController::class, 'batchDestroy'])->name('tenant.companies.batch-delete');
    Route::apiResource('companies', CompanyController::class);

    Route::patch('warehouses/{warehouse}/toggle-status', [WarehouseController::class, 'toggleStatus'])->name('tenant.warehouses.toggle-status');
    Route::get('warehouses/form-options', [WarehouseController::class, 'formOptions'])->name('tenant.warehouses.form-options');
    Route::post('warehouses/batch-delete', [WarehouseController::class, 'batchDestroy'])->name('tenant.warehouses.batch-delete');
    Route::apiResource('warehouses', WarehouseController::class);

    // Ubigeo INEI (catálogo central, read-only)
    Route::get('ubigeo/departments', [UbigeoController::class, 'departments'])
        ->name('tenant.ubigeo.departments');
    Route::get('ubigeo/departments/{department}/provinces', [UbigeoController::class, 'provinces'])
        ->name('tenant.ubigeo.provinces');
    Route::get('ubigeo/provinces/{province}/districts', [UbigeoController::class, 'districts'])
        ->name('tenant.ubigeo.districts');
    Route::get('ubigeo/resolve/{code}', [UbigeoController::class, 'resolve'])
        ->name('tenant.ubigeo.resolve');

    Route::patch('categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('tenant.categories.toggle-status');
    Route::get('categories/form-options', [CategoryController::class, 'formOptions'])->name('tenant.categories.form-options');
    Route::post('categories/batch-delete', [CategoryController::class, 'batchDestroy'])->name('tenant.categories.batch-delete');
    Route::apiResource('categories', CategoryController::class);

    Route::patch('attributes/{attribute}/toggle-status', [AttributeController::class, 'toggleStatus'])
        ->name('tenant.attributes.toggle-status');
    Route::post('attributes/batch-delete', [AttributeController::class, 'batchDestroy'])->name('tenant.attributes.batch-delete');
    Route::apiResource('attributes', AttributeController::class);

    Route::patch('unit-of-measures/{unit_of_measure}/toggle-status', [UnitOfMeasureController::class, 'toggleStatus'])
        ->name('tenant.unit-of-measures.toggle-status');
    Route::get('unit-of-measures/form-options', [UnitOfMeasureController::class, 'formOptions'])
        ->name('tenant.unit-of-measures.form-options');
    Route::post('unit-of-measures/batch-delete', [UnitOfMeasureController::class, 'batchDestroy'])->name('tenant.unit-of-measures.batch-delete');
    Route::apiResource('unit-of-measures', UnitOfMeasureController::class);

    Route::patch('taxes/{tax}/toggle-status', [TaxController::class, 'toggleStatus'])
        ->name('tenant.taxes.toggle-status');
    Route::get('taxes/form-options', [TaxController::class, 'formOptions'])
        ->name('tenant.taxes.form-options');
    Route::post('taxes/batch-delete', [TaxController::class, 'batchDestroy'])->name('tenant.taxes.batch-delete');
    Route::apiResource('taxes', TaxController::class);
    // Supplier routes
    Route::patch('suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])->name('tenant.suppliers.toggle-status');
    Route::get('suppliers/form-options', [SupplierController::class, 'formOptions'])->name('tenant.suppliers.form-options');
    Route::apiResource('suppliers', SupplierController::class);

    // Customer routes
    Route::patch('customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('tenant.customers.toggle-status');
    Route::get('customers/form-options', [CustomerController::class, 'formOptions'])->name('tenant.customers.form-options');
    Route::apiResource('customers', CustomerController::class);

    // ─── Import / Export module ─────────────────────────────────────────
    Route::prefix('import-export')->name('tenant.import-export.')->group(function () {
        Route::get('schemas', [ImportExportController::class, 'schemas'])->name('schemas.index');
        Route::get('schemas/{resource}', [ImportExportController::class, 'schema'])->name('schemas.show');
        Route::post('export', [ImportExportController::class, 'export'])->name('export');

        Route::get('templates', [ImportExportTemplateController::class, 'index'])->name('templates.index');
        Route::post('templates', [ImportExportTemplateController::class, 'store'])->name('templates.store');
        Route::patch('templates/{template}', [ImportExportTemplateController::class, 'update'])->name('templates.update');
        Route::delete('templates/{template}', [ImportExportTemplateController::class, 'destroy'])->name('templates.destroy');

        Route::get('imports', [ImportJobController::class, 'index'])->name('imports.index');
        Route::post('imports', [ImportJobController::class, 'store'])->name('imports.store');
        Route::get('imports/{uuid}', [ImportJobController::class, 'show'])->name('imports.show');
        Route::patch('imports/{uuid}', [ImportJobController::class, 'update'])->name('imports.update');
        Route::delete('imports/{uuid}', [ImportJobController::class, 'destroy'])->name('imports.destroy');
        Route::post('imports/{uuid}/run', [ImportJobController::class, 'run'])->name('imports.run');
        Route::get('imports/{uuid}/errors', [ImportJobController::class, 'downloadErrors'])->name('imports.errors');
    });

    // ─── Inventory module ───────────────────────────────────────────────
    Route::middleware('tenant.feature:inventory')->group(function () {
        Route::patch('product-templates/{product_template}/toggle-status', [ProductTemplateController::class, 'toggleStatus'])
            ->name('tenant.product-templates.toggle-status');
        Route::get('product-templates/form-options', [ProductTemplateController::class, 'formOptions'])
            ->name('tenant.product-templates.form-options');
        Route::apiResource('product-templates', ProductTemplateController::class);

        // Product Products Search
        Route::get('product-products/pos-catalog', [ProductProductController::class, 'posCatalog'])
            ->name('tenant.product-products.pos-catalog');
        Route::get('product-products/search', [ProductProductController::class, 'search'])
            ->name('tenant.product-products.search');
        Route::get('product-products/{productProduct}/available-lots', [LotController::class, 'availableForProduct'])
            ->name('tenant.product-products.available-lots');

        // Lots
        Route::get('lots/form-options', [LotController::class, 'formOptions'])
            ->name('tenant.lots.form-options');
        Route::get('lots/expiring', [LotController::class, 'expiring'])
            ->name('tenant.lots.expiring');
        Route::get('lots/expired', [LotController::class, 'expired'])
            ->name('tenant.lots.expired');
        Route::patch('lots/{lot}/toggle-status', [LotController::class, 'toggleStatus'])
            ->name('tenant.lots.toggle-status');
        Route::apiResource('lots', LotController::class)
            ->only(['index', 'show', 'update', 'destroy']);

        // Lot Alerts
        Route::get('lot-alerts/badge', [LotAlertController::class, 'badge'])
            ->name('tenant.lot-alerts.badge');
        Route::post('lot-alerts/mark-all-read', [LotAlertController::class, 'markAllRead'])
            ->name('tenant.lot-alerts.mark-all-read');
        Route::patch('lot-alerts/{lotAlert}/read', [LotAlertController::class, 'markRead'])
            ->name('tenant.lot-alerts.read');
        Route::get('lot-alerts', [LotAlertController::class, 'index'])
            ->name('tenant.lot-alerts.index');
        Route::delete('lot-alerts/{lotAlert}', [LotAlertController::class, 'destroy'])
            ->name('tenant.lot-alerts.destroy');

        // Movements (entradas/salidas de inventario)
        Route::get('movements/form-options', [MovementController::class, 'formOptions'])
            ->name('tenant.movements.form-options');
        Route::patch('movements/{movement}/submit', [MovementController::class, 'submit'])
            ->name('tenant.movements.submit');
        Route::patch('movements/{movement}/post', [MovementController::class, 'post'])
            ->name('tenant.movements.post');
        Route::patch('movements/{movement}/reject', [MovementController::class, 'reject'])
            ->name('tenant.movements.reject');
        Route::patch('movements/{movement}/cancel', [MovementController::class, 'cancel'])
            ->name('tenant.movements.cancel');
        Route::patch('movements/{movement}/reopen', [MovementController::class, 'reopen'])
            ->name('tenant.movements.reopen');
        Route::apiResource('movements', MovementController::class);
    });

    // ─── Purchases module ───────────────────────────────────────────────
    Route::middleware('tenant.feature:purchases')->group(function () {
    Route::patch('purchases/{purchase}/post', [PurchaseController::class, 'post'])
        ->name('tenant.purchases.post');
    Route::patch('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])
        ->name('tenant.purchases.cancel');
    Route::patch('purchases/{purchase}/draft', [PurchaseController::class, 'draft'])
        ->name('tenant.purchases.draft');
    Route::patch('purchases/{purchase}/pay', [PurchaseController::class, 'pay'])
        ->name('tenant.purchases.pay');
    Route::get('purchases/form-options', [PurchaseController::class, 'formOptions'])
        ->name('tenant.purchases.form-options');
    Route::get('purchases/{purchase}/lots', [PurchaseController::class, 'lots'])
        ->name('tenant.purchases.lots.show');
    Route::put('purchases/{purchase}/lots', [PurchaseController::class, 'updateLots'])
        ->name('tenant.purchases.lots.update');
    Route::apiResource('purchases', PurchaseController::class);
    });

    // ─── Transfers module ───────────────────────────────────────────────
    Route::middleware('tenant.feature:transfers')->group(function () {
    Route::get('transfers/form-options', [TransferController::class, 'formOptions'])
        ->name('tenant.transfers.form-options');
    Route::patch('transfers/{transfer}/send', [TransferController::class, 'send'])
        ->name('tenant.transfers.send');
    Route::patch('transfers/{transfer}/receive', [TransferController::class, 'receive'])
        ->name('tenant.transfers.receive');
    Route::patch('transfers/{transfer}/cancel', [TransferController::class, 'cancel'])
        ->name('tenant.transfers.cancel');
    Route::patch('transfers/{transfer}/draft', [TransferController::class, 'draft'])
        ->name('tenant.transfers.draft');

    // Guía de Remisión Electrónica (GRE) — fase 1
    Route::patch('transfers/{transfer}/gre', [TransferController::class, 'updateGre'])
        ->name('tenant.transfers.gre.update');
    Route::post('transfers/{transfer}/gre/send', [TransferController::class, 'sendGre'])
        ->name('tenant.transfers.gre.send');
    Route::post('transfers/{transfer}/gre/poll', [TransferController::class, 'pollGre'])
        ->name('tenant.transfers.gre.poll');
    Route::get('transfers/{transfer}/gre/xml', [TransferController::class, 'downloadGreXml'])
        ->name('tenant.transfers.gre.xml');
    Route::get('transfers/{transfer}/gre/cdr', [TransferController::class, 'downloadGreCdr'])
        ->name('tenant.transfers.gre.cdr');
    Route::get('transfers/{transfer}/gre/preview', [TransferController::class, 'preview'])
        ->name('tenant.transfers.gre.preview');
    Route::get('transfers/{transfer}/gre/pdf', [TransferController::class, 'pdf'])
        ->name('tenant.transfers.gre.pdf');

    Route::apiResource('transfers', TransferController::class);
    });

    // ─── POS module ─────────────────────────────────────────────────────
    Route::middleware('tenant.feature:pos')->group(function () {
    Route::post('pos-sessions/open', [PosSessionController::class, 'open'])
        ->name('tenant.pos-sessions.open');
    Route::patch('pos-sessions/{pos_session}/close', [PosSessionController::class, 'close'])
        ->name('tenant.pos-sessions.close');
    Route::apiResource('pos-sessions', PosSessionController::class)->only(['index', 'show']);
    Route::post('pos/checkout', [PosCheckoutController::class, 'store'])
        ->name('tenant.pos.checkout');
    Route::post('pos/refunds', [PosRefundController::class, 'store'])
        ->name('tenant.pos.refunds.store');
    Route::post('pos/loyalty/preview', [PosLoyaltyController::class, 'preview'])
        ->name('tenant.pos.loyalty.preview');

    Route::patch('pos-configs/{pos_config}/toggle-status', [PosConfigController::class, 'toggleStatus'])
        ->name('tenant.pos-configs.toggle-status');
    Route::get('pos-configs/form-options', [PosConfigController::class, 'formOptions'])
        ->name('tenant.pos-configs.form-options');
    Route::apiResource('pos-configs', PosConfigController::class);

    Route::get('receipt-template', [ReceiptTemplateController::class, 'show'])
        ->name('tenant.receipt-template.show');
    Route::put('receipt-template', [ReceiptTemplateController::class, 'update'])
        ->name('tenant.receipt-template.update');
    Route::post('receipt-template/reset', [ReceiptTemplateController::class, 'reset'])
        ->name('tenant.receipt-template.reset');
    Route::post('receipt-template/logo', [ReceiptTemplateController::class, 'uploadLogo'])
        ->name('tenant.receipt-template.logo.upload');
    Route::delete('receipt-template/logo', [ReceiptTemplateController::class, 'deleteLogo'])
        ->name('tenant.receipt-template.logo.delete');

    Route::patch('payment-methods/{payment_method}/toggle-status', [PaymentMethodController::class, 'toggleStatus'])
        ->name('tenant.payment-methods.toggle-status');
    Route::apiResource('payment-methods', PaymentMethodController::class);
    });

    // Billing Credentials (facturación electrónica - SUNAT) — base infra
    Route::patch('billing-credentials/{billing_credential}/toggle-status', [BillingCredentialController::class, 'toggleStatus'])
        ->name('tenant.billing-credentials.toggle-status');
    Route::apiResource('billing-credentials', BillingCredentialController::class);

    // ─── Sales module ───────────────────────────────────────────────────
    Route::middleware('tenant.feature:sales')->group(function () {
    Route::get('sales/form-options', [SaleController::class, 'formOptions'])
        ->name('tenant.sales.form-options');
    Route::patch('sales/{sale}/post', [SaleController::class, 'post'])
        ->name('tenant.sales.post');
    Route::patch('sales/{sale}/cancel', [SaleController::class, 'cancel'])
        ->name('tenant.sales.cancel');
    Route::patch('sales/{sale}/pay', [SaleController::class, 'pay'])
        ->name('tenant.sales.pay');
    Route::post('sales/{sale}/refunds', [SaleController::class, 'refund'])
        ->name('tenant.sales.refunds.store');
    Route::post('sales/{sale}/sunat/send', [SaleController::class, 'sendToSunat'])
        ->name('tenant.sales.sunat.send');
    Route::get('sales/{sale}/sunat/xml', [SaleController::class, 'downloadXml'])
        ->name('tenant.sales.sunat.xml');
    Route::get('sales/{sale}/sunat/cdr', [SaleController::class, 'downloadCdr'])
        ->name('tenant.sales.sunat.cdr');
    Route::get('sales/{sale}/sunat/preview', [SaleController::class, 'preview'])
        ->name('tenant.sales.sunat.preview');
    Route::get('sales/{sale}/sunat/pdf', [SaleController::class, 'pdf'])
        ->name('tenant.sales.sunat.pdf');
    Route::apiResource('sales', SaleController::class);
    });

    // ─── Website Builder module ─────────────────────────────────────────
    Route::middleware('tenant.feature:builder')->group(function () {
    // Catálogo de bloques
    Route::get('builder/block-types', [BuilderBlockTypeController::class, 'index'])
        ->name('tenant.builder.block-types.index');
    Route::get('builder/block-types/{key}', [BuilderBlockTypeController::class, 'show'])
        ->name('tenant.builder.block-types.show');

    // Site + Theme
    Route::get('builder/site', [BuilderSiteController::class, 'show'])
        ->name('tenant.builder.site.show');
    Route::put('builder/site', [BuilderSiteController::class, 'update'])
        ->name('tenant.builder.site.update');
    Route::put('builder/site/theme', [BuilderSiteController::class, 'updateTheme'])
        ->name('tenant.builder.site.theme.update');
    Route::put('builder/site/domain', [BuilderSiteController::class, 'setCustomDomain'])
        ->name('tenant.builder.site.domain.set');
    Route::post('builder/site/domain/verify', [BuilderSiteController::class, 'verifyDomain'])
        ->name('tenant.builder.site.domain.verify');
    Route::delete('builder/site/domain', [BuilderSiteController::class, 'removeDomain'])
        ->name('tenant.builder.site.domain.remove');

    // Pages
    Route::get('builder/pages/{page}/preview', [BuilderPageController::class, 'preview'])
        ->name('tenant.builder.pages.preview');
    Route::get('builder/pages/{page}/versions', [BuilderPageController::class, 'versions'])
        ->name('tenant.builder.pages.versions');
    Route::post('builder/pages/{page}/versions/{version}/revert', [BuilderPageController::class, 'revert'])
        ->name('tenant.builder.pages.versions.revert');
    Route::post('builder/pages/{page}/publish', [BuilderPageController::class, 'publish'])
        ->name('tenant.builder.pages.publish');
    Route::post('builder/pages/{page}/unpublish', [BuilderPageController::class, 'unpublish'])
        ->name('tenant.builder.pages.unpublish');
    Route::post('builder/pages/{page}/duplicate', [BuilderPageController::class, 'duplicate'])
        ->name('tenant.builder.pages.duplicate');
    Route::apiResource('builder/pages', BuilderPageController::class)
        ->names('tenant.builder.pages')
        ->parameters(['pages' => 'page']);

    // Sections (nested under pages)
    Route::put('builder/pages/{page}/sections/reorder', [BuilderSectionController::class, 'reorder'])
        ->name('tenant.builder.sections.reorder');
    Route::post('builder/pages/{page}/sections/{section}/duplicate', [BuilderSectionController::class, 'duplicate'])
        ->name('tenant.builder.sections.duplicate');
    Route::apiResource('builder/pages/{page}/sections', BuilderSectionController::class)
        ->names('tenant.builder.sections')
        ->parameters(['sections' => 'section']);

    // Media
    Route::apiResource('builder/media', BuilderMediaController::class)
        ->names('tenant.builder.media')
        ->parameters(['media' => 'asset'])
        ->except(['store']);
    Route::post('builder/media', [BuilderMediaController::class, 'store'])
        ->name('tenant.builder.media.store');

    // Navigation
    Route::get('builder/navs', [BuilderNavController::class, 'index'])
        ->name('tenant.builder.navs.index');
    Route::get('builder/navs/{location}', [BuilderNavController::class, 'show'])
        ->name('tenant.builder.navs.show');
    Route::put('builder/navs/{location}', [BuilderNavController::class, 'update'])
        ->name('tenant.builder.navs.update');
    Route::post('builder/navs/{location}/items', [BuilderNavController::class, 'storeItem'])
        ->name('tenant.builder.navs.items.store');
    Route::put('builder/navs/{location}/items/{item}', [BuilderNavController::class, 'updateItem'])
        ->name('tenant.builder.navs.items.update');
    Route::delete('builder/navs/{location}/items/{item}', [BuilderNavController::class, 'destroyItem'])
        ->name('tenant.builder.navs.items.destroy');
    Route::put('builder/navs/{location}/reorder', [BuilderNavController::class, 'reorderItems'])
        ->name('tenant.builder.navs.items.reorder');

    // Form Submissions
    Route::get('builder/form-submissions/stats', [BuilderFormSubmissionController::class, 'stats'])
        ->name('tenant.builder.form-submissions.stats');
    Route::get('builder/form-submissions/export', [BuilderFormSubmissionController::class, 'export'])
        ->name('tenant.builder.form-submissions.export');
    Route::post('builder/form-submissions/batch-delete', [BuilderFormSubmissionController::class, 'batchDestroy'])
        ->name('tenant.builder.form-submissions.batch-delete');
    Route::patch('builder/form-submissions/{submission}/read', [BuilderFormSubmissionController::class, 'markAsRead'])
        ->name('tenant.builder.form-submissions.read');
    Route::patch('builder/form-submissions/{submission}/archive', [BuilderFormSubmissionController::class, 'archive'])
        ->name('tenant.builder.form-submissions.archive');
    Route::apiResource('builder/form-submissions', BuilderFormSubmissionController::class)
        ->names('tenant.builder.form-submissions')
        ->parameters(['form-submissions' => 'submission'])
        ->only(['index', 'show', 'destroy']);

    // Global Sections
    Route::post('builder/global-sections/{section}/add-to-page', [BuilderGlobalSectionController::class, 'addToPage'])
        ->name('tenant.builder.global-sections.add-to-page');
    Route::apiResource('builder/global-sections', BuilderGlobalSectionController::class)
        ->names('tenant.builder.global-sections')
        ->parameters(['global-sections' => 'section']);

    // Redirects
    Route::apiResource('builder/redirects', BuilderRedirectController::class)
        ->names('tenant.builder.redirects')
        ->parameters(['redirects' => 'redirect']);

    // Templates
    Route::get('builder/templates', [BuilderTemplateController::class, 'index'])
        ->name('tenant.builder.templates.index');
    Route::get('builder/templates/{template}', [BuilderTemplateController::class, 'show'])
        ->name('tenant.builder.templates.show');
    Route::post('builder/templates/save', [BuilderTemplateController::class, 'saveAsTemplate'])
        ->name('tenant.builder.templates.save');
    Route::post('builder/templates/{template}/apply', [BuilderTemplateController::class, 'applyTemplate'])
        ->name('tenant.builder.templates.apply');
    Route::post('builder/site/duplicate', [BuilderTemplateController::class, 'duplicateSite'])
        ->name('tenant.builder.site.duplicate');
    });

    // ─── Loyalty module ─────────────────────────────────────────────────
    Route::middleware('tenant.feature:loyalty')->group(function () {
    // Programs (CRUD completo con rules + rewards inline)
    Route::patch('loyalty/programs/{program}/toggle-status', [LoyaltyProgramController::class, 'toggleStatus'])
        ->name('tenant.loyalty.programs.toggle-status');
    Route::post('loyalty/programs/batch-delete', [LoyaltyProgramController::class, 'batchDestroy'])
        ->name('tenant.loyalty.programs.batch-delete');
    Route::apiResource('loyalty/programs', LoyaltyProgramController::class)
        ->names('tenant.loyalty.programs')
        ->parameters(['programs' => 'program']);

    // Cards (tarjetas/cupones emitidos)
    Route::post('loyalty/cards/find-by-code', [LoyaltyCardController::class, 'findByCode'])
        ->name('tenant.loyalty.cards.find-by-code');
    Route::patch('loyalty/cards/{card}/toggle-status', [LoyaltyCardController::class, 'toggleStatus'])
        ->name('tenant.loyalty.cards.toggle-status');
    Route::apiResource('loyalty/cards', LoyaltyCardController::class)
        ->names('tenant.loyalty.cards')
        ->parameters(['cards' => 'card'])
        ->except(['update']);

    // Transactions (solo lectura + ajuste manual)
    Route::get('loyalty/transactions', [LoyaltyTransactionController::class, 'index'])
        ->name('tenant.loyalty.transactions.index');
    Route::post('loyalty/transactions/adjust', [LoyaltyTransactionController::class, 'adjust'])
        ->name('tenant.loyalty.transactions.adjust');

    // Simulate & Validate (para integración con ventas)
    Route::post('loyalty/simulate', [LoyaltyProgramController::class, 'simulate'])
        ->name('tenant.loyalty.simulate');
    Route::post('loyalty/validate-code', [LoyaltyProgramController::class, 'validateCode'])
        ->name('tenant.loyalty.validate-code');
    });
});
