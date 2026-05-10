import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from "@/composables/useFetchAbort";

export type MovementType = "entry" | "exit";
export type MovementStatus =
    | "draft"
    | "submitted"
    | "posted"
    | "rejected"
    | "cancelled";

export interface MovementWarehouseRef {
    id: number;
    name: string;
    company_id?: number;
    company?: { id: number; name: string; business_name?: string };
}

export interface MovementUserRef {
    id: number;
    name: string;
    email?: string;
}

export interface Movement {
    id: number;
    type: MovementType;
    serie: string;
    correlative: string;
    sequence_code: string;
    date: string | null;
    status: MovementStatus;
    total: number;
    observation: string | null;
    reason: string | null;
    rejection_reason: string | null;

    warehouse_id: number;
    company_id: number;
    journal_id: number | null;
    transfer_id: number | null;

    submitted_at: string | null;
    posted_at: string | null;
    rejected_at: string | null;
    cancelled_at: string | null;

    created_user_id: number | null;
    submitted_user_id: number | null;
    posted_user_id: number | null;
    rejected_user_id: number | null;
    cancelled_user_id: number | null;

    warehouse?: MovementWarehouseRef;
    company?: { id: number; name: string; business_name?: string };
    created_user?: MovementUserRef | null;
    submitted_user?: MovementUserRef | null;
    posted_user?: MovementUserRef | null;
    rejected_user?: MovementUserRef | null;
    cancelled_user?: MovementUserRef | null;
    lines?: Array<{
        id: number;
        product_product_id: number;
        lot_id: number | null;
        quantity: number;
        price: number;
        total: number;
        product?: { id: number; name: string; sku?: string };
        lot?: { id: number; lot_number: string; expires_at?: string | null } | null;
    }>;
    created_at: string | null;
    updated_at: string | null;
}

export interface MovementMeta {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
    draft_total?: number;
    submitted_total?: number;
    posted_total?: number;
    rejected_total?: number;
    cancelled_total?: number;
}

export const useMovementStore = defineStore("movement", () => {
    const movements = ref<Movement[]>([]);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort();
    const meta = ref<MovementMeta>({
        current_page: 1,
        from: 0,
        last_page: 1,
        per_page: 15,
        to: 0,
        total: 0,
        draft_total: 0,
        submitted_total: 0,
        posted_total: 0,
        rejected_total: 0,
        cancelled_total: 0,
    });

    const fetchMovements = async (
        page = 1,
        perPage: number | "total" = 15,
        search = "",
        status: string = "all",
        type: string = "all",
        standalone = false,
    ) => {
        isLoading.value = true;
        error.value = null;
        try {
            const params: Record<string, any> = {
                page,
                per_page: perPage,
                search,
                status,
            };

            if (type && type !== "all") params.type = type;
            if (standalone) params.standalone = 1;

            const response = await apiClient.get<any>("/v1/movements", {
                params,
                signal: getSignal(),
            });

            const responseData = response.data as { data: Movement[]; meta?: MovementMeta };

            movements.value = responseData.data || [];

            if (responseData.meta) {
                meta.value = responseData.meta;
            } else {
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: movements.value.length,
                    to: movements.value.length,
                    total: movements.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value = err.response?.data?.message || err.message;
            throw error.value;
        } finally {
            isLoading.value = false;
        }
    };

    const deleteMovement = async (id: number) => {
        try {
            await apiClient.delete(`/v1/movements/${id}`);
            movements.value = movements.value.filter((m) => m.id !== id);
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const fetchFormOptions = async () => {
        try {
            const response = await apiClient.get<any>("/v1/movements/form-options");
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const fetchMovement = async (id: number | string) => {
        try {
            const response = await apiClient.get<any>(`/v1/movements/${id}`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const createMovement = async (payload: any) => {
        try {
            const response = await apiClient.post<any>("/v1/movements", payload);
            return response.data.data;
        } catch (err: any) {
            throw err;
        }
    };

    const updateMovement = async (id: number | string, payload: any) => {
        try {
            const response = await apiClient.put<any>(`/v1/movements/${id}`, payload);
            return response.data.data;
        } catch (err: any) {
            throw err;
        }
    };

    const submitMovement = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/movements/${id}/submit`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const postMovement = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/movements/${id}/post`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const rejectMovement = async (id: number | string, reason?: string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/movements/${id}/reject`, {
                rejection_reason: reason ?? null,
            });
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const cancelMovement = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/movements/${id}/cancel`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const reopenMovement = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/movements/${id}/reopen`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    return {
        movements,
        isLoading,
        error,
        meta,
        fetchMovements,
        deleteMovement,
        fetchFormOptions,
        fetchMovement,
        createMovement,
        updateMovement,
        submitMovement,
        postMovement,
        rejectMovement,
        cancelMovement,
        reopenMovement,
    };
});
