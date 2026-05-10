import { defineStore } from "pinia";
import { ref } from "vue";
import api, { apiClient } from "../lib/api";

export interface ImportExportFieldDefinition {
    key: string;
    label: string;
    type: string;
    required: boolean;
    unique: boolean;
    exportable: boolean;
    importable: boolean;
    relation_resource: string | null;
    enum_values: string[] | null;
    help: string | null;
}

export interface ImportExportSchema {
    key: string;
    label: string;
    supports_import: boolean;
    fields: ImportExportFieldDefinition[];
}

export interface ImportExportSchemaSummary {
    key: string;
    label: string;
}

export type ExportFormat = "csv" | "xlsx";

export type TemplateDirection = "import" | "export";

export interface ExportPayload {
    resource: string;
    columns?: string[];
    filters?: Record<string, unknown>;
    filename?: string;
    format?: ExportFormat;
    delimiter?: string;
}

export interface ImportExportTemplate {
    id: number;
    name: string;
    resource: string;
    direction: TemplateDirection;
    columns: string[];
    default_options: Record<string, unknown> | null;
    owner_user_id: number;
    owner_name: string | null;
    is_shared: boolean;
    is_mine: boolean;
    can_delete: boolean;
    created_at: string | null;
    updated_at: string | null;
}

export interface SaveTemplatePayload {
    name: string;
    resource: string;
    direction: TemplateDirection;
    columns: string[];
    default_options?: Record<string, unknown> | null;
    is_shared?: boolean;
}

export type ImportMode = "create" | "update" | "upsert";

export type ImportStatus =
    | "draft"
    | "queued"
    | "processing"
    | "done"
    | "partial"
    | "failed"
    | "cancelled";

export interface ImportJob {
    id: number;
    uuid: string;
    resource: string;
    template_id: number | null;
    user_id: number;
    original_filename: string;
    file_size: number | null;
    format: "csv" | "xlsx";
    delimiter: string | null;
    encoding: string | null;
    mapping: Record<string, string | null> | null;
    mode: ImportMode;
    unique_key: string | null;
    options: Record<string, unknown> | null;
    status: ImportStatus;
    total_rows: number | null;
    processed_rows: number;
    imported_rows: number;
    failed_rows: number;
    has_errors_file: boolean;
    error_summary: Record<string, number> | null;
    started_at: string | null;
    finished_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    // Transient (only on upload response)
    preview?: string[][] | null;
    headers?: string[] | null;
    suggested_mapping?: Record<string, string | null> | null;
}

export interface RunImportPayload {
    mode?: ImportMode;
    mapping?: Record<string, string | null>;
    unique_key?: string | null;
    options?: Record<string, unknown>;
}

export const useImportExportStore = defineStore("importExport", () => {
    const schemas = ref<Record<string, ImportExportSchema>>({});
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    const fetchSchema = async (
        resource: string,
        force = false,
    ): Promise<ImportExportSchema> => {
        if (!force && schemas.value[resource]) {
            return schemas.value[resource];
        }

        isLoading.value = true;
        error.value = null;
        try {
            const { data } = await apiClient.get<ImportExportSchema>(
                `/v1/import-export/schemas/${resource}`,
            );
            const schema = data.data as ImportExportSchema;
            schemas.value[resource] = schema;
            return schema;
        } catch (err: any) {
            error.value =
                err?.response?.data?.message ||
                err?.message ||
                "Failed to load schema";
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    const exportFile = async (payload: ExportPayload): Promise<void> => {
        const format = payload.format ?? "csv";
        const response = await api.post(
            "/v1/import-export/export",
            { ...payload, format },
            { responseType: "blob" },
        );

        const disposition = response.headers["content-disposition"] as
            | string
            | undefined;
        let filename =
            payload.filename || `${payload.resource}-export.${format}`;
        if (disposition) {
            const match = /filename="?([^"]+)"?/.exec(disposition);
            if (match?.[1]) filename = match[1];
        }

        const blob = response.data as Blob;
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    const templates = ref<ImportExportTemplate[]>([]);
    const isLoadingTemplates = ref(false);

    const fetchTemplates = async (
        resource: string,
        direction: TemplateDirection = "export",
    ): Promise<ImportExportTemplate[]> => {
        isLoadingTemplates.value = true;
        try {
            const { data } = await apiClient.get<ImportExportTemplate[]>(
                `/v1/import-export/templates`,
                { params: { resource, direction } },
            );
            templates.value = (data.data as ImportExportTemplate[]) ?? [];
            return templates.value;
        } finally {
            isLoadingTemplates.value = false;
        }
    };

    const saveTemplate = async (
        payload: SaveTemplatePayload,
    ): Promise<ImportExportTemplate> => {
        const { data } = await apiClient.post<ImportExportTemplate>(
            `/v1/import-export/templates`,
            payload,
        );
        const created = data.data as ImportExportTemplate;
        templates.value = [...templates.value, created].sort((a, b) =>
            a.name.localeCompare(b.name),
        );
        return created;
    };

    const deleteTemplate = async (id: number): Promise<void> => {
        await apiClient.delete(`/v1/import-export/templates/${id}`);
        templates.value = templates.value.filter((t) => t.id !== id);
    };

    const uploadImport = async (
        resource: string,
        file: File,
    ): Promise<ImportJob> => {
        const form = new FormData();
        form.append("resource", resource);
        form.append("file", file);
        const { data } = await apiClient.post<ImportJob>(
            `/v1/import-export/imports`,
            form,
            { headers: { "Content-Type": "multipart/form-data" } },
        );
        return data.data as ImportJob;
    };

    const updateImport = async (
        uuid: string,
        payload: RunImportPayload,
    ): Promise<ImportJob> => {
        const { data } = await apiClient.patch<ImportJob>(
            `/v1/import-export/imports/${uuid}`,
            payload,
        );
        return data.data as ImportJob;
    };

    const runImport = async (
        uuid: string,
        payload: RunImportPayload = {},
    ): Promise<ImportJob> => {
        const { data } = await apiClient.post<ImportJob>(
            `/v1/import-export/imports/${uuid}/run`,
            payload,
        );
        return data.data as ImportJob;
    };

    const cancelImport = async (uuid: string): Promise<void> => {
        await apiClient.delete(`/v1/import-export/imports/${uuid}`);
    };

    const downloadImportErrors = async (
        uuid: string,
        filename = "errors.csv",
    ): Promise<void> => {
        const response = await api.get(
            `/v1/import-export/imports/${uuid}/errors`,
            { responseType: "blob" },
        );
        const disposition = response.headers["content-disposition"] as
            | string
            | undefined;
        let outName = filename;
        if (disposition) {
            const match = /filename="?([^"]+)"?/.exec(disposition);
            if (match?.[1]) outName = match[1];
        }
        const url = URL.createObjectURL(response.data as Blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = outName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    return {
        schemas,
        isLoading,
        error,
        fetchSchema,
        exportFile,
        templates,
        isLoadingTemplates,
        fetchTemplates,
        saveTemplate,
        deleteTemplate,
        uploadImport,
        updateImport,
        runImport,
        cancelImport,
        downloadImportErrors,
    };
});
