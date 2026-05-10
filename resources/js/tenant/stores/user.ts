import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from "@/composables/useFetchAbort";
import type { User } from "@/types/models";

export const useUserStore = defineStore("tenant-user", () => {
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
    const { getSignal, isAbortError } = createFetchAbort();

    async function fetchUsers(
        page = 1,
        perPage: number | string = 15,
        search = "",
        role = "",
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const qp = new URLSearchParams();
            qp.set("page", String(page));
            qp.set("per_page", String(perPage));
            if (search) qp.set("search", search);
            if (role) qp.set("role", role);
            const { data } = await apiClient.get<any>(
                `/v1/users?${qp.toString()}`,
                { signal: getSignal() },
            );

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
            error.value =
                err.response?.data?.message || "Error fetching users";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchFormOptions() {
        try {
            const { data } = await apiClient.get<any>("/v1/users/form-options");
            return data.data;
        } catch (err: any) {
            console.error("Error fetching form options", err);
            return { roles: [], companies: [] };
        }
    }

    async function fetchUser(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<User>(`/v1/users/${id}`);
            currentUser.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error fetching user";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createUser(payload: Partial<User> & { password?: string; password_confirmation?: string; roles?: string[]; company_ids?: number[] }) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<User>("/v1/users", payload);
            users.value.unshift(data.data as any);
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error creating user";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updateUser(id: number | string, payload: Partial<User> & { password?: string; password_confirmation?: string; roles?: string[]; company_ids?: number[] }) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.put<User>(`/v1/users/${id}`, payload);
            const idx = users.value.findIndex(
                (u) => String(u.id) === String(id),
            );
            if (idx !== -1) users.value[idx] = data.data as any;
            currentUser.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error updating user";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteUser(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/users/${id}`);
            users.value = users.value.filter(
                (u) => String(u.id) !== String(id),
            );
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error deleting user";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteUsers(ids: number[]) {
        isLoading.value = true;
        error.value = null;
        try {
            await Promise.all(
                ids.map((id) => apiClient.delete(`/v1/users/${id}`)),
            );
            users.value = users.value.filter((u) => !ids.includes(u.id));
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error deleting users";
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
        fetchFormOptions,
        fetchUser,
        createUser,
        updateUser,
        deleteUser,
        deleteUsers,
    };
});
