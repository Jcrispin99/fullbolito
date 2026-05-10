import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "../lib/api";
import { createFetchAbort } from '@/composables/useFetchAbort'

export interface PosConfig {
    id: number;
    company_id: number;
    warehouse_id: number;
    default_customer_id: number | null;
    tax_id: number | null;
    name: string;
    has_active_session: boolean;
    apply_tax: boolean;
    prices_include_tax: boolean;
    is_active: boolean;
    default_lot_strategy: 'fefo_auto' | 'fefo_suggest_manual' | 'manual' | null;
    allow_expired_sale_with_override: boolean;
    lot_scan_mode: 'product_only' | 'hybrid' | null;
    auto_print_receipt: boolean;
    created_at: string;
    updated_at: string;
    warehouse?: {
        id: number;
        name: string;
    };
    tax?: any;
}

export interface PosConfigsResponse {
    data: PosConfig[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export const usePosConfigStore = defineStore("posConfig", () => {
    const posConfigs = ref<PosConfig[]>([]);
    const currentPosConfig = ref<PosConfig | null>(null);
    const meta = ref<PosConfigsResponse["meta"]>({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort()

    const fetchPosConfigs = async (
        page = 1,
        perPage: number | string = 15,
        search = "",
        status: "active" | "inactive" | "all" | string = "active",
    ) => {
        isLoading.value = true;
        error.value = null;
        try {
            const qp = new URLSearchParams({ page: String(page) });
            if (perPage) qp.set("per_page", String(perPage));
            if (search) qp.set("search", search);
            if (status) qp.set("status", status);

            const { data } = await apiClient.get<any>(
                `/v1/pos-configs?${qp.toString()}`,
                { signal: getSignal() },
            );

            const responseData = data as any;
            if (responseData?.meta) {
                posConfigs.value = responseData.data || [];
                meta.value = responseData.meta;
            } else if (responseData?.data?.meta) {
                posConfigs.value = responseData.data.data || [];
                meta.value = responseData.data.meta;
            } else {
                posConfigs.value = Array.isArray(responseData?.data)
                    ? responseData.data
                    : responseData?.data?.data || [];
                meta.value = {
                    current_page: 1,
                    last_page: 1,
                    per_page: posConfigs.value.length,
                    total: posConfigs.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return
            error.value = err.message || "Failed to fetch POS configs";
            console.error(err);
        } finally {
            isLoading.value = false;
        }
    };

    const fetchPosConfig = async (id: number | string) => {
        isLoading.value = true;
        try {
            const { data } = await apiClient.get<any>(`/v1/pos-configs/${id}`);
            return data.data;
        } catch (err: any) {
            console.error(err);
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    const fetchFormOptions = async () => {
        const { data } = await apiClient.get<any>("/v1/pos-configs/form-options");
        return data.data;
    };

    const createPosConfig = async (payload: any) => {
        const { data } = await apiClient.post<any>("/v1/pos-configs", payload);
        return data.data;
    };

    const updatePosConfig = async (id: number | string, payload: any) => {
        const { data } = await apiClient.put<any>(
            `/v1/pos-configs/${id}`,
            payload,
        );
        return data.data;
    };

    const toggleActive = async (id: number | string) => {
        const { data } = await apiClient.patch(`/v1/pos-configs/${id}/toggle-status`);
        return data.data; 
    };

    const deletePosConfig = async (id: number | string) => {
        await apiClient.delete(`/v1/pos-configs/${id}`);
    };

    const deletePosConfigs = async (ids: (number | string)[]) => {
        await apiClient.delete("/v1/pos-configs", { data: { ids } });
    };

    return {
        posConfigs,
        currentPosConfig,
        meta,
        isLoading,
        error,
        fetchPosConfigs,
        fetchPosConfig,
        fetchFormOptions,
        createPosConfig,
        updatePosConfig,
        toggleActive,
        deletePosConfig,
        deletePosConfigs,
    };
});
