<script setup lang="ts">
import { computed } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@tenant/stores/auth";
import { 
    Users, 
    Settings, 
    Tags, 
    Package, 
    ShoppingCart, 
    Truck, 
    Building2,
    Settings2,
    Activity,
    MessageSquare,
    Clock,
    Wrench
} from "lucide-vue-next";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const router = useRouter();
const authStore = useAuthStore();

const user = computed(() => authStore.user);

const handleLogout = async () => {
    await authStore.logout();
    router.push("/login");
};

// Apps List Configuration
const apps = [
    {
        name: "Companies",
        route: "/companies",
        icon: Building2,
        colorClass: "bg-[#10B981]" // Emerald
    },
    {
        name: "Pos Configs",
        route: "/pos-configs",
        icon: Settings2,
        colorClass: "bg-[#F59E0B]" // Amber
    },
    {
        name: "Products",
        route: "/products",
        icon: Package,
        colorClass: "bg-[#6366F1]" // Indigo
    },
    {
        name: "Purchases",
        route: "/purchases",
        icon: ShoppingCart,
        colorClass: "bg-[#8B5CF6]" // Violet
    },
    {
        name: "Suppliers",
        route: "/suppliers",
        icon: Truck,
        colorClass: "bg-[#F43F5E]" // Rose
    },
    {
        name: "Customers",
        route: "/customers",
        icon: Users,
        colorClass: "bg-[#0EA5E9]" // Sky
    },
    {
        name: "Categories",
        route: "/categories",
        icon: Tags,
        colorClass: "bg-[#14B8A6]" // Teal
    },
    {
        name: "Attributes",
        route: "/attributes",
        icon: Activity,
        colorClass: "bg-[#EC4899]" // Pink
    },
    {
        name: "Settings",
        route: "/settings", // Placeholder, adjust if needed
        icon: Settings,
        colorClass: "bg-[#64748B]" // Slate
    }
];

const navigateToApp = (route: string) => {
    router.push(route);
};
</script>

<template>
    <!-- We use the same background gradient as the reference image -->
    <div class="min-h-screen bg-[#EBEAF1] relative overflow-hidden flex flex-col font-sans">
        <!-- Subtle background gradient overlay -->
        <div class="absolute inset-0 pointer-events-none" style="background: linear-gradient(135deg, rgba(255,255,255,0.4) 0%, rgba(235,234,241,0) 100%);"></div>

        <!-- Minimalist Header -->
        <header class="relative z-10 flex h-14 items-center justify-between px-6 w-full">
            <div class="flex-1"></div>
            
            <div class="flex items-center gap-6">
                <!-- Notifications/Chat Icons Placeholder -->
                <div class="flex items-center gap-4 text-gray-700">
                    <button class="relative hover:text-gray-900 transition-colors">
                        <MessageSquare class="w-5 h-5" />
                        <span class="absolute -top-1.5 -right-2 bg-rose-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">15</span>
                    </button>
                    <button class="relative hover:text-gray-900 transition-colors">
                        <Clock class="w-5 h-5" />
                        <span class="absolute -top-1.5 -right-2 bg-rose-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">8</span>
                    </button>
                    <button class="hover:text-gray-900 transition-colors">
                        <Wrench class="w-5 h-5" />
                    </button>
                </div>

                <div class="flex items-center gap-3 ml-2">
                    <span class="text-sm font-medium text-gray-700 hidden sm:inline-block uppercase tracking-wide">
                        KDOSH STORE SOCIEDAD ANONIMA CERRADA
                    </span>
                    
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button class="h-8 w-8 rounded bg-[#B57C53] flex items-center justify-center font-medium text-white hover:bg-[#a06b45] shadow-sm transition-colors focus:outline-none">
                                {{ user?.name?.charAt(0)?.toUpperCase() || 'A' }}
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuLabel class="font-normal">
                                <div class="flex flex-col space-y-1">
                                    <p class="text-sm font-medium leading-none">{{ user?.name }}</p>
                                    <p class="text-xs leading-none text-muted-foreground">{{ user?.email }}</p>
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="router.push('/profile')">
                                Profile
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="router.push('/billing')">
                                Billing
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="handleLogout" class="text-destructive">
                                Log out
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </header>

        <!-- Main Apps Grid -->
        <main class="relative z-10 flex-1 flex items-center justify-center p-6">
            <div class="w-full max-w-[900px]">
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-x-6 gap-y-10 justify-items-center">
                    <div 
                        v-for="app in apps" 
                        :key="app.name"
                        class="flex flex-col items-center group cursor-pointer w-[100px]"
                        @click="navigateToApp(app.route)"
                    >
                        <!-- App Icon Container (White card with subtle shadow, precise rounding) -->
                        <div 
                            class="w-[88px] h-[88px] bg-white rounded-[20px] flex items-center justify-center shadow-[0_4px_10px_rgba(0,0,0,0.05)] transition-all duration-200 group-hover:-translate-y-1 group-hover:shadow-[0_8px_15px_rgba(0,0,0,0.08)] mb-3 overflow-hidden relative"
                        >
                            <div :class="['absolute inset-0 opacity-10', app.colorClass]"></div>
                            <component :is="app.icon" :class="['w-11 h-11 relative z-10', app.colorClass.replace('bg-', 'text-').replace('text-white', '')]" />
                        </div>
                        
                        <!-- App Name -->
                        <span class="text-[13px] font-medium text-gray-700 text-center leading-tight">
                            {{ app.name }}
                        </span>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
