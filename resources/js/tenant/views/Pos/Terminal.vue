<script setup lang="ts">
import { ref, nextTick, watch } from "vue";
import { useEventListener } from "@vueuse/core";
import { usePosStore } from "@tenant/stores/pos";
import { cn } from "@/lib/utils";
import { ShoppingBag, CreditCard } from "lucide-vue-next";
import { toast } from "vue-sonner";
import PosShell from "./components/PosShell.vue";
import Cart from "./components/Cart.vue";
import CategorySidebar from "./components/CategorySidebar.vue";
import ProductGrid from "./components/ProductGrid.vue";
import ProductSearch from "./components/ProductSearch.vue";
import PaymentPanel from "./components/PaymentPanel.vue";

const store = usePosStore();
const searchRef = ref<InstanceType<typeof ProductSearch> | null>(null);

watch(
    () => store.cart.length,
    (count) => {
        if (count === 0 && store.activeTab === "payment") {
            store.activeTab = "products";
        }
    },
);

const focusSearch = async () => {
    store.activeTab = "products";
    await nextTick();
    searchRef.value?.focus();
};

const handleConfirmShortcut = async () => {
    if (!store.canConfirmPayment || store.isLoading) return;
    const success = await store.checkout();
    if (!success) return;

    if (store.currentConfig?.auto_print_receipt) {
        toast.success("Venta registrada correctamente");
        return;
    }

    toast.success("Venta registrada correctamente", {
        action: {
            label: "Imprimir",
            onClick: () => {
                store.printLastReceipt().catch(() =>
                    toast.error("No se pudo imprimir el comprobante"),
                );
            },
        },
        duration: 8000,
    });
};

useEventListener(window, "keydown", (event: KeyboardEvent) => {
    if (event.key === "F1") {
        event.preventDefault();
        void focusSearch();
        return;
    }
    if (event.key === "F2") {
        event.preventDefault();
        if (store.cart.length > 0) store.goToPayment();
        return;
    }
    if (event.key === "F4") {
        event.preventDefault();
        if (store.activeTab === "payment") void handleConfirmShortcut();
        return;
    }
    if (event.key === "Escape" && store.activeTab === "payment") {
        event.preventDefault();
        void focusSearch();
    }
});
</script>

<template>
    <PosShell>
        <div class="flex flex-1 overflow-hidden">
            <!-- Left: Cart -->
            <div class="w-[320px] lg:w-[360px] shrink-0 overflow-hidden">
                <Cart />
            </div>

            <!-- Right: Tab Content -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Tabs -->
                <div class="flex items-center gap-1 px-3 pt-2 pb-0 shrink-0">
                    <button
                        :class="
                            cn(
                                'flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-t-md border border-b-0 transition-colors',
                                store.activeTab === 'products'
                                    ? 'bg-card text-foreground border-border'
                                    : 'bg-transparent text-muted-foreground border-transparent hover:text-foreground',
                            )
                        "
                        @click="store.activeTab = 'products'"
                    >
                        <ShoppingBag class="h-4 w-4" />
                        Productos
                        <kbd class="ml-1 hidden md:inline text-[10px] font-mono px-1 py-0.5 rounded border border-border bg-muted text-muted-foreground">F1</kbd>
                    </button>
                    <button
                        :class="
                            cn(
                                'flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-t-md border border-b-0 transition-colors',
                                store.activeTab === 'payment'
                                    ? 'bg-card text-foreground border-border'
                                    : 'bg-transparent text-muted-foreground border-transparent hover:text-foreground',
                                store.cart.length === 0
                                    ? 'opacity-50 cursor-not-allowed'
                                    : '',
                            )
                        "
                        :disabled="store.cart.length === 0"
                        @click="store.cart.length > 0 && (store.activeTab = 'payment')"
                    >
                        <CreditCard class="h-4 w-4" />
                        Pagar
                        <kbd class="ml-1 hidden md:inline text-[10px] font-mono px-1 py-0.5 rounded border border-border bg-muted text-muted-foreground">F2</kbd>
                    </button>
                </div>

                <!-- Products Tab -->
                <div
                    v-show="store.activeTab === 'products'"
                    class="flex-1 flex overflow-hidden bg-card border-t border-border rounded-tr-md"
                >
                    <!-- Products + Search -->
                    <div class="flex-1 flex flex-col overflow-hidden">
                        <div class="p-2 border-b border-border shrink-0">
                            <ProductSearch ref="searchRef" />
                        </div>
                        <div class="flex-1 overflow-y-auto">
                            <ProductGrid />
                        </div>
                    </div>

                    <!-- Category Sidebar (right) -->
                    <div
                        class="border-l border-border overflow-y-auto shrink-0"
                    >
                        <CategorySidebar />
                    </div>
                </div>

                <!-- Payment Tab -->
                <div
                    v-show="store.activeTab === 'payment'"
                    class="flex-1 overflow-hidden bg-card border-t border-border rounded-tr-md"
                >
                    <PaymentPanel />
                </div>
            </div>
        </div>
    </PosShell>
</template>
