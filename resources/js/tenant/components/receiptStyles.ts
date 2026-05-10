export const RECEIPT_STYLES = `
.rcpt-canvas {
    position: relative;
    background: #fff;
    color: #000;
    box-sizing: border-box;
    font-family: 'Courier New', Courier, monospace;
    margin: 0 auto;
    overflow: hidden;
}
.rcpt-canvas.rcpt-w-thermal_58 { width: 220px; font-size: 10px; line-height: 1.25; }
.rcpt-canvas.rcpt-w-thermal_80 { width: 302px; font-size: 11px; line-height: 1.3; }
.rcpt-canvas.rcpt-w-a4 { width: 794px; font-size: 12px; line-height: 1.45; font-family: 'Helvetica Neue', Arial, sans-serif; }
.rcpt-canvas.rcpt-fs-sm { font-size: 9px; }
.rcpt-canvas.rcpt-fs-md {}
.rcpt-canvas.rcpt-fs-lg { font-size: 13px; }
.rcpt-canvas.rcpt-w-a4.rcpt-fs-sm { font-size: 10px; }
.rcpt-canvas.rcpt-w-a4.rcpt-fs-lg { font-size: 14px; }

.rcpt-block {
    position: absolute;
    box-sizing: border-box;
    word-wrap: break-word;
    word-break: break-word;
}
.rcpt-block.rcpt-align-left { text-align: left; }
.rcpt-block.rcpt-align-center { text-align: center; }
.rcpt-block.rcpt-align-right { text-align: right; }
.rcpt-block.rcpt-bold { font-weight: 700; }
.rcpt-block.rcpt-italic { font-style: italic; }
.rcpt-block.rcpt-underline { text-decoration: underline; }
.rcpt-block.rcpt-size-sm { font-size: 0.85em; }
.rcpt-block.rcpt-size-md { font-size: 1em; }
.rcpt-block.rcpt-size-lg { font-size: 1.2em; }

.rcpt-blk-divider { display: flex; align-items: center; }
.rcpt-blk-divider .rcpt-divider-line { width: 100%; border: 0; border-top: 1px dashed #000; margin: 0; }
.rcpt-blk-divider.rcpt-divider-style-solid .rcpt-divider-line { border-top-style: solid; }
.rcpt-blk-divider.rcpt-divider-style-dotted .rcpt-divider-line { border-top-style: dotted; }

.rcpt-blk-logo img { max-width: 100%; max-height: 100%; object-fit: contain; display: block; margin: 0 auto; }
.rcpt-blk-qr img { max-width: 100%; max-height: 100%; object-fit: contain; display: block; margin: 0 auto; }

.rcpt-company-name { font-weight: 700; font-size: 1.15em; }
.rcpt-meta {}
.rcpt-info-row { display: flex; justify-content: space-between; gap: 4px; }
.rcpt-info-row > .rcpt-label { font-weight: 700; }
.rcpt-items { width: 100%; border-collapse: collapse; }
.rcpt-items th, .rcpt-items td { padding: 1px 1px; vertical-align: top; }
.rcpt-items th { border-bottom: 1px dashed #000; font-weight: 700; text-align: left; }
.rcpt-items td.rcpt-num, .rcpt-items th.rcpt-num { text-align: right; }
.rcpt-items td.rcpt-name { word-break: break-word; }
.rcpt-total-row { display: flex; justify-content: space-between; }
.rcpt-total-row.rcpt-grand { font-weight: 700; font-size: 1.1em; }
.rcpt-tabular { font-variant-numeric: tabular-nums; }
.rcpt-kv-row { display: flex; justify-content: space-between; gap: 4px; }
.rcpt-kv-row > .rcpt-label { font-weight: 700; }
.rcpt-paragraph { white-space: pre-wrap; word-break: break-word; }

@media print {
    @page { margin: 0; }
}

/* ===== Editor mode ===== */
.rcpt-canvas.is-editable {
    background-color: #fff;
    cursor: default;
    user-select: none;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(15,23,42,.08);
}
.rcpt-canvas.is-editable.show-grid {
    background-image:
        linear-gradient(to right, rgba(148,163,184,.10) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(148,163,184,.10) 1px, transparent 1px);
    background-size: 20px 20px;
}
.rcpt-canvas.is-editable .rcpt-block {
    cursor: move;
    outline: 1px solid transparent;
    outline-offset: -1px;
    transition: outline-color .1s, box-shadow .1s;
}
.rcpt-canvas.is-editable .rcpt-block:hover { outline-color: rgba(37,99,235,.5); }
.rcpt-canvas.is-editable .rcpt-block.is-disabled { opacity: .35; }
.rcpt-canvas.is-editable .rcpt-block.is-selected { z-index: 999; }
.rcpt-canvas.is-editable .rcpt-selection-frame {
    position: absolute;
    pointer-events: none;
    border: 2px solid #2563eb;
    box-sizing: border-box;
    box-shadow: 0 0 0 2px rgba(37,99,235,.15);
    z-index: 998;
    border-radius: 2px;
}
.rcpt-canvas.is-editable .rcpt-block.is-dragging { opacity: .6; }

.rcpt-canvas .rcpt-block-empty {
    color: #94a3b8; font-style: italic; font-size: .85em;
    display: flex; align-items: center; justify-content: center;
    height: 100%; pointer-events: none;
}

.rcpt-handle {
    position: absolute;
    width: 10px; height: 10px;
    background: #fff;
    border: 1.5px solid #2563eb;
    border-radius: 2px;
    z-index: 1000;
    box-shadow: 0 1px 2px rgba(15,23,42,.18);
}
.rcpt-handle:hover { background: #2563eb; }
.rcpt-handle.h-n { top: -5px; left: 50%; margin-left: -5px; cursor: ns-resize; }
.rcpt-handle.h-s { bottom: -5px; left: 50%; margin-left: -5px; cursor: ns-resize; }
.rcpt-handle.h-e { right: -5px; top: 50%; margin-top: -5px; cursor: ew-resize; }
.rcpt-handle.h-w { left: -5px; top: 50%; margin-top: -5px; cursor: ew-resize; }
.rcpt-handle.h-ne { top: -5px; right: -5px; cursor: nesw-resize; }
.rcpt-handle.h-nw { top: -5px; left: -5px; cursor: nwse-resize; }
.rcpt-handle.h-se { bottom: -5px; right: -5px; cursor: nwse-resize; }
.rcpt-handle.h-sw { bottom: -5px; left: -5px; cursor: nesw-resize; }

.rcpt-marquee {
    position: absolute;
    border: 1px dashed #2563eb;
    background: rgba(37,99,235,.07);
    pointer-events: none;
}

.rcpt-canvas-resize-handle {
    position: absolute;
    left: 0; right: 0; bottom: -6px;
    height: 12px; cursor: ns-resize;
    z-index: 1001;
    display: flex; align-items: center; justify-content: center;
}
.rcpt-canvas-resize-handle::after {
    content: ''; width: 60px; height: 4px; border-radius: 2px;
    background: #cbd5e1; transition: background .15s;
}
.rcpt-canvas-resize-handle:hover::after { background: #2563eb; }

.rcpt-snap-line {
    position: absolute;
    background: #ec4899;
    pointer-events: none;
    z-index: 998;
}
.rcpt-snap-line.snap-h { left: 0; right: 0; height: 1px; }
.rcpt-snap-line.snap-v { top: 0; bottom: 0; width: 1px; }
`

let injected = false
export function ensureReceiptStylesInjected(targetDocument: Document = document): void {
    if (targetDocument === document && injected) return
    if (targetDocument.getElementById('rcpt-styles')) {
        if (targetDocument === document) injected = true
        return
    }
    const style = targetDocument.createElement('style')
    style.id = 'rcpt-styles'
    style.textContent = RECEIPT_STYLES
    targetDocument.head.appendChild(style)
    if (targetDocument === document) injected = true
}
