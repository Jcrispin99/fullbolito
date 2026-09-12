import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { apiClient } from '@tenant/lib/api'
import { toast } from 'vue-sonner'

export interface CreateDialogOptions {
  /** Base API endpoint (e.g. '/v1/categories') — used for POST, GET /:id, PUT /:id */
  endpoint: string
  /** API endpoint for form options (e.g. '/v1/categories/form-options'), optional */
  formOptionsEndpoint?: string
  /** i18n key under common.entities for toast/title messages (e.g. 'category') */
  labelKey?: string
}

export function useCreateDialog(options: CreateDialogOptions) {
  const { t } = useI18n()
  const isOpen = ref(false)
  const isLoading = ref(false)
  const errors = ref<Record<string, string>>({})
  const formOptions = ref<any>(null)
  const initialName = ref('')

  const mode = ref<'create' | 'edit'>('create')
  const recordId = ref<string | number | boolean | null>(null)
  const initialData = ref<any>(null)

  const isEditing = computed(() => mode.value === 'edit')

  const entityLabel = computed(() =>
    options.labelKey
      ? t(`common.entities.${options.labelKey}`)
      : t('common.entities.record'),
  )

  const title = computed(() =>
    mode.value === 'edit'
      ? t('common.dialog.edit', { entity: entityLabel.value })
      : t('common.dialog.create', { entity: entityLabel.value }),
  )

  async function loadFormOptions() {
    if (options.formOptionsEndpoint && !formOptions.value) {
      try {
        const { data } = await apiClient.get<any>(options.formOptionsEndpoint)
        formOptions.value = data.data
      } catch {
        // non-blocking
      }
    }
  }

  async function open(name = '') {
    mode.value = 'create'
    recordId.value = null
    initialData.value = null
    initialName.value = name
    errors.value = {}
    await loadFormOptions()
    isOpen.value = true
  }

  async function edit(id: string | number | boolean) {
    mode.value = 'edit'
    recordId.value = id
    initialData.value = null
    initialName.value = ''
    errors.value = {}
    isLoading.value = true

    try {
      await loadFormOptions()
      const { data } = await apiClient.get<any>(`${options.endpoint}/${id}`)
      initialData.value = data.data
      isOpen.value = true
    } catch (err: any) {
      toast.error(err?.response?.data?.message || t('common.dialog.errorLoading'))
    } finally {
      isLoading.value = false
    }
  }

  function close() {
    isOpen.value = false
    errors.value = {}
    initialData.value = null
    recordId.value = null
  }

  async function handleSubmit(payload: any): Promise<any> {
    isLoading.value = true
    errors.value = {}

    try {
      let result: any
      if (mode.value === 'edit' && recordId.value) {
        const { data } = await apiClient.put<any>(`${options.endpoint}/${recordId.value}`, payload)
        result = data.data
        toast.success(t('common.dialog.updated', { entity: entityLabel.value }))
      } else {
        const { data } = await apiClient.post<any>(options.endpoint, payload)
        result = data.data
        toast.success(t('common.dialog.created', { entity: entityLabel.value }))
      }
      close()
      return result
    } catch (err: any) {
      const e = err?.response?.data
      if (e?.errors) {
        const flat: Record<string, string> = {}
        Object.entries(e.errors).forEach(([k, v]: any) => {
          flat[k] = Array.isArray(v) ? v[0] : String(v)
        })
        errors.value = flat
      } else {
        toast.error(e?.message || t('common.dialog.errorSaving'))
      }
      throw err
    } finally {
      isLoading.value = false
    }
  }

  return {
    isOpen,
    isLoading,
    isEditing,
    errors,
    formOptions,
    initialName,
    initialData,
    mode,
    recordId,
    title,
    open,
    edit,
    close,
    handleSubmit,
  }
}
