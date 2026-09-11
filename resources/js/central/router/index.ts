import {
    createRouter,
    createWebHistory,
    type RouteRecordRaw,
} from "vue-router";
import { useAuthStore } from "@central/stores/auth";
import type { User } from "@/types/models";
import { installNavigationProgress } from "@/lib/navigationProgress";

const routes: RouteRecordRaw[] = [
    {
        path: "/",
        name: "Home",
        component: () => import("@central/views/Home/index.vue"),
    },
    {
        path: "/canchas",
        name: "Marketplace",
        component: () => import("@central/views/Marketplace/index.vue"),
    },
    {
        path: "/login",
        name: "Login",
        component: () => import("@central/views/Login/index.vue"),
        meta: { requiresGuest: true },
    },
    {
        path: "/register",
        name: "Register",
        component: () => import("@central/views/Register/index.vue"),
        meta: { requiresGuest: true },
    },
    {
        path: "/dashboard",
        name: "Dashboard",
        component: () => import("@central/views/Dashboard.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/profile",
        name: "Profile",
        component: () => import("@central/views/Profile.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/plans",
        name: "Plans",
        component: () => import("@central/views/Plans/Index.vue"),
        meta: { requiresAuth: true, roles: ["superadmin"] },
    },
    {
        path: "/plans/create",
        name: "PlansCreate",
        component: () => import("@central/views/Plans/FormPage.vue"),
        meta: { requiresAuth: true, roles: ["superadmin"] },
    },
    {
        path: "/plans/:id/edit",
        name: "PlansEdit",
        component: () => import("@central/views/Plans/FormPage.vue"),
        meta: { requiresAuth: true, roles: ["superadmin"] },
    },
    {
        path: "/modules",
        name: "Modules",
        component: () => import("@central/views/Modules/Index.vue"),
        meta: { requiresAuth: true, roles: ["superadmin"] },
    },
    {
        path: "/modules/create",
        name: "ModulesCreate",
        component: () => import("@central/views/Modules/FormPage.vue"),
        meta: { requiresAuth: true, roles: ["superadmin"] },
    },
    {
        path: "/modules/:id/edit",
        name: "ModulesEdit",
        component: () => import("@central/views/Modules/FormPage.vue"),
        meta: { requiresAuth: true, roles: ["superadmin"] },
    },
    {
        path: "/tenants",
        name: "TenantList",
        component: () => import("@central/views/TenantList.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/tenants/create",
        name: "TenantsCreate",
        component: () => import("@central/views/Tenants/RegisterPage.vue"),
        meta: { requiresAuth: true, roles: ["superadmin"] },
    },
    {
        path: "/tenants/:id/edit",
        name: "TenantsEdit",
        component: () => import("@central/views/Tenants/FormPage.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/users",
        name: "UsersIndex",
        component: () => import("@central/views/Users/Index.vue"),
        meta: { requiresAuth: true, roles: ["superadmin"] },
    },
    {
        path: "/users/create",
        name: "UsersCreate",
        component: () => import("@central/views/Users/FormPage.vue"),
        meta: { requiresAuth: true, roles: ["superadmin"] },
    },
    {
        path: "/users/:id/edit",
        name: "UsersEdit",
        component: () => import("@central/views/Users/FormPage.vue"),
        meta: { requiresAuth: true, roles: ["superadmin"] },
    },
    {
        path: "/subscriptions",
        name: "Subscriptions",
        component: () => import("@central/views/Subscriptions/Index.vue"),
        meta: { requiresAuth: true, roles: ["superadmin"] },
    },
    {
        path: "/billing/success",
        name: "BillingSuccess",
        component: () => import("@central/views/Billing/Return.vue"),
    },
    {
        path: "/billing/cancel",
        name: "BillingCancel",
        component: () => import("@central/views/Billing/Return.vue"),
    },
    {
        path: "/:pathMatch(.*)*",
        name: "NotFound",
        redirect: { name: "Home" },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

installNavigationProgress(router);

router.beforeEach(async (to) => {
    const authStore = useAuthStore();

    // Only try to fetch user once (on first navigation)
    if (!authStore.authChecked) {
        await authStore.initAuth();
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return { name: "Login" };
    }

    if (to.meta.requiresGuest && authStore.isAuthenticated) {
        return { name: "Dashboard" };
    }

    // Role-based access control
    const roles = to.meta.roles as string[] | undefined;
    if (roles) {
        const userRoles = (authStore.user as User | null)?.roles ?? ["user"];
        const hasMatch = roles.some((r) => userRoles.includes(r));
        if (!hasMatch) {
            return { name: "TenantList" };
        }
    }

    return true;
});

export default router;
