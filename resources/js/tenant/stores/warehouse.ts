import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from '@/composables/useFetchAbort'
import type { PaginatedResponse } from "@/types/api";

export interface Warehouse {
    id: number;
    name: string;
    location: string | null;
    is_active: boolean;
    company_id: number;
    created_at: string;
    updated_at: string;
    company?: {
        id: number;
        business_name: string;
    };
}

export const useWarehouseStore = defineStore("tenant-warehouse", () => {
    const warehouses = ref<Warehouse[]>([]);
    const currentWarehouse = ref<Warehouse | null>(null);
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

    async function fetchWarehouses(
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
                `/v1/warehouses?${qp.toString()}`,
                { signal: getSignal() },
            );

            if (data.data?.meta) {
                warehouses.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                warehouses.value = Array.isArray(data.data)
                    ? data.data
                    : data.data.data;
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: warehouses.value.length,
                    to: warehouses.value.length,
                    total: warehouses.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value =
                err.response?.data?.message || "Error fetching warehouses";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteWarehouse(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/warehouses/${id}`);
            warehouses.value = warehouses.value.filter(
                (w) => String(w.id) !== String(id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting warehouse";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteWarehouses(ids: number[]) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.post(`/v1/warehouses/batch-delete`, { ids });
            warehouses.value = warehouses.value.filter(
                (item) => !ids.includes(item.id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting warehouses";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function toggleActive(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.patch<Warehouse>(
                `/v1/warehouses/${id}/toggle-status`
            );
            const idx = warehouses.value.findIndex(
                (w) => String(w.id) === String(id),
            );
            if (idx !== -1) warehouses.value[idx] = data.data;
            if (currentWarehouse.value && String(currentWarehouse.value.id) === String(id)) {
                currentWarehouse.value = data.data;
            }
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error updating warehouse status";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchFormOptions() {
        try {
            const { data } = await apiClient.get<any>("/v1/warehouses/form-options");
            return data.data;
        } catch (err: any) {
            console.error("Error fetching form options", err);
            return { companies: [] };
        }
    }

    async function fetchWarehouse(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<Warehouse>(
                `/v1/warehouses/${id}`,
            );
            currentWarehouse.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error fetching warehouse details";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createWarehouse(payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<Warehouse>(
                "/v1/warehouses",
                payload,
            );
            warehouses.value.unshift(data.data);
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error creating warehouse";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updateWarehouse(id: number | string, payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.put<Warehouse>(
                `/v1/warehouses/${id}`,
                payload,
            );
            const idx = warehouses.value.findIndex(
                (w) => String(w.id) === String(id),
            );
            if (idx !== -1) warehouses.value[idx] = data.data;
            currentWarehouse.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error updating warehouse";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        warehouses,
        currentWarehouse,
        meta,
        isLoading,
        error,
        fetchWarehouses,
        deleteWarehouse,
        deleteWarehouses,
        toggleActive,
        fetchFormOptions,
        fetchWarehouse,
        createWarehouse,
        updateWarehouse,
    };
});
