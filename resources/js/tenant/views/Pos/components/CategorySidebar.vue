<script setup lang="ts">
import { computed } from 'vue'
import { usePosStore } from '@tenant/stores/pos'
import { cn } from '@/lib/utils'
import { LayoutGrid } from 'lucide-vue-next'

const store = usePosStore()

const allCount = computed(() => store.products.filter((p) => p.is_pos_visible).length)
</script>

<template>
    <div class="flex flex-col gap-1 py-2 min-w-[140px]">
        <button
            :class="cn(
                'flex items-center gap-2 px-3 py-2.5 rounded-md text-sm font-medium text-left transition-colors',
                store.selectedCategoryId === null
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
            )"
            @click="store.setCategory(null)"
        >
            <LayoutGrid class="h-4 w-4 shrink-0" />
            <span class="truncate">Todas</span>
            <span class="ml-auto text-xs opacity-70">{{ allCount }}</span>
        </button>

        <button
            v-for="cat in store.categories"
            :key="cat.id"
            :class="cn(
                'flex items-center gap-2 px-3 py-2.5 rounded-md text-sm font-medium text-left transition-colors',
                store.selectedCategoryId === cat.id
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
            )"
            @click="store.setCategory(cat.id)"
        >
            <span class="truncate">{{ cat.name }}</span>
            <span class="ml-auto text-xs opacity-70">
                {{ store.products.filter((p) => p.category_id === cat.id && p.is_pos_visible).length }}
            </span>
        </button>
    </div>
</template>
