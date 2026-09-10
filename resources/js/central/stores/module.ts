import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@central/lib/api";
import { createFetchAbort } from "@/composables/useFetchAbort";
import type { Module } from "@/types/models";

export const useModuleStore = defineStore("module", () => {
    const modules = ref<Module[]>([]);
    const meta = ref({
        current_page: 1,
        from: 0,
        last_page: 1,
        per_page: 15,
        to: 0,
        total: 0,
    });
    const currentModule = ref<Module | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort();

    async function fetchModules(
        page = 1,
        perPage: number | string = 15,
        search = "",
        status = "active",
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const params = { page, per_page: perPage, search, status };
            const { data } = await apiClient.get<any>("/v1/modules", {
                params,
                signal: getSignal(),
            });

            if (data.data.meta) {
                modules.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                modules.value = Array.isArray(data.data)
                    ? data.data
                    : data.data.data;
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: modules.value.length,
                    to: modules.value.length,
                    total: modules.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value =
                err.response?.data?.message || "No se pudieron cargar los módulos";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchModule(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<Module>(`/v1/modules/${id}`);
            currentModule.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "No se pudo cargar el módulo";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createModule(payload: Partial<Module>) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<Module>(
                "/v1/modules",
                payload,
            );
            modules.value.push(data.data);
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "No se pudo crear el módulo";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updateModule(id: number | string, payload: Partial<Module>) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.put<Module>(
                `/v1/modules/${id}`,
                payload,
            );
            const idx = modules.value.findIndex((m) => m.id === Number(id));
            if (idx !== -1) {
                modules.value[idx] = data.data;
            }
            currentModule.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "No se pudo actualizar el módulo";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteModule(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/modules/${id}`);
            modules.value = modules.value.filter((m) => m.id !== Number(id));
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "No se pudo eliminar el módulo";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteModules(ids: number[]) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.post(`/v1/modules/batch-delete`, { ids });
            modules.value = modules.value.filter(
                (item) => !ids.includes(item.id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "No se pudieron eliminar los módulos";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function toggleStatus(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.patch<Module>(
                `/v1/modules/${id}/toggle-status`,
            );
            const idx = modules.value.findIndex((m) => m.id === Number(id));
            if (idx !== -1) {
                modules.value[idx] = data.data;
            }
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "No se pudo actualizar el estado del módulo";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        modules,
        meta,
        currentModule,
        isLoading,
        error,
        fetchModules,
        fetchModule,
        createModule,
        updateModule,
        deleteModule,
        deleteModules,
        toggleStatus,
    };
});
