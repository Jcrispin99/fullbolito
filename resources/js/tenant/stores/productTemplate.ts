import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";

export interface ProductTemplate {
    id: number;
    name: string;
    description: string | null;
    price: string;
    is_active: boolean;
    is_pos_visible: boolean;
    tracks_inventory: boolean;
    is_service: boolean;
    tracked_by_lot: boolean;
    expiration_alert_days: number | null;
    expiration_block_days: number | null;
    image: string | null;
    sku: string | null;
    barcode: string | null;
    category: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
}

export const useProductTemplateStore = defineStore("tenant-product-template", () => {
    const products = ref<ProductTemplate[]>([]);
    const currentProduct = ref<ProductTemplate | null>(null);
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

    async function fetchProducts(
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
                `/v1/product-templates?${qp.toString()}`,
            );

            if (data.data?.meta) {
                products.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                products.value = Array.isArray(data.data)
                    ? data.data
                    : data.data?.data || [];
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: products.value.length,
                    to: products.value.length,
                    total: products.value.length,
                };
            }
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error fetching products";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function toggleActive(id: number | string) {
        try {
            const { data } = await apiClient.patch<any>(
                `/v1/product-templates/${id}/toggle-status`,
            );
            const idx = products.value.findIndex(
                (p) => String(p.id) === String(id),
            );
            if (idx !== -1) products.value[idx] = data.data as any;
            if (currentProduct.value && String(currentProduct.value.id) === String(id)) {
                currentProduct.value = data.data as any;
            }
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error updating product status";
            throw err;
        }
    }

    async function deleteProduct(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/product-templates/${id}`);
            products.value = products.value.filter(
                (p) => String(p.id) !== String(id),
            );
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error deleting product";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteProducts(ids: number[]) {
        isLoading.value = true;
        error.value = null;
        try {
            await Promise.all(
                ids.map((id) => apiClient.delete(`/v1/product-templates/${id}`)),
            );
            products.value = products.value.filter((p) => !ids.includes(p.id));
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error deleting products";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchProduct(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<any>(`/v1/product-templates/${id}`);
            currentProduct.value = data.data as any;
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error fetching product";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createProduct(payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<any>("/v1/product-templates", payload);
            products.value.unshift(data.data as any);
            currentProduct.value = data.data as any;
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error creating product";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updateProduct(id: number | string, payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.patch<any>(`/v1/product-templates/${id}`, payload);
            const updated = data.data as any;
            const idx = products.value.findIndex((p) => String(p.id) === String(id));
            if (idx !== -1) products.value[idx] = updated;
            currentProduct.value = updated;
            return updated;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error updating product";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        products,
        currentProduct,
        meta,
        isLoading,
        error,
        fetchProducts,
        toggleActive,
        deleteProduct,
        deleteProducts,
        fetchProduct,
        createProduct,
        updateProduct,
    };
});
