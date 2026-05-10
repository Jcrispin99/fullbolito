import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from '@/composables/useFetchAbort'

export interface UnitOfMeasure {
    id: number;
    name: string;
    symbol: string | null;
    family: string | null;
    factor: number;
    is_active: boolean;
    base_unit_id: number | null;
    base_unit?: { id: number; name: string; symbol: string | null } | null;
    created_at: string;
    updated_at: string;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number;
    to?: number;
}

export const useUnitOfMeasureStore = defineStore("unit-of-measure", () => {
    const units = ref<UnitOfMeasure[]>([]);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const meta = ref<PaginationMeta>({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });
    const { getSignal, isAbortError } = createFetchAbort()

    const fetchUnits = async (
        page = 1,
        perPage: number | string = 15,
        search = "",
        status = "active",
    ) => {
        isLoading.value = true;
        try {
            const qp = new URLSearchParams();
            qp.set("page", String(page));
            qp.set("per_page", String(perPage));
            if (search) qp.set("search", search);
            if (status) qp.set("status", status);

            const { data } = await apiClient.get<any>(
                `/v1/unit-of-measures?${qp.toString()}`,
                { signal: getSignal() },
            );

            if (data.data?.meta) {
                units.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                units.value = Array.isArray(data.data)
                    ? data.data
                    : data.data?.data || [];
                meta.value = {
                    current_page: 1,
                    last_page: 1,
                    per_page: units.value.length,
                    total: units.value.length,
                };
            }
        } catch (error) {
            if (isAbortError(error)) return;
            console.error("Error fetching units of measure:", error);
            throw error;
        } finally {
            isLoading.value = false;
        }
    };

    const toggleActive = async (id: number) => {
        try {
            const { data } = await apiClient.patch<any>(
                `/v1/unit-of-measures/${id}/toggle-status`,
            );
            const updated = data.data;
            const idx = units.value.findIndex((u) => u.id === id);
            if (idx !== -1) units.value[idx] = updated;
            return updated;
        } catch (error) {
            console.error("Error toggling unit of measure status:", error);
            throw error;
        }
    };

    const deleteUnit = async (id: number) => {
        try {
            await apiClient.delete(`/v1/unit-of-measures/${id}`);
            units.value = units.value.filter((u) => u.id !== id);
        } catch (error) {
            console.error("Error deleting unit of measure:", error);
            throw error;
        }
    };

    const deleteUnits = async (ids: number[]) => {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.post(`/v1/unit-of-measures/batch-delete`, { ids });
            units.value = units.value.filter(
                (item) => !ids.includes(item.id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting units";
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    const fetchUnit = async (id: number | string) => {
        isLoading.value = true;
        try {
            const { data } = await apiClient.get<any>(`/v1/unit-of-measures/${id}`);
            return data.data;
        } catch (error) {
            console.error("Error fetching unit of measure:", error);
            throw error;
        } finally {
            isLoading.value = false;
        }
    };

    const createUnit = async (payload: any) => {
        isLoading.value = true;
        try {
            const { data } = await apiClient.post<any>("/v1/unit-of-measures", payload);
            units.value.unshift(data.data);
            return data.data;
        } catch (error) {
            console.error("Error creating unit of measure:", error);
            throw error;
        } finally {
            isLoading.value = false;
        }
    };

    const updateUnit = async (id: number | string, payload: any) => {
        isLoading.value = true;
        try {
            const { data } = await apiClient.put<any>(`/v1/unit-of-measures/${id}`, payload);
            const updated = data.data;
            const idx = units.value.findIndex((u) => u.id === Number(id));
            if (idx !== -1) units.value[idx] = updated;
            return updated;
        } catch (error) {
            console.error("Error updating unit of measure:", error);
            throw error;
        } finally {
            isLoading.value = false;
        }
    };

    return {
        units,
        isLoading,
        error,
        meta,
        fetchUnits,
        fetchUnit,
        createUnit,
        updateUnit,
        toggleActive,
        deleteUnit,
        deleteUnits,
    };
});
