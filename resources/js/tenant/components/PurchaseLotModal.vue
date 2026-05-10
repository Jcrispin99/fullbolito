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
import {
    X,
    Boxes,
    Save,
    Plus,
    Trash2,
    CheckCircle2,
    AlertCircle,
} from "lucide-vue-next";
import { usePurchaseStore } from "@tenant/stores/purchase";
import { toast } from "vue-sonner";

interface Allocation {
    productable_id?: number | null;
    lot_id?: number | null;
    lot_number?: string | null;
    manufactured_at?: string | null;
    expires_at?: string | null;
    quantity: number;
    is_official?: boolean;
}

interface Group {
    product_product_id: number;
    product_name: string;
    sku?: string | null;
    uom_symbol?: string | null;
    expected_quantity: number;
    allocations: Allocation[];
}

const props = defineProps<{
    open: boolean;
    purchaseId: number | string | null;
    productProductId: number | null;
    productName: string;
    isDraft: boolean;
}>();

const emit = defineEmits<{
    (e: "close"): void;
    (e: "saved", group: Group): void;
}>();

const purchaseStore = usePurchaseStore();
const isLoading = ref(false);
const isSaving = ref(false);
const purchaseStatus = ref<string | null>(null);
const group = ref<Group | null>(null);

const totalAllocated = computed(() => {
    if (!group.value) return 0;
    return group.value.allocations.reduce(
        (s, a) => s + (Number(a.quantity) || 0),
        0,
    );
});

const allocationStatus = computed<"complete" | "partial" | "over" | "empty">(() => {
    if (!group.value) return "empty";
    const total = totalAllocated.value;
    const expected = Number(group.value.expected_quantity) || 0;
    if (total === 0) return "empty";
    if (Math.abs(total - expected) < 0.001) return "complete";
    if (total > expected) return "over";
    return "partial";
});

const canSave = computed(
    () => props.isDraft && allocationStatus.value === "complete",
);

const formatNumber = (n: number) =>
    new Intl.NumberFormat("es-PE", { maximumFractionDigits: 4 }).format(n || 0);

const load = async () => {
    if (!props.purchaseId || !props.productProductId) {
        group.value = null;
        return;
    }
    isLoading.value = true;
    try {
        const data = await purchaseStore.fetchPurchaseLots(props.purchaseId);
        purchaseStatus.value = data?.status ?? null;
        const match = (data?.groups || []).find(
            (g: any) => Number(g.product_product_id) === Number(props.productProductId),
        );
        if (!match) {
            group.value = null;
            toast.error(
                "Esta línea no tiene productos con trazabilidad por lote todavía.",
            );
            return;
        }
        const allocs: Allocation[] = Array.isArray(match.allocations) && match.allocations.length > 0
            ? match.allocations.map((a: any) => ({
                  productable_id: a.productable_id ?? null,
                  lot_id: a.lot_id ?? null,
                  lot_number: a.lot_number ?? "",
                  manufactured_at: a.manufactured_at ?? "",
                  expires_at: a.expires_at ?? "",
                  quantity: Number(a.quantity) || 0,
                  is_official: !!a.is_official,
              }))
            : [
                  {
                      lot_number: "",
                      manufactured_at: "",
                      expires_at: "",
                      quantity: Number(match.expected_quantity) || 0,
                  },
              ];
        group.value = {
            product_product_id: match.product_product_id,
            product_name: match.product_name,
            sku: match.sku ?? null,
            uom_symbol: match.uom_symbol ?? null,
            expected_quantity: Number(match.expected_quantity) || 0,
            allocations: allocs,
        };
    } catch (err: any) {
        toast.error("Error cargando lotes", {
            description: err?.message || "No se pudo obtener la información.",
        });
        group.value = null;
    } finally {
        isLoading.value = false;
    }
};

watch(
    () => [props.open, props.purchaseId, props.productProductId],
    ([open]) => {
        if (open) {
            void load();
        } else {
            group.value = null;
            purchaseStatus.value = null;
        }
    },
    { immediate: true },
);

const addAllocation = () => {
    if (!group.value) return;
    group.value.allocations.push({
        lot_number: "",
        manufactured_at: "",
        expires_at: "",
        quantity: 0,
    });
};

const removeAllocation = (index: number) => {
    if (!group.value) return;
    if (group.value.allocations.length <= 1) return;
    group.value.allocations.splice(index, 1);
};

const save = async () => {
    if (!group.value || !props.purchaseId) return;

    if (allocationStatus.value === "over") {
        toast.error("Las cantidades exceden la cantidad esperada.");
        return;
    }
    if (allocationStatus.value !== "complete") {
        toast.error("La suma de cantidades debe igualar la cantidad esperada.");
        return;
    }

    isSaving.value = true;
    try {
        const payload = {
            groups: [
                {
                    product_product_id: group.value.product_product_id,
                    allocations: group.value.allocations.map((a) => ({
                        lot_number: a.lot_number?.trim() || null,
                        manufactured_at: a.manufactured_at || null,
                        expires_at: a.expires_at || null,
                        quantity: Number(a.quantity),
                    })),
                },
            ],
        };
        const data = await purchaseStore.updatePurchaseLots(
            props.purchaseId,
            payload,
        );
        toast.success("Lotes guardados correctamente.");
        const updated = (data?.groups || []).find(
            (g: any) => Number(g.product_product_id) === Number(props.productProductId),
        );
        if (updated) {
            emit("saved", updated);
        } else {
            emit("close");
        }
    } catch (err: any) {
        const e = err?.response?.data || err;
        toast.error("Error guardando lotes", {
            description: e?.message || "No se pudo guardar.",
        });
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <DialogRoot :open="open" @update:open="(v) => { if (!v) emit('close') }">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <DialogContent
                class="fixed left-1/2 top-1/2 z-50 grid w-full max-w-3xl -translate-x-1/2 -translate-y-1/2 border bg-background shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 sm:rounded-lg max-h-[90vh] flex flex-col"
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
                            <span class="truncate">{{ productName || "Lotes" }}</span>
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground">
                            {{
                                isDraft
                                    ? "Crea y distribuye los lotes para esta línea."
                                    : "Vista de solo lectura (compra no editable)."
                            }}
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
                        v-if="isLoading"
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        Cargando...
                    </div>

                    <template v-else-if="group">
                        <div
                            class="flex items-center justify-between rounded-md border bg-muted/30 px-4 py-3"
                        >
                            <div class="min-w-0">
                                <div class="text-sm font-medium truncate">
                                    {{ group.product_name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    <span v-if="group.sku">SKU: {{ group.sku }} · </span>
                                    Esperado:
                                    <span class="font-medium text-foreground">
                                        {{ formatNumber(group.expected_quantity) }}
                                    </span>
                                    <span v-if="group.uom_symbol">
                                        {{ group.uom_symbol }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0 ml-4">
                                <CheckCircle2
                                    v-if="allocationStatus === 'complete'"
                                    class="h-4 w-4 text-green-600"
                                />
                                <AlertCircle
                                    v-else
                                    class="h-4 w-4"
                                    :class="
                                        allocationStatus === 'over'
                                            ? 'text-red-600'
                                            : 'text-amber-600'
                                    "
                                />
                                <span
                                    class="text-sm font-medium"
                                    :class="
                                        allocationStatus === 'complete'
                                            ? 'text-green-700'
                                            : allocationStatus === 'over'
                                              ? 'text-red-700'
                                              : 'text-amber-700'
                                    "
                                >
                                    {{ formatNumber(totalAllocated) }} /
                                    {{ formatNumber(group.expected_quantity) }}
                                </span>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr
                                        class="border-b text-left text-[11px] text-muted-foreground uppercase tracking-wider"
                                    >
                                        <th class="pb-2 px-2 font-medium">N° Lote</th>
                                        <th class="pb-2 px-2 font-medium w-36">Fab.</th>
                                        <th class="pb-2 px-2 font-medium w-36">Vence</th>
                                        <th
                                            class="pb-2 px-2 font-medium w-32 text-right"
                                        >
                                            Cantidad
                                        </th>
                                        <th class="pb-2 px-2 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(alloc, i) in group.allocations"
                                        :key="i"
                                        class="group border-b border-dashed"
                                    >
                                        <td class="py-2 px-2">
                                            <input
                                                v-model="alloc.lot_number"
                                                type="text"
                                                maxlength="50"
                                                :placeholder="isDraft ? 'Auto' : '—'"
                                                :disabled="!isDraft || alloc.is_official"
                                                class="h-8 w-full bg-transparent border-0 border-b border-input focus:border-primary focus:ring-0 outline-none px-1 py-1 text-sm transition-all rounded-none disabled:cursor-not-allowed"
                                            />
                                            <span
                                                v-if="alloc.is_official"
                                                class="text-[10px] text-blue-600 mt-0.5 inline-block"
                                            >
                                                Lote oficial
                                            </span>
                                        </td>
                                        <td class="py-2 px-2">
                                            <input
                                                v-model="alloc.manufactured_at"
                                                type="date"
                                                :disabled="!isDraft || alloc.is_official"
                                                class="h-8 w-full bg-transparent border-0 border-b border-input focus:border-primary focus:ring-0 outline-none px-1 py-1 text-sm transition-all rounded-none disabled:cursor-not-allowed"
                                            />
                                        </td>
                                        <td class="py-2 px-2">
                                            <input
                                                v-model="alloc.expires_at"
                                                type="date"
                                                :disabled="!isDraft || alloc.is_official"
                                                class="h-8 w-full bg-transparent border-0 border-b border-input focus:border-primary focus:ring-0 outline-none px-1 py-1 text-sm transition-all rounded-none disabled:cursor-not-allowed"
                                            />
                                        </td>
                                        <td class="py-2 px-2 text-right">
                                            <input
                                                v-model.number="alloc.quantity"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                :disabled="!isDraft"
                                                class="h-8 w-full bg-transparent border-0 border-b border-input focus:border-primary focus:ring-0 outline-none px-1 py-1 text-sm text-right transition-all rounded-none disabled:cursor-not-allowed"
                                            />
                                        </td>
                                        <td class="py-2 px-2 text-right">
                                            <Button
                                                v-if="
                                                    isDraft &&
                                                    group.allocations.length > 1
                                                "
                                                variant="ghost"
                                                size="icon"
                                                type="button"
                                                class="h-8 w-8 text-muted-foreground hover:text-destructive opacity-0 group-hover:opacity-100 transition-opacity"
                                                @click="removeAllocation(i)"
                                                aria-label="Remove lot"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="isDraft" class="pt-1">
                            <Button
                                variant="ghost"
                                size="sm"
                                type="button"
                                class="text-primary"
                                @click="addAllocation"
                            >
                                <Plus class="mr-1 h-4 w-4" />
                                Agregar lote
                            </Button>
                        </div>
                    </template>

                    <div
                        v-else
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No hay información de lotes para esta línea.
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t px-6 py-3">
                    <Button type="button" variant="outline" @click="emit('close')">
                        {{ isDraft ? "Cancelar" : "Cerrar" }}
                    </Button>
                    <Button
                        v-if="isDraft"
                        type="button"
                        :disabled="!canSave || isSaving || isLoading"
                        @click="save"
                    >
                        <Save class="mr-2 h-4 w-4" />
                        {{ isSaving ? "Guardando..." : "Guardar lotes" }}
                    </Button>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
