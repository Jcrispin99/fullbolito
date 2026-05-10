import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from '@/composables/useFetchAbort'

export interface Sale {
    id: number;
    serie: string | null;
    correlative: string;
    date: string | null;
    notes: string | null;
    partner_id: number | null;
    partner?: { id: number; name: string; display_name?: string; document_number?: string };
    user_id?: number | null;
    seller_id?: number | null;
    user?: { id: number; name: string };
    seller?: { id: number; name: string };
    total: number;
    status: string;
    payment_status: string;
    created_at: string | null;
    updated_at: string | null;
}

export const useSaleStore = defineStore("sale", () => {
    const sales = ref<Sale[]>([]);
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
        draft_total: 0,
        posted_total: 0,
        cancelled_total: 0,
    });

    const fetchSales = async (
        page = 1,
        perPage: number | "total" = 15,
        search = "",
        status = "all",
        paymentStatus = "all",
    ) => {
        isLoading.value = true;
        error.value = null;
        try {
            const params: Record<string, any> = {
                page,
                per_page: perPage,
                search,
            };

            if (status !== "all") {
                params.status = status;
            }

            if (paymentStatus !== "all") {
                params.payment_status = paymentStatus;
            }

            const response = await apiClient.get<any>("/v1/sales", { params, signal: getSignal() });
            const responseData = response.data as { data: Sale[]; meta?: any };

            sales.value = responseData.data || [];

            if (responseData.meta) {
                meta.value = {
                    ...meta.value,
                    ...responseData.meta,
                };
            } else {
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: sales.value.length,
                    to: sales.value.length,
                    total: sales.value.length,
                    draft_total: 0,
                    posted_total: 0,
                    cancelled_total: 0,
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

    const deleteSale = async (id: number) => {
        try {
            await apiClient.delete(`/v1/sales/${id}`);
            sales.value = sales.value.filter((s) => s.id !== id);
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const fetchFormOptions = async () => {
        try {
            const response = await apiClient.get<any>("/v1/sales/form-options");
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const fetchSale = async (id: number | string) => {
        try {
            const response = await apiClient.get<any>(`/v1/sales/${id}`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const createSale = async (payload: any) => {
        try {
            const response = await apiClient.post<any>("/v1/sales", payload);
            return response.data.data;
        } catch (err: any) {
            throw err;
        }
    };

    const updateSale = async (id: number | string, payload: any) => {
        try {
            const response = await apiClient.put<any>(`/v1/sales/${id}`, payload);
            return response.data.data;
        } catch (err: any) {
            throw err;
        }
    };

    const postSale = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/sales/${id}/post`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const cancelSale = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/sales/${id}/cancel`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const paySale = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/sales/${id}/pay`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const sendToSunat = async (id: number | string) => {
        try {
            const response = await apiClient.post<any>(`/v1/sales/${id}/sunat/send`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const createSaleRefund = async (
        id: number | string,
        payload: {
            lines: { product_product_id: number; quantity: number }[];
            notes?: string | null;
        },
    ) => {
        try {
            const response = await apiClient.post<any>(
                `/v1/sales/${id}/refunds`,
                payload,
            );
            return response.data.data;
        } catch (err: any) {
            throw err;
        }
    };

    return {
        sales,
        isLoading,
        error,
        meta,
        fetchSales,
        deleteSale,
        fetchFormOptions,
        fetchSale,
        createSale,
        updateSale,
        postSale,
        cancelSale,
        paySale,
        sendToSunat,
        createSaleRefund,
    };
});
