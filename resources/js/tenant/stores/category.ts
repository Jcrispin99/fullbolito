import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from '@/composables/useFetchAbort'

export interface Category {
    id: number;
    name: string;
    full_name: string;
    description: string | null;
    parent_id: number | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export const useCategoryStore = defineStore("tenant-category", () => {
    const categories = ref<Category[]>([]);
    const currentCategory = ref<Category | null>(null);
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

    async function fetchCategories(
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
                `/v1/categories?${qp.toString()}`,
                { signal: getSignal() },
            );

            if (data.data?.meta) {
                categories.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                categories.value = Array.isArray(data.data)
                    ? data.data
                    : data.data.data;
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: categories.value.length,
                    to: categories.value.length,
                    total: categories.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value =
                err.response?.data?.message || "Error fetching categories";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteCategory(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/categories/${id}`);
            categories.value = categories.value.filter(
                (c) => String(c.id) !== String(id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting category";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteCategories(ids: number[]) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.post(`/v1/categories/batch-delete`, { ids });
            categories.value = categories.value.filter(
                (item) => !ids.includes(item.id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting categories";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function toggleActive(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.patch<Category>(
                `/v1/categories/${id}/toggle-status`,
            );
            const idx = categories.value.findIndex(
                (c) => String(c.id) === String(id),
            );
            if (idx !== -1) categories.value[idx] = data.data as any;
            if (currentCategory.value && String(currentCategory.value.id) === String(id)) {
                currentCategory.value = data.data as any;
            }
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error updating category status";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    // Placeholders for prospective Create and Edit views
    async function fetchFormOptions() {
        try {
            const { data } = await apiClient.get<any>("/v1/categories/form-options");
            return data.data;
        } catch (err: any) {
            console.error("Error fetching form options", err);
            return { categories: [] };
        }
    }

    async function fetchCategory(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<Category>(
                `/v1/categories/${id}`,
            );
            currentCategory.value = data.data as any;
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error fetching category details";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createCategory(payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<Category>(
                "/v1/categories",
                payload,
            );
            categories.value.unshift(data.data as any);
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error creating category";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updateCategory(id: number | string, payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.put<Category>(
                `/v1/categories/${id}`,
                payload,
            );
            const idx = categories.value.findIndex(
                (c) => String(c.id) === String(id),
            );
            if (idx !== -1) categories.value[idx] = data.data as any;
            currentCategory.value = data.data as any;
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error updating category";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        categories,
        currentCategory,
        meta,
        isLoading,
        error,
        fetchCategories,
        deleteCategory,
        deleteCategories,
        toggleActive,
        fetchFormOptions,
        fetchCategory,
        createCategory,
        updateCategory,
    };
});
