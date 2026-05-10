import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "../lib/api";
import { createFetchAbort } from '@/composables/useFetchAbort'

export interface PaymentMethod {
    id: number;
    name: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface PaymentMethodsResponse {
    data: PaymentMethod[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export const usePaymentMethodStore = defineStore("paymentMethod", () => {
    const paymentMethods = ref<PaymentMethod[]>([]);
    const currentPaymentMethod = ref<PaymentMethod | null>(null);
    const meta = ref<PaymentMethodsResponse["meta"]>({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort()

    const fetchPaymentMethods = async (
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
            
            // Payment methods API doesn't seem to support 'status' param
            // if (status) qp.set("status", status);

            const { data } = await apiClient.get<any>(
                `/v1/payment-methods?${qp.toString()}`,
                { signal: getSignal() },
            );

            const responseData = data as any;
            if (responseData?.meta) {
                paymentMethods.value = responseData.data || [];
                meta.value = responseData.meta;
            } else if (responseData?.data?.meta) {
                paymentMethods.value = responseData.data.data || [];
                meta.value = responseData.data.meta;
            } else {
                paymentMethods.value = Array.isArray(responseData?.data)
                    ? responseData.data
                    : responseData?.data?.data || [];
                meta.value = {
                    current_page: 1,
                    last_page: 1,
                    per_page: paymentMethods.value.length,
                    total: paymentMethods.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return
            error.value = err.message || "Failed to fetch payment methods";
            console.error(err);
        } finally {
            isLoading.value = false;
        }
    };

    const fetchPaymentMethod = async (id: number | string) => {
        isLoading.value = true;
        try {
            const { data } = await apiClient.get<any>(`/v1/payment-methods/${id}`);
            return data.data;
        } catch (err: any) {
            console.error(err);
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    const createPaymentMethod = async (payload: any) => {
        // Ensure is_active is sent as true by default if not provided
        const finalPayload = { is_active: true, ...payload };
        const { data } = await apiClient.post<any>("/v1/payment-methods", finalPayload);
        return data.data;
    };

    const updatePaymentMethod = async (id: number | string, payload: any) => {
        // We use PUT instead of PATCH as per user curl instructions
        const { data } = await apiClient.put<any>(
            `/v1/payment-methods/${id}`,
            payload,
        );
        return data.data;
    };

    const toggleActive = async (id: number | string) => {
        const { data } = await apiClient.patch(`/v1/payment-methods/${id}/toggle-status`);
        return data.data; // Return updated payment method
    };

    const deletePaymentMethod = async (id: number | string) => {
        await apiClient.delete(`/v1/payment-methods/${id}`);
    };

    const deletePaymentMethods = async (ids: (number | string)[]) => {
        await apiClient.delete("/v1/payment-methods", { data: { ids } });
    };

    return {
        paymentMethods,
        currentPaymentMethod,
        meta,
        isLoading,
        error,
        fetchPaymentMethods,
        fetchPaymentMethod,
        createPaymentMethod,
        updatePaymentMethod,
        toggleActive,
        deletePaymentMethod,
        deletePaymentMethods,
    };
});
