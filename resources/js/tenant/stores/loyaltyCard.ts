import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from "@/composables/useFetchAbort";

export interface LoyaltyCard {
    id: number;
    loyalty_program_id: number;
    partner_id: number | null;
    code: string;
    points: string;
    expiration_date: string | null;
    is_active: boolean;
    is_expired: boolean;
    is_usable: boolean;
    program?: { id: number; name: string; program_type: string };
    partner?: { id: number; name: string; display_name?: string };
    created_at: string;
    updated_at: string;
}

export const useLoyaltyCardStore = defineStore("tenant-loyalty-card", () => {
    const cards = ref<LoyaltyCard[]>([]);
    const currentCard = ref<LoyaltyCard | null>(null);
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
    const { getSignal, isAbortError } = createFetchAbort();

    async function fetchCards(
        page = 1,
        perPage: number | string = 15,
        search = "",
        status = "all",
        programId: number | string = "",
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const qp = new URLSearchParams();
            qp.set("page", String(page));
            qp.set("per_page", String(perPage));
            if (search) qp.set("search", search);
            if (status) qp.set("status", status);
            if (programId) qp.set("loyalty_program_id", String(programId));
            const { data } = await apiClient.get<any>(
                `/v1/loyalty/cards?${qp.toString()}`,
                { signal: getSignal() },
            );

            if (data.data?.meta) {
                cards.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                cards.value = Array.isArray(data.data) ? data.data : data.data.data;
                meta.value = {
                    current_page: 1, from: 1, last_page: 1,
                    per_page: cards.value.length, to: cards.value.length, total: cards.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value = err.response?.data?.message || "Error fetching cards";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createCard(payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<LoyaltyCard>("/v1/loyalty/cards", payload);
            cards.value.unshift(data.data as any);
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error creating card";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteCard(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/loyalty/cards/${id}`);
            cards.value = cards.value.filter((c) => String(c.id) !== String(id));
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error deleting card";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function toggleActive(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.patch<LoyaltyCard>(`/v1/loyalty/cards/${id}/toggle-status`);
            const idx = cards.value.findIndex((c) => String(c.id) === String(id));
            if (idx !== -1) cards.value[idx] = data.data as any;
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error toggling card status";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function findByCode(code: string) {
        try {
            const { data } = await apiClient.post<LoyaltyCard>("/v1/loyalty/cards/find-by-code", { code });
            return data.data;
        } catch (err: any) {
            throw err?.response?.data || err;
        }
    }

    return {
        cards, currentCard, meta, isLoading, error,
        fetchCards, createCard, deleteCard, toggleActive, findByCode,
    };
});
