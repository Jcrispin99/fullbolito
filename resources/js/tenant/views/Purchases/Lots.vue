<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { ArrowLeft, Save, Plus, Trash2, Boxes, CheckCircle2, AlertCircle } from "lucide-vue-next";
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
    tax_id?: number | null;
    uom_id?: number | null;
    uom_symbol?: string | null;
    price_uom: number;
    price: number;
    uom_factor: number;
    expected_quantity: number;
    allocations: Allocation[];
}

const route = useRoute();
const router = useRouter();
const purchaseStore = usePurchaseStore();

const purchaseId = computed(() => String(route.params.id));
const isLoading = ref(false);
const isSaving = ref(false);
const headerData = ref<any>({});
const groups = ref<Group[]>([]);

const isDraft = computed(() => headerData.value?.status === "draft");

const breadcrumbs = computed(() => [
    { label: "Purchases", href: "/admin/purchases" },
    { label: `${headerData.value?.sequence_code || "Compra"}`, href: `/admin/purchases/${purchaseId.value}/edit` },
    { label: "Lotes" },
]);

const load = async () => {
    isLoading.value = true;
    try {
        const data = await purchaseStore.fetchPurchaseLots(purchaseId.value);
        headerData.value = data;
        groups.value = (data?.groups || []).map((g: any) => ({
            ...g,
            allocations: Array.isArray(g.allocations) && g.allocations.length > 0
                ? g.allocations.map((a: any) => ({
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
                          quantity: Number(g.expected_quantity) || 0,
                      },
                  ],
        }));
    } catch (err: any) {
        console.error("Error fetching lots:", err);
        toast.error("Error cargando lotes", {
            description: err?.message || "No se pudo obtener la información.",
        });
    } finally {
        isLoading.value = false;
    }
};

onMounted(load);

const totalFor = (group: Group): number =>
    group.allocations.reduce((s, a) => s + (Number(a.quantity) || 0), 0);

const statusFor = (group: Group): "complete" | "partial" | "over" | "empty" => {
    const total = totalFor(group);
    const expected = Number(group.expected_quantity) || 0;
    if (total === 0) return "empty";
    if (Math.abs(total - expected) < 0.001) return "complete";
    if (total > expected) return "over";
    return "partial";
};

const addAllocation = (group: Group) => {
    group.allocations.push({
        lot_number: "",
        manufactured_at: "",
        expires_at: "",
        quantity: 0,
    });
};

const removeAllocation = (group: Group, index: number) => {
    if (group.allocations.length <= 1) return;
    group.allocations.splice(index, 1);
};

const allComplete = computed(() =>
    groups.value.every((g) => statusFor(g) === "complete"),
);

const save = async () => {
    // Basic client-side validation
    for (const g of groups.value) {
        const status = statusFor(g);
        if (status === "over") {
            toast.error(`"${g.product_name}" excede la cantidad esperada.`);
            return;
        }
        if (status === "partial" || status === "empty") {
            toast.error(`"${g.product_name}" tiene una asignación incompleta.`);
            return;
        }
    }

    isSaving.value = true;
    try {
        const payload = {
            groups: groups.value.map((g) => ({
                product_product_id: g.product_product_id,
                allocations: g.allocations.map((a) => ({
                    lot_number: a.lot_number?.trim() || null,
                    manufactured_at: a.manufactured_at || null,
                    expires_at: a.expires_at || null,
                    quantity: Number(a.quantity),
                })),
            })),
        };
        const data = await purchaseStore.updatePurchaseLots(purchaseId.value, payload);
        toast.success("Lotes guardados correctamente.");
        if (data) {
            headerData.value = data;
            groups.value = (data.groups || []).map((g: any) => ({
                ...g,
                allocations: g.allocations.map((a: any) => ({
                    productable_id: a.productable_id ?? null,
                    lot_id: a.lot_id ?? null,
                    lot_number: a.lot_number ?? "",
                    manufactured_at: a.manufactured_at ?? "",
                    expires_at: a.expires_at ?? "",
                    quantity: Number(a.quantity) || 0,
                    is_official: !!a.is_official,
                })),
            }));
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

const goBack = () => {
    router.push(`/admin/purchases/${purchaseId.value}/edit`);
};

const formatNumber = (n: number) =>
    new Intl.NumberFormat("es-PE", { maximumFractionDigits: 4 }).format(n || 0);
</script>

<template>
    <DashboardLayout :breadcrumbs="breadcrumbs">
        <PageHeader :title="`Lotes — ${headerData?.sequence_code || ''}`">
            <template #leading>
                <Button
                    variant="outline"
                    size="icon"
                    class="h-9 w-9"
                    aria-label="Back"
                    @click="goBack"
                >
                    <ArrowLeft class="h-4 w-4" />
                </Button>
            </template>

            <template #trailing>
                <div class="flex items-center gap-2">
                    <span
                        v-if="headerData?.status"
                        class="hidden sm:inline-flex px-2 py-1 mr-2 rounded text-xs font-medium uppercase text-[10px] tracking-wider"
                        :class="[
                            headerData.status === 'posted'
                                ? 'bg-blue-100 text-blue-800'
                                : headerData.status === 'cancelled'
                                  ? 'bg-red-100 text-red-800'
                                  : 'bg-gray-100 text-gray-800',
                        ]"
                    >
                        {{ headerData.status }}
                    </span>

                    <Button
                        v-if="isDraft"
                        size="sm"
                        class="h-9"
                        :disabled="isSaving || isLoading || !allComplete"
                        @click="save"
                    >
                        <Save class="mr-2 h-4 w-4" />
                        {{ isSaving ? "Guardando..." : "Guardar lotes" }}
                    </Button>
                </div>
            </template>
        </PageHeader>

        <div v-if="isLoading" class="text-sm text-muted-foreground py-8 text-center">
            Cargando...
        </div>

        <div
            v-else-if="groups.length === 0"
            class="text-sm text-muted-foreground py-12 text-center"
        >
            Esta compra no tiene productos con trazabilidad por lote.
        </div>

        <div v-else class="space-y-4">
            <Card v-for="group in groups" :key="group.product_product_id">
                <CardHeader class="border-b pb-3">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <Boxes class="h-4 w-4 text-primary shrink-0" />
                                <h3 class="font-semibold truncate">
                                    {{ group.product_name }}
                                </h3>
                                <span
                                    v-if="group.sku"
                                    class="text-xs text-muted-foreground"
                                >
                                    SKU: {{ group.sku }}
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground mt-1">
                                Esperado:
                                <span class="font-medium text-foreground">
                                    {{ formatNumber(group.expected_quantity) }}
                                </span>
                                <span v-if="group.uom_symbol">
                                    {{ group.uom_symbol }}
                                </span>
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <div class="flex items-center gap-2 justify-end">
                                <CheckCircle2
                                    v-if="statusFor(group) === 'complete'"
                                    class="h-4 w-4 text-green-600"
                                />
                                <AlertCircle
                                    v-else
                                    class="h-4 w-4"
                                    :class="
                                        statusFor(group) === 'over'
                                            ? 'text-red-600'
                                            : 'text-amber-600'
                                    "
                                />
                                <span
                                    class="text-sm font-medium"
                                    :class="
                                        statusFor(group) === 'complete'
                                            ? 'text-green-700'
                                            : statusFor(group) === 'over'
                                              ? 'text-red-700'
                                              : 'text-amber-700'
                                    "
                                >
                                    {{ formatNumber(totalFor(group)) }} /
                                    {{ formatNumber(group.expected_quantity) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="pt-4">
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr
                                    class="border-b text-left text-[11px] text-muted-foreground uppercase tracking-wider"
                                >
                                    <th class="pb-2 px-2 font-medium">N° Lote</th>
                                    <th class="pb-2 px-2 font-medium w-36">
                                        Fab.
                                    </th>
                                    <th class="pb-2 px-2 font-medium w-36">
                                        Vence
                                    </th>
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
                                            :placeholder="
                                                isDraft ? 'Auto' : '—'
                                            "
                                            :disabled="
                                                !isDraft || alloc.is_official
                                            "
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
                                            :disabled="
                                                !isDraft || alloc.is_official
                                            "
                                            class="h-8 w-full bg-transparent border-0 border-b border-input focus:border-primary focus:ring-0 outline-none px-1 py-1 text-sm transition-all rounded-none disabled:cursor-not-allowed"
                                        />
                                    </td>
                                    <td class="py-2 px-2">
                                        <input
                                            v-model="alloc.expires_at"
                                            type="date"
                                            :disabled="
                                                !isDraft || alloc.is_official
                                            "
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
                                            @click="removeAllocation(group, i)"
                                            aria-label="Remove lot"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="isDraft"
                        class="flex items-center justify-between pt-3"
                    >
                        <Button
                            variant="ghost"
                            size="sm"
                            type="button"
                            class="text-primary"
                            @click="addAllocation(group)"
                        >
                            <Plus class="mr-1 h-4 w-4" />
                            Agregar lote
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </DashboardLayout>
</template>
