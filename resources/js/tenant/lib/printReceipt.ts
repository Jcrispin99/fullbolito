import { createApp, h, nextTick } from 'vue'
import ReceiptRenderer, {
    type ReceiptCompany,
    type ReceiptSale,
    type ReceiptTemplateLayout,
} from '@tenant/components/ReceiptRenderer.vue'
import { ensureReceiptStylesInjected } from '@tenant/components/receiptStyles'

export async function printReceipt(params: {
    template: ReceiptTemplateLayout
    sale: ReceiptSale
    company: ReceiptCompany
    templateLogoUrl?: string | null
}): Promise<void> {
    const { template, sale, company, templateLogoUrl } = params

    const iframe = document.createElement('iframe')
    iframe.setAttribute('aria-hidden', 'true')
    iframe.style.position = 'fixed'
    iframe.style.right = '0'
    iframe.style.bottom = '0'
    iframe.style.width = '0'
    iframe.style.height = '0'
    iframe.style.border = '0'
    document.body.appendChild(iframe)

    const idoc = iframe.contentDocument
    if (!idoc) {
        iframe.remove()
        throw new Error('No se pudo crear el documento de impresión')
    }

    idoc.open()
    idoc.write(
        '<!doctype html><html><head><meta charset="utf-8"><title>Comprobante</title></head><body><div id="rcpt-mount"></div></body></html>',
    )
    idoc.close()

    ensureReceiptStylesInjected(idoc)

    const mount = idoc.getElementById('rcpt-mount')
    if (!mount) {
        iframe.remove()
        throw new Error('No se encontró el contenedor de impresión')
    }

    const app = createApp({
        render: () =>
            h(ReceiptRenderer, {
                template,
                sale,
                company,
                templateLogoUrl: templateLogoUrl ?? null,
            }),
    })
    app.mount(mount)

    let cleanedUp = false
    const cleanup = () => {
        if (cleanedUp) return
        cleanedUp = true
        try { app.unmount() } catch {}
        if (iframe.parentNode) iframe.parentNode.removeChild(iframe)
        window.removeEventListener('focus', onFocus)
    }
    const onFocus = () => setTimeout(cleanup, 800)

    await nextTick()
    await new Promise((r) => requestAnimationFrame(() => r(null)))

    try {
        iframe.contentWindow?.focus()
        iframe.contentWindow?.print()
        window.addEventListener('focus', onFocus, { once: true })
        setTimeout(cleanup, 60_000)
    } catch (e) {
        cleanup()
        throw e
    }
}
