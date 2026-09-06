import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@tenant/stores/auth'
import { useActiveAppStore } from '@tenant/stores/activeApp'
import { useFeatures } from '@tenant/composables/useFeatures'

const routes: RouteRecordRaw[] = [
  // ─── Auth ─────────────────────────────────────────────────
  {
    path: '/login',
    name: 'Login',
    component: () => import('@tenant/views/Login/index.vue'),
    meta: { requiresGuest: true },
  },

  // ─── Website (público + toolbar si auth) ──────────────────
  {
    path: '/',
    name: 'Home',
    component: () => import('@tenant/views/PublicWebsite.vue'),
  },

  // ─── Admin (/admin prefix, requiere auth) ────────────────
  {
    path: '/admin',
    name: 'AdminDashboard',
    component: () => import('@tenant/views/Dashboard.vue'),
    meta: { requiresAuth: true, defaultApp: 'General' },
  },
  {
    path: '/admin/billing',
    name: 'Billing',
    component: () => import('@tenant/views/Billing.vue'),
    meta: { requiresAuth: true, defaultApp: 'Mi Plan' },
  },
  {
    path: '/admin/apps',
    name: 'AppsCatalog',
    component: () => import('@tenant/views/Apps/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Mi Plan' },
  },

  // Users
  {
    path: '/admin/users',
    name: 'UsersIndex',
    component: () => import('@tenant/views/Users/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Administración' },
  },
  {
    path: '/admin/users/create',
    name: 'UsersCreate',
    component: () => import('@tenant/views/Users/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Administración' },
  },
  {
    path: '/admin/users/:id/edit',
    name: 'UsersEdit',
    component: () => import('@tenant/views/Users/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Administración' },
  },

  // Roles
  {
    path: '/admin/roles',
    name: 'RolesIndex',
    component: () => import('@tenant/views/Roles/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Administración' },
  },
  {
    path: '/admin/roles/create',
    name: 'RolesCreate',
    component: () => import('@tenant/views/Roles/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Administración' },
  },
  {
    path: '/admin/roles/:id/edit',
    name: 'RolesEdit',
    component: () => import('@tenant/views/Roles/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Administración' },
  },

  // Companies
  {
    path: '/admin/companies',
    name: 'CompaniesIndex',
    component: () => import('@tenant/views/Companies/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'General' },
  },
  {
    path: '/admin/companies/create',
    name: 'CompaniesCreate',
    component: () => import('@tenant/views/Companies/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'General' },
  },
  {
    path: '/admin/companies/:id/edit',
    name: 'CompaniesEdit',
    component: () => import('@tenant/views/Companies/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'General' },
  },

  // Courts
  {
    path: '/admin/courts',
    name: 'CourtsIndex',
    component: () => import('@tenant/views/Courts/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Canchas' },
  },
  {
    path: '/admin/courts/create',
    name: 'CourtsCreate',
    component: () => import('@tenant/views/Courts/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Canchas' },
  },
  {
    path: '/admin/courts/:id/edit',
    name: 'CourtsEdit',
    component: () => import('@tenant/views/Courts/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Canchas' },
  },

  // Court Schedules
  {
    path: '/admin/court-schedules',
    name: 'CourtSchedulesIndex',
    component: () => import('@tenant/views/CourtSchedules/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Canchas' },
  },
  {
    path: '/admin/court-schedules/create',
    name: 'CourtSchedulesCreate',
    component: () => import('@tenant/views/CourtSchedules/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Canchas' },
  },
  {
    path: '/admin/court-schedules/:id/edit',
    name: 'CourtSchedulesEdit',
    component: () => import('@tenant/views/CourtSchedules/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Canchas' },
  },

  // Reservations
  {
    path: '/admin/reservations',
    name: 'ReservationsIndex',
    component: () => import('@tenant/views/Reservations/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Canchas' },
  },
  {
    path: '/admin/reservations/create',
    name: 'ReservationsCreate',
    component: () => import('@tenant/views/Reservations/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Canchas' },
  },
  {
    path: '/admin/reservations/:id/edit',
    name: 'ReservationsEdit',
    component: () => import('@tenant/views/Reservations/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Canchas' },
  },

  // Warehouses
  {
    path: '/admin/warehouses',
    name: 'WarehousesIndex',
    component: () => import('@tenant/views/Warehouses/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Inventario', feature: 'inventory' },
  },
  {
    path: '/admin/warehouses/create',
    name: 'WarehousesCreate',
    component: () => import('@tenant/views/Warehouses/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Inventario', feature: 'inventory' },
  },
  {
    path: '/admin/warehouses/:id/edit',
    name: 'WarehousesEdit',
    component: () => import('@tenant/views/Warehouses/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Inventario', feature: 'inventory' },
  },

  // Categories
  {
    path: '/admin/categories',
    name: 'CategoriesIndex',
    component: () => import('@tenant/views/Categories/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Inventario', feature: 'inventory' },
  },
  {
    path: '/admin/categories/create',
    name: 'CategoriesCreate',
    component: () => import('@tenant/views/Categories/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Inventario', feature: 'inventory' },
  },
  {
    path: '/admin/categories/:id/edit',
    name: 'CategoriesEdit',
    component: () => import('@tenant/views/Categories/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Inventario', feature: 'inventory' },
  },

  // Attributes
  {
    path: '/admin/attributes',
    name: 'AttributesIndex',
    component: () => import('@tenant/views/Attributes/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Inventario', feature: 'inventory' },
  },
  {
    path: '/admin/attributes/create',
    name: 'AttributesCreate',
    component: () => import('@tenant/views/Attributes/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Inventario', feature: 'inventory' },
  },
  {
    path: '/admin/attributes/:id/edit',
    name: 'AttributesEdit',
    component: () => import('@tenant/views/Attributes/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Inventario', feature: 'inventory' },
  },

  // Unit of Measures
  {
    path: '/admin/unit-of-measures',
    name: 'UnitOfMeasuresIndex',
    component: () => import('@tenant/views/UnitOfMeasures/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Configuración' },
  },
  {
    path: '/admin/unit-of-measures/create',
    name: 'UnitOfMeasuresCreate',
    component: () => import('@tenant/views/UnitOfMeasures/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Configuración' },
  },
  {
    path: '/admin/unit-of-measures/:id/edit',
    name: 'UnitOfMeasuresEdit',
    component: () => import('@tenant/views/UnitOfMeasures/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Configuración' },
  },

  // Taxes
  {
    path: '/admin/taxes',
    name: 'TaxesIndex',
    component: () => import('@tenant/views/Taxes/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Configuración' },
  },
  {
    path: '/admin/taxes/create',
    name: 'TaxesCreate',
    component: () => import('@tenant/views/Taxes/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Configuración' },
  },
  {
    path: '/admin/taxes/:id/edit',
    name: 'TaxesEdit',
    component: () => import('@tenant/views/Taxes/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Configuración' },
  },

  // Products
  {
    path: '/admin/products',
    name: 'ProductsIndex',
    component: () => import('@tenant/views/Products/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Inventario', feature: 'inventory' },
  },
  {
    path: '/admin/products/create',
    name: 'ProductsCreate',
    component: () => import('@tenant/views/Products/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Inventario', feature: 'inventory' },
  },
  {
    path: '/admin/products/:id/edit',
    name: 'ProductsEdit',
    component: () => import('@tenant/views/Products/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Inventario', feature: 'inventory' },
  },

  // Suppliers
  {
    path: '/admin/suppliers',
    name: 'SuppliersIndex',
    component: () => import('@tenant/views/Suppliers/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Compras', feature: 'purchases' },
  },
  {
    path: '/admin/suppliers/create',
    name: 'SuppliersCreate',
    component: () => import('@tenant/views/Suppliers/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Compras', feature: 'purchases' },
  },
  {
    path: '/admin/suppliers/:id/edit',
    name: 'SuppliersEdit',
    component: () => import('@tenant/views/Suppliers/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Compras', feature: 'purchases' },
  },

  // Customers
  {
    path: '/admin/customers',
    name: 'CustomersIndex',
    component: () => import('@tenant/views/Customers/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Ventas', feature: 'sales' },
  },
  {
    path: '/admin/customers/create',
    name: 'CustomersCreate',
    component: () => import('@tenant/views/Customers/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Ventas', feature: 'sales' },
  },
  {
    path: '/admin/customers/:id/edit',
    name: 'CustomersEdit',
    component: () => import('@tenant/views/Customers/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Ventas', feature: 'sales' },
  },

  // Sales
  {
    path: '/admin/sales',
    name: 'SalesIndex',
    component: () => import('@tenant/views/Sales/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Ventas', feature: 'sales' },
  },
  {
    path: '/admin/sales/create',
    name: 'SalesCreate',
    component: () => import('@tenant/views/Sales/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Ventas', feature: 'sales' },
  },
  {
    path: '/admin/sales/:id/edit',
    name: 'SalesEdit',
    component: () => import('@tenant/views/Sales/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Ventas', feature: 'sales' },
  },

  // Purchases
  {
    path: '/admin/purchases',
    name: 'PurchasesIndex',
    component: () => import('@tenant/views/Purchases/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Compras', feature: 'purchases' },
  },
  {
    path: '/admin/purchases/create',
    name: 'PurchasesCreate',
    component: () => import('@tenant/views/Purchases/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Compras', feature: 'purchases' },
  },
  {
    path: '/admin/purchases/:id/edit',
    name: 'PurchasesEdit',
    component: () => import('@tenant/views/Purchases/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Compras', feature: 'purchases' },
  },
  {
    path: '/admin/purchases/:id/lots',
    name: 'PurchasesLots',
    component: () => import('@tenant/views/Purchases/Lots.vue'),
    meta: { requiresAuth: true, defaultApp: 'Compras', feature: 'purchases' },
  },

  // Lots
  {
    path: '/admin/lots',
    name: 'LotsIndex',
    component: () => import('@tenant/views/Lots/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Inventario', feature: 'inventory' },
  },

  // Movements (entradas/salidas de inventario)
  {
    path: '/admin/movements',
    name: 'MovementsIndex',
    component: () => import('@tenant/views/Movements/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Movimientos', feature: 'transfers' },
  },
  {
    path: '/admin/movements/create',
    name: 'MovementsCreate',
    component: () => import('@tenant/views/Movements/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Movimientos', feature: 'transfers' },
  },
  {
    path: '/admin/movements/:id/edit',
    name: 'MovementsEdit',
    component: () => import('@tenant/views/Movements/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Movimientos', feature: 'transfers' },
  },

  // Transfers
  {
    path: '/admin/transfers',
    name: 'TransfersIndex',
    component: () => import('@tenant/views/Transfers/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Movimientos', feature: 'transfers' },
  },
  {
    path: '/admin/transfers/create',
    name: 'TransfersCreate',
    component: () => import('@tenant/views/Transfers/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Movimientos', feature: 'transfers' },
  },
  {
    path: '/admin/transfers/:id/edit',
    name: 'TransfersEdit',
    component: () => import('@tenant/views/Transfers/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Movimientos', feature: 'transfers' },
  },

  // Payment Methods
  {
    path: '/admin/payment-methods',
    name: 'PaymentMethodsIndex',
    component: () => import('@tenant/views/PaymentMethods/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Configuración' },
  },
  {
    path: '/admin/payment-methods/create',
    name: 'PaymentMethodsCreate',
    component: () => import('@tenant/views/PaymentMethods/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Configuración' },
  },
  {
    path: '/admin/payment-methods/:id/edit',
    name: 'PaymentMethodsEdit',
    component: () => import('@tenant/views/PaymentMethods/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Configuración' },
  },

  // Billing Credentials (Facturación electrónica - SUNAT)
  {
    path: '/admin/billing-credentials',
    name: 'BillingCredentialsIndex',
    component: () => import('@tenant/views/BillingCredentials/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Configuración' },
  },
  {
    path: '/admin/billing-credentials/create',
    name: 'BillingCredentialsCreate',
    component: () => import('@tenant/views/BillingCredentials/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Configuración' },
  },
  {
    path: '/admin/billing-credentials/:id/edit',
    name: 'BillingCredentialsEdit',
    component: () => import('@tenant/views/BillingCredentials/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Configuración' },
  },

  // POS Configs
  {
    path: '/admin/pos-configs',
    name: 'PosConfigsIndex',
    component: () => import('@tenant/views/PosConfigs/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'POS', feature: 'pos' },
  },
  {
    path: '/admin/pos-configs/create',
    name: 'PosConfigsCreate',
    component: () => import('@tenant/views/PosConfigs/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'POS', feature: 'pos' },
  },
  {
    path: '/admin/pos-configs/:id/edit',
    name: 'PosConfigsEdit',
    component: () => import('@tenant/views/PosConfigs/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'POS', feature: 'pos' },
  },
  {
    path: '/admin/receipt-template',
    name: 'ReceiptTemplateEditor',
    component: () => import('@tenant/views/ReceiptTemplate/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'POS', feature: 'pos' },
  },

  // ─── Loyalty ───────────────────────────────────────────────
  {
    path: '/admin/loyalty/programs',
    name: 'LoyaltyProgramsIndex',
    component: () => import('@tenant/views/Loyalty/Programs/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Lealtad', feature: 'loyalty' },
  },
  {
    path: '/admin/loyalty/programs/create',
    name: 'LoyaltyProgramsCreate',
    component: () => import('@tenant/views/Loyalty/Programs/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Lealtad', feature: 'loyalty' },
  },
  {
    path: '/admin/loyalty/programs/:id/edit',
    name: 'LoyaltyProgramsEdit',
    component: () => import('@tenant/views/Loyalty/Programs/FormPage.vue'),
    meta: { requiresAuth: true, defaultApp: 'Lealtad', feature: 'loyalty' },
  },
  {
    path: '/admin/loyalty/cards',
    name: 'LoyaltyCardsIndex',
    component: () => import('@tenant/views/Loyalty/Cards/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Lealtad', feature: 'loyalty' },
  },
  {
    path: '/admin/loyalty/transactions',
    name: 'LoyaltyTransactionsIndex',
    component: () => import('@tenant/views/Loyalty/Transactions/Index.vue'),
    meta: { requiresAuth: true, defaultApp: 'Lealtad', feature: 'loyalty' },
  },

  // ─── POS (fullscreen, sin DashboardLayout) ──────────────────
  {
    path: '/pos',
    component: () => import('@tenant/views/Pos/PosPage.vue'),
    meta: { requiresAuth: true, feature: 'pos' },
    children: [
      {
        path: '',
        redirect: { name: 'PosConfigsIndex' },
      },
      {
        path: ':configId/open',
        name: 'PosOpenSession',
        component: () => import('@tenant/views/Pos/OpenSession.vue'),
      },
      {
        path: ':configId/terminal',
        name: 'PosTerminal',
        component: () => import('@tenant/views/Pos/Terminal.vue'),
      },
      {
        path: ':configId/sales',
        name: 'PosSales',
        component: () => import('@tenant/views/Pos/Sales.vue'),
      },
      {
        path: ':configId/close',
        name: 'PosCloseSession',
        component: () => import('@tenant/views/Pos/CloseSession.vue'),
      },
    ],
  },

  // ─── Reservas públicas (sin auth) ────────────────────────
  // IMPORTANTE: estas rutas DEBEN ir antes del catch-all '/:slug'
  // para que '/canchas' y '/canchas/...' las matcheen y no caigan al builder.
  {
    path: '/canchas',
    name: 'CourtsCatalog',
    component: () => import('@tenant/views/Public/CourtsCatalog.vue'),
  },
  {
    path: '/canchas/:slug',
    name: 'CourtDetail',
    component: () => import('@tenant/views/Public/CourtDetail.vue'),
  },
  {
    path: '/reservas/:code',
    name: 'ReservationStatus',
    component: () => import('@tenant/views/Public/ReservationStatus.vue'),
  },

  // ─── Página pública por slug (catch-all, DEBE ir al final) ─
  {
    path: '/:slug',
    name: 'PublicPage',
    component: () => import('@tenant/views/PublicPage.vue'),
  },

  // ─── 404 ──────────────────────────────────────────────────
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@tenant/views/NotFound.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore()

  if (!authStore.authChecked) {
    await authStore.initAuth()
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'Login' }
  }

  if (to.meta.requiresGuest && authStore.isAuthenticated) {
    return { name: 'Home' }
  }

  // Feature gate: walk matched records so child routes inherit parent gates.
  if (authStore.isAuthenticated) {
    const { has } = useFeatures()
    const required = to.matched
      .map((r) => r.meta.feature as string | undefined)
      .filter((f): f is string => !!f)

    for (const feature of required) {
      if (!has(feature)) {
        return { name: 'AppsCatalog', query: { missing: feature } }
      }
    }

    // Public site routes ('/' and '/:slug') require the 'builder' feature.
    // Authenticated tenants without builder land on /admin instead of seeing
    // a broken/empty website shell.
    const PUBLIC_SITE_ROUTES = new Set(['Home', 'PublicPage'])
    if (PUBLIC_SITE_ROUTES.has(to.name as string) && !has('builder')) {
      return { name: 'AdminDashboard' }
    }
  }

  // Sync active app with route's defaultApp so the AppHeader reflects the
  // current section instead of staying pinned to a stale sessionStorage value.
  const targetApp = to.meta.defaultApp as string | undefined
  if (targetApp) {
    const activeAppStore = useActiveAppStore()
    if (activeAppStore.override !== targetApp) {
      activeAppStore.setApp(targetApp)
    }
  }

  return true
})

export default router
