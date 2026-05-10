import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from "@/composables/useFetchAbort";

export interface LoyaltyTransaction {
    id: number;
    loyalty_program_id: number | null;
    loyalty_card_id: number | null;
    partner_id: number;
    type: "earn" | "redeem" | "expire" | "adjust";
    points: number;
    balance: number;
    description: string | null;
    source_type: string | null;
    source_id: number | null;
    expires_at: string | null;
    partner?: { id: number; name: string; display_name?: string };
    card?: { id: number; code: string; points: string };
    created_at: string;
    updated_at: string;
}

export const useLoyaltyTransactionStore = defineStore("tenant-loyalty-transaction", () => {
    const transactions = ref<LoyaltyTransaction[]>([]);
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

    async function fetchTransactions(
        page = 1,
        perPage: number | string = 15,
        programId: number | string = "",
        cardId: number | string = "",
        type = "",
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const qp = new URLSearchParams();
            qp.set("page", String(page));
            qp.set("per_page", String(perPage));
            if (programId) qp.set("loyalty_program_id", String(programId));
            if (cardId) qp.set("loyalty_card_id", String(cardId));
            if (type) qp.set("type", type);
            const { data } = await apiClient.get<any>(
                `/v1/loyalty/transactions?${qp.toString()}`,
                { signal: getSignal() },
            );

            if (data.data?.meta) {
                transactions.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                transactions.value = Array.isArray(data.data) ? data.data : data.data.data;
                meta.value = {
                    current_page: 1, from: 1, last_page: 1,
                    per_page: transactions.value.length, to: transactions.value.length, total: transactions.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value = err.response?.data?.message || "Error fetching transactions";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function adjust(cardId: number, points: number, description?: string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<LoyaltyTransaction>("/v1/loyalty/transactions/adjust", {
                loyalty_card_id: cardId,
                points,
                description,
            });
            transactions.value.unshift(data.data as any);
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error adjusting points";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        transactions, meta, isLoading, error,
        fetchTransactions, adjust,
    };
});
