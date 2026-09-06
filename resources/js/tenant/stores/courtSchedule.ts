import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from "@/composables/useFetchAbort";

export interface CourtSchedule {
    id: number;
    court_id: number;
    court?: { id: number; name: string; sport: string };
    day_of_week: number;
    start_time: string;
    end_time: string;
    price: number | string;
    slot_duration_minutes: number | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface CourtScheduleFormOptions {
    courts: Array<{
        id: number;
        name: string;
        sport: string;
        slot_duration_minutes: number | null;
    }>;
    days_of_week: Array<{ value: number; label: string }>;
}

export const useCourtScheduleStore = defineStore("tenant-court-schedule", () => {
    const schedules = ref<CourtSchedule[]>([]);
    const currentSchedule = ref<CourtSchedule | null>(null);
    const meta = ref({
        current_page: 1,
        from: 0,
        last_page: 1,
        per_page: 50,
        to: 0,
        total: 0,
    });
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort();

    async function fetchSchedules(
        page = 1,
        perPage: number | string = 50,
        courtId: number | string = "",
        dayOfWeek: number | string = "",
        status = "active",
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const qp = new URLSearchParams();
            qp.set("page", String(page));
            qp.set("per_page", String(perPage));
            if (courtId !== "" && courtId !== null && courtId !== undefined) {
                qp.set("court_id", String(courtId));
            }
            if (
                dayOfWeek !== "" &&
                dayOfWeek !== null &&
                dayOfWeek !== undefined
            ) {
                qp.set("day_of_week", String(dayOfWeek));
            }
            if (status) qp.set("status", status);

            const { data } = await apiClient.get<any>(
                `/v1/court-schedules?${qp.toString()}`,
                { signal: getSignal() },
            );

            if (data.data?.meta) {
                schedules.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                schedules.value = Array.isArray(data.data)
                    ? data.data
                    : data.data.data;
                meta.value = {
                    current_page: 1,
                    from: 1,
                    last_page: 1,
                    per_page: schedules.value.length,
                    to: schedules.value.length,
                    total: schedules.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value =
                err.response?.data?.message || "Error fetching schedules";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchFormOptions(): Promise<CourtScheduleFormOptions> {
        try {
            const { data } = await apiClient.get<any>(
                "/v1/court-schedules/form-options",
            );
            return data.data;
        } catch (err: any) {
            console.error("Error fetching form options", err);
            return { courts: [], days_of_week: [] };
        }
    }

    async function fetchSchedule(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<CourtSchedule>(
                `/v1/court-schedules/${id}`,
            );
            currentSchedule.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error fetching schedule";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createSchedule(
        payload: Omit<Partial<CourtSchedule>, "day_of_week"> & {
            day_of_week: number[];
        },
    ): Promise<CourtSchedule[]> {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<CourtSchedule[]>(
                "/v1/court-schedules",
                payload,
            );
            const created = Array.isArray(data.data) ? data.data : [data.data];
            // Prepend en orden inverso para que el primero quede arriba.
            for (let i = created.length - 1; i >= 0; i--) {
                schedules.value.unshift(created[i]);
            }
            return created;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error creating schedule";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updateSchedule(
        id: number | string,
        payload: Partial<CourtSchedule>,
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.put<CourtSchedule>(
                `/v1/court-schedules/${id}`,
                payload,
            );
            const idx = schedules.value.findIndex(
                (s) => String(s.id) === String(id),
            );
            if (idx !== -1) schedules.value[idx] = data.data;
            currentSchedule.value = data.data;
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error updating schedule";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteSchedule(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/court-schedules/${id}`);
            schedules.value = schedules.value.filter(
                (s) => String(s.id) !== String(id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting schedule";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteSchedules(ids: number[]) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.post(`/v1/court-schedules/batch-delete`, { ids });
            schedules.value = schedules.value.filter(
                (s) => !ids.includes(s.id),
            );
        } catch (err: any) {
            error.value =
                err.response?.data?.message || "Error deleting schedules";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function toggleActive(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.patch<CourtSchedule>(
                `/v1/court-schedules/${id}/toggle-status`,
            );
            const idx = schedules.value.findIndex(
                (s) => String(s.id) === String(id),
            );
            if (idx !== -1) schedules.value[idx] = data.data;
            if (
                currentSchedule.value &&
                String(currentSchedule.value.id) === String(id)
            ) {
                currentSchedule.value = data.data;
            }
            return data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message ||
                "Error toggling schedule status";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        schedules,
        currentSchedule,
        meta,
        isLoading,
        error,
        fetchSchedules,
        fetchFormOptions,
        fetchSchedule,
        createSchedule,
        updateSchedule,
        deleteSchedule,
        deleteSchedules,
        toggleActive,
    };
});
