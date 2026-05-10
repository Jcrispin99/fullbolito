// Configuración global de fecha/hora para el tenant.
// El servidor entrega fechas en hora local (configurada via APP_TIMEZONE en
// el backend), pero el navegador del cliente interpreta strings tipo
// "2026-05-04 14:23:00" según su propia zona horaria. Para evitar desfases
// entre cajas, formateamos siempre con la zona horaria del negocio.
export const APP_TIMEZONE = 'America/Lima'
export const APP_LOCALE = 'es-PE'

/**
 * Convierte un string ISO o "YYYY-MM-DD HH:MM:SS" a Date asumiendo que
 * representa hora del servidor (APP_TIMEZONE). El navegador puede luego
 * formatear con timeZone explícito sin doble conversión.
 */
const parseServerDate = (input: string | Date | null | undefined): Date | null => {
    if (!input) return null
    if (input instanceof Date) return input
    // "YYYY-MM-DD HH:MM:SS" → ISO con espacio reemplazado por 'T'
    return new Date(String(input).replace(' ', 'T'))
}

const baseFormat = (
    input: string | Date | null | undefined,
    options: Intl.DateTimeFormatOptions,
): string => {
    const d = parseServerDate(input)
    if (!d || Number.isNaN(d.getTime())) return '-'
    return d.toLocaleString(APP_LOCALE, { timeZone: APP_TIMEZONE, ...options })
}

export const formatDateTime = (input: string | Date | null | undefined): string =>
    baseFormat(input, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })

export const formatDate = (input: string | Date | null | undefined): string =>
    baseFormat(input, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    })

export const formatTime = (input: string | Date | null | undefined): string =>
    baseFormat(input, { hour: '2-digit', minute: '2-digit' })

export const formatMediumDate = (input: string | Date | null | undefined): string =>
    baseFormat(input, { dateStyle: 'medium' })
