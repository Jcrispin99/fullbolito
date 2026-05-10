import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from "@/composables/useFetchAbort";

/**
 * Estados derivados del par exit+entry (calculados en backend en `Transfer::derivedStatus`).
 * Se mantienen los aliases legacy `sent` (=in_transit) y `received` (=completed) para
 * compatibilidad temporal del FE no migrado.
 */
export type TransferStatus =
    | "draft"
    | "pending_exit"
    | "in_transit"
    | "completed"
    | "with_observation"
    | "cancelled"
    // legacy aliases — el backend los mapea, pero la UI nueva usa los derivados
    | "sent"
    | "received";

export type MovementStatus =
    | "draft"
    | "submitted"
    | "posted"
    | "rejected"
    | "cancelled";

export interface TransferEmbeddedMovement {
    id: number;
    type: "entry" | "exit";
    serie: string;
    correlative: string;
    sequence_code: string;
    status: MovementStatus;
    warehouse_id: number;
    submitted_at: string | null;
    posted_at: string | null;
    rejected_at: string | null;
    cancelled_at: string | null;
    submitted_user_id: number | null;
    posted_user_id: number | null;
    rejected_user_id: number | null;
    cancelled_user_id: number | null;
    rejection_reason: string | null;
    warehouse?: { id: number; name: string; company?: { id: number; name: string } };
    submitted_user?: { id: number; name: string } | null;
    posted_user?: { id: number; name: string } | null;
    rejected_user?: { id: number; name: string } | null;
    cancelled_user?: { id: number; name: string } | null;
}

export interface TransferWarehouseRef {
    id: number;
    name: string;
    company_id?: number;
    company?: { id: number; name: string; business_name?: string };
}

export interface TransferUserRef {
    id: number;
    name: string;
    email?: string;
}

export interface Transfer {
    id: number;
    serie: string;
    correlative: string;
    sequence_code: string;
    date: string | null;
    status: TransferStatus;
    sent_at: string | null;
    received_at: string | null;
    total: number;
    observation: string | null;
    company_id: number | null;
    from_warehouse_id: number;
    to_warehouse_id: number;
    sent_by_user_id: number | null;
    received_by_user_id: number | null;
    company?: { id: number; name: string; business_name?: string };
    from_warehouse?: TransferWarehouseRef;
    to_warehouse?: TransferWarehouseRef;
    sent_by_user?: TransferUserRef | null;
    received_by_user?: TransferUserRef | null;
    // Bloques nuevos del par de movements (fuente de verdad)
    exit_movement?: TransferEmbeddedMovement | null;
    entry_movement?: TransferEmbeddedMovement | null;
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

export interface TransferMeta {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
    // Contadores nuevos (estados derivados)
    draft_total?: number;
    in_transit_total?: number;
    completed_total?: number;
    cancelled_total?: number;
    // Aliases legacy
    sent_total?: number;
    received_total?: number;
}

export const useTransferStore = defineStore("transfer", () => {
    const transfers = ref<Transfer[]>([]);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort();
    const meta = ref<TransferMeta>({
        current_page: 1,
        from: 0,
        last_page: 1,
        per_page: 15,
        to: 0,
        total: 0,
        draft_total: 0,
        in_transit_total: 0,
        completed_total: 0,
        cancelled_total: 0,
        sent_total: 0,
        received_total: 0,
    });

    const fetchTransfers = async (
        page = 1,
        perPage: number | "total" = 15,
        search = "",
        status = "all",
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

            const response = await apiClient.get<any>("/v1/transfers", {
                params,
                signal: getSignal(),
            });

            const responseData = response.data as { data: Transfer[]; meta?: TransferMeta };

            transfers.value = responseData.data || [];

            if (responseData.meta) {
                meta.value = responseData.meta;
            } else {
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: transfers.value.length,
                    to: transfers.value.length,
                    total: transfers.value.length,
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

    const deleteTransfer = async (id: number) => {
        try {
            await apiClient.delete(`/v1/transfers/${id}`);
            transfers.value = transfers.value.filter((t) => t.id !== id);
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const fetchFormOptions = async () => {
        try {
            const response = await apiClient.get<any>("/v1/transfers/form-options");
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const fetchTransfer = async (id: number | string) => {
        try {
            const response = await apiClient.get<any>(`/v1/transfers/${id}`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const createTransfer = async (payload: any) => {
        try {
            const response = await apiClient.post<any>("/v1/transfers", payload);
            return response.data.data;
        } catch (err: any) {
            throw err;
        }
    };

    const updateTransfer = async (id: number | string, payload: any) => {
        try {
            const response = await apiClient.put<any>(`/v1/transfers/${id}`, payload);
            return response.data.data;
        } catch (err: any) {
            throw err;
        }
    };

    const sendTransfer = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/transfers/${id}/send`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const receiveTransfer = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/transfers/${id}/receive`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const cancelTransfer = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/transfers/${id}/cancel`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    const restoreDraftTransfer = async (id: number | string) => {
        try {
            const response = await apiClient.patch<any>(`/v1/transfers/${id}/draft`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    /**
     * Persiste el bloque de transporte de la GRE (motivo/modalidad/peso/
     * vehículo/conductor). El backend rechaza la edición si la guía ya
     * fue aceptada o tiene ticket pendiente.
     */
    const updateGre = async (id: number | string, payload: Record<string, any>) => {
        try {
            const response = await apiClient.patch<any>(
                `/v1/transfers/${id}/gre`,
                payload,
            );
            return response.data.data;
        } catch (err: any) {
            throw err;
        }
    };

    /**
     * Envía la GRE a SUNAT (síncrono — devuelve ticket pendiente).
     */
    const sendGre = async (id: number | string) => {
        try {
            const response = await apiClient.post<any>(`/v1/transfers/${id}/gre/send`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    /**
     * Consulta el estado del ticket GRE — actualiza gre_status y archiva CDR.
     */
    const pollGre = async (id: number | string) => {
        try {
            const response = await apiClient.post<any>(`/v1/transfers/${id}/gre/poll`);
            return response.data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    };

    return {
        transfers,
        isLoading,
        error,
        meta,
        fetchTransfers,
        deleteTransfer,
        fetchFormOptions,
        fetchTransfer,
        createTransfer,
        updateTransfer,
        sendTransfer,
        receiveTransfer,
        cancelTransfer,
        restoreDraftTransfer,
        updateGre,
        sendGre,
        pollGre,
    };
});
