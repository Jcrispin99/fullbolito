import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@central/lib/api";
import { createFetchAbort } from '@/composables/useFetchAbort'
import type { Tenant } from "@/types/models";
import type { RegisterTenantResponse } from "@/types/api";
import { useAuthStore } from "@/central/stores/auth";

export const useTenantStore = defineStore("tenant", () => {
    const tenants = ref<Tenant[]>([]);
    const meta = ref({
        current_page: 1,
        from: 0,
        last_page: 1,
        per_page: 15,
        to: 0,
        total: 0,
    });
    const currentTenant = ref<Tenant | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort()

    async function fetchTenants(page = 1, perPage = 15) {
        const authStore = useAuthStore();
        isLoading.value = true;
        error.value = null;
        try {
            const params = { page, per_page: perPage };
            if (authStore.isAuthenticated && !authStore.user) {
                await authStore.fetchUser();
            }

            const endpoint =
                (authStore.user?.roles ?? []).includes("superadmin")
                    ? "/v1/tenants"
                    : "/v1/my-tenants";

            const { data } = await apiClient.get<any>(endpoint, {
                params,
                signal: getSignal(),
            });

            if (data.data?.meta) {
                tenants.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                tenants.value = Array.isArray(data.data)
                    ? data.data
                    : data.data.data;
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: tenants.value.length,
                    to: tenants.value.length,
                    total: tenants.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value =
                err.response?.data?.message || "Error fetching tenants";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchTenant(id: string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<Tenant>(`/v1/tenants/${id}`);
            currentTenant.value = data.data as any;
            return data.data as any;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error fetching tenant";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function registerTenant(payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<RegisterTenantResponse>(
                "/v1/register-tenant",
                payload,
            );
            const tenant = data.data.tenant as any;
            tenants.value.unshift(tenant);
            return tenant;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error registering tenant";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updateTenant(id: string, payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.put<Tenant>(
                `/v1/tenants/${id}`,
                payload,
            );
            const index = tenants.value.findIndex((t) => t.id === id);
            if (index !== -1) tenants.value[index] = data.data as any;
            currentTenant.value = data.data as any;
            return data.data as any;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error updating tenant";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteTenant(id: string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/tenants/${id}`);
            tenants.value = tenants.value.filter((t) => t.id !== id);
            if (currentTenant.value?.id === id) currentTenant.value = null;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting tenant";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function renewSubscription(
        tenantId: string,
        payload: { duration_days: number; payment_reference?: string },
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<any>(
                `/v1/tenants/${tenantId}/renew`,
                payload,
            );
            await fetchTenant(tenantId);
            return data.data as any;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error renewing subscription";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        tenants,
        meta,
        currentTenant,
        isLoading,
        error,
        fetchTenants,
        fetchTenant,
        registerTenant,
        updateTenant,
        deleteTenant,
        renewSubscription,
    };
});
