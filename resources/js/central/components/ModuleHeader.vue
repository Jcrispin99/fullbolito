<script setup lang="ts">
import { Button } from "@/components/ui/button";
import { ChevronLeft, ChevronRight, Plus, LayoutGrid, LayoutList } from "lucide-vue-next";
import { computed, ref, watch, onUnmounted, type Component } from "vue";
import PageHeader from "@/components/PageHeader.vue";

export interface ViewModeOption {
    value: string;
    icon: Component;
    label?: string;
}

const props = withDefaults(
    defineProps<{
        title: string;
        itemsCount: number;
        totalItems: number;
        perPage: number | string;
        currentPage: number;
        loading?: boolean;
        canCreate?: boolean;
        canSearch?: boolean;
        search?: string;
        selectedItems?: any[];
        viewMode?: string;
        viewModes?: ViewModeOption[];
        newLabel?: string;
        searchPlaceholder?: string;
        prevLabel?: string;
        nextLabel?: string;
    }>(),
    {
        selectedItems: () => [],
        canSearch: true,
        newLabel: "Nuevo",
        searchPlaceholder: "Buscar...",
        prevLabel: "Anterior",
        nextLabel: "Siguiente",
    },
);

const emit = defineEmits<{
    (e: "create"): void;
    (e: "update:perPage", value: number | string): void;
    (e: "update:search", value: string): void;
    (e: "update:viewMode", value: string): void;
    (e: "pageChange", page: number): void;
}>();

const DEFAULT_VIEW_MODES: ViewModeOption[] = [
    { value: "table", icon: LayoutList, label: "Tabla" },
    { value: "grid", icon: LayoutGrid, label: "Cuadrícula" },
];

const resolvedViewModes = computed<ViewModeOption[]>(
    () => props.viewModes ?? DEFAULT_VIEW_MODES,
);

// Debounced search: input updates immediately, emit fires after 300ms
const localSearch = ref(props.search || "");
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

watch(() => props.search, (val) => { localSearch.value = val || ""; });

const handleSearchInput = (event: Event) => {
    const value = (event.target as HTMLInputElement).value;
    localSearch.value = value;
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => emit("update:search", value), 300);
};

onUnmounted(() => {
    if (searchTimeout) clearTimeout(searchTimeout);
});

const displayRange = computed(() => {
    if (props.totalItems === 0) return "0-0";
    const pp =
        props.perPage === "total" ? props.totalItems : Number(props.perPage);
    const start = (props.currentPage - 1) * pp + 1;
    const end = Math.min(start + pp - 1, props.totalItems);
    return `${start}-${end}`;
});

const isLastPage = computed(() => {
    if (props.totalItems === 0) return true;
    const parts = displayRange.value.split("-");
    const end = Number(parts[1] ?? 0);
    return end >= props.totalItems;
});

const handleRangeInput = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const value = input.value.trim();

    if (value.toLowerCase() === "total") {
        emit("update:perPage", "total");
        return;
    }

    const asNumber = Number(value);
    if (Number.isInteger(asNumber) && asNumber > 0) {
        emit("update:perPage", asNumber);
        return;
    }

    const match = value.match(/^1\s*-\s*(\d+)$/);
    if (!match) return;
    const end = Number(match[1]);
    if (!Number.isInteger(end) || end <= 0) return;
    emit("update:perPage", end);
};
</script>

<template>
    <PageHeader :title="title">
        <template #leading>
            <Button
                v-if="canCreate"
                @click="$emit('create')"
                size="sm"
                class="h-9"
            >
                <Plus class="mr-2 h-4 w-4" />
                {{ newLabel }}
            </Button>
        </template>

        <template #center>
            <div
                v-if="selectedItems.length > 0"
                class="w-full flex justify-center animate-in fade-in zoom-in-95 duration-200"
            >
                <slot name="actions" />
            </div>
            <div
                v-else
                class="flex items-center gap-2 w-full max-w-lg animate-in fade-in zoom-in-95 duration-200"
            >
                <div v-if="canSearch" class="relative flex-1">
                    <input
                        :value="localSearch"
                        @input="handleSearchInput"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    />
                </div>
                <slot name="filters" />
            </div>
        </template>

        <template #trailing>
            <div class="flex items-center gap-2 text-sm text-muted-foreground mt-2 sm:mt-0">
                <div class="flex items-center gap-1">
                    <input
                        :value="displayRange"
                        @change="handleRangeInput"
                        class="w-20 h-8 bg-transparent text-center border-0 border-b border-border focus:outline-none focus:border-ring"
                    />
                    <span class="text-xs text-muted-foreground">
                        / {{ totalItems }}
                    </span>
                </div>

                <div
                    class="flex items-center gap-1 ml-4"
                    v-if="totalItems > 0 && perPage !== 'total'"
                >
                    <Button
                        variant="outline"
                        size="icon"
                        class="h-8 w-8 rounded-full"
                        :disabled="currentPage <= 1 || loading"
                        @click="$emit('pageChange', currentPage - 1)"
                    >
                        <span class="sr-only">{{ prevLabel }}</span>
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="h-8 w-8 rounded-full"
                        :disabled="isLastPage || loading"
                        @click="$emit('pageChange', currentPage + 1)"
                    >
                        <span class="sr-only">{{ nextLabel }}</span>
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>

                <div
                    v-if="viewMode !== undefined"
                    class="flex items-center gap-1 bg-muted/50 p-0.5 rounded-md ml-4"
                >
                    <Button
                        v-for="m in resolvedViewModes"
                        :key="m.value"
                        variant="ghost"
                        size="icon"
                        class="h-7 w-7 rounded-sm"
                        :class="
                            viewMode === m.value
                                ? 'bg-background shadow-sm text-foreground'
                                : ''
                        "
                        :title="m.label || m.value"
                        @click="$emit('update:viewMode', m.value)"
                    >
                        <component :is="m.icon" class="h-4 w-4" />
                        <span class="sr-only">{{ m.label || m.value }}</span>
                    </Button>
                </div>
            </div>
        </template>
    </PageHeader>
</template>
