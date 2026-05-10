<script setup lang="ts">
import { Bell, ChevronsUpDown, CreditCard, LogOut } from "lucide-vue-next";

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from "@/components/ui/sidebar";
import { computed } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@tenant/stores/auth";

const props = defineProps<{
    user: {
        name: string;
        email: string;
        avatar: string;
    };
}>();

const { isMobile } = useSidebar();
const authStore = useAuthStore();
const router = useRouter();

const initials = computed(() => {
    const name = props.user?.name?.trim() || "";
    const email = props.user?.email?.trim() || "";
    const label = name || email;
    if (!label) return "?";
    if (label.includes("@")) return label[0]?.toUpperCase() || "?";
    const parts = label.split(/\s+/).filter(Boolean);
    const first = parts[0]?.[0] || "?";
    const second = parts[1]?.[0] || "";
    return (first + second).toUpperCase();
});

const handleLogout = async () => {
    await authStore.logout();
    router.push({ name: "Login" });
};

const goToBilling = () => {
    router.push({ name: "Billing" });
};
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                    >
                        <Avatar class="h-8 w-8 rounded-lg">
                            <AvatarImage :src="user.avatar" :alt="user.name" />
                            <AvatarFallback
                                class="rounded-lg bg-secondary font-semibold text-secondary-foreground"
                            >
                                {{ initials }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="grid flex-1 text-left text-sm leading-tight">
                            <span class="truncate font-medium">{{
                                user.name
                            }}</span>
                            <span class="truncate text-xs">{{ user.email }}</span>
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-[--reka-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                    :side="isMobile ? 'bottom' : 'right'"
                    align="end"
                    :side-offset="4"
                >
                    <DropdownMenuLabel class="p-0 font-normal">
                        <div
                            class="flex items-center gap-2 px-1 py-1.5 text-left text-sm"
                        >
                            <Avatar class="h-8 w-8 rounded-lg">
                                <AvatarImage
                                    :src="user.avatar"
                                    :alt="user.name"
                                />
                                <AvatarFallback
                                    class="rounded-lg bg-secondary font-semibold text-secondary-foreground"
                                >
                                    {{ initials }}
                                </AvatarFallback>
                            </Avatar>
                            <div
                                class="grid flex-1 text-left text-sm leading-tight"
                            >
                                <span class="truncate font-semibold">{{
                                    user.name
                                }}</span>
                                <span class="truncate text-xs">{{
                                    user.email
                                }}</span>
                            </div>
                        </div>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        <DropdownMenuItem @click="goToBilling">
                            <CreditCard />
                            Billing
                        </DropdownMenuItem>
                        <DropdownMenuItem>
                            <Bell />
                            Notificaciones
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem @click="handleLogout">
                        <LogOut />
                        Log out
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
