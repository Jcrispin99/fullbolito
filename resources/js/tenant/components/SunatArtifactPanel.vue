<script setup lang="ts">
/**
 * Visor de artefactos SUNAT genérico (Sale o Despatch/GRE).
 *
 * Carga automáticamente la representación impresa (HTML renderizado por
 * backend) y la embebe en un iframe srcdoc al montarse el panel. El operador
 * ve el documento apenas abre el tab SUNAT — sin clicks intermedios.
 *
 * Acciones secundarias para usuarios técnicos: descargar XML firmado y
 * descargar CDR (.zip) según los pida el contador/auditor.
 *
 * Prop `kind` controla el endpoint base:
 *   - 'sale'     → /v1/sales/{id}/sunat/{preview,pdf,xml,cdr}
 *   - 'despatch' → /v1/transfers/{id}/gre/{preview,pdf,xml,cdr}
 *
 * El caller mapea sus campos al shape `SunatDoc` (status, response, sent_at,
 * has_signed_xml, has_cdr_zip) — el componente no conoce el dominio.
 */
import { computed, onMounted, ref, watch } from "vue";
import api from "@tenant/lib/api";
import { Button } from "@/components/ui/button";
import {
    FileCode2,
    FileArchive,
    FileDown,
    Printer,
    Send,
    RefreshCw,
    Loader2,
} from "lucide-vue-next";
import { toast } from "vue-sonner";

type SunatResponse = {
    accepted?: boolean;
    channel?: string;
    cdr_code?: number | null;
    description?: string | null;
    notes?: string[] | null;
    error?: string | null;
    updated_at?: string | null;
    sunat?: {
        success?: boolean;
        error?: { code?: string; message?: string };
    };
    [k: string]: any;
};

type DocKind = "sale" | "despatch";

type SunatDoc = {
    id?: number;
    serie?: string | null;
    correlative?: string | null;
    sunat_status?: string | null;
    sunat_response?: SunatResponse | null;
    sunat_sent_at?: string | null;
    has_signed_xml?: boolean;
    has_cdr_zip?: boolean;
};

const props = withDefaults(
    defineProps<{
        doc: SunatDoc;
        kind?: DocKind;
        canSend?: boolean;
        isLoading?: boolean;
    }>(),
    { kind: "sale" },
);

const emit = defineEmits<{
    send: [];
}>();

// Endpoint base por tipo de documento. Sale usa /sunat, despatch usa /gre
// (mismas acciones: preview, pdf, xml, cdr).
const baseUrl = computed(() => {
    const id = props.doc.id;
    if (!id) return null;
    return props.kind === "despatch"
        ? `/v1/transfers/${id}/gre`
        : `/v1/sales/${id}/sunat`;
});

// Etiqueta para mensajes vacíos / títulos.
const docNoun = computed(() =>
    props.kind === "despatch" ? "guía" : "venta",
);

const previewLoading = ref(false);
const previewError = ref<string | null>(null);
const previewHtml = ref<string>("");

const xmlDownloading = ref(false);
const cdrLoading = ref(false);
const pdfDownloading = ref(false);
const previewIframe = ref<HTMLIFrameElement | null>(null);

const docLabel = computed(() => {
    const s = props.doc.serie || "";
    const c = props.doc.correlative || "";
    if (!s && !c) return "—";
    return `${s}${c ? "-" + c : ""}`;
});

const responseDescription = computed(
    () => props.doc.sunat_response?.description ?? null,
);
// Mensaje de error consolidado: prioriza el detalle SUNAT cuando existe.
const responseError = computed(() => {
    const r = props.doc.sunat_response;
    if (!r) return null;
    const sunatErr = r.sunat?.error;
    if (sunatErr?.code || sunatErr?.message) {
        const code = sunatErr.code ? `[${sunatErr.code}] ` : "";
        return `${code}${sunatErr.message ?? ""}`.trim();
    }
    return r.error ?? null;
});
const responseCdrCode = computed(() => {
    const v = props.doc.sunat_response?.cdr_code;
    return v === null || v === undefined ? null : v;
});
const notes = computed<string[]>(() => {
    const n = props.doc.sunat_response?.notes;
    return Array.isArray(n) ? n : [];
});

const showSendButton = computed(
    () =>
        props.canSend &&
        props.doc.sunat_status &&
        props.doc.sunat_status !== "accepted" &&
        props.doc.sunat_status !== "skipped",
);

const loadPreview = async () => {
    if (!baseUrl.value) return;
    previewLoading.value = true;
    previewError.value = null;
    try {
        const { data } = await api.get(`${baseUrl.value}/preview`, {
            responseType: "text",
            transformResponse: [(d) => d],
        });
        previewHtml.value = typeof data === "string" ? data : String(data);
    } catch (e: any) {
        previewError.value =
            e?.response?.data?.message ||
            e?.message ||
            "No se pudo cargar la representación";
    } finally {
        previewLoading.value = false;
    }
};

// Carga automática al montar y cuando cambia el doc.id.
onMounted(() => {
    if (props.doc.id) loadPreview();
});
watch(
    () => props.doc.id,
    (id, prev) => {
        if (id && id !== prev) loadPreview();
    },
);

const downloadXml = async () => {
    if (!baseUrl.value) return;
    xmlDownloading.value = true;
    try {
        const { data } = await api.get(`${baseUrl.value}/xml`, {
            responseType: "blob",
        });
        const url = URL.createObjectURL(data as Blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `${docLabel.value || "documento"}.xml`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    } catch (e: any) {
        toast.error("No se pudo descargar XML", {
            description: e?.message || "Error desconocido",
        });
    } finally {
        xmlDownloading.value = false;
    }
};

const downloadPdf = async () => {
    if (!baseUrl.value) return;
    pdfDownloading.value = true;
    try {
        const { data } = await api.get(`${baseUrl.value}/pdf`, {
            responseType: "blob",
        });
        const blob = new Blob([data as BlobPart], { type: "application/pdf" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `${docLabel.value || "documento"}.pdf`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    } catch (e: any) {
        toast.error("No se pudo descargar PDF", {
            description: e?.message || "Error desconocido",
        });
    } finally {
        pdfDownloading.value = false;
    }
};

// Imprime el contenido del iframe usando @media print del blade
// (A4 sin sombras ni transform de auto-fit). Si el iframe no responde,
// abre el PDF en una pestaña nueva como fallback.
const printPreview = () => {
    const win = previewIframe.value?.contentWindow;
    if (!win) {
        toast.error("No se pudo abrir la impresión");
        return;
    }
    try {
        win.focus();
        win.print();
    } catch {
        toast.error("Imprime desde tu navegador (Ctrl+P)");
    }
};

const downloadCdr = async () => {
    if (!baseUrl.value) return;
    cdrLoading.value = true;
    try {
        const { data } = await api.get(`${baseUrl.value}/cdr`, {
            responseType: "blob",
        });
        const url = URL.createObjectURL(data as Blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `R-${docLabel.value || "documento"}.zip`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    } catch (e: any) {
        toast.error("No se pudo descargar CDR", {
            description: e?.message || "Error desconocido",
        });
    } finally {
        cdrLoading.value = false;
    }
};
</script>

<template>
    <div class="space-y-3 text-sm">
        <!-- Acción principal (Enviar/Reintentar) + slot opcional para acciones extra -->
        <div
            v-if="showSendButton || !!$slots.actions"
            class="flex flex-wrap items-center justify-end gap-2"
        >
            <Button
                v-if="showSendButton"
                variant="secondary"
                size="sm"
                class="h-8 text-xs"
                :disabled="isLoading"
                @click="emit('send')"
            >
                <RefreshCw
                    v-if="doc.sunat_status === 'error'"
                    class="mr-1.5 h-3.5 w-3.5"
                />
                <Send v-else class="mr-1.5 h-3.5 w-3.5" />
                {{
                    doc.sunat_status === "error"
                        ? "Reintentar"
                        : "Enviar a SUNAT"
                }}
            </Button>
            <slot name="actions" />
        </div>

        <!-- Visor (siempre visible cuando el documento está guardado) -->
        <div
            v-if="doc.id"
            class="rounded border bg-white overflow-hidden"
        >
            <div class="flex items-center gap-1.5 px-3 py-2 border-b bg-muted/40 flex-wrap">
                <span
                    class="text-[10px] uppercase font-medium text-muted-foreground"
                >
                    Representación impresa
                </span>
                <span class="ml-auto"></span>

                <!-- Acciones primarias: PDF + Imprimir -->
                <Button
                    variant="default"
                    size="sm"
                    class="h-7 text-[11px] px-2.5"
                    :disabled="pdfDownloading"
                    @click="downloadPdf"
                >
                    <Loader2
                        v-if="pdfDownloading"
                        class="mr-1 h-3 w-3 animate-spin"
                    />
                    <FileDown v-else class="mr-1 h-3 w-3" />
                    PDF
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-7 text-[11px] px-2.5"
                    :disabled="!previewHtml"
                    @click="printPreview"
                >
                    <Printer class="mr-1 h-3 w-3" />
                    Imprimir
                </Button>

                <!-- Acciones secundarias: refrescar + XML/CDR (técnicas) -->
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-7 text-[10px] px-2 text-muted-foreground"
                    :disabled="previewLoading"
                    title="Recargar comprobante"
                    @click="loadPreview"
                >
                    <Loader2
                        v-if="previewLoading"
                        class="mr-1 h-3 w-3 animate-spin"
                    />
                    <RefreshCw v-else class="mr-1 h-3 w-3" />
                    Refrescar
                </Button>
                <Button
                    v-if="doc.has_signed_xml"
                    variant="ghost"
                    size="sm"
                    class="h-7 text-[10px] px-2 text-muted-foreground"
                    :disabled="xmlDownloading"
                    title="Descargar XML firmado (técnico)"
                    @click="downloadXml"
                >
                    <Loader2
                        v-if="xmlDownloading"
                        class="mr-1 h-3 w-3 animate-spin"
                    />
                    <FileCode2 v-else class="mr-1 h-3 w-3" />
                    XML
                </Button>
                <Button
                    v-if="doc.has_cdr_zip"
                    variant="ghost"
                    size="sm"
                    class="h-7 text-[10px] px-2 text-muted-foreground"
                    :disabled="cdrLoading"
                    title="Descargar CDR (constancia SUNAT)"
                    @click="downloadCdr"
                >
                    <Loader2
                        v-if="cdrLoading"
                        class="mr-1 h-3 w-3 animate-spin"
                    />
                    <FileArchive v-else class="mr-1 h-3 w-3" />
                    CDR
                </Button>
            </div>
            <div class="bg-white">
                <div
                    v-if="previewLoading"
                    class="flex items-center justify-center py-10 text-muted-foreground text-xs"
                >
                    <Loader2 class="h-4 w-4 animate-spin mr-2" />
                    Cargando comprobante...
                </div>
                <div
                    v-else-if="previewError"
                    class="text-xs text-destructive p-3"
                >
                    {{ previewError }}
                </div>
                <iframe
                    v-else-if="previewHtml"
                    ref="previewIframe"
                    :srcdoc="previewHtml"
                    class="w-full border-0 bg-white"
                    style="height: 720px"
                    title="Representación impresa"
                />
            </div>
        </div>

        <!-- Sin estado SUNAT -->
        <div
            v-if="!doc.sunat_status"
            class="rounded border border-dashed bg-muted/20 p-4 text-xs text-muted-foreground"
        >
            Esta {{ docNoun }} aún no se ha enviado a SUNAT.
        </div>

        <!-- Detalle de respuesta -->
        <div v-else class="space-y-3">
            <div
                v-if="responseDescription"
                class="rounded border bg-muted/20 px-3 py-2"
            >
                <div
                    class="text-[10px] uppercase font-medium text-muted-foreground mb-0.5"
                >
                    Descripción
                </div>
                <div class="text-xs">
                    {{ responseDescription }}
                    <span
                        v-if="responseCdrCode !== null"
                        class="text-muted-foreground"
                    >
                        (código {{ responseCdrCode }})
                    </span>
                </div>
            </div>

            <div
                v-if="responseError"
                class="rounded border border-red-200 bg-red-50 px-3 py-2"
            >
                <div
                    class="text-[10px] uppercase font-medium text-red-700 mb-0.5"
                >
                    Error SUNAT
                </div>
                <div class="text-xs text-red-900 whitespace-pre-wrap break-words">
                    {{ responseError }}
                </div>
            </div>

            <div
                v-if="notes.length > 0"
                class="rounded border border-amber-200 bg-amber-50 px-3 py-2"
            >
                <div
                    class="text-[10px] uppercase font-medium text-amber-800 mb-1"
                >
                    Observaciones SUNAT ({{ notes.length }})
                </div>
                <ul
                    class="text-xs space-y-0.5 list-disc list-inside text-amber-900"
                >
                    <li v-for="(n, i) in notes" :key="i">{{ n }}</li>
                </ul>
            </div>

            <div
                v-if="
                    !responseDescription &&
                    !responseError &&
                    notes.length === 0
                "
                class="text-xs text-muted-foreground"
            >
                Sin detalle adicional en la última respuesta.
            </div>
        </div>

        <div
            v-if="!doc.has_signed_xml && !doc.has_cdr_zip && doc.sunat_status"
            class="text-[11px] text-muted-foreground italic"
        >
            <FileCode2 class="inline h-3 w-3 mr-1" />
            No hay XML/CDR archivados todavía. Aparecerán tras un envío local
            con greenter/lite.
        </div>
    </div>
</template>
