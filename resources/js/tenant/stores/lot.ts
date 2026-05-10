import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "../lib/api";
import { createFetchAbort } from "@/composables/useFetchAbort";

export type LotStatus = "active" | "blocked" | "expired" | "depleted";

export interface LotInventoryByWarehouse {
    warehouse_id: number;
    warehouse_name: string | null;
    quantity_balance: number;
}

export interface Lot {
    id: number;
    product_product_id: number;
    company_id: number;
    lot_number: string;
    manufactured_at: string | null;
    expires_at: string | null;
    is_expired: boolean;
    days_to_expire: number | null;
    supplier_id: number | null;
    purchase_id: number | null;
    initial_quantity: number;
    initial_cost: number;
    status: LotStatus;
    notes: string | null;
    total_stock: number | null;
    product_product?: any;
    supplier?: any;
    purchase?: {
        id: number;
        serie: string | null;
        correlative: string | null;
        sequence_code: string;
    };
    inventories_by_warehouse?: LotInventoryByWarehouse[];
    created_at: string;
    updated_at: string;
}

export interface LotsResponse {
    data: Lot[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export interface LotFilters {
    search?: string;
    status?: LotStatus | "all";
    product_product_id?: number | null;
    warehouse_id?: number | null;
    expiring_in_days?: number | null;
    expired?: boolean;
    in_stock?: boolean;
    from?: string;
    to?: string;
}

export const useLotStore = defineStore("lot", () => {
    const lots = ref<Lot[]>([]);
    const meta = ref<LotsResponse["meta"] | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort();

    const fetchLots = async (
        page = 1,
        perPage: number | string = 25,
        filters: LotFilters = {},
    ) => {
        isLoading.value = true;
        error.value = null;
        try {
            const qp = new URLSearchParams({ page: String(page) });
            if (perPage) qp.set("per_page", String(perPage));
            if (filters.search) qp.set("search", filters.search);
            if (filters.status && filters.status !== "all") qp.set("status", filters.status);
            if (filters.product_product_id) qp.set("product_product_id", String(filters.product_product_id));
            if (filters.warehouse_id) qp.set("warehouse_id", String(filters.warehouse_id));
            if (filters.expiring_in_days) qp.set("expiring_in_days", String(filters.expiring_in_days));
            if (filters.expired) qp.set("expired", "1");
            if (filters.in_stock) qp.set("in_stock", "1");
            if (filters.from) qp.set("from", filters.from);
            if (filters.to) qp.set("to", filters.to);

            const { data } = await apiClient.get<any>(`/v1/lots?${qp.toString()}`, {
                signal: getSignal(),
            });

            if (data?.meta) {
                lots.value = data.data || [];
                meta.value = data.meta;
            } else if (data?.data?.meta) {
                lots.value = data.data.data || [];
                meta.value = data.data.meta;
            } else {
                lots.value = Array.isArray(data?.data) ? data.data : [];
                meta.value = {
                    current_page: 1,
                    last_page: 1,
                    per_page: lots.value.length,
                    total: lots.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value = err.message || "Failed to fetch lots";
            console.error(err);
        } finally {
            isLoading.value = false;
        }
    };

    const fetchLot = async (id: number | string): Promise<Lot> => {
        const { data } = await apiClient.get<any>(`/v1/lots/${id}`);
        return data.data;
    };

    const fetchFormOptions = async () => {
        const { data } = await apiClient.get<any>("/v1/lots/form-options");
        return data.data;
    };

    const updateLot = async (id: number | string, payload: Partial<Lot>) => {
        const { data } = await apiClient.put<any>(`/v1/lots/${id}`, payload);
        return data.data;
    };

    const toggleStatus = async (id: number | string) => {
        const { data } = await apiClient.patch<any>(`/v1/lots/${id}/toggle-status`);
        return data.data;
    };

    const deleteLot = async (id: number | string) => {
        await apiClient.delete(`/v1/lots/${id}`);
    };

    const fetchAvailableForProduct = async (
        productProductId: number,
        warehouseId?: number | null,
    ): Promise<Lot[]> => {
        const qp = new URLSearchParams();
        if (warehouseId) qp.set("warehouse_id", String(warehouseId));
        const url = `/v1/product-products/${productProductId}/available-lots${qp.toString() ? `?${qp.toString()}` : ""}`;
        const { data } = await apiClient.get<any>(url);
        return data.data || [];
    };

    return {
        lots,
        meta,
        isLoading,
        error,
        fetchLots,
        fetchLot,
        fetchFormOptions,
        updateLot,
        toggleStatus,
        deleteLot,
        fetchAvailableForProduct,
    };
});
