import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import type { PaginatedResponse } from "@/types/api";
import { createFetchAbort } from '@/composables/useFetchAbort'

export interface Purchase {
    id: number;
    serie: string | null;
    correlative: string;
    date: string | null;
    partner_id: number;
    partner: { id: number; trade_name: string; email: string };
    warehouse_id: number;
    company_id: number | null;
    total: string;
    observation: string | null;
    status: string;
    payment_status: string;
    vendor_bill_number: string | null;
    vendor_bill_date: string | null;
    created_at: string;
    updated_at: string;
}

export const usePurchaseStore = defineStore("purchase", () => {
    const purchases = ref<Purchase[]>([]);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort()
    const meta = ref({
        current_page: 1,
        from: 0,
        last_page: 1,
        per_page: 15,
        to: 0,
        total: 0,
    });

    const fetchPurchases = async (
        page = 1,
        perPage: number | "total" = 15,
        search = "",
        statusParam = "all",
    ) => {
        isLoading.value = true;
        error.value = null;
        try {
            const params: Record<string, any> = {
                page,
                per_page: perPage,
                search,
                statusParam,
            };

            const response = await apiClient.get<any>(
                "/v1/purchases",
                { params, signal: getSignal() },
            );

            // Our controller returns: { success, message, data: [...items], meta: {...}, links: {...} }
            // So response.data is the ApiResponse, meaning response.data.data is the array of purchases
            // and response.data.meta is the pagination meta.
            const responseData = response.data as { data: Purchase[], meta?: any };
            
            purchases.value = responseData.data || [];
            
            if (responseData.meta) {
                meta.value = responseData.meta;
            } else {
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: purchases.value.length,
                    to: purchases.value.length,
                    total: purchases.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return
            error.value = err.response?.data?.message || err.message;
            throw error.value;
        } finally {
            isLoading.value = false;
        }
    };

    const deletePurchase = async (id: number) => {
        try {
            await apiClient.delete(`/v1/purchases/${id}`);
            purchases.value = purchases.value.filter((p) => p.id !== id);
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const deletePurchases = async (ids: number[]) => {
        try {
            await apiClient.post("/v1/purchases/batch-delete", { ids });
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const fetchFormOptions = async () => {
        try {
            const response = await apiClient.get<any>("/v1/purchases/form-options");
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const fetchPurchase = async (id: number | string) => {
        try {
            const response = await apiClient.get<any>(`/v1/purchases/${id}`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const createPurchase = async (payload: any) => {
        try {
            const response = await apiClient.post<any>("/v1/purchases", payload);
            return response.data.data;
        } catch (err: any) {
            throw err;
        }
    };

    const updatePurchase = async (id: number | string, payload: any) => {
        try {
            const response = await apiClient.put<any>(`/v1/purchases/${id}`, payload);
            return response.data.data;
        } catch (err: any) {
            throw err;
        }
    };

    const postPurchase = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/purchases/${id}/post`);
            return response.data.data;
        } catch (err: any) {
            throw err;
        }
    };

    const cancelPurchase = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/purchases/${id}/cancel`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const draftPurchase = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/purchases/${id}/draft`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const payPurchase = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/purchases/${id}/pay`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const fetchPurchaseLots = async (id: number | string) => {
        try {
            const response = await apiClient.get<any>(`/v1/purchases/${id}/lots`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const updatePurchaseLots = async (id: number | string, payload: any) => {
        try {
            const response = await apiClient.put<any>(`/v1/purchases/${id}/lots`, payload);
            return response.data.data;
        } catch (err: any) {
            throw err;
        }
    };

    return {
        purchases,
        isLoading,
        error,
        meta,
        fetchPurchases,
        deletePurchase,
        deletePurchases,
        fetchFormOptions,
        fetchPurchase,
        createPurchase,
        updatePurchase,
        postPurchase,
        cancelPurchase,
        draftPurchase,
        payPurchase,
        fetchPurchaseLots,
        updatePurchaseLots,
    };
});
