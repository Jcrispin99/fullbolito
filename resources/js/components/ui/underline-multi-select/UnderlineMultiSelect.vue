<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { computed, ref, useAttrs, watch } from "vue";
import { useVModel } from "@vueuse/core";
import { Badge } from "@/components/ui/badge";
import { X } from "lucide-vue-next";
import { cn } from "@/lib/utils";

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        defaultValue?: string[] | undefined;
        modelValue?: string[] | undefined;
        placeholder?: string;
        disabled?: boolean;
        class?: HTMLAttributes["class"];
        inputClass?: HTMLAttributes["class"];
        badgeClass?: HTMLAttributes["class"];
    }>(),
    {
        placeholder: "Empiece a escribir...",
        disabled: false,
    },
);

const emits = defineEmits<{
    (e: "update:modelValue", payload: string[]): void;
}>();

const modelValue = useVModel(props, "modelValue", emits, {
    passive: true,
    defaultValue: props.defaultValue ?? [],
});

const attrs = useAttrs();
const inputValue = ref("");

const normalizedItems = computed(() =>
    (modelValue.value ?? []).map((v) => String(v)),
);

watch(
    () => modelValue.value,
    (v) => {
        if (!Array.isArray(v)) modelValue.value = [];
    },
    { immediate: true },
);

const addItem = () => {
    if (props.disabled) return;
    const raw = inputValue.value.trim();
    if (!raw) return;
    const current = normalizedItems.value;
    if (!current.includes(raw)) {
        modelValue.value = [...current, raw];
    }
    inputValue.value = "";
};

const removeItem = (item: string) => {
    if (props.disabled) return;
    modelValue.value = normalizedItems.value.filter((v) => v !== item);
};

const onKeydown = (e: KeyboardEvent) => {
    if (props.disabled) return;
    if (e.key === "Enter" || e.key === ",") {
        e.preventDefault();
        addItem();
        return;
    }
    if (e.key === "Backspace" && inputValue.value === "") {
        const items = normalizedItems.value;
        if (items.length === 0) return;
        modelValue.value = items.slice(0, -1);
    }
};
</script>

<template>
    <div :class="cn('w-full', props.class)">
        <div
            class="flex min-h-10 w-full flex-wrap items-center gap-2 bg-transparent px-0 py-2 text-sm text-foreground border-b border-transparent transition-colors hover:border-input focus-within:border-ring disabled:cursor-not-allowed disabled:opacity-50"
        >
            <Badge
                v-for="item in normalizedItems"
                :key="item"
                variant="secondary"
                :class="
                    cn(
                        'text-xs px-2 py-1 h-auto flex items-center pr-1 overflow-hidden group',
                        props.badgeClass,
                    )
                "
            >
                <span class="truncate max-w-[150px]">{{ item }}</span>
                <button
                    type="button"
                    class="ml-1 rounded-full text-muted-foreground hover:bg-muted hover:text-foreground focus:outline-none p-0.5"
                    :disabled="disabled"
                    @click="removeItem(item)"
                >
                    <X class="w-3 h-3" />
                </button>
            </Badge>

            <input
                v-model="inputValue"
                v-bind="attrs"
                :disabled="disabled"
                :placeholder="normalizedItems.length === 0 ? placeholder : ''"
                :class="
                    cn(
                        'min-w-[120px] flex-1 bg-transparent px-0 py-0 text-sm text-foreground placeholder:text-muted-foreground outline-none',
                        props.inputClass,
                    )
                "
                @keydown="onKeydown"
                @blur="addItem"
            >
        </div>
    </div>
</template>
