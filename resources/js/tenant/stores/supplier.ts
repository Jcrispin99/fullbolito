import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "../lib/api";
import { createFetchAbort } from '@/composables/useFetchAbort'

export interface Supplier {
    id: number;
    name: string;
    document_type: string | null;
    document_number: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    status: string; // "active" | "inactive" typically
    provider_category: string | null;
    created_at: string;
    updated_at: string;
}

export interface SuppliersResponse {
    data: Supplier[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export const useSupplierStore = defineStore("supplier", () => {
    const suppliers = ref<Supplier[]>([]);
    const meta = ref<SuppliersResponse["meta"] | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort()

    const fetchSuppliers = async (
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
                `/v1/suppliers?${qp.toString()}`,
                { signal: getSignal() },
            );

            if (data.data?.meta) {
                suppliers.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                suppliers.value = Array.isArray(data.data)
                    ? data.data
                    : data.data?.data || [];
                meta.value = {
                    current_page: 1,
                    last_page: 1,
                    per_page: suppliers.value.length,
                    total: suppliers.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return
            error.value = err.message || "Failed to fetch suppliers";
            console.error(err);
        } finally {
            isLoading.value = false;
        }
    };

    const toggleStatus = async (id: number | string) => {
        const { data } = await apiClient.patch(`/v1/suppliers/${id}/toggle-status`);
        return data.data; // Return updated supplier
    };

    const deleteSupplier = async (id: number | string) => {
        await apiClient.delete(`/v1/suppliers/${id}`);
    };

    const deleteSuppliers = async (ids: (number | string)[]) => {
        await apiClient.delete("/v1/suppliers", { data: { ids } });
    };

    return {
        suppliers,
        meta,
        isLoading,
        error,
        fetchSuppliers,
        toggleStatus,
        deleteSupplier,
        deleteSuppliers,
    };
});
