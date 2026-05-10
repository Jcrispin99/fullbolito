import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from '@/composables/useFetchAbort'
import type { Company } from "@/types/models";

export const useCompanyStore = defineStore("tenant-company", () => {
    const companies = ref<Company[]>([]);
    const currentCompany = ref<Company | null>(null);
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

    async function fetchCompanies(
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
                `/v1/companies?${qp.toString()}`,
                { signal: getSignal() },
            );

            if (data.data?.meta) {
                companies.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                companies.value = Array.isArray(data.data)
                    ? data.data
                    : data.data.data;
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: companies.value.length,
                    to: companies.value.length,
                    total: companies.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value =
                err.response?.data?.message || "Error fetching companies";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchFormOptions(params?: { exclude_id?: number | string }) {
        try {
            const { data } = await apiClient.get<any>(
                "/v1/companies/form-options",
                { params },
            );
            return data.data;
        } catch (err: any) {
            console.error("Error fetching form options", err);
            return { parent_companies: [] };
        }
    }

    async function fetchCompany(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<Company>(
                `/v1/companies/${id}`,
            );
            currentCompany.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error fetching company";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createCompany(payload: Partial<Company>) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<Company>(
                "/v1/companies",
                payload,
            );
            companies.value.unshift(data.data as any);
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error creating company";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updateCompany(
        id: number | string,
        payload: Partial<Company>,
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.put<Company>(
                `/v1/companies/${id}`,
                payload,
            );
            const idx = companies.value.findIndex(
                (c) => String(c.id) === String(id),
            );
            if (idx !== -1) companies.value[idx] = data.data as any;
            currentCompany.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error updating company";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteCompany(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/companies/${id}`);
            companies.value = companies.value.filter(
                (c) => String(c.id) !== String(id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting company";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteCompanies(ids: number[]) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.post(`/v1/companies/batch-delete`, { ids });
            companies.value = companies.value.filter(
                (item) => !ids.includes(item.id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting companies";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function toggleActive(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.patch<Company>(
                `/v1/companies/${id}/toggle-status`
            );
            const idx = companies.value.findIndex(
                (c) => String(c.id) === String(id),
            );
            if (idx !== -1) companies.value[idx] = data.data as any;
            if (currentCompany.value && String(currentCompany.value.id) === String(id)) {
                currentCompany.value = data.data;
            }
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error updating company status";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        companies,
        currentCompany,
        meta,
        isLoading,
        error,
        fetchCompanies,
        fetchFormOptions,
        fetchCompany,
        createCompany,
        updateCompany,
        deleteCompany,
        deleteCompanies,
        toggleActive,
    };
});
