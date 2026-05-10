import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "../lib/api";

export type LotAlertType = "expiring" | "expired" | "blocked";
export type LotAlertStatus = "unread" | "read";

export interface LotAlert {
    id: number;
    lot_id: number;
    product_product_id: number;
    company_id: number;
    alert_type: LotAlertType;
    days_until_expiry: number | null;
    alert_date: string | null;
    message: string;
    status: LotAlertStatus;
    read_at: string | null;
    created_at: string;
    lot?: {
        id: number;
        lot_number: string;
        expires_at: string | null;
        status: string;
    };
    product_product?: any;
}

export interface LotAlertBadge {
    unread_count: number;
    by_type: {
        expired: number;
        expiring: number;
        blocked: number;
    };
}

export interface LotAlertFilters {
    status?: LotAlertStatus | "all";
    alert_type?: LotAlertType;
    from?: string;
    to?: string;
}

export const useLotAlertStore = defineStore("lotAlert", () => {
    const alerts = ref<LotAlert[]>([]);
    const badge = ref<LotAlertBadge>({
        unread_count: 0,
        by_type: { expired: 0, expiring: 0, blocked: 0 },
    });
    const isLoading = ref(false);

    const fetchBadge = async (): Promise<LotAlertBadge> => {
        const { data } = await apiClient.get<any>("/v1/lot-alerts/badge");
        badge.value = data.data;
        return badge.value;
    };

    const fetchAlerts = async (
        page = 1,
        perPage: number | string = 25,
        filters: LotAlertFilters = {},
    ) => {
        isLoading.value = true;
        try {
            const qp = new URLSearchParams({ page: String(page) });
            if (perPage) qp.set("per_page", String(perPage));
            if (filters.status && filters.status !== "all")
                qp.set("status", filters.status);
            if (filters.alert_type) qp.set("alert_type", filters.alert_type);
            if (filters.from) qp.set("from", filters.from);
            if (filters.to) qp.set("to", filters.to);

            const { data } = await apiClient.get<any>(
                `/v1/lot-alerts?${qp.toString()}`,
            );
            alerts.value = Array.isArray(data?.data) ? data.data : [];
            return alerts.value;
        } finally {
            isLoading.value = false;
        }
    };

    const markRead = async (id: number) => {
        await apiClient.patch(`/v1/lot-alerts/${id}/read`);
        const item = alerts.value.find((a) => a.id === id);
        if (item) {
            item.status = "read";
            item.read_at = new Date().toISOString();
        }
        await fetchBadge();
    };

    const markAllRead = async () => {
        await apiClient.post("/v1/lot-alerts/mark-all-read");
        for (const a of alerts.value) {
            a.status = "read";
            a.read_at = a.read_at ?? new Date().toISOString();
        }
        await fetchBadge();
    };

    const destroy = async (id: number) => {
        await apiClient.delete(`/v1/lot-alerts/${id}`);
        alerts.value = alerts.value.filter((a) => a.id !== id);
        await fetchBadge();
    };

    return {
        alerts,
        badge,
        isLoading,
        fetchBadge,
        fetchAlerts,
        markRead,
        markAllRead,
        destroy,
    };
});
