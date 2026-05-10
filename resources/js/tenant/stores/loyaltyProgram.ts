import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "@tenant/lib/api";
import { createFetchAbort } from "@/composables/useFetchAbort";

export interface LoyaltyRule {
    id: number;
    loyalty_program_id: number;
    reward_point_amount: string;
    reward_point_mode: "order" | "money" | "unit";
    minimum_qty: number;
    minimum_amount: string;
    code: string | null;
    conditions: Record<string, any> | null;
    product_variant_ids: number[];
    product_template_ids: number[];
    category_ids: number[];
    product_variants?: { id: number; name: string }[];
    product_templates?: { id: number; name: string }[];
    categories?: { id: number; name: string }[];
}

export interface LoyaltyReward {
    id: number;
    loyalty_program_id: number;
    reward_type: "discount" | "product";
    required_points: string;
    description: string | null;
    discount: string | null;
    discount_mode: "percent" | "fixed_amount" | "per_point" | null;
    discount_applicability: "order" | "cheapest" | "specific" | null;
    discount_max_amount: string | null;
    reward_product_id: number | null;
    reward_product_qty: number;
    reward_product?: { id: number; name: string } | null;
    discount_product_ids: number[];
    discount_category_ids: number[];
    discount_products?: { id: number; name: string }[];
    discount_categories?: { id: number; name: string }[];
}

export interface LoyaltyProgram {
    id: number;
    name: string;
    description: string | null;
    program_type: "promotion" | "coupon" | "loyalty" | "buy_x_get_y" | "promo_code";
    is_pos: boolean;
    is_web: boolean;
    is_sales: boolean;
    applies_on: "current" | "future" | "both";
    trigger: "auto" | "with_code";
    point_name: string;
    starts_at: string | null;
    ends_at: string | null;
    max_uses: number | null;
    max_uses_per_customer: number | null;
    current_uses: number;
    is_active: boolean;
    is_available: boolean;
    rules: LoyaltyRule[];
    rewards: LoyaltyReward[];
    cards_count?: number;
    created_at: string;
    updated_at: string;
}

export const useLoyaltyProgramStore = defineStore("tenant-loyalty-program", () => {
    const programs = ref<LoyaltyProgram[]>([]);
    const currentProgram = ref<LoyaltyProgram | null>(null);
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

    async function fetchPrograms(
        page = 1,
        perPage: number | string = 15,
        search = "",
        status = "all",
        module = "",
        programType = "",
    ) {
        isLoading.value = true;
        error.value = null;
        try {
            const qp = new URLSearchParams();
            qp.set("page", String(page));
            qp.set("per_page", String(perPage));
            if (search) qp.set("search", search);
            if (status) qp.set("status", status);
            if (module) qp.set("module", module);
            if (programType) qp.set("program_type", programType);
            const { data } = await apiClient.get<any>(
                `/v1/loyalty/programs?${qp.toString()}`,
                { signal: getSignal() },
            );

            if (data.data?.meta) {
                programs.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                programs.value = Array.isArray(data.data) ? data.data : data.data.data;
                meta.value = {
                    current_page: 1, from: 1, last_page: 1,
                    per_page: programs.value.length, to: programs.value.length, total: programs.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return;
            error.value = err.response?.data?.message || "Error fetching programs";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchProgram(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<LoyaltyProgram>(`/v1/loyalty/programs/${id}`);
            currentProgram.value = data.data as any;
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error fetching program";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function createProgram(payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.post<LoyaltyProgram>("/v1/loyalty/programs", payload);
            programs.value.unshift(data.data as any);
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error creating program";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function updateProgram(id: number | string, payload: any) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.put<LoyaltyProgram>(`/v1/loyalty/programs/${id}`, payload);
            const idx = programs.value.findIndex((p) => String(p.id) === String(id));
            if (idx !== -1) programs.value[idx] = data.data as any;
            currentProgram.value = data.data as any;
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error updating program";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteProgram(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.delete(`/v1/loyalty/programs/${id}`);
            programs.value = programs.value.filter((p) => String(p.id) !== String(id));
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error deleting program";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function deletePrograms(ids: number[]) {
        isLoading.value = true;
        error.value = null;
        try {
            await apiClient.post("/v1/loyalty/programs/batch-delete", { ids });
            programs.value = programs.value.filter((p) => !ids.includes(p.id));
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error deleting programs";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function toggleActive(id: number | string) {
        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.patch<LoyaltyProgram>(`/v1/loyalty/programs/${id}/toggle-status`);
            const idx = programs.value.findIndex((p) => String(p.id) === String(id));
            if (idx !== -1) programs.value[idx] = data.data as any;
            if (currentProgram.value && String(currentProgram.value.id) === String(id)) {
                currentProgram.value = data.data as any;
            }
            return data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || "Error toggling status";
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        programs, currentProgram, meta, isLoading, error,
        fetchPrograms, fetchProgram, createProgram, updateProgram,
        deleteProgram, deletePrograms, toggleActive,
    };
});
