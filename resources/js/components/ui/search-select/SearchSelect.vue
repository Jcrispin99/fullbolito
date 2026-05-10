<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useAttrs,
    watch,
} from "vue";
import { useVModel } from "@vueuse/core";
import { ChevronDown, ArrowRight } from "lucide-vue-next";
import { cn } from "@/lib/utils";

defineOptions({ inheritAttrs: false });

type Option = {
    value: string | number | boolean;
    label: string;
    description?: string | null;
};

const props = withDefaults(
    defineProps<{
        defaultValue?: string | number | boolean | null | undefined;
        modelValue?: string | number | boolean | null | undefined;
        options: Option[];
        placeholder?: string;
        limit?: number;
        disabled?: boolean;
        showCreate?: boolean;
        showCreateEdit?: boolean;
        showEdit?: boolean;
        clearOnEmpty?: boolean;
        class?: HTMLAttributes["class"];
        inputClass?: HTMLAttributes["class"];
    }>(),
    {
        placeholder: "Buscar...",
        limit: 5,
        disabled: false,
        showCreate: false,
        showCreateEdit: false,
        showEdit: false,
        clearOnEmpty: false,
    },
);

const emits = defineEmits<{
    (
        e: "update:modelValue",
        payload: string | number | boolean | null | undefined,
    ): void;
    (e: "create", payload: string): void;
    (e: "create-edit", payload: string): void;
    (e: "edit", payload: string | number | boolean): void;
    (e: "search", payload: string): void;
    (e: "open"): void;
    (e: "close"): void;
}>();

const modelValue = useVModel(props, "modelValue", emits, {
    passive: true,
    defaultValue: props.defaultValue,
});

const attrs = useAttrs();
const rootRef = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);
const panelRef = ref<HTMLElement | null>(null);
const isOpen = ref(false);
const query = ref("");
const panelStyle = ref<Record<string, string>>({});

const normalizedQuery = computed(() => query.value.trim().toLowerCase());
const filtered = computed(() => {
    const q = normalizedQuery.value;
    if (q === "") return props.options;
    return props.options.filter((o) => o.label.toLowerCase().includes(q));
});
const limited = computed(() => filtered.value.slice(0, props.limit));
const hasResults = computed(() => limited.value.length > 0);

const hasExactMatch = computed(() => {
    const q = normalizedQuery.value;
    if (q === "") return false;
    return props.options.some((o) => o.label.toLowerCase() === q);
});

const hasSelection = computed(() =>
    modelValue.value !== null && modelValue.value !== undefined && modelValue.value !== "",
);

const selectedLabel = computed(() => {
    if (modelValue.value === null || modelValue.value === undefined) return "";
    const found = props.options.find((o) => o.value === modelValue.value);
    return found?.label ?? "";
});

const open = async () => {
    if (props.disabled) return;
    if (isOpen.value) return;
    isOpen.value = true;
    emits("open");
    await nextTick();
    updatePosition();
};

const close = () => {
    if (!isOpen.value) return;
    isOpen.value = false;
    emits("close");
    if (props.clearOnEmpty && query.value.trim() === "") return;
    query.value = selectedLabel.value;
};

const selectOption = (opt: Option) => {
    modelValue.value = opt.value;
    query.value = opt.label;
    close();
};

// ─── Keyboard navigation ─────────────────────────────────────────────────────
const highlightedIndex = ref(-1);

watch(
    () => limited.value,
    () => { highlightedIndex.value = -1; },
);

const onKeydown = (e: KeyboardEvent) => {
    if (!isOpen.value) {
        if (e.key === "ArrowDown" || e.key === "ArrowUp") {
            e.preventDefault();
            open();
        }
        return;
    }

    switch (e.key) {
        case "ArrowDown":
            e.preventDefault();
            highlightedIndex.value = Math.min(highlightedIndex.value + 1, limited.value.length - 1);
            break;
        case "ArrowUp":
            e.preventDefault();
            highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0);
            break;
        case "Enter":
            e.preventDefault();
            if (highlightedIndex.value >= 0 && highlightedIndex.value < limited.value.length) {
                selectOption(limited.value[highlightedIndex.value]!);
            }
            break;
        case "Escape":
            e.preventDefault();
            close();
            break;
        case "Tab":
            close();
            break;
    }
};

const handleCreate = () => {
    const q = query.value.trim();
    close();
    emits("create", q);
};

const handleCreateEdit = () => {
    const q = query.value.trim();
    close();
    emits("create-edit", q);
};

const handleEdit = () => {
    if (modelValue.value == null || modelValue.value === "") return;
    close();
    emits("edit", modelValue.value);
};

const updatePosition = async () => {
    const el = inputRef.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const width = rect.width;
    let left = rect.left;
    let top = rect.bottom + 6;

    panelStyle.value = {
        position: "fixed",
        left: `${left}px`,
        top: `${top}px`,
        width: `${width}px`,
    };

    await nextTick();
    const panel = panelRef.value;
    if (!panel) return;

    const panelRect = panel.getBoundingClientRect();
    if (
        panelRect.bottom > window.innerHeight &&
        rect.top > panelRect.height + 6
    ) {
        top = rect.top - panelRect.height - 6;
        panelStyle.value = {
            position: "fixed",
            left: `${left}px`,
            top: `${top}px`,
            width: `${width}px`,
        };
    }
};

const onGlobalPointerDown = (e: PointerEvent) => {
    const root = rootRef.value;
    const panel = panelRef.value;
    if (!root) return;
    const target = e.target as Node | null;
    if (target && root.contains(target)) return;
    if (target && panel && panel.contains(target)) return;
    close();
};

const onWindowChange = () => {
    if (!isOpen.value) return;
    updatePosition();
};

watch(
    selectedLabel,
    (newLabel) => {
        if (!isOpen.value) query.value = newLabel;
    },
    { immediate: true },
);

watch(
    () => [isOpen.value, query.value] as const,
    () => {
        if (!isOpen.value) return;
        updatePosition();
    },
);

watch(
    () => query.value,
    (q) => {
        if (!isOpen.value) return;
        if (
            props.clearOnEmpty &&
            q.trim() === "" &&
            modelValue.value !== undefined &&
            modelValue.value !== null
        ) {
            modelValue.value = undefined;
        }
        emits("search", q);
    },
);

onMounted(() => {
    document.addEventListener("pointerdown", onGlobalPointerDown, {
        capture: true,
    });
    window.addEventListener("scroll", onWindowChange, { capture: true });
    window.addEventListener("resize", onWindowChange);
});

onBeforeUnmount(() => {
    document.removeEventListener("pointerdown", onGlobalPointerDown, {
        capture: true,
    } as any);
    window.removeEventListener("scroll", onWindowChange, {
        capture: true,
    } as any);
    window.removeEventListener("resize", onWindowChange);
});
</script>

<template>
    <div ref="rootRef" :class="cn('relative', props.class)">
        <div class="relative">
            <input
                ref="inputRef"
                v-model="query"
                v-bind="attrs"
                :disabled="disabled"
                :placeholder="placeholder"
                autocomplete="off"
                :class="
                    cn(
                        'flex h-10 w-full bg-transparent px-0 py-2 pr-6 text-sm text-foreground placeholder:text-muted-foreground border-b border-transparent transition-colors hover:border-input focus:border-ring focus:outline-none disabled:cursor-not-allowed disabled:opacity-50',
                        props.inputClass,
                    )
                "
                @focus="open"
                @click="open"
                @keydown="onKeydown"
            />
            <button
                v-if="showEdit && hasSelection && !isOpen"
                type="button"
                class="absolute right-0 top-1/2 -translate-y-1/2 p-0.5 rounded text-muted-foreground hover:text-primary transition-colors"
                tabindex="-1"
                @mousedown.prevent.stop
                @click.stop="handleEdit"
            >
                <ArrowRight class="h-4 w-4" />
            </button>
            <ChevronDown
                v-else
                class="pointer-events-none absolute right-0 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
            />
        </div>

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="panelRef"
                class="z-[9999] overflow-hidden rounded-none border bg-popover text-popover-foreground shadow-md pointer-events-auto"
                :style="panelStyle"
            >
                <div class="p-1">
                    <!-- Create button (top) -->
                    <template v-if="showCreate && !hasExactMatch">
                        <button
                            type="button"
                            class="w-full rounded-none px-2 py-2 text-left text-xs font-medium text-primary hover:bg-accent focus:bg-accent focus:outline-none"
                            @mousedown.prevent
                            @click="handleCreate"
                        >
                            + Crear
                            <span v-if="query.trim()" class="font-normal text-muted-foreground">
                                "{{ query.trim() }}"
                            </span>
                            <span v-else class="font-normal text-muted-foreground">
                                nuevo
                            </span>
                        </button>
                        <div class="my-1 h-px bg-border" />
                    </template>

                    <!-- Options -->
                    <div
                        v-if="!hasResults"
                        class="px-2 py-2 text-sm text-muted-foreground"
                    >
                        {{
                            query.trim() === ""
                                ? "No hay opciones"
                                : "Sin resultados"
                        }}
                    </div>

                    <button
                        v-for="(opt, idx) in limited"
                        :key="String(opt.value)"
                        type="button"
                        :class="[
                            'w-full rounded-none px-2 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none',
                            idx === highlightedIndex && 'bg-accent text-accent-foreground',
                        ]"
                        @mousedown.prevent
                        @click="selectOption(opt)"
                        @mouseenter="highlightedIndex = idx"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate">{{ opt.label }}</span>
                        </div>
                        <div
                            v-if="opt.description"
                            class="mt-0.5 truncate text-xs text-muted-foreground"
                        >
                            {{ opt.description }}
                        </div>
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
