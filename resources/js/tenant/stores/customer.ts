import { defineStore } from "pinia";
import { ref } from "vue";
import { apiClient } from "../lib/api";
import { createFetchAbort } from '@/composables/useFetchAbort'

export interface Customer {
    id: number;
    name: string;
    document_type: string | null;
    document_number: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    status: string; // "active" | "inactive" typically
    birth_date: string | null;
    gender: string | null;
    payment_terms: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface CustomersResponse {
    data: Customer[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export const useCustomerStore = defineStore("customer", () => {
    const customers = ref<Customer[]>([]);
    const meta = ref<CustomersResponse["meta"] | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const { getSignal, isAbortError } = createFetchAbort()

    const fetchCustomers = async (
        page = 1,
        perPage: number | string = 15,
        search = "",
        status: "active" | "inactive" | "all" | string = "active",
    ) => {
        isLoading.value = true;
        error.value = null;
        try {
            const qp = new URLSearchParams({ page: String(page) });
            if (perPage) qp.set("per_page", String(perPage));
            if (search) qp.set("search", search);
            if (status) qp.set("status", status);

            const { data } = await apiClient.get<any>(
                `/v1/customers?${qp.toString()}`,
                { signal: getSignal() },
            );

            if (data.data?.meta) {
                customers.value = data.data.data;
                meta.value = data.data.meta;
            } else {
                customers.value = Array.isArray(data.data)
                    ? data.data
                    : data.data?.data || [];
                meta.value = {
                    current_page: 1,
                    last_page: 1,
                    per_page: customers.value.length,
                    total: customers.value.length,
                };
            }
        } catch (err: any) {
            if (isAbortError(err)) return
            error.value = err.message || "Failed to fetch customers";
            console.error(err);
        } finally {
            isLoading.value = false;
        }
    };

    const toggleStatus = async (id: number | string) => {
        const { data } = await apiClient.patch(`/v1/customers/${id}/toggle-status`);
        return data.data; // Return updated customer
    };

    const deleteCustomer = async (id: number | string) => {
        await apiClient.delete(`/v1/customers/${id}`);
    };

    const deleteCustomers = async (ids: (number | string)[]) => {
        await apiClient.delete("/v1/customers", { data: { ids } });
    };

    return {
        customers,
        meta,
        isLoading,
        error,
        fetchCustomers,
        toggleStatus,
        deleteCustomer,
        deleteCustomers,
    };
});
