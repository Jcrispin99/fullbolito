<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { toast } from "vue-sonner";
import {
    useImportExportStore,
    type ImportExportFieldDefinition,
    type ImportJob,
    type ImportMode,
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
    Loader2,
    UploadCloud,
    FileSpreadsheet,
    Download,
    AlertTriangle,
    CheckCircle2,
    XCircle,
    ArrowLeft,
    ArrowRight,
    Play,
    X,
} from "lucide-vue-next";

type Step = "upload" | "configure" | "result";

const props = withDefaults(
    defineProps<{
        open: boolean;
        resource: string;
        title?: string;
    }>(),
    {
        title: "Importar",
    },
);

const emit = defineEmits<{
    (e: "update:open", value: boolean): void;
    (e: "imported", job: ImportJob): void;
}>();

const store = useImportExportStore();

const step = ref<Step>("upload");
const file = ref<File | null>(null);
const isDragging = ref(false);

const job = ref<ImportJob | null>(null);
const fields = ref<ImportExportFieldDefinition[]>([]);
const isUploading = ref(false);
const isRunning = ref(false);

const mapping = ref<Record<string, string | null>>({});
const mode = ref<ImportMode>("create");
const uniqueKey = ref<string>("document_number");
const stopOnError = ref(false);
const dryRun = ref(false);

const importableFields = computed(() =>
    fields.value.filter((f) => f.importable),
);

const uniqueKeyOptions = computed(() =>
    importableFields.value.filter((f) => f.unique || f.key === "id"),
);

const requiredKeys = computed(() =>
    importableFields.value.filter((f) => f.required).map((f) => f.key),
);

const mappedKeys = computed(() => {
    const set = new Set<string>();
    for (const v of Object.values(mapping.value)) {
        if (v) set.add(v);
    }
    return set;
});

const missingRequired = computed(() =>
    requiredKeys.value.filter((k) => !mappedKeys.value.has(k)),
);

const reset = () => {
    step.value = "upload";
    file.value = null;
    job.value = null;
    mapping.value = {};
    mode.value = "create";
    uniqueKey.value = "document_number";
    stopOnError.value = false;
    dryRun.value = false;
    isUploading.value = false;
    isRunning.value = false;
};

const loadSchema = async () => {
    try {
        const schema = await store.fetchSchema(props.resource);
        fields.value = schema.fields;
    } catch (err: any) {
        toast.error("No se pudieron cargar los campos", {
            description: err?.message,
        });
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            reset();
            void loadSchema();
        }
    },
    { immediate: true },
);

const close = () => emit("update:open", false);

const onFileSelected = (e: Event) => {
    const input = e.target as HTMLInputElement;
    file.value = input.files?.[0] ?? null;
};

const onDrop = (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = false;
    const f = e.dataTransfer?.files?.[0];
    if (f) file.value = f;
};

const handleUpload = async () => {
    if (!file.value) return;
    isUploading.value = true;
    try {
        const created = await store.uploadImport(props.resource, file.value);
        job.value = created;
        mapping.value = { ...(created.suggested_mapping ?? {}) };
        step.value = "configure";
    } catch (err: any) {
        toast.error("No se pudo subir el archivo", {
            description:
                err?.response?.data?.message ||
                err?.message ||
                "Intenta de nuevo.",
        });
    } finally {
        isUploading.value = false;
    }
};

const onMappingChange = (header: string, value: string) => {
    mapping.value = { ...mapping.value, [header]: value || null };
};

const handleRun = async () => {
    if (!job.value) return;
    if (missingRequired.value.length > 0) {
        toast.warning("Faltan campos obligatorios por mapear", {
            description: missingRequired.value.join(", "),
        });
        return;
    }
    isRunning.value = true;
    try {
        const result = await store.runImport(job.value.uuid, {
            mode: mode.value,
            mapping: mapping.value,
            unique_key: mode.value === "create" ? null : uniqueKey.value,
            options: {
                stop_on_error: stopOnError.value,
                dry_run: dryRun.value,
            },
        });
        job.value = result;
        step.value = "result";
        if (
            result.status === "done" ||
            result.status === "partial"
        ) {
            emit("imported", result);
        }
    } catch (err: any) {
        toast.error("Error al importar", {
            description:
                err?.response?.data?.message ||
                err?.message ||
                "Intenta de nuevo.",
        });
    } finally {
        isRunning.value = false;
    }
};

const handleDownloadErrors = async () => {
    if (!job.value) return;
    try {
        await store.downloadImportErrors(
            job.value.uuid,
            `errores-${job.value.original_filename}`,
        );
    } catch (err: any) {
        toast.error("No se pudo descargar", {
            description: err?.message,
        });
    }
};

const handleStartOver = () => {
    reset();
};

const formatBytes = (bytes: number | null) => {
    if (!bytes) return "—";
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
};
</script>

<template>
    <DialogRoot :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-black/60 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <DialogContent
                class="fixed left-1/2 top-1/2 z-50 -translate-x-1/2 -translate-y-1/2 w-[95vw] max-w-5xl h-[85vh] bg-background rounded-lg shadow-lg flex flex-col data-[state=open]:animate-in data-[state=closed]:animate-out"
            >
                <!-- Header -->
                <div
                    class="flex items-start justify-between p-5 border-b shrink-0"
                >
                    <div>
                        <DialogTitle class="text-lg font-semibold">
                            {{ title }}
                        </DialogTitle>
                        <DialogDescription
                            class="text-sm text-muted-foreground mt-1"
                        >
                            <span v-if="step === 'upload'">
                                Sube un archivo CSV o Excel para empezar.
                            </span>
                            <span v-else-if="step === 'configure'">
                                Revisa el mapeo de columnas y elige las
                                opciones de importación.
                            </span>
                            <span v-else>Resultado de la importación.</span>
                        </DialogDescription>
                    </div>
                    <DialogClose
                        class="rounded-sm opacity-70 hover:opacity-100 focus:outline-none"
                    >
                        <X class="h-5 w-5" />
                    </DialogClose>
                </div>

                <!-- Steps progress -->
                <div
                    class="border-b px-5 py-2 flex items-center gap-3 text-xs shrink-0"
                >
                    <div
                        :class="[
                            'flex items-center gap-1.5',
                            step === 'upload'
                                ? 'text-primary font-medium'
                                : 'text-muted-foreground',
                        ]"
                    >
                        <span
                            :class="[
                                'h-5 w-5 rounded-full flex items-center justify-center text-[10px]',
                                step === 'upload'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted',
                            ]"
                            >1</span
                        >
                        Subir
                    </div>
                    <ArrowRight
                        class="h-3 w-3 text-muted-foreground"
                    />
                    <div
                        :class="[
                            'flex items-center gap-1.5',
                            step === 'configure'
                                ? 'text-primary font-medium'
                                : 'text-muted-foreground',
                        ]"
                    >
                        <span
                            :class="[
                                'h-5 w-5 rounded-full flex items-center justify-center text-[10px]',
                                step === 'configure'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted',
                            ]"
                            >2</span
                        >
                        Mapear y opciones
                    </div>
                    <ArrowRight
                        class="h-3 w-3 text-muted-foreground"
                    />
                    <div
                        :class="[
                            'flex items-center gap-1.5',
                            step === 'result'
                                ? 'text-primary font-medium'
                                : 'text-muted-foreground',
                        ]"
                    >
                        <span
                            :class="[
                                'h-5 w-5 rounded-full flex items-center justify-center text-[10px]',
                                step === 'result'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted',
                            ]"
                            >3</span
                        >
                        Resultado
                    </div>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto min-h-0 p-5">
                    <!-- STEP 1: UPLOAD -->
                    <div
                        v-if="step === 'upload'"
                        class="h-full flex items-center justify-center"
                    >
                        <div
                            class="w-full max-w-xl border-2 border-dashed rounded-lg p-10 text-center transition-colors"
                            :class="
                                isDragging
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border'
                            "
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop="onDrop"
                        >
                            <UploadCloud
                                class="h-12 w-12 mx-auto text-muted-foreground mb-3"
                            />
                            <h3 class="text-base font-medium">
                                Arrastra tu archivo aquí
                            </h3>
                            <p
                                class="text-sm text-muted-foreground mt-1"
                            >
                                o haz click para seleccionar (CSV, XLSX, hasta
                                20 MB)
                            </p>
                            <input
                                id="import-file"
                                type="file"
                                accept=".csv,.xlsx"
                                class="hidden"
                                @change="onFileSelected"
                            />
                            <label for="import-file" class="inline-block mt-4">
                                <Button as="span" variant="outline">
                                    Seleccionar archivo
                                </Button>
                            </label>

                            <div
                                v-if="file"
                                class="mt-5 p-3 bg-muted/30 rounded flex items-center gap-2 text-left"
                            >
                                <FileSpreadsheet
                                    class="h-5 w-5 text-muted-foreground"
                                />
                                <div class="flex-1 min-w-0">
                                    <div
                                        class="text-sm font-medium truncate"
                                    >
                                        {{ file.name }}
                                    </div>
                                    <div
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ formatBytes(file.size) }}
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-destructive"
                                    @click="file = null"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: CONFIGURE -->
                    <div v-else-if="step === 'configure' && job" class="space-y-5">
                        <div class="text-xs text-muted-foreground">
                            <FileSpreadsheet class="h-3 w-3 inline -mt-0.5" />
                            {{ job.original_filename }} ·
                            {{ job.total_rows }} filas detectadas ·
                            formato {{ job.format.toUpperCase() }}
                        </div>

                        <!-- Required missing alert -->
                        <div
                            v-if="missingRequired.length > 0"
                            class="border border-amber-300 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-700 rounded p-3 flex items-start gap-2"
                        >
                            <AlertTriangle
                                class="h-4 w-4 text-amber-600 mt-0.5 shrink-0"
                            />
                            <div class="text-xs">
                                Faltan campos obligatorios por mapear:
                                <span class="font-mono">{{
                                    missingRequired.join(", ")
                                }}</span>
                            </div>
                        </div>

                        <!-- Mapping table -->
                        <div class="border rounded overflow-hidden">
                            <div
                                class="grid grid-cols-12 gap-2 px-3 py-2 bg-muted/40 text-xs font-medium border-b"
                            >
                                <div class="col-span-3">Columna del archivo</div>
                                <div class="col-span-3">Campo destino</div>
                                <div class="col-span-6">
                                    Vista previa (3 filas)
                                </div>
                            </div>
                            <div class="max-h-[280px] overflow-y-auto">
                                <div
                                    v-for="(header, hi) in job.headers || []"
                                    :key="header"
                                    class="grid grid-cols-12 gap-2 px-3 py-2 text-xs border-b last:border-b-0 items-center"
                                >
                                    <div class="col-span-3 font-medium truncate">
                                        {{ header }}
                                    </div>
                                    <div class="col-span-3">
                                        <select
                                            :value="mapping[header] ?? ''"
                                            @change="
                                                onMappingChange(
                                                    header,
                                                    ($event.target as HTMLSelectElement).value,
                                                )
                                            "
                                            class="w-full text-xs border rounded h-8 px-2 bg-background"
                                        >
                                            <option value="">— Ignorar —</option>
                                            <option
                                                v-for="f in importableFields"
                                                :key="f.key"
                                                :value="f.key"
                                            >
                                                {{ f.label
                                                }}{{ f.required ? " *" : "" }}
                                            </option>
                                        </select>
                                    </div>
                                    <div
                                        class="col-span-6 text-muted-foreground space-y-0.5"
                                    >
                                        <div
                                            v-for="(p, pi) in (job.preview || []).slice(0, 3)"
                                            :key="pi"
                                            class="font-mono truncate"
                                        >
                                            {{ p[hi] || "—" }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Options -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="space-y-1.5">
                                <label
                                    class="text-xs font-medium text-muted-foreground"
                                    >Modo</label
                                >
                                <div class="flex flex-col gap-1">
                                    <label
                                        v-for="opt in [
                                            {
                                                value: 'create',
                                                label: 'Solo crear nuevos',
                                            },
                                            {
                                                value: 'update',
                                                label: 'Solo actualizar existentes',
                                            },
                                            {
                                                value: 'upsert',
                                                label: 'Crear o actualizar (upsert)',
                                            },
                                        ]"
                                        :key="opt.value"
                                        class="flex items-center gap-2 text-xs cursor-pointer"
                                    >
                                        <input
                                            type="radio"
                                            :value="opt.value"
                                            v-model="mode"
                                            name="import-mode"
                                        />
                                        {{ opt.label }}
                                    </label>
                                </div>
                            </div>

                            <div
                                v-if="mode !== 'create'"
                                class="space-y-1.5"
                            >
                                <label
                                    class="text-xs font-medium text-muted-foreground"
                                    >Llave única</label
                                >
                                <select
                                    v-model="uniqueKey"
                                    class="w-full text-xs border rounded h-9 px-2 bg-background"
                                >
                                    <option
                                        v-for="f in uniqueKeyOptions"
                                        :key="f.key"
                                        :value="f.key"
                                    >
                                        {{ f.label }}
                                    </option>
                                </select>
                                <p class="text-[10px] text-muted-foreground">
                                    Campo usado para detectar registros
                                    existentes.
                                </p>
                            </div>

                            <div class="space-y-2">
                                <label
                                    class="text-xs font-medium text-muted-foreground block"
                                    >Otras opciones</label
                                >
                                <label
                                    class="flex items-center gap-2 text-xs cursor-pointer"
                                >
                                    <Checkbox
                                        :checked="dryRun"
                                        @update:checked="(v: boolean) => (dryRun = v)"
                                    />
                                    Solo simular (no guarda en BD)
                                </label>
                                <label
                                    class="flex items-center gap-2 text-xs cursor-pointer"
                                >
                                    <Checkbox
                                        :checked="stopOnError"
                                        @update:checked="
                                            (v: boolean) => (stopOnError = v)
                                        "
                                    />
                                    Detener al primer error
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: RESULT -->
                    <div
                        v-else-if="step === 'result' && job"
                        class="h-full flex flex-col items-center justify-center gap-4"
                    >
                        <component
                            :is="
                                job.status === 'done'
                                    ? CheckCircle2
                                    : job.status === 'failed'
                                      ? XCircle
                                      : AlertTriangle
                            "
                            :class="[
                                'h-16 w-16',
                                job.status === 'done'
                                    ? 'text-green-500'
                                    : job.status === 'failed'
                                      ? 'text-destructive'
                                      : 'text-amber-500',
                            ]"
                        />
                        <h3 class="text-xl font-semibold">
                            <span v-if="job.status === 'done'">
                                Importación completa
                            </span>
                            <span v-else-if="job.status === 'partial'">
                                Importación con errores
                            </span>
                            <span v-else-if="job.status === 'failed'">
                                La importación falló
                            </span>
                            <span v-else>Importación: {{ job.status }}</span>
                        </h3>

                        <div
                            class="grid grid-cols-3 gap-4 w-full max-w-md text-center"
                        >
                            <div class="border rounded p-3">
                                <div class="text-2xl font-semibold">
                                    {{ job.total_rows ?? 0 }}
                                </div>
                                <div
                                    class="text-xs text-muted-foreground"
                                >
                                    Total
                                </div>
                            </div>
                            <div
                                class="border rounded p-3 text-green-600 dark:text-green-400"
                            >
                                <div class="text-2xl font-semibold">
                                    {{ job.imported_rows }}
                                </div>
                                <div
                                    class="text-xs text-muted-foreground"
                                >
                                    Importados
                                </div>
                            </div>
                            <div
                                class="border rounded p-3"
                                :class="
                                    job.failed_rows > 0
                                        ? 'text-destructive'
                                        : ''
                                "
                            >
                                <div class="text-2xl font-semibold">
                                    {{ job.failed_rows }}
                                </div>
                                <div
                                    class="text-xs text-muted-foreground"
                                >
                                    Errores
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="
                                job.error_summary &&
                                Object.keys(job.error_summary).length > 0
                            "
                            class="w-full max-w-md border rounded p-3 bg-muted/30 max-h-40 overflow-y-auto"
                        >
                            <div
                                class="text-xs font-medium mb-2 text-muted-foreground"
                            >
                                Errores más comunes:
                            </div>
                            <div
                                v-for="(count, msg) in job.error_summary"
                                :key="msg"
                                class="text-xs flex justify-between gap-2 py-1 border-b last:border-b-0"
                            >
                                <span class="truncate">{{ msg }}</span>
                                <span
                                    class="font-mono text-muted-foreground shrink-0"
                                    >×{{ count }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div
                    class="border-t px-5 py-3 flex items-center justify-between gap-2 shrink-0"
                >
                    <div class="text-xs text-muted-foreground">
                        <span v-if="step === 'configure' && job">
                            {{
                                Object.values(mapping).filter((v) => v).length
                            }}
                            de
                            {{ (job.headers || []).length }} columnas
                            mapeadas
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Step 1 footer -->
                        <template v-if="step === 'upload'">
                            <Button
                                variant="outline"
                                @click="close"
                                :disabled="isUploading"
                                >Cancelar</Button
                            >
                            <Button
                                @click="handleUpload"
                                :disabled="!file || isUploading"
                            >
                                <Loader2
                                    v-if="isUploading"
                                    class="h-4 w-4 mr-2 animate-spin"
                                />
                                <ArrowRight
                                    v-else
                                    class="h-4 w-4 mr-2"
                                />
                                Continuar
                            </Button>
                        </template>

                        <!-- Step 2 footer -->
                        <template v-else-if="step === 'configure'">
                            <Button
                                variant="ghost"
                                @click="handleStartOver"
                                :disabled="isRunning"
                            >
                                <ArrowLeft class="h-4 w-4 mr-2" />
                                Volver
                            </Button>
                            <Button
                                @click="handleRun"
                                :disabled="isRunning"
                            >
                                <Loader2
                                    v-if="isRunning"
                                    class="h-4 w-4 mr-2 animate-spin"
                                />
                                <Play v-else class="h-4 w-4 mr-2" />
                                {{
                                    dryRun
                                        ? "Simular importación"
                                        : "Iniciar importación"
                                }}
                            </Button>
                        </template>

                        <!-- Step 3 footer -->
                        <template v-else-if="step === 'result'">
                            <Button
                                v-if="job?.has_errors_file"
                                variant="outline"
                                @click="handleDownloadErrors"
                            >
                                <Download class="h-4 w-4 mr-2" />
                                Descargar errores
                            </Button>
                            <Button
                                variant="ghost"
                                @click="handleStartOver"
                            >
                                Importar otro
                            </Button>
                            <Button @click="close">Cerrar</Button>
                        </template>
                    </div>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
