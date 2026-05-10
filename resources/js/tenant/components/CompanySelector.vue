<script setup lang="ts">
import {
    ref,
    computed,
    onMounted,
    onBeforeUnmount,
    nextTick,
    watch,
} from "vue";
import { useCompanyStore } from "@tenant/stores/company";
import { useCompanyFilterStore } from "@tenant/stores/companyFilter";
import { storeToRefs } from "pinia";
import { ChevronDown, Check } from "lucide-vue-next";
import type { Company } from "@/types/models";

// ── Stores ───────────────────────────────────────────────────────────────────
const companyStore = useCompanyStore();
const filterStore = useCompanyFilterStore();
const { companies, isLoading } = storeToRefs(companyStore);

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
    if (companies.value.length === 0) {
        await companyStore.fetchCompanies(1, "total", "", "active");
    }
    document.addEventListener("pointerdown", onGlobalDown, { capture: true });
});

onBeforeUnmount(() => {
    document.removeEventListener("pointerdown", onGlobalDown, {
        capture: true,
    } as any);
});

// ── Dropdown state ───────────────────────────────────────────────────────────
const isOpen = ref(false);
const rootRef = ref<HTMLElement | null>(null);
const panelRef = ref<HTMLElement | null>(null);
const panelStyle = ref<Record<string, string>>({});

const open = async () => {
    if (isOpen.value) return;
    isOpen.value = true;
    await nextTick();
    updatePosition();
};

const close = () => {
    isOpen.value = false;
    if (dirty.value) {
        window.location.reload();
    }
};

const toggle = () => (isOpen.value ? close() : open());

const updatePosition = async () => {
    const el = rootRef.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    panelStyle.value = {
        position: "fixed",
        left: `${rect.left}px`,
        top: `${rect.bottom + 4}px`,
        minWidth: `${Math.max(rect.width, 200)}px`,
    };

    await nextTick();
    const panel = panelRef.value;
    if (!panel) return;
    const panelRect = panel.getBoundingClientRect();
    if (panelRect.right > window.innerWidth) {
        panelStyle.value.left = `${rect.right - panelRect.width}px`;
    }
};

const dirty = ref(false);

const onGlobalDown = (e: PointerEvent) => {
    const root = rootRef.value;
    const panel = panelRef.value;
    const target = e.target as Node | null;
    if (root && root.contains(target)) return;
    if (panel && panel.contains(target)) return;
    close();
};

// ── Selection logic ──────────────────────────────────────────────────────────
const allIds = computed(() => (companies.value as Company[]).map((c) => c.id));

const isAllSelected = computed(
    () =>
        filterStore.selectedIds.length === 0 ||
        filterStore.selectedIds.length === allIds.value.length,
);

const toggleAll = () => {
    if (isAllSelected.value && filterStore.selectedIds.length > 0) {
        filterStore.clear();
    } else {
        filterStore.setAll(allIds.value);
    }
    dirty.value = true;
};

const toggleCompany = (id: number) => {
    filterStore.toggle(id);
    if (filterStore.selectedIds.length === allIds.value.length) {
        filterStore.clear();
    }
    dirty.value = true;
};

// ── Hierarchical list ────────────────────────────────────────────────────────
const orderedCompanies = computed<{ company: Company; depth: number }[]>(() => {
    const all = companies.value as Company[];
    const parents = all.filter((c) => !c.parent_id);
    const result: { company: Company; depth: number }[] = [];
    for (const parent of parents) {
        result.push({ company: parent, depth: 0 });
        for (const child of all.filter((c) => c.parent_id === parent.id)) {
            result.push({ company: child, depth: 1 });
        }
    }
    const listed = new Set(result.map((r) => r.company.id));
    for (const c of all) {
        if (!listed.has(c.id)) result.push({ company: c, depth: 0 });
    }
    return result;
});

// ── Trigger label ────────────────────────────────────────────────────────────
const triggerLabel = computed(() => {
    const count = filterStore.selectedIds.length;
    if (count === 0) return "Todas";
    if (count === 1) {
        const c = (companies.value as Company[]).find(
            (c) => c.id === filterStore.selectedIds[0],
        );
        return c?.business_name ?? "1 empresa";
    }
    return `${count} empresas`;
});
</script>

<template>
    <div ref="rootRef" class="relative inline-block">
        <button
            type="button"
            class="flex items-center gap-1 h-10 w-full bg-transparent px-0 py-2 pr-5 text-sm text-foreground border-b border-transparent transition-colors hover:border-input focus:border-ring focus:outline-none uppercase tracking-wide font-medium max-w-[200px] truncate"
            :disabled="isLoading"
            @click="toggle"
        >
            <span class="truncate">{{ triggerLabel }}</span>
        </button>

        <ChevronDown
            class="pointer-events-none absolute right-0 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground transition-transform duration-150"
            :class="{ 'rotate-180': isOpen }"
        />

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="panelRef"
                class="z-[9999] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md"
                :style="panelStyle"
            >
                <div class="p-1">
                    <!-- Toggle all -->
                    <button
                        type="button"
                        class="w-full flex items-center gap-2 rounded-sm px-2 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground focus:outline-none"
                        :class="isAllSelected ? 'font-medium' : ''"
                        @mousedown.prevent
                        @click="toggleAll"
                    >
                        <span
                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded-sm border border-primary"
                            :class="isAllSelected ? 'bg-primary text-primary-foreground' : ''"
                        >
                            <Check v-if="isAllSelected" class="h-3 w-3" />
                        </span>
                        Todas las empresas
                    </button>

                    <div class="my-1 h-px bg-border" />

                    <!-- Individual companies -->
                    <button
                        v-for="{ company, depth } in orderedCompanies"
                        :key="company.id"
                        type="button"
                        class="w-full flex items-center gap-2 rounded-sm px-2 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground focus:outline-none"
                        :class="filterStore.isSelected(company.id) ? 'font-medium' : ''"
                        :style="depth === 1 ? 'padding-left: 28px' : ''"
                        @mousedown.prevent
                        @click="toggleCompany(company.id)"
                    >
                        <span
                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded-sm border border-primary"
                            :class="filterStore.isSelected(company.id) || isAllSelected ? 'bg-primary text-primary-foreground' : ''"
                        >
                            <Check v-if="filterStore.isSelected(company.id) || isAllSelected" class="h-3 w-3" />
                        </span>
                        {{ company.business_name }}
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
