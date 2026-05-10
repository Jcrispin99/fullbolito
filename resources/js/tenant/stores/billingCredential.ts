import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "../lib/api";
import { createFetchAbort } from "@/composables/useFetchAbort";

export interface BillingCredential {
    id: number;
    name: string;
    sol_user: string;
    cert_path: string | null;
    cert_filename: string | null;
    client_id: string | null;
    production: boolean;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface BillingCredentialsResponse {
    data: BillingCredential[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export const useBillingCredentialStore = defineStore("billingCredential", () => {
    const billingCredentials = ref<BillingCredential[]>([]);
    const currentBillingCredential = ref<BillingCredential | null>(null);
    const meta = ref<BillingCredentialsResponse["meta"]>({
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total: 0,
    });
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort();

    const fetchBillingCredentials = async (
        page = 1,
        perPage: number | string = 25,
        search = "",
        status: "active" | "inactive" | "all" | string = "active",
    ) => {
        isLoading.value = true;
        error.value = null;
        try {
            const qp = new URLSearchParams({ page: String(page) });
            if (perPage) qp.set("per_page", String(perPage));
            if (search) qp.set("q", search);
            if (status) qp.set("status", status);

            const { data } = await apiClient.get<any>(
                `/v1/billing-credentials?${qp.toString()}`,
                { signal: getSignal() },
            );

            const responseData = data as any;
            if (responseData?.meta) {
                billingCredentials.value = responseData.data || [];
                meta.value = responseData.meta;
            } else if (responseData?.data?.meta) {
                billingCredentials.value = responseData.data.data || [];
                meta.value = responseData.data.meta;
            } else {
                billingCredentials.value = Array.isArray(responseData?.data)
                    ? responseData.data
                    : responseData?.data?.data || [];
                meta.value = {
                    current_page: 1,
                    last_page: 1,
                    per_page: billingCredentials.value.length,
                    total: billingCredentials.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value = err.message || "Failed to fetch billing credentials";
            console.error(err);
        } finally {
            isLoading.value = false;
        }
    };

    const fetchBillingCredential = async (id: number | string) => {
        isLoading.value = true;
        try {
            const { data } = await apiClient.get<any>(`/v1/billing-credentials/${id}`);
            return data.data;
        } catch (err: any) {
            console.error(err);
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    /**
     * Crea credencial. payload puede contener `cert_file` (File) — convertimos
     * a FormData automáticamente cuando hay archivo presente.
     */
    const createBillingCredential = async (payload: Record<string, any>) => {
        const formData = buildFormData(payload);
        const { data } = await apiClient.post<any>(
            "/v1/billing-credentials",
            formData,
            { headers: { "Content-Type": "multipart/form-data" } },
        );
        return data.data;
    };

    /**
     * Actualiza credencial. Laravel no parsea bien multipart/form-data en PUT,
     * por lo que usamos POST con `_method=PUT` (Laravel form method spoofing).
     */
    const updateBillingCredential = async (id: number | string, payload: Record<string, any>) => {
        const formData = buildFormData({ ...payload, _method: "PUT" });
        const { data } = await apiClient.post<any>(
            `/v1/billing-credentials/${id}`,
            formData,
            { headers: { "Content-Type": "multipart/form-data" } },
        );
        return data.data;
    };

    const toggleActive = async (id: number | string) => {
        const { data } = await apiClient.patch(
            `/v1/billing-credentials/${id}/toggle-status`,
        );
        return data.data;
    };

    const deleteBillingCredential = async (id: number | string) => {
        await apiClient.delete(`/v1/billing-credentials/${id}`);
    };

    /**
     * Convierte un payload en FormData, omitiendo undefined/null.
     * Booleans se mandan como "1"/"0" para PHP.
     */
    function buildFormData(payload: Record<string, any>): FormData {
        const fd = new FormData();
        for (const [key, value] of Object.entries(payload)) {
            if (value === undefined || value === null) continue;
            if (value instanceof File) {
                fd.append(key, value);
            } else if (typeof value === "boolean") {
                fd.append(key, value ? "1" : "0");
            } else {
                fd.append(key, String(value));
            }
        }
        return fd;
    }

    return {
        billingCredentials,
        currentBillingCredential,
        meta,
        isLoading,
        error,
        fetchBillingCredentials,
        fetchBillingCredential,
        createBillingCredential,
        updateBillingCredential,
        toggleActive,
        deleteBillingCredential,
    };
});
