<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import {
    useImportExportStore,
    type ImportJob,
} from "@tenant/stores/importExport";
import ExportDialog from "./ExportDialog.vue";
import ImportDialog from "./ImportDialog.vue";

const props = withDefaults(
    defineProps<{
        resource: string;
        filters?: Record<string, unknown>;
        defaultExportColumns?: string[];
        exportTitle?: string;
        importTitle?: string;
    }>(),
    {
        filters: () => ({}),
        defaultExportColumns: () => [],
        exportTitle: "Exportar",
        importTitle: "Importar",
    },
);

const emit = defineEmits<{
    (e: "exported"): void;
    (e: "imported", job: ImportJob): void;
}>();

const store = useImportExportStore();
const { schemas } = storeToRefs(store);

const exportOpen = ref(false);
const importOpen = ref(false);

const supportsImport = computed(
    () => schemas.value[props.resource]?.supports_import ?? false,
);

watch(
    () => props.resource,
    (r) => {
        void store.fetchSchema(r).catch(() => {
            /* swallow: dialogs will surface errors when opened */
        });
    },
    { immediate: true },
);

const openExport = () => {
    exportOpen.value = true;
};

const openImport = () => {
    importOpen.value = true;
};

defineExpose({
    openExport,
    openImport,
    supportsImport,
});
</script>

<template>
    <div>
        <ExportDialog
            v-model:open="exportOpen"
            :resource="resource"
            :filters="filters"
            :default-columns="defaultExportColumns"
            :title="exportTitle"
            @exported="emit('exported')"
        />
        <ImportDialog
            v-if="supportsImport"
            v-model:open="importOpen"
            :resource="resource"
            :title="importTitle"
            @imported="(job) => emit('imported', job)"
        />
    </div>
</template>
