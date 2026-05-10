<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        tone?: "neutral" | "danger";
    }>(),
    {
        tone: "neutral",
    },
);
</script>

<template>
    <div
        class="pointer-events-none absolute right-[-64px] top-[28px] w-[220px] rotate-45 border px-2 py-1 text-center text-xs font-semibold tracking-wide"
        :class="
            tone === 'danger'
                ? 'border-destructive/40 bg-destructive/10 text-destructive'
                : 'border-border bg-muted text-foreground/80'
        "
    >
        {{ label }}
    </div>
</template>
