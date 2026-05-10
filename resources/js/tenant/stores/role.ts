import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from "@/composables/useFetchAbort";
import type { Role } from "@/types/models";

export const useRoleStore = defineStore("tenant-role", () => {
    const roles = ref<Role[]>([]);
    const currentRole = ref<Role | null>(null);
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
    const { getSignal, isAbortError } = createFetchAbort();

    async function fetchRoles(
        page = 1,
        perPage: number | string = 15,
        search = "",
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const qp = new URLSearchParams();
            qp.set("page", String(page));
            qp.set("per_page", String(perPage));
            if (search) qp.set("search", search);
            const { data } = await apiClient.get<any>(
                `/v1/roles?${qp.toString()}`,
                { signal: getSignal() },
            );

            if (data.data?.meta) {
                roles.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                roles.value = Array.isArray(data.data)
                    ? data.data
                    : data.data.data;
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: roles.value.length,
                    to: roles.value.length,
                    total: roles.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value = err.response?.data?.message || "Error fetching roles";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchFormOptions(): Promise<{ permissions_grouped: Record<string, string[]> }> {
        try {
            const { data } = await apiClient.get<any>("/v1/roles/form-options");
            return data.data;
        } catch (err: any) {
            console.error("Error fetching form options", err);
            return { permissions_grouped: {} };
        }
    }

    async function fetchRole(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<Role>(`/v1/roles/${id}`);
            currentRole.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error fetching role";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createRole(payload: { name: string; permissions?: string[] }) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<Role>("/v1/roles", payload);
            roles.value.unshift(data.data as any);
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error creating role";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updateRole(id: number | string, payload: { name?: string; permissions?: string[] }) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.put<Role>(`/v1/roles/${id}`, payload);
            const idx = roles.value.findIndex(
                (r) => String(r.id) === String(id),
            );
            if (idx !== -1) roles.value[idx] = data.data as any;
            currentRole.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error updating role";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteRole(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/roles/${id}`);
            roles.value = roles.value.filter(
                (r) => String(r.id) !== String(id),
            );
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error deleting role";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteRoles(ids: number[]) {
        isLoading.value = true;
        error.value = null;
        try {
            await Promise.all(
                ids.map((id) => apiClient.delete(`/v1/roles/${id}`)),
            );
            roles.value = roles.value.filter((r) => !ids.includes(r.id));
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error deleting roles";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        roles,
        currentRole,
        meta,
        isLoading,
        error,
        fetchRoles,
        fetchFormOptions,
        fetchRole,
        createRole,
        updateRole,
        deleteRole,
        deleteRoles,
    };
});
