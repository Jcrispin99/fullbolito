import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@central/lib/api";
import { createFetchAbort } from '@/composables/useFetchAbort'
import type { User } from "@/types/models";

export const useUserStore = defineStore("user", () => {
    const users = ref<User[]>([]);
    const currentUser = ref<User | null>(null);
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

    async function fetchUsers(
        page = 1,
        perPage: number | string = 15,
        search = "",
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const params: any = {
                page,
                per_page: perPage,
                search: search || undefined,
            };
            const { data } = await apiClient.get<any>("/v1/users", { params, signal: getSignal() });

            if (data.data?.meta) {
                users.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                users.value = Array.isArray(data.data)
                    ? data.data
                    : data.data.data;
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: users.value.length,
                    to: users.value.length,
                    total: users.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value = err.response?.data?.message || "No se pudieron cargar los usuarios";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchUser(id: string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<any>(`/v1/users/${id}`);
            currentUser.value = data.data as any;
            return data.data as any;
        } catch (err: any) {
            error.value = err.response?.data?.message || "No se pudo cargar el usuario";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createUser(payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<any>("/v1/users", payload);
            users.value.unshift(data.data as any);
            return data.data as any;
        } catch (err: any) {
            error.value = err.response?.data?.message || "No se pudo crear el usuario";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updateUser(id: string, payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.put<any>(
                `/v1/users/${id}`,
                payload,
            );
            const idx = users.value.findIndex(
                (u) => String(u.id) === String(id),
            );
            if (idx !== -1) users.value[idx] = data.data as any;
            currentUser.value = data.data as any;
            return data.data as any;
        } catch (err: any) {
            error.value = err.response?.data?.message || "No se pudo actualizar el usuario";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteUser(id: string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/users/${id}`);
            users.value = users.value.filter(
                (u) => String(u.id) !== String(id),
            );
            if (
                currentUser.value &&
                String(currentUser.value.id) === String(id)
            ) {
                currentUser.value = null;
            }
        } catch (err: any) {
            error.value = err.response?.data?.message || "No se pudo eliminar el usuario";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        users,
        currentUser,
        meta,
        isLoading,
        error,
        fetchUsers,
        fetchUser,
        createUser,
        updateUser,
        deleteUser,
    };
});
