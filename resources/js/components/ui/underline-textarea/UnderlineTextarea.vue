<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { useVModel } from "@vueuse/core";
import { cn } from "@/lib/utils";

const props = defineProps<{
    defaultValue?: string | number | null | undefined;
    modelValue?: string | number | null | undefined;
    class?: HTMLAttributes["class"];
}>();

const emits = defineEmits<{
    (e: "update:modelValue", payload: string | number | null | undefined): void;
}>();

const modelValue = useVModel(props, "modelValue", emits, {
    passive: true,
    defaultValue: props.defaultValue,
});
</script>

<template>
    <textarea
        v-model="modelValue"
        :class="
            cn(
                'flex min-h-[80px] w-full bg-transparent px-0 py-2 text-sm text-foreground placeholder:text-muted-foreground border-b border-transparent transition-colors hover:border-input focus:border-ring focus:outline-none disabled:cursor-not-allowed disabled:opacity-50',
                props.class,
            )
        "
    />
</template>
