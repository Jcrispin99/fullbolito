import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@central/lib/api";
import { createFetchAbort } from '@/composables/useFetchAbort'
import type { Plan } from "@/types/models";
import type { PaginatedResponse } from "@/types/api";

export const usePlanStore = defineStore("plan", () => {
    const plans = ref<Plan[]>([]);
    const meta = ref({
        current_page: 1,
        from: 0,
        last_page: 1,
        per_page: 15,
        to: 0,
        total: 0,
    });
    const currentPlan = ref<Plan | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort()

    async function fetchPlans(
        page = 1,
        perPage = 15,
        search = "",
        status = "active",
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const params = { page, per_page: perPage, search, status };
            const { data } = await apiClient.get<any>("/v1/plans", { params, signal: getSignal() });

            // Check if response is paginated or flat list
            if (data.data.meta) {
                plans.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                // Flat list (e.g. per_page='total')
                plans.value = Array.isArray(data.data)
                    ? data.data
                    : data.data.data;
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: plans.value.length,
                    to: plans.value.length,
                    total: plans.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value = err.response?.data?.message || "No se pudieron cargar los planes";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchPlan(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<Plan>(`/v1/plans/${id}`);
            currentPlan.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "No se pudo cargar el plan";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createPlan(plan: Partial<Plan>) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<Plan>("/v1/plans", plan);
            plans.value.push(data.data);
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "No se pudo crear el plan";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updatePlan(id: number | string, plan: Partial<Plan>) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.put<Plan>(`/v1/plans/${id}`, plan);
            const index = plans.value.findIndex((p) => p.id === Number(id));
            if (index !== -1) {
                plans.value[index] = data.data;
            }
            currentPlan.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "No se pudo actualizar el plan";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deletePlan(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/plans/${id}`);
            plans.value = plans.value.filter((p) => p.id !== Number(id));
        } catch (err: any) {
            error.value = err.response?.data?.message || "No se pudo eliminar el plan";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deletePlans(ids: number[]) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.post(`/v1/plans/batch-delete`, { ids });
            plans.value = plans.value.filter(
                (item) => !ids.includes(item.id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "No se pudieron eliminar los planes";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function toggleStatus(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.patch<Plan>(
                `/v1/plans/${id}/toggle-status`,
            );
            const index = plans.value.findIndex((p) => p.id === Number(id));
            if (index !== -1) {
                plans.value[index] = data.data;
            }
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "No se pudo actualizar el estado del plan";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        plans,
        meta,
        currentPlan,
        isLoading,
        error,
        fetchPlans,
        fetchPlan,
        createPlan,
        updatePlan,
        deletePlan,
        deletePlans,
        toggleStatus,
    };
});
