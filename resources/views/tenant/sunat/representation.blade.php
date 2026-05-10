@php
    /**
     * Representación impresa SUNAT (formato A4) — boleta/factura/NC/ND.
     *
     * Cumple con R 097-2012/SUNAT y modificatorias:
     *  - Datos completos de emisor + receptor
     *  - Detalle de ítems con cantidad, U.M., valor unitario, IGV, total
     *  - Op. Gravadas / IGV / Total
     *  - Importe en letras (formato SUNAT)
     *  - Hash del XML
     *  - QR pipe-separated (RUC|TipoDoc|Serie|Correlativo|MtoIGV|MtoTotal|Fecha|TipoDocReceptor|NumDocReceptor|Hash)
     *  - Leyendas obligatorias según operación
     *  - Forma de pago (contado/crédito)
     *
     * Variables esperadas:
     *   $sale         App\Models\Sale (con relaciones cargadas)
     *   $company      App\Models\Company  (incluye logo_path, brand_color, invoice_footer)
     *   $partner      App\Models\Partner|null
     *   $journal      App\Models\Journal
     *   $items        Collection<App\Models\Productable>
     *   $docTypeName  string  ej. "BOLETA DE VENTA ELECTRÓNICA"
     *   $hash         string|null
     *   $qrDataUri    string|null  data:image/png;base64,...
     *   $amountWords  string  importe en letras
     */
    use Illuminate\Support\Facades\Storage;

    $currency = 'S/';
    $issueDate = $sale->date ?? $sale->created_at;
    $docNumber = trim(($sale->serie ?? '').'-'.($sale->correlative ?? ''));
    $docType = (string) ($journal?->document_type_code ?? '');
    $isCreditNote = $docType === '07';
    $isDebitNote  = $docType === '08';
    $isFactura    = $docType === '01';
    $isBoleta     = $docType === '03';

    // Branding
    $brandColor = $company->brand_color ?: '#1f2937';
    $invoiceFooter = trim((string) ($company->invoice_footer ?? ''));
    $logoDataUri = null;
    if (! empty($company->logo_path) && Storage::disk('local')->exists($company->logo_path)) {
        $mime = Storage::disk('local')->mimeType($company->logo_path) ?: 'image/png';
        $logoDataUri = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($company->logo_path));
    }

    // Forma de pago
    $isCredit = ($sale->payment_status ?? null) === 'unpaid';
    $paymentForm = $isCredit ? 'CRÉDITO' : 'CONTADO';

    // Leyendas obligatorias SUNAT
    $legends = [];
    $legends[] = ['code' => '1000', 'value' => $amountWords];
    $hasGratuitous = $items->contains(fn ($l) => (float) ($l->total ?? 0) === 0.0);
    if ($hasGratuitous) {
        $legends[] = ['code' => '1002', 'value' => 'TRANSFERENCIA GRATUITA DE UN BIEN Y/O SERVICIO PRESTADO GRATUITAMENTE'];
    }

    // Paginación: dividir items en páginas A4 (~22 items la primera, ~28 las siguientes)
    $itemsArray = $items->values();
    $firstPageLimit = 18;
    $otherPageLimit = 28;
    $pages = [];
    $i = 0;
    $total = $itemsArray->count();
    while ($i < $total) {
        $limit = empty($pages) ? $firstPageLimit : $otherPageLimit;
        $slice = $itemsArray->slice($i, $limit)->values();
        $pages[] = $slice;
        $i += $limit;
    }
    if (empty($pages)) $pages = [collect()];
    $totalPages = count($pages);

    // Estado SUNAT
    $sunatStatus = $sale->sunat_status ?? 'pending';
    $statusLabel = [
        'accepted' => 'Aceptado por SUNAT',
        'error' => 'Rechazado por SUNAT',
        'sent' => 'Enviado a SUNAT',
        'pending' => 'Pendiente',
        'processing' => 'Procesando',
        'skipped' => 'No aplicable',
    ][$sunatStatus] ?? $sunatStatus;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>{{ $docTypeName }} {{ $docNumber }}</title>
<style>
    * { box-sizing: border-box; }
    html, body {
        margin: 0;
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        font-size: 11px;
        color: #1f2937;
        background: #f3f4f6;
        overflow-x: hidden; /* el wrap escala visualmente, no layout */
    }
    /* La hoja siempre es A4 real — en pantalla se ajusta visualmente con
       transform: scale() vía JS para caber en el iframe (auto-fit). */
    .page {
        width: 210mm;
        min-height: 297mm;
        background: #ffffff;
        padding: 12mm 12mm 14mm;
        margin: 0 0 8mm;
        box-shadow: 0 1px 4px rgba(0,0,0,0.10);
        page-break-after: always;
        position: relative;
        display: flex;
        flex-direction: column;
    }
    .page.last { page-break-after: auto; margin-bottom: 0; }

    /* Wrapper anclado top-left para evitar offset al escalar.
       JS calcula scale + margin-left para centrar el A4 escalado. */
    #pages-wrap {
        transform-origin: top left;
        transition: transform 120ms ease-out;
        width: 210mm;
        margin: 8px 0 0;
    }

    @media print {
        @page { size: A4; margin: 0; }
        html, body { background: #ffffff; padding: 0; margin: 0; overflow: visible; }
        #pages-wrap { transform: none !important; margin: 0 !important; width: 210mm; }
        .page { margin: 0; box-shadow: none; }
    }

    .header {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        flex-wrap: wrap;
        border-bottom: 2px solid {{ $brandColor }};
        padding-bottom: 10px;
        margin-bottom: 12px;
    }
    .issuer { flex: 1 1 220px; display: flex; gap: 10px; align-items: flex-start; min-width: 0; }
    .issuer .logo {
        width: 70px; height: 70px;
        object-fit: contain;
        flex: 0 0 auto;
    }
    .issuer .logo-fallback {
        width: 70px; height: 70px;
        background: {{ $brandColor }};
        color: #ffffff;
        display: flex; align-items: center; justify-content: center;
        font-size: 28px; font-weight: 700;
        border-radius: 6px;
        flex: 0 0 auto;
    }
    .issuer .info { flex: 1 1 auto; }
    .issuer .name {
        font-size: 15px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 2px;
    }
    .issuer .meta {
        font-size: 10px;
        color: #4b5563;
        line-height: 1.5;
    }
    .docbox {
        flex: 0 1 180px;
        min-width: 150px;
        border: 1.5px solid {{ $brandColor }};
        border-radius: 6px;
        padding: 8px;
        text-align: center;
    }
    .docbox .ruc {
        font-size: 10px;
        color: #4b5563;
    }
    .docbox .type {
        font-size: 11px;
        font-weight: 700;
        margin: 4px 0 6px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: {{ $brandColor }};
    }
    .docbox .num {
        font-size: 16px;
        font-weight: 700;
        color: #111827;
        letter-spacing: 0.6px;
    }

    /* Header reducido para páginas siguientes */
    .header-mini {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid {{ $brandColor }};
        padding-bottom: 6px;
        margin-bottom: 10px;
        font-size: 10px;
        color: #4b5563;
    }
    .header-mini strong { color: #111827; }

    .section {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 8px 10px;
        margin-bottom: 10px;
    }
    .section h4 {
        margin: 0 0 6px;
        font-size: 9px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6b7280;
    }
    .grid2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4px 16px;
        font-size: 10px;
    }
    .grid2 .k { color: #6b7280; }
    .grid2 .v { font-weight: 500; }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
        table-layout: fixed;
    }
    tbody td > div { word-wrap: break-word; overflow-wrap: anywhere; }
    thead th {
        background: #f3f4f6;
        font-size: 9px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #4b5563;
        padding: 6px 6px;
        border-bottom: 1px solid #d1d5db;
        text-align: left;
    }
    thead th.num { text-align: right; }
    tbody td {
        padding: 5px 6px;
        border-bottom: 1px solid #f3f4f6;
        font-size: 10px;
        vertical-align: top;
    }
    tbody td.num { text-align: right; font-variant-numeric: tabular-nums; }

    .totals {
        margin-left: auto;
        width: 100%;
        max-width: 280px;
        border-top: 2px solid {{ $brandColor }};
        padding-top: 6px;
        margin-bottom: 10px;
    }
    .totals .row {
        display: flex;
        justify-content: space-between;
        padding: 2px 0;
        font-size: 10px;
    }
    .totals .row.total {
        font-size: 13px;
        font-weight: 700;
        border-top: 1px solid {{ $brandColor }};
        padding-top: 5px;
        margin-top: 4px;
        color: #111827;
    }

    .legend {
        margin-bottom: 8px;
        padding: 6px 10px;
        background: #f9fafb;
        border-left: 3px solid {{ $brandColor }};
        border-radius: 3px;
        font-size: 10px;
    }
    .legend .label {
        font-weight: 600;
        color: #4b5563;
        text-transform: uppercase;
        font-size: 9px;
        letter-spacing: 0.4px;
        margin-bottom: 2px;
    }

    .pay-form {
        display: flex; gap: 12px;
        margin-bottom: 8px;
        font-size: 10px;
    }
    .pay-form .pill {
        background: #f3f4f6;
        padding: 4px 10px;
        border-radius: 4px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: {{ $brandColor }};
    }

    .qr-block {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        margin-top: 8px;
        padding-top: 10px;
        border-top: 1px solid #e5e7eb;
    }
    .qr-block img { width: 110px; height: 110px; flex: 0 0 auto; }
    .qr-block .qr-info { flex: 1 1 auto; font-size: 9px; color: #6b7280; line-height: 1.5; }
    .qr-block .qr-info .label {
        text-transform: uppercase; letter-spacing: 0.4px;
        color: #4b5563; font-weight: 600;
        font-size: 8px;
    }
    .hash {
        font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
        font-size: 8px;
        color: #6b7280;
        word-break: break-all;
    }

    .footer-bar {
        margin-top: auto;
        padding-top: 10px;
        border-top: 1px solid #e5e7eb;
        font-size: 9px;
        color: #6b7280;
        display: flex;
        justify-content: space-between;
        gap: 12px;
    }
    .footer-bar .free-text { white-space: pre-wrap; }
    .page-num { font-size: 9px; color: #9ca3af; text-align: right; }

    .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        font-weight: 600;
    }
    .badge.accepted { background: #dcfce7; color: #166534; }
    .badge.error { background: #fee2e2; color: #991b1b; }
    .badge.sent { background: #dbeafe; color: #1e40af; }
    .badge.pending { background: #f3f4f6; color: #4b5563; }
    .badge.processing { background: #fef3c7; color: #92400e; }
    .badge.skipped { background: #f3f4f6; color: #6b7280; }

    .ref {
        background: #fffbeb;
        border: 1px solid #fde68a;
        color: #92400e;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 10px;
        margin-bottom: 8px;
    }
</style>
</head>
<body>
<div id="pages-wrap">
@foreach($pages as $pageIdx => $pageItems)
@php $isFirst = $pageIdx === 0; $isLast = $pageIdx === $totalPages - 1; @endphp
<div class="page @if($isLast) last @endif">

    {{-- ===== HEADER ===== --}}
    @if($isFirst)
        <div class="header">
            <div class="issuer">
                @if($logoDataUri)
                    <img class="logo" src="{{ $logoDataUri }}" alt="Logo">
                @else
                    <div class="logo-fallback">
                        {{ mb_strtoupper(mb_substr($company->business_name ?? '?', 0, 1)) }}
                    </div>
                @endif
                <div class="info">
                    <div class="name">{{ $company->business_name ?? '—' }}</div>
                    <div class="meta">
                        @if($company->trade_name){{ $company->trade_name }}<br>@endif
                        {{ $company->address ?? '' }}@if($company->ubigeo) · UBIGEO {{ $company->ubigeo }}@endif<br>
                        @if($company->phone)Tel: {{ $company->phone }}@endif
                        @if($company->email) · {{ $company->email }}@endif
                    </div>
                </div>
            </div>
            <div class="docbox">
                <div class="ruc">RUC {{ $company->ruc ?? '—' }}</div>
                <div class="type">{{ $docTypeName }}</div>
                <div class="num">{{ $docNumber }}</div>
            </div>
        </div>

        @if($isCreditNote || $isDebitNote)
            @php $orig = $sale->originalSale ?? null; @endphp
            @if($orig)
                <div class="ref">
                    <strong>Documento que modifica:</strong>
                    {{ $orig->serie }}-{{ $orig->correlative }}
                    @if($orig->journal?->name) ({{ $orig->journal->name }}) @endif
                </div>
            @endif
        @endif

        <div class="section">
            <h4>Datos del Cliente</h4>
            <div class="grid2">
                <div><span class="k">Razón Social / Nombre:</span> <span class="v">{{ $partner?->name ?? 'VARIOS' }}</span></div>
                <div><span class="k">Documento:</span> <span class="v">{{ $partner?->document_type ?? '-' }} {{ $partner?->document_number ?? '-' }}</span></div>
                <div><span class="k">Dirección:</span> <span class="v">{{ $partner?->address ?? '—' }}</span></div>
                <div><span class="k">Fecha de Emisión:</span> <span class="v">{{ $issueDate?->format('d/m/Y H:i') ?? '—' }}</span></div>
            </div>
        </div>

        <div class="pay-form">
            <span class="pill">FORMA DE PAGO: {{ $paymentForm }}</span>
            <span class="pill">MONEDA: SOLES</span>
        </div>
    @else
        <div class="header-mini">
            <span><strong>{{ $company->business_name ?? '—' }}</strong> · RUC {{ $company->ruc ?? '—' }}</span>
            <span><strong>{{ $docTypeName }}</strong> {{ $docNumber }}</span>
        </div>
    @endif

    {{-- ===== ITEMS ===== --}}
    <table>
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th style="width:34%">Descripción</th>
                <th class="num" style="width:8%">Cant.</th>
                <th style="width:8%">U.M.</th>
                <th class="num" style="width:11%">P. Unit.</th>
                <th class="num" style="width:11%">Subtotal</th>
                <th class="num" style="width:11%">IGV</th>
                <th class="num" style="width:13%">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pageItems as $i => $line)
                @php
                    $globalIndex = ($pageIdx === 0 ? 0 : $firstPageLimit + ($pageIdx - 1) * $otherPageLimit) + $i;
                    $name = $line->productProduct?->template?->name ?? '—';
                    $sku = $line->productProduct?->sku;
                    $qty = (float) $line->quantity;
                    $uom = $line->uom?->symbol ?? 'UND';
                    $unit = (float) $line->price;
                    $sub = (float) $line->subtotal;
                    $igv = (float) $line->tax_amount;
                    $tot = (float) $line->total;
                @endphp
                <tr>
                    <td>{{ $globalIndex + 1 }}</td>
                    <td>
                        <div style="font-weight:500">{{ $name }}</div>
                        @if($sku)<div style="font-size:9px;color:#9ca3af">SKU {{ $sku }}</div>@endif
                        @if($line->lot_number_input)
                            <div style="font-size:9px;color:#9ca3af">Lote {{ $line->lot_number_input }}</div>
                        @elseif($line->lot?->lot_number)
                            <div style="font-size:9px;color:#9ca3af">Lote {{ $line->lot->lot_number }}</div>
                        @endif
                    </td>
                    <td class="num">{{ number_format($qty, 2) }}</td>
                    <td>{{ $uom }}</td>
                    <td class="num">{{ $currency }} {{ number_format($unit, 2) }}</td>
                    <td class="num">{{ $currency }} {{ number_format($sub, 2) }}</td>
                    <td class="num">{{ $currency }} {{ number_format($igv, 2) }}</td>
                    <td class="num">{{ $currency }} {{ number_format($tot, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ===== TOTALES + LEYENDAS + QR (solo última página) ===== --}}
    @if($isLast)
        <div class="totals">
            <div class="row">
                <span>Op. Gravadas</span>
                <span>{{ $currency }} {{ number_format((float) $sale->subtotal, 2) }}</span>
            </div>
            <div class="row">
                <span>IGV (18%)</span>
                <span>{{ $currency }} {{ number_format((float) $sale->tax_amount, 2) }}</span>
            </div>
            <div class="row total">
                <span>Importe Total</span>
                <span>{{ $currency }} {{ number_format((float) $sale->total, 2) }}</span>
            </div>
        </div>

        @foreach($legends as $leg)
            <div class="legend">
                <div class="label">Leyenda {{ $leg['code'] }}</div>
                <div>{{ $leg['value'] }}</div>
            </div>
        @endforeach

        @if($sale->notes)
            <div class="section">
                <h4>Observaciones</h4>
                <div style="font-size:10px;white-space:pre-wrap">{{ $sale->notes }}</div>
            </div>
        @endif

        <div class="qr-block">
            @if($qrDataUri)
                <img src="{{ $qrDataUri }}" alt="QR SUNAT">
            @else
                <div style="width:110px;height:110px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:9px;text-align:center;">QR pendiente<br>(envío SUNAT)</div>
            @endif
            <div class="qr-info">
                <div class="label">Estado SUNAT</div>
                <div style="margin-bottom:4px;">
                    <span class="badge {{ $sunatStatus }}">{{ $statusLabel }}</span>
                    @if($sale->sunat_sent_at)
                        · Enviado: {{ $sale->sunat_sent_at?->format('d/m/Y H:i') }}
                    @endif
                </div>
                @if($hash)
                    <div class="label">Hash del XML</div>
                    <div class="hash">{{ $hash }}</div>
                @endif
                <div style="margin-top:6px;color:#9ca3af;">
                    Representación impresa del comprobante electrónico. Consulte autenticidad en
                    <strong>www.sunat.gob.pe</strong>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== FOOTER (todas las páginas) ===== --}}
    <div class="footer-bar">
        <div class="free-text">@if($invoiceFooter && $isLast){{ $invoiceFooter }}@endif</div>
        <div class="page-num">Página {{ $pageIdx + 1 }} de {{ $totalPages }}</div>
    </div>
</div>
@endforeach
</div>

<script>
    // Auto-fit: escala #pages-wrap (top-left) y centra horizontalmente con
    // margin-left calculado, para que la hoja A4 quepa en el ancho del iframe
    // sin offset ni recorte. Recalcula en resize, load y mutaciones.
    (function () {
        var A4_PX = 210 * 96 / 25.4; // 210mm a 96dpi ≈ 793.7px
        var wrap = document.getElementById('pages-wrap');
        if (!wrap) return;

        function fit() {
            // Reset para medir altura natural sin contar transform/margen previo.
            wrap.style.transform = '';
            wrap.style.height = '';
            wrap.style.marginLeft = '';
            var natural = wrap.scrollHeight;
            // Usamos el ancho real del cliente (sin scrollbar vertical).
            var available = document.documentElement.clientWidth || window.innerWidth;
            var scale = Math.min(1, (available - 8) / A4_PX);
            if (scale <= 0 || !isFinite(scale)) scale = 1;
            var scaledW = A4_PX * scale;
            wrap.style.transform = 'scale(' + scale + ')';
            wrap.style.height = (natural * scale) + 'px';
            // Centrar horizontalmente: si sobra espacio, empujar con margin-left.
            wrap.style.marginLeft = Math.max(0, (available - scaledW) / 2) + 'px';
        }

        fit();
        window.addEventListener('resize', fit);
        window.addEventListener('load', fit);
        if (typeof ResizeObserver !== 'undefined') {
            try { new ResizeObserver(fit).observe(document.documentElement); } catch (e) {}
        }
    })();
</script>
</body>
</html>
