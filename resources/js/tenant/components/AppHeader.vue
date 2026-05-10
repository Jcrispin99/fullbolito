<script setup lang="ts">
import { ref, computed } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "@tenant/stores/auth";
import {
    Grid,
    ChevronDown,
} from "lucide-vue-next";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import type { NavSubMenu } from "@tenant/config/navigation";
import {
    Avatar,
    AvatarImage,
    AvatarFallback,
} from "@/components/ui/avatar";
import AppLauncher from "./AppLauncher.vue";
import CompanySelector from "./CompanySelector.vue";
import LotAlertsBell from "./LotAlertsBell.vue";
import { useActiveAppStore } from "@tenant/stores/activeApp";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const activeAppStore = useActiveAppStore();

const user = computed(() => authStore.user);

const isLauncherOpen = ref(false);

const handleLogout = async () => {
    await authStore.logout();
    router.push("/login");
};

const closeLauncher = () => {
    isLauncherOpen.value = false;
};

const activeApp = computed(() => activeAppStore.resolve(route));

// Hide submenus on the admin landing page (/admin) — keep the header clean.
const showSubmenus = computed(() => route.path !== "/admin");

const matchesUrl = (url: string) => {
    // Allow submenu URLs with query strings (e.g. /admin/movements?type=entry).
    const [path, queryString] = url.split("?");
    if (!path) return false;

    const pathMatch =
        route.path === path || (route.path.startsWith(path) && path !== "/");
    if (!pathMatch) return false;

    if (!queryString) return true;
    const params = new URLSearchParams(queryString);
    for (const [key, value] of params) {
        if (route.query[key] !== value) return false;
    }
    return true;
};

const isGroupActive = (menu: NavSubMenu) => {
    return menu.children?.some((child) => matchesUrl(child.url)) ?? false;
};

const isDirectActive = (url: string) => matchesUrl(url);

</script>

<template>
    <header class="flex h-14 items-center justify-between px-4 w-full bg-background border-b border-border relative z-20 shrink-0">
        <!-- Left: Launcher + Module Identity + Submenus -->
        <div class="flex items-center gap-4 h-full flex-1 min-w-0">
            <button
                class="h-10 w-10 flex items-center justify-center rounded-xl hover:bg-gray-200/80 transition-colors focus:outline-none shrink-0"
                @click="isLauncherOpen = true"
            >
                <Grid class="w-6 h-6 text-gray-700" />
            </button>

            <div class="flex items-center gap-2 cursor-pointer shrink-0" @click="activeApp ? router.push(activeApp.defaultRoute) : null">
                <component v-if="activeApp?.icon" :is="activeApp.icon" class="w-6 h-6 text-gray-700" />
                <span class="text-[15px] font-semibold text-foreground">{{ activeApp?.title || 'Odoo' }}</span>
            </div>

            <!-- Dynamic Submenus (inline, left-aligned) -->
            <div v-if="showSubmenus && activeApp?.submenus && activeApp.submenus.length > 0" class="hidden md:flex items-center gap-1 h-full overflow-hidden">
            <template v-for="(menu, index) in activeApp.submenus" :key="index">
                <!-- Direct link -->
                <router-link
                    v-if="menu.url"
                    :to="menu.url"
                    class="px-3 h-full flex items-center text-[14px] text-muted-foreground hover:text-foreground hover:bg-black/5 dark:hover:bg-white/5 transition-colors border-b-2"
                    :class="[ isDirectActive(menu.url) ? 'border-primary text-foreground font-medium bg-black/5 dark:bg-white/5' : 'border-transparent' ]"
                >
                    {{ menu.title }}
                </router-link>

                <!-- Grouped dropdown -->
                <DropdownMenu v-else-if="menu.children">
                    <DropdownMenuTrigger
                        class="px-3 h-full flex items-center gap-1 text-[14px] text-muted-foreground hover:text-foreground hover:bg-black/5 dark:hover:bg-white/5 transition-colors border-b-2 outline-none"
                        :class="[ isGroupActive(menu) ? 'border-primary text-foreground font-medium bg-black/5 dark:bg-white/5' : 'border-transparent' ]"
                    >
                        {{ menu.title }}
                        <ChevronDown class="w-3.5 h-3.5" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="min-w-[160px]">
                        <DropdownMenuItem
                            v-for="child in menu.children"
                            :key="child.url"
                            class="cursor-pointer"
                            :class="{ 'bg-accent font-medium': isDirectActive(child.url) }"
                            @click="router.push(child.url)"
                        >
                            {{ child.title }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
            </div>
        </div>

        <!-- Right: Notifications & Profile -->
        <div class="flex items-center gap-5">
            <div class="flex items-center gap-3.5 text-muted-foreground">
                <LotAlertsBell />
            </div>

            <div class="flex items-center gap-2">
                <CompanySelector class="hidden lg:block" />
                
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Avatar shape="square" class="h-7 w-7 cursor-pointer shadow-sm">
                            <AvatarImage
                                :src="(user as any)?.avatar_url ?? ''"
                                :alt="user?.name ?? 'User'"
                            />
                            <AvatarFallback class="rounded bg-secondary text-secondary-foreground text-xs font-medium">
                                {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                            </AvatarFallback>
                        </Avatar>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <DropdownMenuLabel class="font-normal">
                            <div class="flex flex-col space-y-1">
                                <p class="text-sm font-medium leading-none">{{ user?.name }}</p>
                                <p class="text-xs leading-none text-muted-foreground">{{ user?.email }}</p>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="router.push('/profile')">Profile</DropdownMenuItem>
                        <DropdownMenuItem @click="router.push('/admin/apps')">Apps &amp; Plan</DropdownMenuItem>
                        <DropdownMenuItem @click="router.push('/admin/billing')">Billing</DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="handleLogout" class="text-destructive">Log out</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- The Fullscreen Launcher Overlay -->
        <AppLauncher :is-open="isLauncherOpen" @close="closeLauncher" />
    </header>
</template>
