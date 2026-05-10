@php
    /**
     * Representación impresa SUNAT — Guía de Remisión Electrónica (GRE-Remitente, cód 09).
     *
     * Cumple con R 097-2012/SUNAT y la R-2022 de GRE:
     *  - Datos del emisor + destinatario (en motivo 04, mismo RUC)
     *  - Punto de partida + punto de llegada (warehouses con ubigeo + dirección)
     *  - Detalle de ítems (cantidad, UM, descripción, código) — SIN precios
     *  - Datos del traslado: motivo + modalidad + fecha + peso + bultos
     *  - Datos del transporte: vehículo (placa) + conductor (DNI, licencia)
     *  - Hash del XML
     *  - QR pipe-separated específico GRE (sin montos)
     *
     * Variables esperadas:
     *   $transfer     App\Models\Transfer (con relaciones cargadas)
     *   $company      App\Models\Company   (remitente, con branding)
     *   $exitWh       App\Models\Warehouse (origen)
     *   $entryWh      App\Models\Warehouse (destino)
     *   $items        Collection<App\Models\Productable>  (líneas del exit)
     *   $docTypeName  string  ej. "GUÍA DE REMISIÓN ELECTRÓNICA - REMITENTE"
     *   $hash         string|null
     *   $qrDataUri    string|null  data:image/png;base64,...
     */
    use Illuminate\Support\Facades\Storage;

    $issueDate = $transfer->date ?? $transfer->created_at;
    $docNumber = trim(($transfer->serie ?? '').'-'.($transfer->correlative ?? ''));

    // Branding (mismo patrón que Sale)
    $brandColor = $company->brand_color ?: '#1f2937';
    $invoiceFooter = trim((string) ($company->invoice_footer ?? ''));
    $logoDataUri = null;
    if (! empty($company->logo_path) && Storage::disk('local')->exists($company->logo_path)) {
        $mime = Storage::disk('local')->mimeType($company->logo_path) ?: 'image/png';
        $logoDataUri = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($company->logo_path));
    }

    // Catálogos GRE
    $motivoCode = (string) ($transfer->gre_motive_code ?? '04');
    $motivoLabel = [
        '01' => 'Venta',
        '02' => 'Compra',
        '04' => 'Traslado entre establecimientos de la misma empresa',
        '08' => 'Importación',
        '09' => 'Exportación',
        '13' => 'Otros',
    ][$motivoCode] ?? 'Traslado';

    $modalityCode = (string) ($transfer->gre_modality ?? 'private');
    $modalityLabel = $modalityCode === 'public' ? 'PÚBLICA (transportista)' : 'PRIVADA';

    // Paginación: ítems por hoja A4 (~22 primera, ~28 siguientes)
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

    // Estado SUNAT GRE (gre_status)
    $sunatStatus = $transfer->gre_status ?? 'pending';
    $statusLabel = [
        'accepted' => 'Aceptado por SUNAT',
        'error' => 'Rechazado por SUNAT',
        'sent' => 'Enviado a SUNAT',
        'ticket_pending' => 'Ticket en cola SUNAT',
        'processing' => 'Procesando',
        'pending' => 'Pendiente',
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
        overflow-x: hidden;
    }
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
    .issuer .logo { width: 70px; height: 70px; object-fit: contain; flex: 0 0 auto; }
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
    .issuer .name { font-size: 15px; font-weight: 700; color: #111827; margin-bottom: 2px; }
    .issuer .meta { font-size: 10px; color: #4b5563; line-height: 1.5; }

    .docbox {
        flex: 0 1 200px;
        min-width: 160px;
        border: 1.5px solid {{ $brandColor }};
        border-radius: 6px;
        padding: 8px;
        text-align: center;
    }
    .docbox .ruc { font-size: 10px; color: #4b5563; }
    .docbox .type {
        font-size: 11px;
        font-weight: 700;
        margin: 4px 0 6px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: {{ $brandColor }};
    }
    .docbox .num { font-size: 16px; font-weight: 700; color: #111827; letter-spacing: 0.6px; }

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

    .twocol {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 10px;
    }
    .point {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 8px 10px;
    }
    .point h4 {
        margin: 0 0 4px;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: {{ $brandColor }};
    }
    .point .name { font-weight: 600; font-size: 11px; color: #111827; margin-bottom: 2px; }
    .point .addr { font-size: 10px; color: #4b5563; line-height: 1.4; }

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

    .pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; font-size: 10px; }
    .pill {
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
    .badge.sent, .badge.ticket_pending { background: #dbeafe; color: #1e40af; }
    .badge.pending { background: #f3f4f6; color: #4b5563; }
    .badge.processing { background: #fef3c7; color: #92400e; }
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

        {{-- Datos del traslado --}}
        <div class="section">
            <h4>Datos del Traslado</h4>
            <div class="grid2">
                <div><span class="k">Motivo ({{ $motivoCode }}):</span> <span class="v">{{ $motivoLabel }}</span></div>
                <div><span class="k">Modalidad:</span> <span class="v">{{ $modalityLabel }}</span></div>
                <div><span class="k">Fecha de Emisión:</span> <span class="v">{{ $issueDate?->format('d/m/Y H:i') ?? '—' }}</span></div>
                <div><span class="k">Inicio del Traslado:</span> <span class="v">{{ $transfer->gre_transfer_start_date?->format('d/m/Y') ?? '—' }}</span></div>
                <div><span class="k">Peso Bruto Total:</span> <span class="v">{{ number_format((float) ($transfer->gre_gross_weight ?? 0), 3) }} KGM</span></div>
                <div><span class="k">N° de Bultos:</span> <span class="v">{{ (int) ($transfer->gre_packages ?? 0) }}</span></div>
            </div>
        </div>

        {{-- Punto de partida + llegada --}}
        <div class="twocol">
            <div class="point">
                <h4>Punto de Partida</h4>
                <div class="name">{{ $exitWh->name ?? '—' }}</div>
                <div class="addr">
                    {{ $exitWh->address_line ?? $exitWh->location ?? '—' }}<br>
                    @if($exitWh->ubigeo)UBIGEO {{ $exitWh->ubigeo }}@endif
                    @if($exitWh->establishment_code) · Cód. Local {{ $exitWh->establishment_code }}@endif
                </div>
            </div>
            <div class="point">
                <h4>Punto de Llegada</h4>
                <div class="name">{{ $entryWh->name ?? '—' }}</div>
                <div class="addr">
                    {{ $entryWh->address_line ?? $entryWh->location ?? '—' }}<br>
                    @if($entryWh->ubigeo)UBIGEO {{ $entryWh->ubigeo }}@endif
                    @if($entryWh->establishment_code) · Cód. Local {{ $entryWh->establishment_code }}@endif
                </div>
            </div>
        </div>

        {{-- Datos del transporte --}}
        <div class="section">
            <h4>Datos del Transporte</h4>
            <div class="grid2">
                <div><span class="k">Vehículo (placa):</span> <span class="v">{{ $transfer->gre_vehicle_plate ?? '—' }}</span></div>
                <div><span class="k">Conductor:</span> <span class="v">{{ $transfer->gre_driver_name ?? '—' }}</span></div>
                <div><span class="k">Documento:</span> <span class="v">{{ $transfer->gre_driver_doc_type ?? '—' }} {{ $transfer->gre_driver_doc_number ?? '—' }}</span></div>
                <div><span class="k">Licencia de Conducir:</span> <span class="v">{{ $transfer->gre_driver_license ?? '—' }}</span></div>
            </div>
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
                <th style="width:6%">#</th>
                <th style="width:14%">Código</th>
                <th style="width:54%">Descripción</th>
                <th class="num" style="width:13%">Cantidad</th>
                <th style="width:13%">U.M.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pageItems as $i => $line)
                @php
                    $globalIndex = ($pageIdx === 0 ? 0 : $firstPageLimit + ($pageIdx - 1) * $otherPageLimit) + $i;
                    $name = $line->productProduct?->template?->name ?? '—';
                    $sku = $line->productProduct?->sku ?? $line->productProduct?->barcode ?? '—';
                    $qty = (float) $line->quantity;
                    $uom = $line->uom?->code ?? $line->uom?->symbol ?? 'NIU';
                @endphp
                <tr>
                    <td>{{ $globalIndex + 1 }}</td>
                    <td>{{ $sku }}</td>
                    <td>
                        <div style="font-weight:500">{{ $name }}</div>
                        @if($line->lot_number_input)
                            <div style="font-size:9px;color:#9ca3af">Lote {{ $line->lot_number_input }}</div>
                        @elseif($line->lot?->lot_number)
                            <div style="font-size:9px;color:#9ca3af">Lote {{ $line->lot->lot_number }}</div>
                        @endif
                    </td>
                    <td class="num">{{ number_format($qty, 2) }}</td>
                    <td>{{ $uom }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ===== OBSERVACIONES + QR (solo última página) ===== --}}
    @if($isLast)
        @if($transfer->observation)
            <div class="section">
                <h4>Observaciones</h4>
                <div style="font-size:10px;white-space:pre-wrap">{{ $transfer->observation }}</div>
            </div>
        @endif

        <div class="qr-block">
            @if($qrDataUri)
                <img src="{{ $qrDataUri }}" alt="QR SUNAT GRE">
            @else
                <div style="width:110px;height:110px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:9px;text-align:center;">QR pendiente<br>(envío SUNAT)</div>
            @endif
            <div class="qr-info">
                <div class="label">Estado SUNAT</div>
                <div style="margin-bottom:4px;">
                    <span class="badge {{ $sunatStatus }}">{{ $statusLabel }}</span>
                    @if($transfer->gre_sent_at)
                        · Enviado: {{ $transfer->gre_sent_at?->format('d/m/Y H:i') }}
                    @endif
                </div>
                @if($hash)
                    <div class="label">Hash del XML</div>
                    <div class="hash">{{ $hash }}</div>
                @endif
                <div style="margin-top:6px;color:#9ca3af;">
                    Representación impresa de la guía de remisión electrónica. Consulte autenticidad en
                    <strong>www.sunat.gob.pe</strong>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== FOOTER ===== --}}
    <div class="footer-bar">
        <div class="free-text">@if($invoiceFooter && $isLast){{ $invoiceFooter }}@endif</div>
        <div class="page-num">Página {{ $pageIdx + 1 }} de {{ $totalPages }}</div>
    </div>
</div>
@endforeach
</div>

<script>
    // Auto-fit: misma lógica que la representation.blade.php de Sale.
    (function () {
        var A4_PX = 210 * 96 / 25.4;
        var wrap = document.getElementById('pages-wrap');
        if (!wrap) return;

        function fit() {
            wrap.style.transform = '';
            wrap.style.height = '';
            wrap.style.marginLeft = '';
            var natural = wrap.scrollHeight;
            var available = document.documentElement.clientWidth || window.innerWidth;
            var scale = Math.min(1, (available - 8) / A4_PX);
            if (scale <= 0 || !isFinite(scale)) scale = 1;
            var scaledW = A4_PX * scale;
            wrap.style.transform = 'scale(' + scale + ')';
            wrap.style.height = (natural * scale) + 'px';
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
