<script setup lang="ts" generic="V extends string | number">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { Spinner } from "@/components/ui/spinner";
import { ChevronDown, X, Search } from "lucide-vue-next";
import { cn } from "@/lib/utils";

interface Option {
    value: V;
    label: string;
    description?: string | null;
}

const props = withDefaults(
    defineProps<{
        modelValue: V[];
        options: Option[];
        placeholder?: string;
        searchPlaceholder?: string;
        emptyMessage?: string;
        disabled?: boolean;
        maxHeight?: string;
        /** When true, `options` is assumed to already be the search result for the
         * current term (server-side search) — the component skips its own local
         * substring filtering and just renders what it's given. */
        remote?: boolean;
        /** Shows a loading affordance while a remote search is in flight. */
        loading?: boolean;
    }>(),
    {
        placeholder: "Seleccionar...",
        searchPlaceholder: "Buscar...",
        emptyMessage: "Sin resultados.",
        disabled: false,
        maxHeight: "260px",
        remote: false,
        loading: false,
    },
);

const emit = defineEmits<{
    (e: "update:modelValue", value: V[]): void;
    (e: "search", term: string): void;
}>();

const open = ref(false);
const search = ref("");
const triggerRef = ref<HTMLDivElement | null>(null);
const panelRef = ref<HTMLDivElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);

const panelStyle = ref<{
    top: string;
    left: string;
    width: string;
    visibility: "visible" | "hidden";
}>({
    top: "0px",
    left: "0px",
    width: "0px",
    visibility: "hidden",
});

const selectedSet = computed(() => new Set(props.modelValue));

const filteredOptions = computed(() => {
    if (props.remote) return props.options;
    const term = search.value.trim().toLowerCase();
    if (!term) return props.options;
    return props.options.filter(
        (o) =>
            o.label.toLowerCase().includes(term) ||
            String(o.value).toLowerCase().includes(term) ||
            (o.description ?? "").toLowerCase().includes(term),
    );
});

let searchDebounceTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, (term) => {
    if (!props.remote) return;
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => emit("search", term.trim()), 300);
});

const selectedOptions = computed(() =>
    props.options.filter((o) => selectedSet.value.has(o.value)),
);

const updatePanelPosition = () => {
    if (!triggerRef.value) return;
    const rect = triggerRef.value.getBoundingClientRect();
    const panelMaxHeight = 320;
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;
    const placeBelow = spaceBelow >= panelMaxHeight || spaceBelow >= spaceAbove;

    panelStyle.value = {
        top: placeBelow
            ? `${rect.bottom + 4}px`
            : `${rect.top - panelMaxHeight - 4}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        visibility: "visible",
    };
};

const toggle = (val: V) => {
    if (props.disabled) return;
    const set = new Set(props.modelValue);
    if (set.has(val)) set.delete(val);
    else set.add(val);
    emit("update:modelValue", [...set]);
};

const remove = (val: V, e?: MouseEvent) => {
    e?.stopPropagation();
    if (props.disabled) return;
    emit(
        "update:modelValue",
        props.modelValue.filter((v) => v !== val),
    );
};

const clearAll = (e: MouseEvent) => {
    e.stopPropagation();
    if (props.disabled) return;
    emit("update:modelValue", []);
};

const openPanel = async () => {
    if (props.disabled) return;
    open.value = true;
    search.value = "";
    if (props.remote) emit("search", "");
    await nextTick();
    updatePanelPosition();
    searchInputRef.value?.focus();
};

const closePanel = () => {
    open.value = false;
};

const togglePanel = () => {
    if (open.value) closePanel();
    else openPanel();
};

const onClickOutside = (e: MouseEvent) => {
    if (!open.value) return;
    const target = e.target as Node;
    if (
        triggerRef.value?.contains(target) ||
        panelRef.value?.contains(target)
    ) {
        return;
    }
    closePanel();
};

const onEsc = (e: KeyboardEvent) => {
    if (e.key === "Escape" && open.value) closePanel();
};

const onWindowChange = () => {
    if (open.value) updatePanelPosition();
};

watch(
    () => props.options.length,
    () => {
        if (open.value) {
            nextTick(updatePanelPosition);
        }
    },
);

onMounted(() => {
    document.addEventListener("mousedown", onClickOutside);
    document.addEventListener("keydown", onEsc);
    window.addEventListener("scroll", onWindowChange, true);
    window.addEventListener("resize", onWindowChange);
});

onBeforeUnmount(() => {
    clearTimeout(searchDebounceTimer);
    document.removeEventListener("mousedown", onClickOutside);
    document.removeEventListener("keydown", onEsc);
    window.removeEventListener("scroll", onWindowChange, true);
    window.removeEventListener("resize", onWindowChange);
});
</script>

<template>
    <div class="relative w-full">
        <!-- Trigger -->
        <div
            ref="triggerRef"
            :class="
                cn(
                    'flex min-h-10 w-full flex-wrap items-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-sm cursor-pointer transition-colors hover:border-ring focus-within:border-ring',
                    open && 'border-ring ring-1 ring-ring',
                    disabled &&
                        'opacity-50 cursor-not-allowed pointer-events-none',
                )
            "
            @click="togglePanel"
        >
            <template v-if="selectedOptions.length === 0">
                <span class="text-muted-foreground flex-1">
                    {{ placeholder }}
                </span>
            </template>
            <template v-else>
                <Badge
                    v-for="opt in selectedOptions"
                    :key="String(opt.value)"
                    variant="secondary"
                    class="text-xs gap-1 pr-1"
                >
                    <span class="truncate max-w-[160px]">{{ opt.label }}</span>
                    <button
                        type="button"
                        class="rounded-full p-0.5 hover:bg-muted-foreground/20"
                        :disabled="disabled"
                        @click="(e) => remove(opt.value, e)"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </Badge>
            </template>

            <div class="ml-auto flex items-center gap-1 shrink-0">
                <button
                    v-if="selectedOptions.length > 0 && !disabled"
                    type="button"
                    class="text-muted-foreground hover:text-foreground p-0.5"
                    title="Limpiar"
                    @click="clearAll"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
                <ChevronDown
                    :class="
                        cn(
                            'h-4 w-4 text-muted-foreground transition-transform',
                            open && 'rotate-180',
                        )
                    "
                />
            </div>
        </div>

        <!-- Panel teleported to body to escape ancestor overflow:hidden -->
        <Teleport to="body">
            <div
                v-if="open"
                ref="panelRef"
                class="fixed z-[60] rounded-md border bg-popover text-popover-foreground shadow-md"
                :style="{
                    top: panelStyle.top,
                    left: panelStyle.left,
                    width: panelStyle.width,
                    visibility: panelStyle.visibility,
                }"
            >
                <div class="flex items-center gap-2 border-b px-3 py-2">
                    <Spinner v-if="loading" class="h-4 w-4 text-muted-foreground" />
                    <Search v-else class="h-4 w-4 text-muted-foreground" />
                    <input
                        ref="searchInputRef"
                        v-model="search"
                        :placeholder="searchPlaceholder"
                        class="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                    />
                </div>

                <div class="overflow-y-auto py-1" :style="{ maxHeight }">
                    <p
                        v-if="!loading && filteredOptions.length === 0"
                        class="px-3 py-4 text-center text-sm text-muted-foreground"
                    >
                        {{ emptyMessage }}
                    </p>
                    <label
                        v-for="opt in filteredOptions"
                        :key="String(opt.value)"
                        class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-accent text-sm"
                    >
                        <Checkbox
                            :checked="selectedSet.has(opt.value)"
                            @update:checked="toggle(opt.value)"
                        />
                        <div class="flex-1 min-w-0">
                            <div class="truncate">{{ opt.label }}</div>
                            <div
                                v-if="opt.description"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ opt.description }}
                            </div>
                        </div>
                    </label>
                </div>

                <div
                    v-if="selectedOptions.length > 0"
                    class="border-t px-3 py-2 text-xs text-muted-foreground flex items-center justify-between"
                >
                    <span>{{ selectedOptions.length }} seleccionado(s)</span>
                    <button
                        type="button"
                        class="text-primary hover:underline"
                        @click="clearAll"
                    >
                        Limpiar todo
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
