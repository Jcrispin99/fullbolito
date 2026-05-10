<script setup lang="ts">
import { ref, computed, watch } from "vue";
import {
    DialogRoot,
    DialogPortal,
    DialogOverlay,
    DialogContent,
    DialogTitle,
    DialogDescription,
    DialogClose,
} from "radix-vue";
import { Button } from "@/components/ui/button";
import { X, Boxes, Check, Zap } from "lucide-vue-next";
import { useLotStore, type Lot } from "@tenant/stores/lot";
import { toast } from "vue-sonner";

const props = defineProps<{
    open: boolean;
    productProductId: number | null;
    productName: string;
    warehouseId: number | null;
    currentLotId: number | null;
}>();

const emit = defineEmits<{
    (e: "close"): void;
    (
        e: "select",
        payload: {
            lot_id: number | null;
            lot_number?: string | null;
            expires_at?: string | null;
        },
    ): void;
}>();

const lotStore = useLotStore();
const isLoading = ref(false);
const lots = ref<Lot[]>([]);
const selectedId = ref<number | null>(null);
const search = ref("");

const load = async () => {
    if (!props.productProductId) {
        lots.value = [];
        return;
    }
    isLoading.value = true;
    try {
        lots.value = await lotStore.fetchAvailableForProduct(
            props.productProductId,
            props.warehouseId ?? null,
        );
    } catch (err: any) {
        toast.error("Error cargando lotes disponibles", {
            description: err?.message || "No se pudo obtener la lista.",
        });
        lots.value = [];
    } finally {
        isLoading.value = false;
    }
};

watch(
    () => [props.open, props.productProductId, props.warehouseId],
    ([open]) => {
        if (open) {
            selectedId.value = props.currentLotId ?? null;
            search.value = "";
            void load();
        } else {
            lots.value = [];
        }
    },
    { immediate: true },
);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return lots.value;
    return lots.value.filter((l) =>
        (l.lot_number ?? "").toLowerCase().includes(q),
    );
});

const confirm = () => {
    if (selectedId.value === null) {
        emit("select", { lot_id: null });
        return;
    }
    const lot = lots.value.find((l) => l.id === selectedId.value);
    emit("select", {
        lot_id: selectedId.value,
        lot_number: lot?.lot_number ?? null,
        expires_at: lot?.expires_at ?? null,
    });
};

const selectAuto = () => {
    emit("select", { lot_id: null, lot_number: null, expires_at: null });
};
</script>

<template>
    <DialogRoot :open="open" @update:open="(v) => { if (!v) emit('close') }">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <DialogContent
                class="fixed left-1/2 top-1/2 z-50 grid w-full max-w-2xl -translate-x-1/2 -translate-y-1/2 border bg-background shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 sm:rounded-lg max-h-[90vh] flex flex-col"
                :trap-focus="false"
                @pointer-down-outside="(e: any) => e.preventDefault()"
                @interact-outside="(e: any) => e.preventDefault()"
                @close-auto-focus="(e: Event) => e.preventDefault()"
            >
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <div class="min-w-0">
                        <DialogTitle
                            class="text-lg font-semibold flex items-center gap-2"
                        >
                            <Boxes class="h-5 w-5" />
                            <span class="truncate">
                                {{ productName || "Elegir lote" }}
                            </span>
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground">
                            Selecciona un lote del almacén origen o deja Auto (FEFO)
                            para asignación automática por vencimiento.
                        </DialogDescription>
                    </div>
                    <DialogClose
                        class="rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                    >
                        <X class="h-4 w-4" />
                        <span class="sr-only">Close</span>
                    </DialogClose>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-3">
                    <div
                        v-if="!warehouseId"
                        class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800"
                    >
                        Seleccione un almacén origen en la cabecera para ver los
                        lotes disponibles.
                    </div>

                    <input
                        v-model="search"
                        type="text"
                        placeholder="Buscar por número de lote..."
                        class="w-full h-9 px-3 rounded-md border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    />

                    <button
                        type="button"
                        class="w-full flex items-center justify-between rounded-md border px-3 py-2 text-sm transition-colors hover:bg-accent"
                        :class="
                            selectedId === null
                                ? 'border-primary bg-primary/5'
                                : 'border-input'
                        "
                        @click="selectedId = null"
                    >
                        <span class="flex items-center gap-2">
                            <Zap class="h-4 w-4 text-primary" />
                            <span class="font-medium">Auto (FEFO)</span>
                            <span class="text-xs text-muted-foreground">
                                El sistema elige los lotes por vencimiento
                            </span>
                        </span>
                        <Check
                            v-if="selectedId === null"
                            class="h-4 w-4 text-primary"
                        />
                    </button>

                    <div
                        v-if="isLoading"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        Cargando lotes...
                    </div>

                    <div
                        v-else-if="filtered.length === 0"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        <template v-if="search">
                            No hay lotes que coincidan con "{{ search }}".
                        </template>
                        <template v-else>
                            No hay lotes disponibles para este producto en el
                            almacén origen.
                        </template>
                    </div>

                    <div v-else class="space-y-1">
                        <button
                            v-for="lot in filtered"
                            :key="lot.id"
                            type="button"
                            class="w-full flex items-center justify-between rounded-md border px-3 py-2 text-sm transition-colors hover:bg-accent"
                            :class="
                                selectedId === lot.id
                                    ? 'border-primary bg-primary/5'
                                    : 'border-input'
                            "
                            @click="selectedId = lot.id"
                        >
                            <div class="min-w-0 text-left">
                                <div class="font-medium font-mono">
                                    {{ lot.lot_number }}
                                </div>
                                <div
                                    class="text-xs text-muted-foreground flex gap-3 flex-wrap"
                                >
                                    <span v-if="lot.expires_at">
                                        Vence:
                                        <span
                                            :class="
                                                lot.is_expired
                                                    ? 'text-destructive font-medium'
                                                    : ''
                                            "
                                        >
                                            {{ lot.expires_at }}
                                        </span>
                                        <span
                                            v-if="
                                                lot.days_to_expire !== null &&
                                                !lot.is_expired
                                            "
                                        >
                                            ({{ lot.days_to_expire }} días)
                                        </span>
                                    </span>
                                    <span v-if="lot.total_stock !== null">
                                        Stock:
                                        <span class="font-medium text-foreground">
                                            {{ lot.total_stock }}
                                        </span>
                                    </span>
                                </div>
                            </div>
                            <Check
                                v-if="selectedId === lot.id"
                                class="h-4 w-4 text-primary shrink-0 ml-2"
                            />
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t px-6 py-3">
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('close')"
                    >
                        Cancelar
                    </Button>
                    <Button
                        v-if="currentLotId !== null"
                        type="button"
                        variant="ghost"
                        @click="selectAuto"
                    >
                        Limpiar (Auto)
                    </Button>
                    <Button type="button" @click="confirm">
                        <Check class="mr-2 h-4 w-4" />
                        Confirmar
                    </Button>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
