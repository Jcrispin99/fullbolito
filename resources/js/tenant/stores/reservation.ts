import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from "@/composables/useFetchAbort";

export type ReservationStatus =
    | "held"
    | "confirmed"
    | "paid"
    | "played"
    | "cancelled"
    | "no_show";

export interface Reservation {
    id: number;
    code: string;
    journal_id: number;
    serie: string;
    correlative: string;
    court_id: number;
    court?: { id: number; name: string; sport: string; slug: string };
    company_id: number;
    start_at: string;
    end_at: string;
    status: ReservationStatus;
    is_blocking: boolean;
    held_until: string | null;
    partner_id: number | null;
    partner?: { id: number; name: string | null } | null;
    customer_name: string | null;
    customer_phone: string | null;
    customer_email: string | null;
    total: number | string;
    notes: string | null;
    sale_id: number | null;
    sale?: {
        id: number;
        serie: string;
        correlative: string;
        document_number: string;
        subtotal: number | string;
        tax_amount: number | string;
        total: number | string;
        status: string;
        payment_status: string;
    } | null;
    created_by_user_id: number | null;
    created_by?: { id: number; name: string } | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    created_at: string;
    updated_at: string;
}

export interface ReservationFormOptions {
    courts: Array<{
        id: number;
        name: string;
        sport: string;
        company_id: number;
        slot_duration_minutes: number | null;
    }>;
    statuses: Array<{ value: ReservationStatus; label: string }>;
}

export const useReservationStore = defineStore("tenant-reservation", () => {
    const reservations = ref<Reservation[]>([]);
    const currentReservation = ref<Reservation | null>(null);
    const meta = ref({
        current_page: 1,
        from: 0,
        last_page: 1,
        per_page: 25,
        to: 0,
        total: 0,
    });
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort();

    async function fetchReservations(
        page = 1,
        perPage: number | string = 25,
        search = "",
        courtId: number | string = "",
        status = "",
        dateFrom = "",
        dateTo = "",
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const qp = new URLSearchParams();
            qp.set("page", String(page));
            qp.set("per_page", String(perPage));
            if (search) qp.set("search", search);
            if (courtId) qp.set("court_id", String(courtId));
            if (status) qp.set("status", status);
            if (dateFrom) qp.set("date_from", dateFrom);
            if (dateTo) qp.set("date_to", dateTo);

            const { data } = await apiClient.get<any>(
                `/v1/reservations?${qp.toString()}`,
                { signal: getSignal() },
            );

            if (data.data?.meta) {
                reservations.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                reservations.value = Array.isArray(data.data)
                    ? data.data
                    : data.data.data;
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: reservations.value.length,
                    to: reservations.value.length,
                    total: reservations.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value =
                err.response?.data?.message || "Error fetching reservations";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchFormOptions(): Promise<ReservationFormOptions> {
        try {
            const { data } = await apiClient.get<any>(
                "/v1/reservations/form-options",
            );
            return data.data;
        } catch (err: any) {
            console.error("Error fetching form options", err);
            return { courts: [], statuses: [] };
        }
    }

    async function fetchReservation(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<Reservation>(
                `/v1/reservations/${id}`,
            );
            currentReservation.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error fetching reservation";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createReservation(payload: Partial<Reservation>) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<Reservation>(
                "/v1/reservations",
                payload,
            );
            reservations.value.unshift(data.data);
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error creating reservation";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updateReservation(
        id: number | string,
        payload: Partial<Reservation>,
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.put<Reservation>(
                `/v1/reservations/${id}`,
                payload,
            );
            replaceInList(id, data.data);
            currentReservation.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error updating reservation";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteReservation(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/reservations/${id}`);
            reservations.value = reservations.value.filter(
                (r) => String(r.id) !== String(id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting reservation";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteReservations(ids: number[]) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.post(`/v1/reservations/batch-delete`, { ids });
            reservations.value = reservations.value.filter(
                (r) => !ids.includes(r.id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting reservations";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    // -------- Transiciones de estado --------

    async function confirm(id: number | string) {
        return runTransition(`/v1/reservations/${id}/confirm`, id, "PATCH");
    }

    async function cancel(id: number | string, reason: string = "") {
        return runTransition(
            `/v1/reservations/${id}/cancel`,
            id,
            "PATCH",
            reason ? { reason } : {},
        );
    }

    async function markPaid(id: number | string) {
        return runTransition(`/v1/reservations/${id}/mark-paid`, id, "PATCH");
    }

    async function markPlayed(id: number | string) {
        return runTransition(`/v1/reservations/${id}/mark-played`, id, "PATCH");
    }

    async function markNoShow(id: number | string) {
        return runTransition(
            `/v1/reservations/${id}/mark-no-show`,
            id,
            "PATCH",
        );
    }

    async function runTransition(
        url: string,
        id: number | string,
        _method: "PATCH",
        body: Record<string, any> = {},
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.patch<Reservation>(url, body);
            replaceInList(id, data.data);
            if (
                currentReservation.value &&
                String(currentReservation.value.id) === String(id)
            ) {
                currentReservation.value = data.data;
            }
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error en la transición";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    function replaceInList(id: number | string, item: Reservation) {
        const idx = reservations.value.findIndex(
            (r) => String(r.id) === String(id),
        );
        if (idx !== -1) reservations.value[idx] = item;
    }

    return {
        reservations,
        currentReservation,
        meta,
        isLoading,
        error,
        fetchReservations,
        fetchFormOptions,
        fetchReservation,
        createReservation,
        updateReservation,
        deleteReservation,
        deleteReservations,
        confirm,
        cancel,
        markPaid,
        markPlayed,
        markNoShow,
    };
});
