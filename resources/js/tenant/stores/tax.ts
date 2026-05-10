import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from '@/composables/useFetchAbort'

export interface Tax {
    id: number;
    name: string;
    description: string | null;
    invoice_label: string | null;
    tax_type: string;
    affectation_type_code: string | null;
    rate_percent: number;
    is_price_inclusive: boolean;
    is_active: boolean;
    is_default: boolean;
    created_at: string;
    updated_at: string;
}

export const useTaxStore = defineStore("tenant-tax", () => {
    const taxes = ref<Tax[]>([]);
    const currentTax = ref<Tax | null>(null);
    const meta = ref({
        current_page: 1,
        from: 0,
        last_page: 1,
        per_page: 15,
        to: 0,
        total: 0,
    });
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort()

    async function fetchTaxes(
        page = 1,
        perPage: number | string = 15,
        search = "",
        status = "active",
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const qp = new URLSearchParams();
            qp.set("page", String(page));
            qp.set("per_page", String(perPage));
            if (search) qp.set("search", search);
            if (status) qp.set("status", status);

            const { data } = await apiClient.get<any>(
                `/v1/taxes?${qp.toString()}`,
                { signal: getSignal() },
            );

            if (data.data?.meta) {
                taxes.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                taxes.value = Array.isArray(data.data)
                    ? data.data
                    : data.data?.data || [];
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: taxes.value.length,
                    to: taxes.value.length,
                    total: taxes.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value = err.response?.data?.message || "Error fetching taxes";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function toggleActive(id: number | string) {
        try {
            const { data } = await apiClient.patch<Tax>(
                `/v1/taxes/${id}/toggle-status`,
            );
            const idx = taxes.value.findIndex(
                (t) => String(t.id) === String(id),
            );
            if (idx !== -1) taxes.value[idx] = data.data as any;
            if (currentTax.value && String(currentTax.value.id) === String(id)) {
                currentTax.value = data.data as any;
            }
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error updating tax status";
            throw err;
        }
    }

    async function deleteTax(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/taxes/${id}`);
            taxes.value = taxes.value.filter(
                (t) => String(t.id) !== String(id),
            );
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error deleting tax";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteTaxes(ids: number[]) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.post(`/v1/taxes/batch-delete`, { ids });
            taxes.value = taxes.value.filter(
                (item) => !ids.includes(item.id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting taxes";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchTax(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<Tax>(`/v1/taxes/${id}`);
            currentTax.value = data.data as any;
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error fetching tax";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createTax(payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<Tax>("/v1/taxes", payload);
            taxes.value.unshift(data.data as any);
            currentTax.value = data.data as any;
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error creating tax";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updateTax(id: number | string, payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.put<Tax>(`/v1/taxes/${id}`, payload);
            const updated = data.data as any;
            const idx = taxes.value.findIndex((t) => String(t.id) === String(id));
            if (idx !== -1) taxes.value[idx] = updated;
            currentTax.value = updated;
            return updated;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error updating tax";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        taxes,
        currentTax,
        meta,
        isLoading,
        error,
        fetchTaxes,
        toggleActive,
        deleteTax,
        deleteTaxes,
        fetchTax,
        createTax,
        updateTax,
    };
});
