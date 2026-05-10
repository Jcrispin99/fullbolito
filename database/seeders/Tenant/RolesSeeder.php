<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolesSeeder extends Seeder
{
    /**
     * Permisos agrupados por módulo/recurso. Derivados directamente de
     * routes/api/tenant/v1.php — si agregas una ruta, agrega su permiso aquí.
     *
     * @return array<string, list<string>>
     */
    private function permissionGroups(): array
    {
        return [
            'general' => [
                'access_dashboard',
                'read_activity',
            ],

            'apps' => [
                'read_apps',
                'attach_app_addon',
                'detach_app_addon',
                'switch_apps_plan',
            ],

            'billing' => [
                'read_billing',
                'read_billing_plans',
                'read_billing_invoices',
                'create_billing_checkout',
                'create_billing_portal',
            ],

            'users' => [
                'create_users',
                'read_users',
                'update_users',
                'delete_users',
            ],

            'roles' => [
                'create_roles',
                'read_roles',
                'update_roles',
                'delete_roles',
            ],

            'permissions' => [
                'create_permissions',
                'read_permissions',
                'update_permissions',
                'delete_permissions',
            ],

            'companies' => [
                'create_companies',
                'read_companies',
                'update_companies',
                'delete_companies',
                'toggle_companies',
                'batch_delete_companies',
            ],

            'warehouses' => [
                'create_warehouses',
                'read_warehouses',
                'update_warehouses',
                'delete_warehouses',
                'toggle_warehouses',
                'batch_delete_warehouses',
            ],

            'ubigeo' => [
                'read_ubigeo',
            ],

            'categories' => [
                'create_categories',
                'read_categories',
                'update_categories',
                'delete_categories',
                'toggle_categories',
                'batch_delete_categories',
            ],

            'attributes' => [
                'create_attributes',
                'read_attributes',
                'update_attributes',
                'delete_attributes',
                'toggle_attributes',
                'batch_delete_attributes',
            ],

            'unit_of_measures' => [
                'create_unit_of_measures',
                'read_unit_of_measures',
                'update_unit_of_measures',
                'delete_unit_of_measures',
                'toggle_unit_of_measures',
                'batch_delete_unit_of_measures',
            ],

            'taxes' => [
                'create_taxes',
                'read_taxes',
                'update_taxes',
                'delete_taxes',
                'toggle_taxes',
                'batch_delete_taxes',
            ],

            'suppliers' => [
                'create_suppliers',
                'read_suppliers',
                'update_suppliers',
                'delete_suppliers',
                'toggle_suppliers',
            ],

            'customers' => [
                'create_customers',
                'read_customers',
                'update_customers',
                'delete_customers',
                'toggle_customers',
            ],

            'import_export' => [
                'read_import_export_schemas',
                'create_export',
                'create_import_export_templates',
                'read_import_export_templates',
                'update_import_export_templates',
                'delete_import_export_templates',
                'create_import_jobs',
                'read_import_jobs',
                'update_import_jobs',
                'delete_import_jobs',
                'run_import_jobs',
                'download_import_errors',
            ],

            'product_templates' => [
                'create_product_templates',
                'read_product_templates',
                'update_product_templates',
                'delete_product_templates',
                'toggle_product_templates',
            ],

            'product_products' => [
                'read_product_products',
                'read_pos_catalog',
                'search_product_products',
            ],

            'lots' => [
                'read_lots',
                'update_lots',
                'delete_lots',
                'toggle_lots',
                'read_expiring_lots',
                'read_expired_lots',
            ],

            'lot_alerts' => [
                'read_lot_alerts',
                'delete_lot_alerts',
                'mark_lot_alerts_read',
            ],

            'movements' => [
                'create_movements',
                'read_movements',
                'update_movements',
                'delete_movements',
                'submit_movements',
                'post_movements',
                'reject_movements',
                'cancel_movements',
                'reopen_movements',
            ],

            'purchases' => [
                'create_purchases',
                'read_purchases',
                'update_purchases',
                'delete_purchases',
                'post_purchases',
                'cancel_purchases',
                'draft_purchases',
                'pay_purchases',
                'manage_purchase_lots',
            ],

            'transfers' => [
                'create_transfers',
                'read_transfers',
                'update_transfers',
                'delete_transfers',
                'send_transfers',
                'receive_transfers',
                'cancel_transfers',
                'draft_transfers',
                'update_transfers_gre',
                'send_transfers_gre',
                'poll_transfers_gre',
                'download_transfers_gre',
            ],

            'pos' => [
                'access_pos',
                'create_pos_sessions',
                'read_pos_sessions',
                'open_pos_sessions',
                'close_pos_sessions',
                'process_pos_checkout',
                'process_pos_refunds',
                'preview_pos_loyalty',
            ],

            'pos_configs' => [
                'create_pos_configs',
                'read_pos_configs',
                'update_pos_configs',
                'delete_pos_configs',
                'toggle_pos_configs',
            ],

            'receipt_templates' => [
                'read_receipt_template',
                'update_receipt_template',
                'reset_receipt_template',
                'manage_receipt_template_logo',
            ],

            'payment_methods' => [
                'create_payment_methods',
                'read_payment_methods',
                'update_payment_methods',
                'delete_payment_methods',
                'toggle_payment_methods',
            ],

            'billing_credentials' => [
                'create_billing_credentials',
                'read_billing_credentials',
                'update_billing_credentials',
                'delete_billing_credentials',
                'toggle_billing_credentials',
            ],

            'sales' => [
                'create_sales',
                'read_sales',
                'update_sales',
                'delete_sales',
                'post_sales',
                'cancel_sales',
                'pay_sales',
                'refund_sales',
                'send_sales_sunat',
                'download_sales_sunat',
            ],

            'builder_site' => [
                'read_builder_block_types',
                'read_builder_site',
                'update_builder_site',
                'update_builder_theme',
                'manage_builder_domain',
                'duplicate_builder_site',
            ],

            'builder_pages' => [
                'create_builder_pages',
                'read_builder_pages',
                'update_builder_pages',
                'delete_builder_pages',
                'publish_builder_pages',
                'unpublish_builder_pages',
                'duplicate_builder_pages',
                'revert_builder_pages',
                'preview_builder_pages',
            ],

            'builder_sections' => [
                'create_builder_sections',
                'read_builder_sections',
                'update_builder_sections',
                'delete_builder_sections',
                'reorder_builder_sections',
                'duplicate_builder_sections',
            ],

            'builder_media' => [
                'create_builder_media',
                'read_builder_media',
                'update_builder_media',
                'delete_builder_media',
            ],

            'builder_navs' => [
                'read_builder_navs',
                'update_builder_navs',
            ],

            'builder_form_submissions' => [
                'read_builder_form_submissions',
                'delete_builder_form_submissions',
                'export_builder_form_submissions',
                'archive_builder_form_submissions',
                'mark_builder_form_submissions_read',
                'batch_delete_builder_form_submissions',
            ],

            'builder_global_sections' => [
                'create_builder_global_sections',
                'read_builder_global_sections',
                'update_builder_global_sections',
                'delete_builder_global_sections',
            ],

            'builder_redirects' => [
                'create_builder_redirects',
                'read_builder_redirects',
                'update_builder_redirects',
                'delete_builder_redirects',
            ],

            'builder_templates' => [
                'read_builder_templates',
                'save_builder_templates',
                'apply_builder_templates',
            ],

            'loyalty_programs' => [
                'create_loyalty_programs',
                'read_loyalty_programs',
                'update_loyalty_programs',
                'delete_loyalty_programs',
                'toggle_loyalty_programs',
                'batch_delete_loyalty_programs',
                'simulate_loyalty',
                'validate_loyalty_code',
            ],

            'loyalty_cards' => [
                'create_loyalty_cards',
                'read_loyalty_cards',
                'delete_loyalty_cards',
                'toggle_loyalty_cards',
                'find_loyalty_card_by_code',
            ],

            'loyalty_transactions' => [
                'read_loyalty_transactions',
                'adjust_loyalty_transactions',
            ],

        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissions = collect($this->permissionGroups())
            ->flatten()
            ->unique()
            ->values()
            ->all();

        foreach ($allPermissions as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        Permission::where('guard_name', 'web')
            ->whereNotIn('name', $allPermissions)
            ->delete();

        $this->syncRoles($allPermissions);

        $admin = User::query()->find(1);
        if ($admin) {
            $admin->syncRoles(['admin']);
        }
    }

    /**
     * @param  list<string>  $allPermissions
     */
    private function syncRoles(array $allPermissions): void
    {
        $groups = $this->permissionGroups();

        $rolePermissions = [
            'admin' => $allPermissions,

            'manager' => array_merge(
                $groups['general'],
                $groups['apps'],
                $groups['billing'],
                $groups['companies'],
                $groups['warehouses'],
                $groups['ubigeo'],
                $groups['categories'],
                $groups['attributes'],
                $groups['unit_of_measures'],
                $groups['taxes'],
                $groups['suppliers'],
                $groups['customers'],
                $groups['product_templates'],
                $groups['product_products'],
                $groups['lots'],
                $groups['lot_alerts'],
                $groups['movements'],
                $groups['purchases'],
                $groups['transfers'],
                $groups['pos'],
                $groups['pos_configs'],
                $groups['receipt_templates'],
                $groups['payment_methods'],
                $groups['billing_credentials'],
                $groups['sales'],
                $groups['loyalty_programs'],
                $groups['loyalty_cards'],
                $groups['loyalty_transactions'],
            ),

            'almacen' => array_merge(
                ['access_dashboard'],
                $groups['categories'],
                $groups['attributes'],
                $groups['unit_of_measures'],
                $groups['suppliers'],
                $groups['warehouses'],
                $groups['product_templates'],
                $groups['product_products'],
                $groups['lots'],
                $groups['lot_alerts'],
                $groups['movements'],
                $groups['transfers'],
                $groups['purchases'],
            ),

            'cajero' => array_merge(
                ['access_dashboard'],
                $groups['pos'],
                $groups['pos_configs'],
                $groups['payment_methods'],
                $groups['receipt_templates'],
                ['read_categories', 'read_attributes'],
                ['read_product_templates', 'read_product_products', 'read_pos_catalog', 'search_product_products'],
                ['read_warehouses'],
                ['create_customers', 'read_customers', 'update_customers'],
                ['create_sales', 'read_sales', 'pay_sales', 'refund_sales'],
                ['read_loyalty_cards', 'find_loyalty_card_by_code'],
                ['simulate_loyalty', 'validate_loyalty_code'],
            ),

            'ventas' => array_merge(
                ['access_dashboard'],
                ['read_categories', 'read_attributes'],
                ['read_product_templates', 'read_product_products', 'search_product_products'],
                ['read_warehouses'],
                $groups['customers'],
                $groups['sales'],
                ['read_payment_methods'],
                ['read_billing_credentials'],
                ['read_loyalty_cards', 'find_loyalty_card_by_code'],
                ['simulate_loyalty', 'validate_loyalty_code'],
            ),

            'editor' => array_merge(
                ['access_dashboard'],
                $groups['categories'],
                $groups['attributes'],
                $groups['product_templates'],
                $groups['product_products'],
                $groups['warehouses'],
                $groups['suppliers'],
                $groups['unit_of_measures'],
            ),

            'web_editor' => array_merge(
                ['access_dashboard'],
                $groups['builder_site'],
                $groups['builder_pages'],
                $groups['builder_sections'],
                $groups['builder_media'],
                $groups['builder_navs'],
                $groups['builder_form_submissions'],
                $groups['builder_global_sections'],
                $groups['builder_redirects'],
                $groups['builder_templates'],
            ),

            'lector' => array_filter(
                $allPermissions,
                static fn (string $p): bool => str_starts_with($p, 'read_') || $p === 'access_dashboard',
            ),

            'viewer' => array_filter(
                $allPermissions,
                static fn (string $p): bool => str_starts_with($p, 'read_') || $p === 'access_dashboard',
            ),
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions(array_values(array_unique($permissions)));
        }
    }
}
