<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { toast } from "vue-sonner";
import {
    useImportExportStore,
    type ExportFormat,
    type ImportExportFieldDefinition,
    type ImportExportTemplate,
} from "@tenant/stores/importExport";
import {
    DialogRoot,
    DialogPortal,
    DialogOverlay,
    DialogContent,
    DialogTitle,
    DialogDescription,
    DialogClose,
} from "radix-vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    Loader2,
    Download,
    Search,
    ChevronDown,
    Trash2,
    Users,
    User,
    Save,
    X,
    Plus,
    ChevronUp,
    ChevronsRight,
    ChevronsLeft,
    GripVertical,
} from "lucide-vue-next";

const props = withDefaults(
    defineProps<{
        open: boolean;
        resource: string;
        filters?: Record<string, unknown>;
        defaultColumns?: string[];
        title?: string;
    }>(),
    {
        filters: () => ({}),
        defaultColumns: () => [],
        title: "Exportar",
    },
);

const emit = defineEmits<{
    (e: "update:open", value: boolean): void;
    (e: "exported"): void;
}>();

const store = useImportExportStore();
const { isLoading, templates } = storeToRefs(store);

const search = ref("");
const selectedKeys = ref<string[]>([]);
const filename = ref("");
const format = ref<ExportFormat>("xlsx");
const isExporting = ref(false);
const fields = ref<ImportExportFieldDefinition[]>([]);
const activeTemplateId = ref<number | null>(null);

const templateName = ref("");
const newTemplateShared = ref(false);
const isSavingTemplate = ref(false);

const fieldsByKey = computed(() => {
    const map: Record<string, ImportExportFieldDefinition> = {};
    for (const f of fields.value) map[f.key] = f;
    return map;
});

const exportableFields = computed(() =>
    fields.value.filter((f) => f.exportable),
);

const selectedSet = computed(() => new Set(selectedKeys.value));

const availableFields = computed(() => {
    const q = search.value.trim().toLowerCase();
    return exportableFields.value.filter((f) => {
        if (selectedSet.value.has(f.key)) return false;
        if (!q) return true;
        return (
            f.label.toLowerCase().includes(q) ||
            f.key.toLowerCase().includes(q)
        );
    });
});

const selectedFields = computed(() =>
    selectedKeys.value
        .map((k) => fieldsByKey.value[k])
        .filter((f): f is ImportExportFieldDefinition => Boolean(f)),
);

const activeTemplate = computed<ImportExportTemplate | null>(() => {
    if (activeTemplateId.value === null) return null;
    return (
        templates.value.find((t) => t.id === activeTemplateId.value) ?? null
    );
});

const matchingTemplate = computed<ImportExportTemplate | null>(() => {
    const name = templateName.value.trim().toLowerCase();
    if (!name) return null;
    return (
        templates.value.find((t) => t.name.toLowerCase() === name) ?? null
    );
});

const canSaveAsNew = computed(
    () =>
        templateName.value.trim().length > 0 &&
        matchingTemplate.value === null &&
        selectedKeys.value.length > 0,
);

const clearActiveTemplate = () => {
    activeTemplateId.value = null;
};

const addField = (key: string) => {
    if (!selectedSet.value.has(key)) {
        selectedKeys.value = [...selectedKeys.value, key];
        clearActiveTemplate();
    }
};

const removeField = (key: string) => {
    selectedKeys.value = selectedKeys.value.filter((k) => k !== key);
    clearActiveTemplate();
};

const moveField = (key: string, dir: -1 | 1) => {
    const idx = selectedKeys.value.indexOf(key);
    const newIdx = idx + dir;
    if (idx < 0 || newIdx < 0 || newIdx >= selectedKeys.value.length) return;
    const next = [...selectedKeys.value];
    const a = next[idx] as string;
    const b = next[newIdx] as string;
    next[idx] = b;
    next[newIdx] = a;
    selectedKeys.value = next;
    clearActiveTemplate();
};

const addAllVisible = () => {
    const toAdd = availableFields.value.map((f) => f.key);
    if (toAdd.length === 0) return;
    selectedKeys.value = [...selectedKeys.value, ...toAdd];
    clearActiveTemplate();
};

const removeAll = () => {
    selectedKeys.value = [];
    clearActiveTemplate();
};

const applyDefaults = () => {
    const initial = props.defaultColumns.length
        ? props.defaultColumns
        : exportableFields.value.map((f) => f.key);
    selectedKeys.value = [...initial];
    activeTemplateId.value = null;
    templateName.value = "";
};

const applyTemplate = (template: ImportExportTemplate) => {
    selectedKeys.value = [...template.columns];
    activeTemplateId.value = template.id;
    templateName.value = template.name;
    const tplFormat = template.default_options?.format as
        | ExportFormat
        | undefined;
    if (tplFormat === "csv" || tplFormat === "xlsx") {
        format.value = tplFormat;
    }
};

const clearTemplateName = () => {
    templateName.value = "";
    activeTemplateId.value = null;
};

const loadSchema = async () => {
    try {
        const schema = await store.fetchSchema(props.resource);
        fields.value = schema.fields;
        applyDefaults();
    } catch (err: any) {
        toast.error("No se pudieron cargar los campos", {
            description: err?.message || "Intenta de nuevo.",
        });
    }
};

const loadTemplates = async () => {
    try {
        await store.fetchTemplates(props.resource, "export");
    } catch (err: any) {
        console.warn("Failed to load templates", err);
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            search.value = "";
            filename.value = "";
            templateName.value = "";
            newTemplateShared.value = false;
            void loadSchema();
            void loadTemplates();
        }
    },
    { immediate: true },
);

const close = () => emit("update:open", false);

const handleSaveTemplate = async () => {
    const name = templateName.value.trim();
    if (!name) {
        toast.warning("Escribe un nombre para la plantilla.");
        return;
    }
    if (selectedKeys.value.length === 0) {
        toast.warning("Selecciona al menos una columna antes de guardar.");
        return;
    }
    if (matchingTemplate.value) {
        toast.warning("Ya existe una plantilla con ese nombre.");
        return;
    }

    isSavingTemplate.value = true;
    try {
        const created = await store.saveTemplate({
            name,
            resource: props.resource,
            direction: "export",
            columns: [...selectedKeys.value],
            default_options: { format: format.value },
            is_shared: newTemplateShared.value,
        });
        activeTemplateId.value = created.id;
        templateName.value = created.name;
        newTemplateShared.value = false;
        toast.success("Plantilla guardada", {
            description: created.is_shared
                ? "Visible para todos los usuarios."
                : "Privada para ti.",
        });
    } catch (err: any) {
        toast.error("No se pudo guardar la plantilla", {
            description:
                err?.response?.data?.message ||
                err?.message ||
                "Intenta de nuevo.",
        });
    } finally {
        isSavingTemplate.value = false;
    }
};

const handleDeleteTemplate = async (template: ImportExportTemplate) => {
    if (!confirm(`¿Eliminar plantilla "${template.name}"?`)) return;
    try {
        await store.deleteTemplate(template.id);
        if (activeTemplateId.value === template.id) {
            activeTemplateId.value = null;
            templateName.value = "";
        }
        toast.success("Plantilla eliminada");
    } catch (err: any) {
        toast.error("No se pudo eliminar", {
            description:
                err?.response?.data?.message ||
                err?.message ||
                "Intenta de nuevo.",
        });
    }
};

const handleExport = async () => {
    if (selectedKeys.value.length === 0) {
        toast.warning("Selecciona al menos una columna para exportar.");
        return;
    }

    isExporting.value = true;
    try {
        await store.exportFile({
            resource: props.resource,
            columns: [...selectedKeys.value],
            filters: props.filters,
            filename: filename.value || undefined,
            format: format.value,
        });
        toast.success("Exportación lista", {
            description: `Tu archivo ${format.value.toUpperCase()} se está descargando.`,
        });
        emit("exported");
        close();
    } catch (err: any) {
        toast.error("Error al exportar", {
            description:
                err?.response?.data?.message ||
                err?.message ||
                "Intenta de nuevo.",
        });
    } finally {
        isExporting.value = false;
    }
};
</script>

<template>
    <DialogRoot :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-black/60 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <DialogContent
                class="fixed left-1/2 top-1/2 z-50 -translate-x-1/2 -translate-y-1/2 w-[95vw] max-w-5xl h-[85vh] bg-background rounded-lg shadow-lg flex flex-col data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            >
                <!-- Header -->
                <div
                    class="flex items-start justify-between p-5 border-b shrink-0"
                >
                    <div>
                        <DialogTitle class="text-lg font-semibold">
                            {{ title }}
                        </DialogTitle>
                        <DialogDescription class="text-sm text-muted-foreground mt-1">
                            Selecciona las columnas a exportar. Los filtros de
                            la vista actual se aplican.
                        </DialogDescription>
                    </div>
                    <DialogClose
                        class="rounded-sm opacity-70 hover:opacity-100 focus:outline-none"
                    >
                        <X class="h-5 w-5" />
                        <span class="sr-only">Cerrar</span>
                    </DialogClose>
                </div>

                <!-- Two-column body -->
                <div class="flex-1 grid grid-cols-2 gap-4 p-5 min-h-0">
                    <!-- LEFT: Available -->
                    <div
                        class="flex flex-col border rounded-md min-h-0 overflow-hidden"
                    >
                        <div class="p-3 border-b space-y-2 shrink-0">
                            <div
                                class="flex items-center justify-between"
                            >
                                <h3 class="text-sm font-semibold">
                                    Disponibles
                                </h3>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 text-xs"
                                    @click="addAllVisible"
                                    :disabled="availableFields.length === 0"
                                >
                                    <ChevronsRight
                                        class="h-3 w-3 mr-1"
                                    />
                                    Añadir todos
                                </Button>
                            </div>
                            <div class="relative">
                                <Search
                                    class="absolute left-2 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                                />
                                <Input
                                    v-model="search"
                                    placeholder="Buscar campos..."
                                    class="pl-8 h-8"
                                />
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto p-2 min-h-0">
                            <div
                                v-if="isLoading && fields.length === 0"
                                class="flex items-center justify-center py-8 text-muted-foreground"
                            >
                                <Loader2
                                    class="h-5 w-5 animate-spin mr-2"
                                />
                                Cargando campos...
                            </div>
                            <div
                                v-else-if="availableFields.length === 0"
                                class="text-center text-sm text-muted-foreground py-8 px-3"
                            >
                                <span v-if="search">
                                    Sin resultados para "{{ search }}".
                                </span>
                                <span v-else>
                                    Todos los campos están seleccionados.
                                </span>
                            </div>
                            <button
                                v-for="field in availableFields"
                                :key="field.key"
                                type="button"
                                class="w-full text-left flex items-start gap-2 px-2 py-1.5 rounded hover:bg-muted transition-colors group"
                                @click="addField(field.key)"
                            >
                                <Plus
                                    class="h-3.5 w-3.5 mt-1 text-muted-foreground group-hover:text-primary shrink-0"
                                />
                                <div class="flex-1 min-w-0">
                                    <div
                                        class="text-sm font-medium truncate"
                                    >
                                        {{ field.label }}
                                        <span
                                            v-if="field.required"
                                            class="text-xs text-destructive ml-1"
                                            >*</span
                                        >
                                    </div>
                                    <div
                                        class="text-[10px] text-muted-foreground font-mono truncate"
                                    >
                                        {{ field.key }} · {{ field.type }}
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- RIGHT: Selected -->
                    <div
                        class="flex flex-col border rounded-md min-h-0 overflow-hidden"
                    >
                        <div
                            class="p-3 border-b shrink-0 flex items-center justify-between"
                        >
                            <h3 class="text-sm font-semibold">
                                Seleccionadas
                                <span
                                    class="text-xs text-muted-foreground font-normal ml-1"
                                    >({{ selectedKeys.length }})</span
                                >
                            </h3>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 text-xs"
                                @click="removeAll"
                                :disabled="selectedKeys.length === 0"
                            >
                                <ChevronsLeft class="h-3 w-3 mr-1" />
                                Quitar todas
                            </Button>
                        </div>

                        <div class="flex-1 overflow-y-auto p-2 min-h-0">
                            <div
                                v-if="selectedFields.length === 0"
                                class="text-center text-sm text-muted-foreground py-8 px-3"
                            >
                                Aún no has seleccionado columnas. Haz click
                                en los campos de la izquierda.
                            </div>
                            <div
                                v-for="(field, idx) in selectedFields"
                                :key="field.key"
                                class="flex items-center gap-1 px-2 py-1.5 rounded hover:bg-muted/50 group"
                            >
                                <GripVertical
                                    class="h-3.5 w-3.5 text-muted-foreground/40 shrink-0"
                                />
                                <div class="flex-1 min-w-0">
                                    <div
                                        class="text-sm font-medium truncate"
                                    >
                                        {{ field.label }}
                                    </div>
                                    <div
                                        class="text-[10px] text-muted-foreground font-mono truncate"
                                    >
                                        {{ field.key }}
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="h-6 w-6 rounded hover:bg-muted flex items-center justify-center opacity-50 hover:opacity-100 disabled:opacity-20 disabled:cursor-not-allowed"
                                    @click="moveField(field.key, -1)"
                                    :disabled="idx === 0"
                                    aria-label="Subir"
                                >
                                    <ChevronUp class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="h-6 w-6 rounded hover:bg-muted flex items-center justify-center opacity-50 hover:opacity-100 disabled:opacity-20 disabled:cursor-not-allowed"
                                    @click="moveField(field.key, 1)"
                                    :disabled="
                                        idx === selectedKeys.length - 1
                                    "
                                    aria-label="Bajar"
                                >
                                    <ChevronUp
                                        class="h-3.5 w-3.5 rotate-180"
                                    />
                                </button>
                                <button
                                    type="button"
                                    class="h-6 w-6 rounded hover:bg-destructive/10 hover:text-destructive flex items-center justify-center opacity-50 hover:opacity-100"
                                    @click="removeField(field.key)"
                                    aria-label="Quitar"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom config row: template + format + filename -->
                <div
                    class="border-t px-5 py-4 grid grid-cols-1 md:grid-cols-3 gap-4 shrink-0 bg-muted/20"
                >
                    <!-- Template -->
                    <div class="space-y-1.5">
                        <label
                            class="text-xs font-medium text-muted-foreground"
                            >Plantilla</label
                        >
                        <div class="flex gap-1.5">
                            <div class="relative flex-1">
                                <Input
                                    v-model="templateName"
                                    placeholder="Nombre o nueva..."
                                    class="pr-7 h-9"
                                    @keyup.enter="
                                        canSaveAsNew && handleSaveTemplate()
                                    "
                                />
                                <button
                                    v-if="templateName"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    @click="clearTemplateName"
                                    aria-label="Limpiar"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        type="button"
                                        class="h-9 px-2"
                                        aria-label="Lista de plantillas"
                                    >
                                        <ChevronDown class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="start"
                                    class="w-[260px]"
                                >
                                    <DropdownMenuLabel
                                        >Plantillas guardadas</DropdownMenuLabel
                                    >
                                    <DropdownMenuItem
                                        @click="applyDefaults"
                                    >
                                        <span
                                            class="text-muted-foreground italic"
                                            >Selección por defecto</span
                                        >
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator
                                        v-if="templates.length > 0"
                                    />
                                    <DropdownMenuItem
                                        v-for="t in templates"
                                        :key="t.id"
                                        class="flex items-start justify-between gap-2"
                                        @click="applyTemplate(t)"
                                    >
                                        <div class="flex-1 min-w-0">
                                            <div
                                                class="flex items-center gap-1"
                                            >
                                                <Users
                                                    v-if="t.is_shared"
                                                    class="h-3 w-3 text-muted-foreground shrink-0"
                                                />
                                                <User
                                                    v-else
                                                    class="h-3 w-3 text-muted-foreground shrink-0"
                                                />
                                                <span
                                                    class="text-sm truncate"
                                                    >{{ t.name }}</span
                                                >
                                            </div>
                                            <div
                                                v-if="!t.is_mine"
                                                class="text-[10px] text-muted-foreground"
                                            >
                                                por
                                                {{
                                                    t.owner_name || "otro"
                                                }}
                                            </div>
                                        </div>
                                        <button
                                            v-if="t.can_delete"
                                            class="text-destructive hover:opacity-70"
                                            @click.stop="
                                                handleDeleteTemplate(t)
                                            "
                                            type="button"
                                            aria-label="Eliminar"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="templates.length === 0"
                                        disabled
                                    >
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >Aún no hay plantillas</span
                                        >
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <div
                            v-if="activeTemplate"
                            class="flex items-center gap-1 text-[10px] text-muted-foreground"
                        >
                            <Users
                                v-if="activeTemplate.is_shared"
                                class="h-3 w-3"
                            />
                            <User v-else class="h-3 w-3" />
                            <span>
                                Plantilla activa
                                {{
                                    activeTemplate.is_shared
                                        ? "(compartida)"
                                        : "(privada)"
                                }}
                            </span>
                        </div>
                        <div
                            v-else-if="canSaveAsNew"
                            class="space-y-1.5 pt-1"
                        >
                            <label
                                class="flex items-center gap-1.5 cursor-pointer text-[11px]"
                            >
                                <Checkbox
                                    :checked="newTemplateShared"
                                    @update:checked="
                                        (v: boolean) =>
                                            (newTemplateShared = v)
                                    "
                                />
                                <span>Compartir con otros usuarios</span>
                            </label>
                            <Button
                                size="sm"
                                class="w-full h-7 text-xs"
                                @click="handleSaveTemplate"
                                :disabled="isSavingTemplate"
                            >
                                <Loader2
                                    v-if="isSavingTemplate"
                                    class="h-3 w-3 mr-1 animate-spin"
                                />
                                <Save v-else class="h-3 w-3 mr-1" />
                                Guardar plantilla
                            </Button>
                        </div>
                    </div>

                    <!-- Format -->
                    <div class="space-y-1.5">
                        <label
                            class="text-xs font-medium text-muted-foreground"
                            >Formato</label
                        >
                        <div class="flex gap-1.5">
                            <Button
                                type="button"
                                :variant="
                                    format === 'xlsx' ? 'default' : 'outline'
                                "
                                size="sm"
                                class="flex-1 h-9"
                                @click="format = 'xlsx'"
                            >
                                Excel (.xlsx)
                            </Button>
                            <Button
                                type="button"
                                :variant="
                                    format === 'csv' ? 'default' : 'outline'
                                "
                                size="sm"
                                class="flex-1 h-9"
                                @click="format = 'csv'"
                            >
                                CSV (.csv)
                            </Button>
                        </div>
                    </div>

                    <!-- Filename -->
                    <div class="space-y-1.5">
                        <label
                            class="text-xs font-medium text-muted-foreground"
                            >Nombre de archivo</label
                        >
                        <Input
                            v-model="filename"
                            :placeholder="`${resource}-${new Date().toISOString().slice(0, 10)}.${format}`"
                            class="h-9"
                        />
                    </div>
                </div>

                <!-- Footer -->
                <div
                    class="border-t px-5 py-3 flex items-center justify-end gap-2 shrink-0"
                >
                    <Button
                        variant="outline"
                        @click="close"
                        :disabled="isExporting"
                    >
                        Cancelar
                    </Button>
                    <Button
                        @click="handleExport"
                        :disabled="
                            isExporting || selectedKeys.length === 0
                        "
                    >
                        <Loader2
                            v-if="isExporting"
                            class="h-4 w-4 mr-2 animate-spin"
                        />
                        <Download v-else class="h-4 w-4 mr-2" />
                        Exportar {{ selectedKeys.length }} columnas
                    </Button>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
