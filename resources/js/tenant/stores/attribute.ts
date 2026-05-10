import { defineStore } from "pinia";
import { ref } from "vue";
import api from "@tenant/lib/api";
import { createFetchAbort } from '@/composables/useFetchAbort'

export interface Attribute {
    id: number;
    name: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    values?: any[]; // Adjust based on your AttributeValue structure
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export const useAttributeStore = defineStore("attribute", () => {
    const attributes = ref<Attribute[]>([]);
    const currentAttribute = ref<Attribute | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort()
    const meta = ref<PaginationMeta>({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });

    const fetchAttributes = async (
        page: number = 1,
        perPage: number | string = 15,
        search: string = "",
        status: string = "active",
    ) => {
        isLoading.value = true;
        try {
            const { data } = await api.get("/v1/attributes", {
                params: {
                    page,
                    per_page: perPage,
                    search,
                    status,
                },
                signal: getSignal(),
            });
            
            if (data.data?.meta) {
                attributes.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                attributes.value = Array.isArray(data.data)
                    ? data.data
                    : data.data?.data || [];
                meta.value = {
                    current_page: 1,
                    last_page: 1,
                    per_page: attributes.value.length,
                    total: attributes.value.length,
                };
            }
        } catch (error) {
            if (isAbortError(error)) return
            console.error("Error fetching attributes:", error);
            throw error;
        } finally {
            isLoading.value = false;
        }
    };

    const fetchAttribute = async (id: number | string) => {
        isLoading.value = true;
        try {
            const response = await api.get(`/v1/attributes/${id}`);
            const data = response.data.data;
            currentAttribute.value = data;
            return data;
        } catch (error) {
            console.error("Error fetching attribute:", error);
            throw error;
        } finally {
            isLoading.value = false;
        }
    };

    const createAttribute = async (payload: any) => {
        isLoading.value = true;
        try {
            const response = await api.post("/v1/attributes", payload);
            const data = response.data.data;
            attributes.value.unshift(data);
            return data;
        } catch (error) {
            console.error("Error creating attribute:", error);
            throw error;
        } finally {
            isLoading.value = false;
        }
    };

    const updateAttribute = async (id: number | string, payload: any) => {
        isLoading.value = true;
        try {
            const response = await api.put(`/v1/attributes/${id}`, payload);
            const updated = response.data.data;

            const index = attributes.value.findIndex((a) => a.id === Number(id));
            if (index !== -1) {
                attributes.value[index] = updated;
            }
            if (currentAttribute.value?.id === Number(id)) {
                currentAttribute.value = updated;
            }
            return updated;
        } catch (error) {
            console.error("Error updating attribute:", error);
            throw error;
        } finally {
            isLoading.value = false;
        }
    };

    const toggleActive = async (id: number) => {
        try {
            const response = await api.patch(`/v1/attributes/${id}/toggle-status`);
            const updated = response.data.data;
            const index = attributes.value.findIndex((a) => a.id === id);
            if (index !== -1) {
                attributes.value[index] = updated;
            }
            if (currentAttribute.value?.id === id) {
                currentAttribute.value = updated;
            }
            return updated;
        } catch (error) {
            console.error("Error toggling attribute status:", error);
            throw error;
        }
    };

    const deleteAttribute = async (id: number) => {
        try {
            await api.delete(`/v1/attributes/${id}`);
            attributes.value = attributes.value.filter((a) => a.id !== id);
        } catch (error) {
            console.error("Error deleting attribute:", error);
            throw error;
        }
    };

    const deleteAttributes = async (ids: number[]) => {
        isLoading.value = true;
        error.value = null;
        try {
            await api.post(`/v1/attributes/batch-delete`, { ids });
            attributes.value = attributes.value.filter(
                (item) => !ids.includes(item.id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting attributes";
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    return {
        attributes,
        currentAttribute,
        isLoading,
        error,
        meta,
        fetchAttributes,
        fetchAttribute,
        createAttribute,
        updateAttribute,
        toggleActive,
        deleteAttribute,
        deleteAttributes,
    };
});
