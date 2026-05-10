<script setup lang="ts">
import type { SidebarProps } from "@/components/ui/sidebar";
import {
    Building2,
    GalleryVerticalEnd,
    LayoutDashboard,
    Settings2,
} from "lucide-vue-next";
import { computed } from "vue";
import NavMain from "@/components/NavMain.vue";
import NavUser from "@/components/NavUser.vue";
import TeamSwitcher from "@/components/TeamSwitcher.vue";

import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarRail,
} from "@/components/ui/sidebar";

import { useAuthStore } from "@/central/stores/auth";

const props = withDefaults(defineProps<SidebarProps>(), {
    collapsible: "icon",
});

const authStore = useAuthStore();

const isSuperAdmin = computed(() =>
  (authStore.user?.roles ?? []).includes("superadmin"),
);

const data = computed(() => ({
    user: {
        name: authStore.user?.name || "User",
        email: authStore.user?.email || "",
        avatar: "",
    },
    teams: [
        {
            name: "Central",
            logo: GalleryVerticalEnd,
            plan: isSuperAdmin.value ? "Superadmin" : "User",
        },
    ],
    navMain: [
        {
            title: "Central",
            url: "#",
            icon: LayoutDashboard,
            items: [
                { title: "Dashboard", url: "/dashboard" },
                { title: "Perfil", url: "/profile" },
            ],
        },
        {
            title: isSuperAdmin.value ? "Tenants" : "Mis Tenants",
            url: "#",
            icon: Building2,
            items: [{ title: "Tenants", url: "/tenants" }],
        },
        ...(isSuperAdmin.value
            ? [
                  {
                      title: "Administración",
                      url: "#",
                      icon: Settings2,
                      items: [
                          { title: "Planes", url: "/plans" },
                          { title: "Módulos", url: "/modules" },
                          { title: "Suscripciones", url: "/subscriptions" },
                          { title: "Usuarios", url: "/users" },
                      ],
                  },
              ]
            : []),
    ],
}));
</script>

<template>
    <Sidebar v-bind="props">
        <SidebarHeader>
            <TeamSwitcher :teams="data.teams" />
        </SidebarHeader>
        <SidebarContent>
            <NavMain :items="data.navMain" />
        </SidebarContent>
        <SidebarFooter>
            <NavUser :user="data.user" />
        </SidebarFooter>
        <SidebarRail />
    </Sidebar>
</template>
