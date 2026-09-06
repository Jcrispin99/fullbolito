import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from "@/composables/useFetchAbort";

export interface Court {
    id: number;
    name: string;
    slug: string;
    code: string | null;
    description: string | null;
    sport: string;
    surface: string | null;
    capacity: number | null;
    slot_duration_minutes: number | null;
    price: number | null;
    company_id: number;
    product_product_id: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    company?: { id: number; business_name: string; trade_name?: string | null };
}

export interface CourtFormOptions {
    companies: Array<{
        id: number;
        business_name: string;
        trade_name?: string | null;
        ruc?: string | null;
    }>;
}

export const useCourtStore = defineStore("tenant-court", () => {
    const courts = ref<Court[]>([]);
    const currentCourt = ref<Court | null>(null);
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

    async function fetchCourts(
        page = 1,
        perPage: number | string = 15,
        search = "",
        status = "active",
        sport = "",
        companyId: number | string = "",
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const qp = new URLSearchParams();
            qp.set("page", String(page));
            qp.set("per_page", String(perPage));
            if (search) qp.set("search", search);
            if (status) qp.set("status", status);
            if (sport) qp.set("sport", sport);
            if (companyId) qp.set("company_id", String(companyId));
            const { data } = await apiClient.get<any>(
                `/v1/courts?${qp.toString()}`,
                { signal: getSignal() },
            );

            if (data.data?.meta) {
                courts.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                courts.value = Array.isArray(data.data)
                    ? data.data
                    : data.data.data;
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: courts.value.length,
                    to: courts.value.length,
                    total: courts.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value =
                err.response?.data?.message || "Error fetching courts";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchFormOptions(): Promise<CourtFormOptions> {
        try {
            const { data } = await apiClient.get<any>("/v1/courts/form-options");
            return data.data;
        } catch (err: any) {
            console.error("Error fetching form options", err);
            return { companies: [] };
        }
    }

    async function fetchCourt(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<Court>(`/v1/courts/${id}`);
            currentCourt.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error fetching court";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createCourt(payload: Partial<Court>) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<Court>("/v1/courts", payload);
            courts.value.unshift(data.data);
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error creating court";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updateCourt(id: number | string, payload: Partial<Court>) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.put<Court>(
                `/v1/courts/${id}`,
                payload,
            );
            const idx = courts.value.findIndex(
                (c) => String(c.id) === String(id),
            );
            if (idx !== -1) courts.value[idx] = data.data;
            currentCourt.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error updating court";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteCourt(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/courts/${id}`);
            courts.value = courts.value.filter(
                (c) => String(c.id) !== String(id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting court";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteCourts(ids: number[]) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.post(`/v1/courts/batch-delete`, { ids });
            courts.value = courts.value.filter((c) => !ids.includes(c.id));
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting courts";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function toggleActive(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.patch<Court>(
                `/v1/courts/${id}/toggle-status`,
            );
            const idx = courts.value.findIndex(
                (c) => String(c.id) === String(id),
            );
            if (idx !== -1) courts.value[idx] = data.data;
            if (
                currentCourt.value &&
                String(currentCourt.value.id) === String(id)
            ) {
                currentCourt.value = data.data;
            }
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error toggling court status";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        courts,
        currentCourt,
        meta,
        isLoading,
        error,
        fetchCourts,
        fetchFormOptions,
        fetchCourt,
        createCourt,
        updateCourt,
        deleteCourt,
        deleteCourts,
        toggleActive,
    };
});
