import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { apiClient } from "@tenant/lib/api";
import {
    mapApiCategory,
    mapApiConfig,
    mapApiCustomer,
    mapApiPaymentMethod,
    mapApiProduct,
    mapApiSession,
} from "@tenant/lib/posMappers";
import { lineCalc } from "@tenant/lib/posCalc";
import { buildReceiptPayload } from "@tenant/lib/posReceipt";
import type {
    CartLine,
    LoyaltyPreview,
    PaymentLine,
    PosCategory,
    PosConfigState,
    PosCustomer,
    PosJournal,
    PosPaymentMethod,
    PosProduct,
    PosSession,
    ReceiptPayload,
} from "@tenant/types/pos";
import { useLotStore, type Lot } from "@tenant/stores/lot";
import type { ReceiptTemplateLayout } from "@tenant/components/ReceiptRenderer.vue";

export const usePosStore = defineStore("pos", () => {
    // ─── State ────────────────────────────────────────────────────────────────
    const currentConfig = ref<PosConfigState | null>(null);
    const currentSession = ref<PosSession | null>(null);
    const activeTab = ref<"products" | "payment">("products");

    const cart = ref<CartLine[]>([]);
    const selectedCustomer = ref<PosCustomer | null>(null);
    const selectedJournal = ref<PosJournal | null>(null);
    const payments = ref<PaymentLine[]>([]);

    const products = ref<PosProduct[]>([]);
    const categories = ref<PosCategory[]>([]);
    const paymentMethods = ref<PosPaymentMethod[]>([]);
    const customers = ref<PosCustomer[]>([]);
    const posConfigs = ref<PosConfigState[]>([]);
    const receiptTemplate = ref<ReceiptTemplateLayout | null>(null);
    const receiptTemplateLogoUrl = ref<string | null>(null);

    const selectedCategoryId = ref<number | null>(null);
    const searchQuery = ref("");
    const isLoading = ref(false);
    const lastReceiptPayload = ref<ReceiptPayload | null>(null);

    const loyaltyPreview = ref<LoyaltyPreview | null>(null);
    const loyaltyRedeemPoints = ref<number>(0);
    const isLoadingLoyalty = ref(false);
    let loyaltyDebounceTimer: ReturnType<typeof setTimeout> | null = null;

    const recentPosSales = ref<any[]>([]);
    const isLoadingRecentSales = ref(false);
    const selectedSaleDetail = ref<any | null>(null);
    const isLoadingSaleDetail = ref(false);

    // ─── Getters ──────────────────────────────────────────────────────────────
    const filteredProducts = computed(() => {
        let result = products.value.filter((p) => p.is_pos_visible);

        if (selectedCategoryId.value !== null) {
            result = result.filter(
                (p) => p.category_id === selectedCategoryId.value,
            );
        }

        if (searchQuery.value.trim()) {
            const q = searchQuery.value.toLowerCase().trim();
            result = result.filter(
                (p) =>
                    p.display_name.toLowerCase().includes(q) ||
                    p.sku.toLowerCase().includes(q) ||
                    p.barcode.includes(q),
            );
        }

        return result;
    });

    const cartSubtotal = computed(() =>
        cart.value.reduce((sum, line) => sum + lineCalc(line).subtotal, 0),
    );

    const cartTaxAmount = computed(() =>
        cart.value.reduce((sum, line) => sum + lineCalc(line).taxAmount, 0),
    );

    const cartTotal = computed(() =>
        cart.value.reduce((sum, line) => sum + lineCalc(line).total, 0),
    );

    const cartItemCount = computed(() =>
        cart.value.reduce((sum, line) => sum + line.quantity, 0),
    );

    const isSessionOpen = computed(
        () => currentSession.value?.status === "opened",
    );

    const loyaltyDiscount = computed(() => {
        const redeem = loyaltyPreview.value?.redeem;
        if (!redeem || loyaltyRedeemPoints.value <= 0) return 0;
        const raw = loyaltyRedeemPoints.value * redeem.rate;
        return Math.round(Math.min(cartTotal.value, raw) * 100) / 100;
    });

    const cartFinalTotal = computed(() =>
        Math.max(
            0,
            Math.round((cartTotal.value - loyaltyDiscount.value) * 100) / 100,
        ),
    );

    const totalPaid = computed(() =>
        payments.value.reduce((sum, p) => sum + p.amount, 0),
    );

    const changeAmount = computed(() => {
        const diff = totalPaid.value - cartFinalTotal.value;
        return diff > 0 ? diff : 0;
    });

    const canConfirmPayment = computed(
        () =>
            totalPaid.value >= cartFinalTotal.value &&
            cart.value.length > 0 &&
            selectedJournal.value !== null,
    );

    // ─── Helpers ──────────────────────────────────────────────────────────────
    const applyConfigDefaults = () => {
        if (!currentConfig.value) return;

        if (currentConfig.value.default_customer_id) {
            const customer = customers.value.find(
                (item) => item.id === currentConfig.value?.default_customer_id,
            );
            if (customer) selectedCustomer.value = customer;
        }

        const defaultJournal =
            currentConfig.value.journals.find((j) => j.is_default) ||
            currentConfig.value.journals[0];

        selectedJournal.value = defaultJournal ?? null;
    };

    const unwrapList = (response: any): any[] => {
        const data = response.data.data;
        return Array.isArray(data) ? data : (data?.data ?? []);
    };

    // ─── Catalog loaders ──────────────────────────────────────────────────────
    const loadConfig = async (configId: number) => {
        const response = await apiClient.get<any>(
            `/v1/pos-configs/${configId}`,
        );
        const config = mapApiConfig(response.data.data);
        currentConfig.value = config;
        applyConfigDefaults();
        return config;
    };

    const loadAvailableConfigs = async () => {
        const response = await apiClient.get<any>("/v1/pos-configs", {
            params: { per_page: "total", status: "active" },
        });
        posConfigs.value = unwrapList(response).map(mapApiConfig);
        return posConfigs.value;
    };

    const loadCategories = async () => {
        const response = await apiClient.get<any>("/v1/categories", {
            params: { per_page: "total", status: "active" },
        });
        categories.value = unwrapList(response).map(mapApiCategory);
        return categories.value;
    };

    const loadCustomers = async () => {
        const response = await apiClient.get<any>("/v1/customers", {
            params: { per_page: "total", status: "active" },
        });
        customers.value = unwrapList(response).map(mapApiCustomer);
        applyConfigDefaults();
        return customers.value;
    };

    const loadProducts = async (
        search?: string,
        categoryId?: number | null,
    ) => {
        const response = await apiClient.get<any>(
            "/v1/product-products/pos-catalog",
            {
                params: {
                    limit: 300,
                    warehouse_id:
                        currentConfig.value?.warehouse_id ?? undefined,
                    search: search || undefined,
                    category_id: categoryId ?? undefined,
                },
            },
        );
        products.value = unwrapList(response).map(mapApiProduct);
        return products.value;
    };

    const loadPaymentMethods = async () => {
        const response = await apiClient.get<any>("/v1/payment-methods", {
            params: { per_page: "total", status: "active" },
        });
        paymentMethods.value = unwrapList(response).map(mapApiPaymentMethod);
        return paymentMethods.value;
    };

    const loadRecentPosSales = async (
        search?: string,
        days: number = 30,
    ) => {
        isLoadingRecentSales.value = true;
        try {
            const fromDate = new Date();
            fromDate.setDate(fromDate.getDate() - days);
            const fromIso = fromDate.toISOString().slice(0, 10);

            const response = await apiClient.get<any>("/v1/sales", {
                params: {
                    from_pos: 1,
                    from: fromIso,
                    per_page: 50,
                    search: search || undefined,
                },
            });

            const items = Array.isArray(response.data.data)
                ? response.data.data
                : (response.data.data?.data ?? []);

            recentPosSales.value = items;
            return items;
        } finally {
            isLoadingRecentSales.value = false;
        }
    };

    const loadSaleDetail = async (saleId: number) => {
        isLoadingSaleDetail.value = true;
        try {
            const response = await apiClient.get<any>(`/v1/sales/${saleId}`);
            selectedSaleDetail.value = response.data.data ?? null;
            return selectedSaleDetail.value;
        } finally {
            isLoadingSaleDetail.value = false;
        }
    };

    const clearSaleDetail = () => {
        selectedSaleDetail.value = null;
    };

    const createPosRefund = async (payload: {
        original_sale_id: number;
        lines: Array<{ product_product_id: number; quantity: number }>;
        notes?: string | null;
    }) => {
        if (!currentSession.value) {
            throw new Error("No hay sesión POS abierta");
        }

        const response = await apiClient.post<any>("/v1/pos/refunds", {
            original_sale_id: payload.original_sale_id,
            pos_session_id: currentSession.value.id,
            lines: payload.lines,
            notes: payload.notes ?? null,
        });

        return response.data.data ?? response.data;
    };

    const loadReceiptTemplate = async () => {
        try {
            const response = await apiClient.get<any>("/v1/receipt-template");
            const data = response.data.data;
            if (data?.layout) {
                receiptTemplate.value = data.layout as ReceiptTemplateLayout;
            }
            receiptTemplateLogoUrl.value = data?.logo_url ?? null;
        } catch (e) {
            console.warn("No se pudo cargar la plantilla de comprobante", e);
        }
    };

    const loadPosReadData = async () => {
        isLoading.value = true;
        try {
            await Promise.all([
                loadCategories(),
                loadCustomers(),
                loadProducts(),
                loadPaymentMethods(),
                loadReceiptTemplate(),
            ]);
        } finally {
            isLoading.value = false;
        }
    };

    // ─── Session lifecycle ────────────────────────────────────────────────────
    const fetchOpenSession = async (configId: number) => {
        const response = await apiClient.get<any>("/v1/pos-sessions", {
            params: {
                pos_config_id: configId,
                status: "opened",
                per_page: 1,
            },
        });
        const session = response.data.data?.[0]
            ? mapApiSession(response.data.data[0])
            : null;
        currentSession.value = session;
        return session;
    };

    const fetchSessionDetails = async (sessionId: number) => {
        const response = await apiClient.get<any>(
            `/v1/pos-sessions/${sessionId}`,
        );
        const session = mapApiSession(response.data.data);
        currentSession.value = session;
        return session;
    };

    const openSession = async (
        openingBalance: number,
        openingNote?: string,
    ) => {
        if (!currentConfig.value) return null;
        const response = await apiClient.post<any>("/v1/pos-sessions/open", {
            pos_config_id: currentConfig.value.id,
            opening_balance: openingBalance,
            opening_note: openingNote,
        });
        const session = mapApiSession(response.data.data);
        currentSession.value = session;
        return session;
    };

    const closeSession = async (
        closingBalance: number,
        closingNote?: string,
    ) => {
        if (!currentSession.value) return null;
        const response = await apiClient.patch<any>(
            `/v1/pos-sessions/${currentSession.value.id}/close`,
            {
                closing_balance: closingBalance,
                closing_note: closingNote,
            },
        );
        const session = mapApiSession(response.data.data);
        currentSession.value = session;
        return session;
    };

    const resetSession = () => {
        currentSession.value = null;
        currentConfig.value = null;
        clearCart();
        activeTab.value = "products";
    };

    // ─── Lots (per cart line) ─────────────────────────────────────────────────
    const lotsCacheByProduct = ref<Record<string, Lot[]>>({});
    const isLoadingLotsByProduct = ref<Record<string, boolean>>({});
    const lotStore = useLotStore();

    const lotKey = (productProductId: number) =>
        `${productProductId}:${currentConfig.value?.warehouse_id ?? 0}`;

    const fetchLotsForProduct = async (productProductId: number) => {
        const key = lotKey(productProductId);
        if (lotsCacheByProduct.value[key] !== undefined) {
            return lotsCacheByProduct.value[key];
        }
        if (isLoadingLotsByProduct.value[key]) return [];
        isLoadingLotsByProduct.value[key] = true;
        try {
            const lots = await lotStore.fetchAvailableForProduct(
                productProductId,
                currentConfig.value?.warehouse_id ?? null,
            );
            lotsCacheByProduct.value[key] = lots;
            return lots;
        } catch {
            lotsCacheByProduct.value[key] = [];
            return [];
        } finally {
            isLoadingLotsByProduct.value[key] = false;
        }
    };

    const getLotsForProduct = (productProductId: number): Lot[] =>
        lotsCacheByProduct.value[lotKey(productProductId)] ?? [];

    const lotLabel = (lot: { lot_number: string; expires_at: string | null }) =>
        `${lot.lot_number}${lot.expires_at ? ` · ${lot.expires_at}` : ""}`;

    const suggestFefoForLine = async (index: number) => {
        const line = cart.value[index];
        if (!line || !line.product.is_tracked_by_lot) return;
        const lots = await fetchLotsForProduct(line.product.id);
        const candidate = lots[0];
        if (!candidate) return;
        const updated = cart.value[index];
        if (!updated || updated.lot_id) return;
        updated.lot_id = candidate.id;
        updated.lot_label = lotLabel(candidate);
    };

    const setLineLot = (index: number, lotId: number | null) => {
        const line = cart.value[index];
        if (!line) return;
        line.lot_id = lotId;
        if (!lotId) {
            line.lot_label = null;
            return;
        }
        const found = getLotsForProduct(line.product.id).find(
            (l) => l.id === lotId,
        );
        if (found) line.lot_label = lotLabel(found);
    };

    // ─── Cart ─────────────────────────────────────────────────────────────────
    const addToCart = (
        product: PosProduct,
        options: { lotId?: number | null; lotLabel?: string | null } = {},
    ) => {
        const lotId = options.lotId ?? null;

        // Dedupe by (product.id, lot_id). Same product + different lot is a new line.
        const existing = cart.value.find(
            (line) =>
                line.product.id === product.id &&
                (line.lot_id ?? null) === lotId,
        );

        if (existing) {
            existing.quantity += 1;
            schedulePreview();
            return;
        }

        const taxRate = currentConfig.value?.apply_tax
            ? (currentConfig.value?.tax_rate ?? 0)
            : 0;
        const pricesIncludeTax =
            currentConfig.value?.prices_include_tax ?? false;

        cart.value.push({
            product,
            quantity: 1,
            price: product.price,
            taxRate,
            pricesIncludeTax,
            lot_id: lotId,
            lot_label: options.lotLabel ?? null,
        });

        const newLineIndex = cart.value.length - 1;

        if (lotId !== null) {
            if (!options.lotLabel) {
                const found = getLotsForProduct(product.id).find(
                    (l) => l.id === lotId,
                );
                if (found) cart.value[newLineIndex]!.lot_label = lotLabel(found);
            }
            schedulePreview();
            return;
        }

        // FEFO suggestion when no explicit lot
        if (
            product.is_tracked_by_lot &&
            currentConfig.value &&
            (currentConfig.value.default_lot_strategy === "fefo_auto" ||
                currentConfig.value.default_lot_strategy ===
                    "fefo_suggest_manual")
        ) {
            void suggestFefoForLine(newLineIndex);
        }

        schedulePreview();
    };

    const updateQuantity = (index: number, quantity: number) => {
        if (quantity <= 0) {
            cart.value.splice(index, 1);
        } else {
            const line = cart.value[index];
            if (line) line.quantity = quantity;
        }
        schedulePreview();
    };

    const removeLine = (index: number) => {
        cart.value.splice(index, 1);
        schedulePreview();
    };

    const clearCart = () => {
        cart.value = [];
        payments.value = [];
        loyaltyRedeemPoints.value = 0;
        loyaltyPreview.value = null;
    };

    const setCustomer = (customer: PosCustomer | null) => {
        selectedCustomer.value = customer;
        loyaltyRedeemPoints.value = 0;
        schedulePreview();
    };

    const setJournal = (journal: PosJournal | null) => {
        selectedJournal.value = journal;
    };

    // ─── Payments ─────────────────────────────────────────────────────────────
    const addPayment = (paymentMethodId: number) => {
        const method = paymentMethods.value.find(
            (m) => m.id === paymentMethodId,
        );
        if (!method) return;

        const remaining = cartFinalTotal.value - totalPaid.value;
        payments.value.push({
            payment_method_id: method.id,
            payment_method_name: method.name,
            amount: Math.max(remaining, 0),
        });
    };

    const updatePaymentAmount = (index: number, amount: number) => {
        const payment = payments.value[index];
        if (payment) payment.amount = amount;
    };

    const removePayment = (index: number) => {
        payments.value.splice(index, 1);
    };

    const goToPayment = () => {
        const firstMethod = paymentMethods.value[0];
        if (payments.value.length === 0 && firstMethod) {
            addPayment(firstMethod.id);
        }
        activeTab.value = "payment";
    };

    // ─── Checkout ─────────────────────────────────────────────────────────────
    const checkout = async (): Promise<boolean> => {
        if (!canConfirmPayment.value) return false;
        if (
            !currentConfig.value ||
            !currentSession.value ||
            !selectedJournal.value
        ) {
            return false;
        }

        isLoading.value = true;

        try {
            const payload = {
                pos_config_id: currentConfig.value.id,
                pos_session_id: currentSession.value.id,
                partner_id:
                    selectedCustomer.value?.id ??
                    currentConfig.value.default_customer_id ??
                    null,
                journal_id: selectedJournal.value.id,
                warehouse_id: currentConfig.value.warehouse_id,
                items: cart.value.map((line) => ({
                    product_product_id: line.product.id,
                    quantity: line.quantity,
                    price: line.price,
                    tax_id:
                        currentConfig.value?.apply_tax &&
                        currentConfig.value?.tax_id
                            ? currentConfig.value.tax_id
                            : null,
                    lot_id: line.lot_id ?? null,
                })),
                payments: payments.value.map((payment) => ({
                    payment_method_id: payment.payment_method_id,
                    amount: payment.amount,
                    reference: null,
                })),
                loyalty_card_id: loyaltyPreview.value?.card?.id ?? null,
                redeem_points:
                    loyaltyRedeemPoints.value > 0
                        ? loyaltyRedeemPoints.value
                        : null,
            };

            const response = await apiClient.post<any>(
                "/v1/pos/checkout",
                payload,
            );

            if (response.data.data?.session) {
                currentSession.value = mapApiSession(
                    response.data.data.session,
                );
            } else {
                await fetchSessionDetails(currentSession.value.id);
            }

            const saleData = response.data.data?.sale ?? null;
            const paymentsSnapshot = payments.value.map((p) => ({
                method_name: p.payment_method_name,
                amount: p.amount,
            }));

            if (saleData) {
                lastReceiptPayload.value = buildReceiptPayload(
                    saleData,
                    paymentsSnapshot,
                );
            }

            clearCart();
            resetLoyalty();
            activeTab.value = "products";
            searchQuery.value = "";
            await loadProducts();
            applyConfigDefaults();

            if (
                lastReceiptPayload.value &&
                currentConfig.value?.auto_print_receipt
            ) {
                await printLastReceipt().catch((e) =>
                    console.warn("Auto-print failed", e),
                );
            }

            return true;
        } finally {
            isLoading.value = false;
        }
    };

    const printLastReceipt = async (): Promise<boolean> => {
        if (!lastReceiptPayload.value) return false;

        const { printReceipt } = await import("@tenant/lib/printReceipt");
        let template = receiptTemplate.value;
        if (!template) {
            await loadReceiptTemplate();
            template = receiptTemplate.value;
        }
        if (!template) return false;

        await printReceipt({
            template,
            templateLogoUrl: receiptTemplateLogoUrl.value,
            ...lastReceiptPayload.value,
        });
        return true;
    };

    // ─── Loyalty ──────────────────────────────────────────────────────────────
    const fetchLoyaltyPreview = async () => {
        if (!currentConfig.value) {
            loyaltyPreview.value = null;
            return;
        }

        const partnerId =
            selectedCustomer.value?.id ??
            currentConfig.value.default_customer_id ??
            null;

        if (cart.value.length === 0 || !partnerId) {
            loyaltyPreview.value = null;
            loyaltyRedeemPoints.value = 0;
            return;
        }

        isLoadingLoyalty.value = true;
        try {
            const response = await apiClient.post<any>(
                "/v1/pos/loyalty/preview",
                {
                    pos_config_id: currentConfig.value.id,
                    partner_id: partnerId,
                    total: cartTotal.value,
                },
            );
            const data = response.data.data as LoyaltyPreview;
            loyaltyPreview.value = data;

            const max = data?.redeem?.max_points ?? 0;
            if (loyaltyRedeemPoints.value > max) {
                loyaltyRedeemPoints.value = max;
            }
        } catch {
            loyaltyPreview.value = null;
        } finally {
            isLoadingLoyalty.value = false;
        }
    };

    const schedulePreview = () => {
        if (loyaltyDebounceTimer) clearTimeout(loyaltyDebounceTimer);
        loyaltyDebounceTimer = setTimeout(() => {
            void fetchLoyaltyPreview();
        }, 250);
    };

    const setRedeemPoints = (value: number) => {
        const max = loyaltyPreview.value?.redeem?.max_points ?? 0;
        const n = Math.max(0, Math.min(Math.floor(value || 0), max));
        loyaltyRedeemPoints.value = n;
    };

    const resetLoyalty = () => {
        loyaltyPreview.value = null;
        loyaltyRedeemPoints.value = 0;
    };

    const setCategory = (categoryId: number | null) => {
        selectedCategoryId.value = categoryId;
    };

    const setSearch = (query: string) => {
        searchQuery.value = query;
    };

    return {
        // State
        currentConfig,
        currentSession,
        activeTab,
        cart,
        selectedCustomer,
        selectedJournal,
        payments,
        products,
        categories,
        paymentMethods,
        customers,
        posConfigs,
        selectedCategoryId,
        searchQuery,
        isLoading,
        loyaltyPreview,
        loyaltyRedeemPoints,
        isLoadingLoyalty,
        lastReceiptPayload,
        recentPosSales,
        isLoadingRecentSales,
        selectedSaleDetail,
        isLoadingSaleDetail,

        // Getters
        filteredProducts,
        lineCalc,
        cartSubtotal,
        cartTaxAmount,
        cartTotal,
        cartItemCount,
        isSessionOpen,
        totalPaid,
        changeAmount,
        canConfirmPayment,
        loyaltyDiscount,
        cartFinalTotal,

        // Actions
        loadConfig,
        loadAvailableConfigs,
        loadCategories,
        loadCustomers,
        loadProducts,
        loadPaymentMethods,
        loadRecentPosSales,
        loadSaleDetail,
        clearSaleDetail,
        createPosRefund,
        loadPosReadData,
        fetchOpenSession,
        fetchSessionDetails,
        openSession,
        closeSession,
        resetSession,
        addToCart,
        updateQuantity,
        removeLine,
        clearCart,
        fetchLotsForProduct,
        getLotsForProduct,
        suggestFefoForLine,
        setLineLot,
        setCustomer,
        setJournal,
        addPayment,
        updatePaymentAmount,
        removePayment,
        goToPayment,
        checkout,
        printLastReceipt,
        setCategory,
        setSearch,
        fetchLoyaltyPreview,
        setRedeemPoints,
        resetLoyalty,
    };
});
