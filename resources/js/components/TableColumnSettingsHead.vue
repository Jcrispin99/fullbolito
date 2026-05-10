<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { Settings2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { TableHead } from '@/components/ui/table'

type Visibility = Record<string, boolean>

const props = withDefaults(defineProps<{
  modelValue: Visibility
  columns: { key: string, label: string }[]
  storageKey?: string
  menuLabel?: string
  ariaLabel?: string
}>(), {
  menuLabel: 'Columns',
  ariaLabel: 'Column settings',
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: Visibility): void
}>()

const visibleCount = computed(() => Object.values(props.modelValue).filter(Boolean).length)

const setVisible = (key: string, checked: boolean) => {
  const next = { ...props.modelValue, [key]: checked }
  if (Object.values(next).every(v => !v)) return
  emit('update:modelValue', next)
}

const save = (value: Visibility) => {
  if (!props.storageKey) return
  if (typeof window === 'undefined') return
  localStorage.setItem(props.storageKey, JSON.stringify(value))
}

const load = () => {
  if (!props.storageKey) return
  if (typeof window === 'undefined') return
  const raw = localStorage.getItem(props.storageKey)
  if (!raw) return
  try {
    const parsed = JSON.parse(raw) as Visibility
    const next = { ...props.modelValue, ...parsed }
    if (Object.values(next).every(v => !v)) return
    emit('update:modelValue', next)
  } catch {
  }
}

onMounted(() => {
  load()
})

watch(
  () => props.modelValue,
  (value) => {
    if (visibleCount.value === 0) return
    save(value)
  },
  { deep: true }
)
</script>

<template>
  <TableHead class="w-[52px] text-right">
    <DropdownMenu>
      <DropdownMenuTrigger as-child>
        <Button variant="outline" size="icon-sm" class="h-8 w-8" :aria-label="ariaLabel">
          <Settings2 class="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" class="w-[200px]">
        <DropdownMenuLabel>{{ menuLabel }}</DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuCheckboxItem
          v-for="col in columns"
          :key="col.key"
          :checked="modelValue[col.key]"
          @update:checked="(checked) => setVisible(col.key, checked)"
        >
          {{ col.label }}
        </DropdownMenuCheckboxItem>
      </DropdownMenuContent>
    </DropdownMenu>
  </TableHead>
</template>

