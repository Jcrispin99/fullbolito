<script setup lang="ts">
import type { LucideIcon } from "lucide-vue-next"
import { ChevronRight } from "lucide-vue-next"
import { useRoute } from "vue-router"
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from "@/components/ui/sidebar"

const route = useRoute()

defineProps<{
  items: {
    title: string
    url: string
    icon?: LucideIcon
    isActive?: boolean
    items?: {
      title: string
      url: string
    }[]
  }[]
}>()

function isSubItemActive(url: string): boolean {
  return route.path === url || route.path.startsWith(url + "/")
}

function isGroupActive(items?: { title: string; url: string }[]): boolean {
  return items?.some((i) => isSubItemActive(i.url)) ?? false
}
</script>

<template>
  <SidebarGroup>
    <SidebarGroupLabel>Platform</SidebarGroupLabel>
    <SidebarMenu>
      <Collapsible
        v-for="item in items"
        :key="item.title"
        as-child
        :default-open="item.isActive || isGroupActive(item.items)"
        class="group/collapsible"
      >
        <SidebarMenuItem>
          <CollapsibleTrigger as-child>
            <SidebarMenuButton
              :tooltip="item.title"
              :class="isGroupActive(item.items) ? 'text-sidebar-primary font-semibold' : ''"
            >
              <component
                :is="item.icon"
                v-if="item.icon"
                :class="isGroupActive(item.items) ? 'text-sidebar-primary' : ''"
              />
              <span>{{ item.title }}</span>
              <ChevronRight class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
            </SidebarMenuButton>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <SidebarMenuSub>
              <SidebarMenuSubItem v-for="subItem in item.items" :key="subItem.title">
                <SidebarMenuSubButton as-child>
                  <RouterLink
                    :to="subItem.url"
                    :class="[
                      'flex items-center gap-2 rounded-full px-3 py-1.5 text-sm transition-colors w-full',
                      isSubItemActive(subItem.url)
                        ? 'bg-sidebar-primary text-sidebar-primary-foreground shadow-sm'
                        : 'hover:bg-sidebar-accent/50 text-sidebar-foreground',
                    ]"
                  >
                    <span>{{ subItem.title }}</span>
                  </RouterLink>
                </SidebarMenuSubButton>
              </SidebarMenuSubItem>
            </SidebarMenuSub>
          </CollapsibleContent>
        </SidebarMenuItem>
      </Collapsible>
    </SidebarMenu>
  </SidebarGroup>
</template>
