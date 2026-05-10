<script setup lang="ts">
import { ref, watch, onMounted, nextTick } from 'vue'
import { usePosStore } from '@tenant/stores/pos'
import { Search } from 'lucide-vue-next'

const store = usePosStore()
const inputRef = ref<HTMLInputElement | null>(null)
const query = ref('')

watch(query, (val) => {
    store.setSearch(val)
})

const focus = () => {
    inputRef.value?.focus()
}

onMounted(async () => {
    await nextTick()
    focus()
})

defineExpose({ focus })
</script>

<template>
    <div class="relative">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
        <input
            ref="inputRef"
            v-model="query"
            type="text"
            placeholder="Buscar producto o escanear código..."
            class="w-full h-10 pl-10 pr-4 bg-background border border-input rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent placeholder:text-muted-foreground"
        />
    </div>
</template>
