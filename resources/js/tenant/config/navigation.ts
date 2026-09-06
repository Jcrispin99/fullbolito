import {
    LayoutDashboard,
    ShoppingCart,
    Building2,
    Warehouse,
    Monitor,
    Settings,
    Globe,
    Heart,
    ArrowLeftRight,
    Users,
    Sparkles,
    LandPlot,
} from "lucide-vue-next";

export type NavSubMenu = {
    title: string;
    url?: string;
    children?: { title: string; url: string; feature?: string }[];
    feature?: string;
};

export type NavApp = {
    title: string;
    icon: any;
    colorClass: string;
    submenus: NavSubMenu[];
    defaultRoute: string;
    /**
     * Feature key required to display this app in navigation. Apps without
     * a feature are always shown (e.g. General, Configuración).
     */
    feature?: string;
};

export const MAIN_APPS: NavApp[] = [
    {
        title: "General",
        icon: LayoutDashboard,
        colorClass: "border-primary text-primary",
        defaultRoute: "/admin",
        submenus: [
            { title: "Dashboard", url: "/admin" },
            { title: "Companies", url: "/admin/companies" },
        ],
    },
    {
        title: "Mi Plan",
        icon: Sparkles,
        colorClass: "border-fuchsia-500 text-fuchsia-500",
        defaultRoute: "/admin/apps",
        submenus: [
            { title: "Apps & Módulos", url: "/admin/apps" },
            { title: "Facturación", url: "/admin/billing" },
        ],
    },
    {
        title: "Ventas",
        icon: ShoppingCart,
        colorClass: "border-secondary text-secondary",
        defaultRoute: "/admin/customers",
        feature: "sales",
        submenus: [
            { title: "Clientes", url: "/admin/customers" },
            { title: "Ventas", url: "/admin/sales" },
            {
                title: "Catálogo",
                children: [
                    { title: "Productos", url: "/admin/products" },
                    { title: "Categorías", url: "/admin/categories" },
                    { title: "Atributos", url: "/admin/attributes" },
                ],
            },
        ],
    },
    {
        title: "Compras",
        icon: Building2,
        colorClass: "border-chart-5 text-chart-5",
        defaultRoute: "/admin/suppliers",
        feature: "purchases",
        submenus: [
            { title: "Proveedores", url: "/admin/suppliers" },
            { title: "Compras", url: "/admin/purchases" },
            {
                title: "Catálogo",
                children: [
                    { title: "Productos", url: "/admin/products" },
                    { title: "Categorías", url: "/admin/categories" },
                    { title: "Atributos", url: "/admin/attributes" },
                ],
            },
        ],
    },
    {
        title: "Canchas",
        icon: LandPlot,
        colorClass: "border-emerald-500 text-emerald-500",
        defaultRoute: "/admin/reservations",
        submenus: [
            { title: "Reservas", url: "/admin/reservations" },
            { title: "Canchas", url: "/admin/courts" },
            { title: "Horarios", url: "/admin/court-schedules" },
        ],
    },
    {
        title: "Inventario",
        icon: Warehouse,
        colorClass: "border-chart-3 text-chart-3",
        defaultRoute: "/admin/products",
        feature: "inventory",
        submenus: [
            { title: "Productos", url: "/admin/products" },
            { title: "Categorías", url: "/admin/categories" },
            { title: "Atributos", url: "/admin/attributes" },
            { title: "Almacenes", url: "/admin/warehouses" },
            { title: "Lotes", url: "/admin/lots" },
        ],
    },
    {
        title: "Movimientos",
        icon: ArrowLeftRight,
        colorClass: "border-cyan-500 text-cyan-500",
        defaultRoute: "/admin/transfers",
        feature: "transfers",
        submenus: [
            { title: "Transferencias", url: "/admin/transfers" },
            { title: "Entradas", url: "/admin/movements?type=entry" },
            { title: "Salidas", url: "/admin/movements?type=exit" },
        ],
    },
    {
        title: "POS",
        icon: Monitor,
        colorClass: "border-orange-500 text-orange-500",
        defaultRoute: "/admin/pos-configs",
        feature: "pos",
        submenus: [
            { title: "Configuraciones", url: "/admin/pos-configs" },
            { title: "Diseño de comprobante", url: "/admin/receipt-template" },
            {
                title: "Catálogo",
                children: [
                    { title: "Productos", url: "/admin/products" },
                    { title: "Categorías", url: "/admin/categories" },
                ],
            },
        ],
    },
    {
        title: "Lealtad",
        icon: Heart,
        colorClass: "border-pink-500 text-pink-500",
        defaultRoute: "/admin/loyalty/programs",
        feature: "loyalty",
        submenus: [
            { title: "Programas", url: "/admin/loyalty/programs" },
            { title: "Tarjetas", url: "/admin/loyalty/cards" },
            { title: "Transacciones", url: "/admin/loyalty/transactions" },
        ],
    },
    {
        title: "Website",
        icon: Globe,
        colorClass: "border-chart-2 text-chart-2",
        defaultRoute: "/",
        feature: "builder",
        submenus: [
            { title: "Ir al sitio", url: "/" },
        ],
    },
    {
        title: "Administración",
        icon: Users,
        colorClass: "border-violet-500 text-violet-500",
        defaultRoute: "/admin/users",
        submenus: [
            { title: "Usuarios", url: "/admin/users" },
            { title: "Roles", url: "/admin/roles" },
        ],
    },
    {
        title: "Configuración",
        icon: Settings,
        colorClass: "border-chart-4 text-chart-4",
        defaultRoute: "/admin/payment-methods",
        submenus: [
            { title: "Métodos de Pago", url: "/admin/payment-methods" },
            { title: "Unidades de Medida", url: "/admin/unit-of-measures" },
            { title: "Impuestos", url: "/admin/taxes" },
            { title: "Facturación Electrónica", url: "/admin/billing-credentials" },
        ],
    },
];
