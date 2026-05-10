<?php

declare(strict_types=1);

namespace App\Providers;

use App\ImportExport\ImportExportRegistry;
use App\ImportExport\Schemas\AttributeSchema;
use App\ImportExport\Schemas\BillingCredentialSchema;
use App\ImportExport\Schemas\CategorySchema;
use App\ImportExport\Schemas\CompanySchema;
use App\ImportExport\Schemas\LotSchema;
use App\ImportExport\Schemas\LoyaltyCardSchema;
use App\ImportExport\Schemas\MovementLineSchema;
use App\ImportExport\Schemas\MovementSchema;
use App\ImportExport\Schemas\LoyaltyProgramSchema;
use App\ImportExport\Schemas\LoyaltyTransactionSchema;
use App\ImportExport\Schemas\PartnerSchema;
use App\ImportExport\Schemas\PaymentMethodSchema;
use App\ImportExport\Schemas\PosConfigSchema;
use App\ImportExport\Schemas\ProductTemplateSchema;
use App\ImportExport\Schemas\ProductVariantSchema;
use App\ImportExport\Schemas\PurchaseLineSchema;
use App\ImportExport\Schemas\PurchaseSchema;
use App\ImportExport\Schemas\SaleLineSchema;
use App\ImportExport\Schemas\SaleSchema;
use App\ImportExport\Schemas\TaxSchema;
use App\ImportExport\Schemas\TransferSchema;
use App\ImportExport\Schemas\UnitOfMeasureSchema;
use App\ImportExport\Schemas\WarehouseSchema;
use Illuminate\Support\ServiceProvider;

final class ImportExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ImportExportRegistry::class);
    }

    public function boot(): void
    {
        $registry = $this->app->make(ImportExportRegistry::class);

        $registry->register(new PartnerSchema());
        $registry->register(new CategorySchema());
        $registry->register(new TaxSchema());
        $registry->register(new UnitOfMeasureSchema());
        $registry->register(new PaymentMethodSchema());
        $registry->register(new WarehouseSchema());
        $registry->register(new CompanySchema());
        $registry->register(new BillingCredentialSchema());
        $registry->register(new PosConfigSchema());
        $registry->register(new LoyaltyProgramSchema());
        $registry->register(new LoyaltyCardSchema());
        $registry->register(new ProductTemplateSchema());
        $registry->register(new ProductVariantSchema());
        $registry->register(new AttributeSchema());
        $registry->register(new LotSchema());
        $registry->register(new LoyaltyTransactionSchema());
        $registry->register(new SaleSchema());
        $registry->register(new SaleLineSchema());
        $registry->register(new PurchaseSchema());
        $registry->register(new PurchaseLineSchema());
        $registry->register(new MovementSchema());
        $registry->register(new MovementLineSchema());
        $registry->register(new TransferSchema());
    }
}
