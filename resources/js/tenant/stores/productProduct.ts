import { defineStore } from "pinia";
import apiClient from "@tenant/lib/api";

export const useProductProductStore = defineStore("productProduct", () => {
    const searchProductProducts = async (search: string, limit = 20) => {
        try {
            const response = await apiClient.get<any>("/v1/product-products/search", {
                params: { search, limit }
            });
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    return {
        searchProductProducts
    };
});
