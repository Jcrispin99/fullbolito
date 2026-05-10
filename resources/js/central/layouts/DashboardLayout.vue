<script setup lang="ts">
import AppSidebar from "@/components/AppSidebar.vue"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Separator } from "@/components/ui/separator"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar"

defineProps<{
  title?: string
  description?: string
  breadcrumbs?: Array<{ label: string, href?: string }>
}>()
</script>

<template>
  <SidebarProvider>
    <AppSidebar />
    <SidebarInset class="bg-gradient-to-b from-background via-background to-muted/40">
      <header class="sticky top-0 z-30 flex shrink-0 flex-col gap-2 border-b border-border/60 bg-background/80 backdrop-blur-md transition-[width,height] ease-linear">
        <div class="flex h-14 items-center gap-2 px-4">
          <SidebarTrigger class="-ml-1" />
          <Separator orientation="vertical" class="mr-2 data-[orientation=vertical]:h-4" />
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem>
                <BreadcrumbLink href="/dashboard">Dashboard</BreadcrumbLink>
              </BreadcrumbItem>
              <template v-if="breadcrumbs">
                <BreadcrumbSeparator />
                <template v-for="(crumb, index) in breadcrumbs" :key="index">
                  <BreadcrumbItem>
                    <BreadcrumbLink v-if="crumb.href" :href="crumb.href">{{ crumb.label }}</BreadcrumbLink>
                    <BreadcrumbPage v-else>{{ crumb.label }}</BreadcrumbPage>
                  </BreadcrumbItem>
                  <BreadcrumbSeparator v-if="index < breadcrumbs.length - 1" />
                </template>
              </template>
            </BreadcrumbList>
          </Breadcrumb>
        </div>

        <div
          v-if="title || $slots.actions"
          class="flex flex-col gap-3 px-6 pb-4 sm:flex-row sm:items-end sm:justify-between"
        >
          <div v-if="title" class="space-y-0.5">
            <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
            <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
          </div>
          <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
          </div>
        </div>
      </header>

      <div class="flex flex-1 flex-col gap-4 p-6 pt-4">
        <slot />
      </div>
    </SidebarInset>
  </SidebarProvider>
</template>
