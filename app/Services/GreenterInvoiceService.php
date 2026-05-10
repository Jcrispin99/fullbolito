<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BillingCredential;
use App\Models\Company;
use App\Models\Sale;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Envío de comprobantes electrónicos a SUNAT vía Greenter.
 *
 * Adaptaciones a este proyecto (vs. la referencia original):
 *  - Las credenciales SOL + certificado viven en `BillingCredential`
 *    (singleton por tenant), no en `Company`. Company sigue aportando
 *    RUC + razón social + dirección + ubigeo.
 *  - El certificado se lee del disk privado `local` (que el
 *    `FilesystemTenancyBootstrapper` redirige a `storage/{tenant}/app/`).
 *  - `is_service` vive en `ProductTemplate` (accesible vía
 *    `productProduct->template->is_service`).
 *  - Si la línea tiene lote asociado (`Productable::lot_id`), se anexa
 *    `(Lote: XXX)` a la descripción del item — útil para industrias con
 *    trazabilidad de lote en factura PDF.
 *
 * Canal por defecto: firma local con greenter/lite. Si no hay
 * BillingCredential activa o el certificado no existe, cae al canal API
 * (PSE externo) configurado en `config/services.greenter`.
 */
class GreenterInvoiceService
{
    private string $baseUrl;

    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.greenter.api_url', ''), '/').'/';
        $this->token = trim((string) config('services.greenter.api_token', ''));
    }

    public function sendInvoiceFromSale(Sale $sale): bool
    {
        $sale->loadMissing([
            'journal',
            'company',
            'partner',
            'products.tax',
            'products.lot',
            'products.productProduct.template',
            'originalSale.journal',
            'originalSale.products',
        ]);

        $journal = $sale->journal;
        $docType = (string) ($journal->document_type_code ?? '');
        $isFiscal = (bool) ($journal->is_fiscal ?? false);

        // Sólo facturas (01), boletas (03), notas de crédito (07) y débito (08).
        if (! $isFiscal || ! in_array($docType, ['01', '03', '07', '08'], true)) {
            $sale->sunat_status = 'skipped';
            $sale->sunat_response = [
                'accepted' => false,
                'error' => $isFiscal ? 'Tipo de documento no soportado' : 'No fiscal',
                'updated_at' => now()->toIso8601String(),
            ];
            $sale->save();

            $this->logSunatActivity($sale, 'sunat.skipped', 'No se envía a SUNAT (documento no fiscal o tipo no soportado)');

            return true;
        }

        // Idempotencia: si ya fue aceptado, no reenviar.
        $alreadyAccepted = data_get($sale->sunat_response, 'accepted') === true
            || $sale->sunat_status === 'accepted';
        if ($alreadyAccepted) {
            return true;
        }

        // Validación de notas (07/08): debe existir venta original 01/03 con numeración.
        if (in_array($docType, ['07', '08'], true)) {
            $original = $sale->originalSale;
            $hasNumber = ! empty($original?->serie) && ! empty($original?->correlative);
            $affectedType = (string) ($original?->journal?->document_type_code ?? '');

            if (! $original || ! $hasNumber || ! in_array($affectedType, ['01', '03'], true)) {
                $sale->sunat_status = 'error';
                $sale->sunat_response = [
                    'accepted' => false,
                    'error' => 'Falta venta original válida para Nota de Crédito/Débito (01/03 con numeración).',
                    'updated_at' => now()->toIso8601String(),
                ];
                $sale->save();

                $this->logSunatActivity($sale, 'sunat.failed', 'Nota sin venta original válida (07/08 requiere 01/03)');

                return false;
            }
        }

        $sale->sunat_status = 'processing';
        $sale->save();

        $this->logSunatActivity($sale, 'sunat.sent', 'Envío a SUNAT iniciado', [
            'serie' => $sale->serie,
            'correlative' => $sale->correlative,
            'doc_type' => $docType,
        ]);

        $payload = in_array($docType, ['07', '08'], true)
            ? $this->buildNotePayloadFromSale($sale, $docType)
            : $this->buildPayloadFromSale($sale);
        $payload = $this->withComputedTotalsAndLegends($payload);

        try {
            $credential = $this->resolveBillingCredential();
            $useLocal = $this->shouldUseLocalGreenter($credential, $sale->company);

            if ($useLocal && ! class_exists('Greenter\\See')) {
                $result = $this->sendViaApi($payload, $docType);
                $result['sunat_response']['local_fallback'] = 'greenter/lite no instalado';
            } else {
                $result = $useLocal
                    ? $this->sendViaLocalGreenter($credential, $sale->company, $sale, $payload, $docType)
                    : $this->sendViaApi($payload, $docType);
            }

            $sale->sunat_status = (string) ($result['sunat_status'] ?? 'error');
            $sale->sunat_response = array_merge(
                (array) ($result['sunat_response'] ?? []),
                ['updated_at' => now()->toIso8601String()]
            );
            $sale->sunat_sent_at = now();

            // Persistir paths de XML firmado y CDR si el canal local los archivó.
            // Se conservan incluso en envíos rechazados — son evidencia legal.
            if (isset($result['signed_xml_path'])) {
                $sale->signed_xml_path = (string) $result['signed_xml_path'];
            }
            if (isset($result['cdr_zip_path'])) {
                $sale->cdr_zip_path = (string) $result['cdr_zip_path'];
            }

            $sale->save();

            $accepted = data_get($result, 'sunat_response.accepted') === true
                || ($sale->sunat_status === 'accepted');
            $event = $accepted ? 'sunat.accepted' : (
                $sale->sunat_status === 'error' ? 'sunat.rejected' : 'sunat.sent'
            );
            $description = $accepted
                ? 'SUNAT aceptó el comprobante'
                : ($sale->sunat_status === 'error'
                    ? 'SUNAT rechazó el comprobante'
                    : 'SUNAT recibió el comprobante (sin CDR)');

            $this->logSunatActivity($sale, $event, $description, [
                'doc_type' => $docType,
                'serie' => $sale->serie,
                'correlative' => $sale->correlative,
                'channel' => data_get($result, 'sunat_response.channel'),
                'cdr_code' => data_get($result, 'sunat_response.cdr_code'),
                'description' => data_get($result, 'sunat_response.description'),
                'notes' => data_get($result, 'sunat_response.notes'),
                'has_signed_xml' => ! empty($result['signed_xml_path']),
                'has_cdr_zip' => ! empty($result['cdr_zip_path']),
            ]);

            return (bool) ($result['ok'] ?? false);
        } catch (\Throwable $e) {
            $sale->sunat_status = 'error';
            $sale->sunat_response = [
                'accepted' => false,
                'error' => $e->getMessage(),
                'updated_at' => now()->toIso8601String(),
            ];
            $sale->save();

            $this->logSunatActivity($sale, 'sunat.failed', 'Excepción durante envío a SUNAT', [
                'doc_type' => $docType,
                'serie' => $sale->serie,
                'correlative' => $sale->correlative,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Registra una entrada en activity log con `log_name='sunat'` para que
     * el visor de logs pueda reconstruir la línea de tiempo del envío.
     *
     * Se ejecuta dentro del job (sin sesión HTTP) por lo que no hay causer.
     *
     * @param  array<string, mixed>  $properties
     */
    private function logSunatActivity(Sale $sale, string $event, string $description, array $properties = []): void
    {
        try {
            activity('sunat')
                ->performedOn($sale)
                ->event($event)
                ->withProperties(array_merge($properties, [
                    'sunat_status' => $sale->sunat_status,
                ]))
                ->log($description);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo escribir activity log SUNAT', [
                'sale_id' => $sale->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resuelve la credencial activa del tenant (singleton). Si no existe,
     * se devuelve null y el servicio cae al canal API.
     */
    private function resolveBillingCredential(): ?BillingCredential
    {
        return BillingCredential::query()->active()->first();
    }

    private function shouldUseLocalGreenter(?BillingCredential $credential, ?Company $company): bool
    {
        if (! $credential || ! $company) {
            return false;
        }

        return filled($credential->cert_path)
            && filled($credential->sol_user)
            && filled($credential->sol_pass)
            && filled($company->ruc);
    }

    private function sendViaApi(array $payload, string $docType): array
    {
        if ($this->baseUrl === '/' || $this->token === '') {
            return [
                'ok' => false,
                'sunat_status' => 'error',
                'sunat_response' => [
                    'channel' => 'api',
                    'accepted' => false,
                    'error' => 'Falta configuración GREENTER_API_URL o GREENTER_API_TOKEN',
                ],
            ];
        }

        $endpoint = $this->baseUrl.(in_array($docType, ['07', '08'], true) ? 'notes/send' : 'invoices/send');

        /** @var Response $response */
        $response = Http::withToken($this->token)
            ->acceptJson()
            ->post($endpoint, $payload);

        $json = $response->json();
        $successFlag = data_get($json, 'response.SunatResponse.success')
            ?? data_get($json, 'body.response.SunatResponse.success')
            ?? data_get($json, 'data.SunatResponse.success');
        $cdrCode = data_get($json, 'response.SunatResponse.cdrResponse.code')
            ?? data_get($json, 'cdrResponse.code')
            ?? data_get($json, 'data.cdrResponse.code');
        $accepted = $response->successful() && ($successFlag === true || (is_numeric($cdrCode) && (int) $cdrCode === 0));

        return [
            'ok' => $response->successful(),
            'sunat_status' => $accepted ? 'accepted' : ($response->successful() ? 'sent' : 'error'),
            'sunat_response' => [
                'channel' => 'api',
                'http_status' => $response->status(),
                'accepted' => $accepted,
                'cdr_code' => is_numeric($cdrCode) ? (int) $cdrCode : null,
                'hash' => data_get($json, 'response.hash') ?? data_get($json, 'data.hash'),
                'document_id' => data_get($json, 'response.document_id') ?? data_get($json, 'data.document_id'),
                'payload_tipoDoc' => $payload['tipoDoc'] ?? null,
                'payload_serie' => $payload['serie'] ?? null,
                'payload_correlativo' => $payload['correlativo'] ?? null,
                'raw' => $json,
                'error' => $response->successful() ? null : $response->body(),
            ],
        ];
    }

    private function sendViaLocalGreenter(BillingCredential $credential, Company $company, Sale $sale, array $payload, string $docType): array
    {
        if (! class_exists('Greenter\\See')) {
            return [
                'ok' => false,
                'sunat_status' => 'error',
                'sunat_response' => [
                    'channel' => 'local',
                    'accepted' => false,
                    'error' => 'No se encontró greenter/lite instalado.',
                ],
            ];
        }

        // Cert vive en disk privado del tenant.
        if (! Storage::disk('local')->exists($credential->cert_path)) {
            return [
                'ok' => false,
                'sunat_status' => 'error',
                'sunat_response' => [
                    'channel' => 'local',
                    'accepted' => false,
                    'error' => 'No existe el certificado en cert_path.',
                ],
            ];
        }

        $seeClass = 'Greenter\\See';
        $endpointsClass = 'Greenter\\Ws\\Services\\SunatEndpoints';
        $see = new $seeClass();
        $see->setCertificate(Storage::disk('local')->get($credential->cert_path));
        $see->setService($credential->production ? $endpointsClass::FE_PRODUCCION : $endpointsClass::FE_BETA);
        $see->setClaveSOL((string) $company->ruc, (string) $credential->sol_user, (string) $credential->sol_pass);

        $document = in_array($docType, ['07', '08'], true)
            ? $this->buildGreenterNote($payload)
            : $this->buildGreenterInvoice($payload);

        $result = $see->send($document);
        $sunatResponse = $this->normalizeGreenterResult($result);
        $cdrCode = data_get($sunatResponse, 'cdrResponse.code');
        $accepted = ($sunatResponse['success'] ?? false) === true && is_numeric($cdrCode) && (int) $cdrCode === 0;

        // Archivar XML firmado + CDR ZIP en disk privado del tenant.
        // Conservación obligatoria por SUNAT (Res. 097-2012/SUNAT y act.).
        $archives = $this->archiveDocuments($sale, $company, $see, $result, $payload, $docType);

        return [
            'ok' => true,
            'sunat_status' => $accepted ? 'accepted' : (($sunatResponse['success'] ?? false) ? 'sent' : 'error'),
            'sunat_response' => [
                'channel' => 'local',
                'environment' => $credential->production ? 'produccion' : 'beta',
                'accepted' => $accepted,
                'cdr_code' => is_numeric($cdrCode) ? (int) $cdrCode : null,
                'hash' => $this->extractXmlHash($see),
                'sunat' => $sunatResponse,
                'payload_tipoDoc' => $payload['tipoDoc'] ?? null,
                'payload_serie' => $payload['serie'] ?? null,
                'payload_correlativo' => $payload['correlativo'] ?? null,
            ],
            'signed_xml_path' => $archives['xml'] ?? null,
            'cdr_zip_path' => $archives['cdr'] ?? null,
        ];
    }

    /**
     * Persiste el XML firmado y la CDR (si están disponibles) en el disk
     * privado del tenant. Devuelve los paths relativos guardados.
     *
     * Convención de nombres SUNAT: {RUC}-{tipoDoc}-{serie}-{correlativo}.xml
     * y la CDR con prefijo R-: R-{RUC}-{tipoDoc}-{serie}-{correlativo}.zip
     */
    private function archiveDocuments(Sale $sale, Company $company, $see, $result, array $payload, string $docType): array
    {
        $ruc = (string) ($company->ruc ?? '');
        $serie = (string) ($payload['serie'] ?? '');
        $correlativo = (string) ($payload['correlativo'] ?? '');
        $baseName = $ruc.'-'.$docType.'-'.$serie.'-'.$correlativo;
        $folder = 'billing/sales/'.$sale->id;

        $paths = [];

        try {
            $xml = $see->getFactory()->getLastXml();
            if (! empty($xml)) {
                $xmlPath = $folder.'/'.$baseName.'.xml';
                Storage::disk('local')->put($xmlPath, $xml);
                $paths['xml'] = $xmlPath;
            }
        } catch (\Throwable $e) {
            // No bloqueamos por fallo de archivado.
        }

        try {
            $cdrZip = method_exists($result, 'getCdrZip') ? $result->getCdrZip() : null;
            if (! empty($cdrZip)) {
                $cdrPath = $folder.'/R-'.$baseName.'.zip';
                Storage::disk('local')->put($cdrPath, $cdrZip);
                $paths['cdr'] = $cdrPath;
            }
        } catch (\Throwable $e) {
            // Ignorar — el envío principal ya quedó persistido.
        }

        return $paths;
    }

    private function normalizeGreenterResult($result): array
    {
        $success = $result->isSuccess();
        if (! $success) {
            return [
                'success' => false,
                'error' => [
                    'code' => $result->getError()?->getCode(),
                    'message' => $result->getError()?->getMessage(),
                ],
            ];
        }

        $cdr = $result->getCdrResponse();

        return [
            'success' => true,
            'cdrResponse' => [
                'code' => (int) $cdr->getCode(),
                'description' => $cdr->getDescription(),
                'notes' => $cdr->getNotes(),
            ],
        ];
    }

    public function buildPayloadFromSale(Sale $sale): array
    {
        $sale->loadMissing([
            'journal',
            'company',
            'partner',
            'products.tax',
            'products.lot',
            'products.productProduct.template',
        ]);

        $journal = $sale->journal;
        $docType = (string) ($journal->document_type_code ?? '01');

        $details = [];

        foreach ($sale->products as $line) {
            $qty = (float) ($line->quantity ?? 0);
            $base = (float) ($line->subtotal ?? 0);
            $igv = (float) ($line->tax_amount ?? 0);
            $rate = (float) ($line->tax_rate ?? 0);
            $unitBase = $qty > 0 ? ($base / $qty) : (float) ($line->price ?? 0);
            $unitWithTax = $unitBase * (1 + ($rate / 100));

            $tax = $line->tax;
            $tipAfeIgv = (string) ($tax?->affectation_type_code ?: ($rate > 0 ? '10' : '20'));
            $isGratuito = $tipAfeIgv === '21';

            $productProduct = $line->productProduct;
            $template = $productProduct?->template;
            $description = $productProduct?->display_name ?? 'Producto';

            // Anexar trazabilidad de lote si la línea está asociada a un lote.
            if ($line->lot && filled($line->lot->lot_number)) {
                $description .= ' (Lote: '.$line->lot->lot_number.')';
            }

            $sku = $productProduct?->sku;
            $barcode = $productProduct?->barcode;
            $isService = (bool) ($template?->is_service ?? false);

            $lineDetail = [
                'codProducto' => (string) ($barcode ?: ($sku ?: $line->id)),
                'unidad' => $isService ? 'ZZ' : 'NIU',
                'cantidad' => $qty,
                'descripcion' => $description,
                'mtoValorUnitario' => $isGratuito ? 0.0 : round($unitBase, 2),
                'mtoValorVenta' => $isGratuito ? 0.0 : round($base, 2),
                'mtoBaseIgv' => $isGratuito ? 0.0 : round($base, 2),
                'porcentajeIgv' => $isGratuito ? 0.0 : round($rate, 2),
                'igv' => $isGratuito ? 0.0 : round($igv, 2),
                'tipAfeIgv' => $tipAfeIgv,
                'totalImpuestos' => $isGratuito ? 0.0 : round($igv, 2),
                'mtoPrecioUnitario' => $isGratuito ? 0.0 : round($unitWithTax, 2),
            ];

            if ($isGratuito) {
                $lineDetail['mtoValorGratuito'] = round($unitWithTax, 2);
            }

            $details[] = $lineDetail;
        }

        $company = $sale->company;
        $partner = $sale->partner;

        return [
            'tipoDoc' => $docType,
            'tipoOperacion' => '0101',
            'serie' => (string) ($sale->serie ?? ''),
            'correlativo' => (string) ($sale->correlative ?? ''),
            'fechaEmision' => ($sale->date ?? now())->setTimezone('America/Lima')->format('Y-m-d\TH:i:sP'),
            'formaPago' => [
                'moneda' => 'PEN',
                'tipo' => 'Contado',
            ],
            'tipoMoneda' => 'PEN',
            'company' => [
                'ruc' => (string) ($company?->ruc ?? ''),
                'razonSocial' => (string) ($company?->business_name ?? $company?->trade_name ?? ''),
                'nombreComercial' => (string) ($company?->trade_name ?? ''),
                'address' => $this->buildCompanyAddress($company),
            ],
            'client' => [
                'tipoDoc' => $this->mapPartnerDocType($partner?->document_type),
                'numDoc' => (string) ($partner?->document_number ?? ''),
                'rznSocial' => (string) ($partner?->display_name ?? 'Cliente'),
            ],
            'details' => $details,
        ];
    }

    public function buildNotePayloadFromSale(Sale $sale, string $docType): array
    {
        $sale->loadMissing([
            'journal',
            'company',
            'partner',
            'products.tax',
            'products.lot',
            'products.productProduct.template',
            'originalSale.journal',
            'originalSale.products',
        ]);

        $original = $sale->originalSale;
        if (! $original) {
            return $this->buildPayloadFromSale($sale);
        }

        $tipDocAfectado = (string) ($original->journal?->document_type_code ?? '');
        $numDocAfectado = (string) ($original->serie && $original->correlative ? ($original->serie.'-'.$original->correlative) : '');

        [$codMotivo, $desMotivo] = $this->resolveCreditNoteReason($sale, $original);

        $payload = $this->buildPayloadFromSale($sale);
        $payload['tipoDoc'] = $docType;
        $payload['tipDocAfectado'] = $tipDocAfectado;
        $payload['numDocAfectado'] = $numDocAfectado;
        // Greenter usa el typo `numDocfectado` en algunas versiones; mantenemos ambos.
        $payload['numDocfectado'] = $numDocAfectado;
        $payload['codMotivo'] = $codMotivo;
        $payload['desMotivo'] = $desMotivo;

        return $payload;
    }

    private function resolveCreditNoteReason(Sale $note, Sale $original): array
    {
        $originalMap = [];
        foreach ($original->products as $line) {
            $key = (string) $line->product_product_id;
            $qty = number_format((float) $line->quantity, 2, '.', '');
            $originalMap[$key] = number_format(((float) ($originalMap[$key] ?? 0)) + (float) $qty, 2, '.', '');
        }

        $noteMap = [];
        foreach ($note->products as $line) {
            $key = (string) $line->product_product_id;
            $qty = number_format((float) $line->quantity, 2, '.', '');
            $noteMap[$key] = number_format(((float) ($noteMap[$key] ?? 0)) + (float) $qty, 2, '.', '');
        }

        ksort($originalMap);
        ksort($noteMap);

        // Nota de débito (08): aumento en el valor.
        if ($note->journal?->document_type_code === '08') {
            return ['02', 'AUMENTO EN EL VALOR'];
        }

        // Nota de crédito (07): devolución total vs por item.
        if ($originalMap === $noteMap && ! empty($originalMap)) {
            return ['06', 'DEVOLUCION TOTAL'];
        }

        return ['07', 'DEVOLUCION POR ITEM'];
    }

    private function mapPartnerDocType(?string $name): string
    {
        $n = mb_strtolower(trim((string) ($name ?? '')));

        return match ($n) {
            'dni' => '1',
            'ruc' => '6',
            'carnet de extranjería', 'carnet de extranjeria', 'ce' => '4',
            'pasaporte' => '7',
            default => '0',
        };
    }

    private function withComputedTotalsAndLegends(array $data): array
    {
        $details = collect($data['details'] ?? []);
        $isType = static fn ($row, string $type): bool => (string) ($row['tipAfeIgv'] ?? '') === $type;

        $data['mtoOperGravadas'] = (float) $details->filter(fn ($row) => $isType($row, '10'))->sum('mtoValorVenta');
        $data['mtoOperExoneradas'] = (float) $details->filter(fn ($row) => $isType($row, '20'))->sum('mtoValorVenta');
        $data['mtoOperInafectadas'] = (float) $details->filter(fn ($row) => $isType($row, '30'))->sum('mtoValorVenta');
        $data['mtoOperExportacion'] = (float) $details->filter(fn ($row) => $isType($row, '40'))->sum('mtoValorVenta');
        $data['mtoOperGratuitas'] = (float) $details->filter(fn ($row) => ! in_array((string) ($row['tipAfeIgv'] ?? ''), ['10', '20', '30', '40'], true))->sum('mtoValorVenta');

        $data['mtoIGV'] = (float) $details->filter(fn ($row) => in_array((string) ($row['tipAfeIgv'] ?? ''), ['10', '20', '30', '40'], true))->sum('igv');
        $data['mtoIGVGratuitas'] = (float) $details->filter(fn ($row) => ! in_array((string) ($row['tipAfeIgv'] ?? ''), ['10', '20', '30', '40'], true))->sum('igv');
        $data['icbper'] = (float) $details->sum('icbper');
        $data['totalImpuestos'] = round($data['mtoIGV'] + $data['icbper'], 2);

        $data['valorVenta'] = (float) $details->filter(fn ($row) => in_array((string) ($row['tipAfeIgv'] ?? ''), ['10', '20', '30', '40'], true))->sum('mtoValorVenta');
        $data['subTotal'] = round($data['valorVenta'] + $data['mtoIGV'], 2);
        $data['mtoImpVenta'] = round($data['subTotal'], 2, PHP_ROUND_HALF_UP);
        $data['redondeo'] = round($data['mtoImpVenta'] - $data['subTotal'], 2, PHP_ROUND_HALF_UP);

        if (class_exists('Luecano\\NumeroALetras\\NumeroALetras')) {
            $formatterClass = 'Luecano\\NumeroALetras\\NumeroALetras';
            $formatter = new $formatterClass();
            $legendValue = $formatter->toInvoice($data['mtoImpVenta'], 2, 'SOLES');
        } else {
            $legendValue = 'SON '.number_format((float) $data['mtoImpVenta'], 2, '.', '').' SOLES';
        }

        $data['legends'] = [[
            'code' => '1000',
            'value' => $legendValue,
        ]];

        return $data;
    }

    private function buildGreenterCompany(array $company)
    {
        $addressClass = 'Greenter\\Model\\Company\\Address';
        $companyClass = 'Greenter\\Model\\Company\\Company';
        $address = (new $addressClass())
            ->setUbigueo($company['address']['ubigueo'] ?? null)
            ->setDepartamento($company['address']['departamento'] ?? null)
            ->setProvincia($company['address']['provincia'] ?? null)
            ->setDistrito($company['address']['distrito'] ?? null)
            ->setUrbanizacion($company['address']['urbanizacion'] ?? null)
            ->setDireccion($company['address']['direccion'] ?? null)
            ->setCodLocal($company['address']['codLocal'] ?? '0000');

        return (new $companyClass())
            ->setRuc($company['ruc'] ?? null)
            ->setRazonSocial($company['razonSocial'] ?? null)
            ->setNombreComercial($company['nombreComercial'] ?? null)
            ->setAddress($address);
    }

    private function buildGreenterClient(array $client)
    {
        $clientClass = 'Greenter\\Model\\Client\\Client';

        return (new $clientClass())
            ->setTipoDoc($client['tipoDoc'] ?? null)
            ->setNumDoc($client['numDoc'] ?? null)
            ->setRznSocial($client['rznSocial'] ?? null);
    }

    /**
     * Construye el array `address` del payload de company resolviendo
     * `departamento`/`provincia`/`distrito` desde el catálogo INEI central.
     *
     * SUNAT acepta sólo `ubigueo` para identificar la geografía, pero los
     * campos granulares matan los warnings 4096/4097/4098 ("Tag no debe
     * estar vacío") que ensucian la respuesta del CDR. Esto hace además
     * que el XML firmado refleje fielmente la dirección como aparece en
     * ficha RUC.
     *
     * @return array<string, string>
     */
    private function buildCompanyAddress(mixed $company): array
    {
        $resolver = app(UbigeoResolver::class);
        $ubigeo = $company instanceof Company ? (string) ($company->ubigeo ?? '') : '';
        $tripleta = $resolver->resolve($ubigeo);

        return [
            'direccion' => $company instanceof Company ? (string) ($company->address ?? '') : '',
            'ubigueo' => $ubigeo,
            'departamento' => $tripleta['department'] ?? '',
            'provincia' => $tripleta['province'] ?? '',
            'distrito' => $tripleta['district'] ?? '',
            'urbanizacion' => '',
            'codLocal' => '0000',
        ];
    }

    private function buildGreenterDetails(array $details): array
    {
        $items = [];
        $detailClass = 'Greenter\\Model\\Sale\\SaleDetail';

        foreach ($details as $detail) {
            $items[] = (new $detailClass())
                ->setTipAfeIgv($detail['tipAfeIgv'] ?? null)
                ->setCodProducto($detail['codProducto'] ?? null)
                ->setUnidad($detail['unidad'] ?? 'NIU')
                ->setDescripcion($detail['descripcion'] ?? null)
                ->setCantidad((float) ($detail['cantidad'] ?? 0))
                ->setMtoValorUnitario((float) ($detail['mtoValorUnitario'] ?? 0))
                ->setMtoValorVenta((float) ($detail['mtoValorVenta'] ?? 0))
                ->setMtoBaseIgv((float) ($detail['mtoBaseIgv'] ?? 0))
                ->setPorcentajeIgv((float) ($detail['porcentajeIgv'] ?? 0))
                ->setIgv((float) ($detail['igv'] ?? 0))
                ->setFactorIcbper((float) ($detail['factorIcbper'] ?? 0))
                ->setIcbper((float) ($detail['icbper'] ?? 0))
                ->setTotalImpuestos((float) ($detail['totalImpuestos'] ?? 0))
                ->setMtoPrecioUnitario((float) ($detail['mtoPrecioUnitario'] ?? 0));
        }

        return $items;
    }

    private function buildGreenterLegends(array $legends): array
    {
        $result = [];
        $legendClass = 'Greenter\\Model\\Sale\\Legend';

        foreach ($legends as $legend) {
            $result[] = (new $legendClass())
                ->setCode((string) ($legend['code'] ?? '1000'))
                ->setValue((string) ($legend['value'] ?? ''));
        }

        return $result;
    }

    private function buildGreenterInvoice(array $data)
    {
        $invoiceClass = 'Greenter\\Model\\Sale\\Invoice';
        $formaPagoClass = 'Greenter\\Model\\Sale\\FormaPagos\\FormaPagoContado';

        return (new $invoiceClass())
            ->setUblVersion($data['ublVersion'] ?? '2.1')
            ->setTipoOperacion($data['tipoOperacion'] ?? '0101')
            ->setTipoDoc($data['tipoDoc'] ?? '01')
            ->setSerie($data['serie'] ?? null)
            ->setCorrelativo($data['correlativo'] ?? null)
            ->setFechaEmision(new \DateTime($data['fechaEmision'] ?? 'now'))
            ->setFormaPago(new $formaPagoClass())
            ->setTipoMoneda($data['tipoMoneda'] ?? 'PEN')
            ->setCompany($this->buildGreenterCompany((array) ($data['company'] ?? [])))
            ->setClient($this->buildGreenterClient((array) ($data['client'] ?? [])))
            ->setMtoOperGravadas((float) ($data['mtoOperGravadas'] ?? 0))
            ->setMtoOperExoneradas((float) ($data['mtoOperExoneradas'] ?? 0))
            ->setMtoOperInafectas((float) ($data['mtoOperInafectadas'] ?? 0))
            ->setMtoOperExportacion((float) ($data['mtoOperExportacion'] ?? 0))
            ->setMtoOperGratuitas((float) ($data['mtoOperGratuitas'] ?? 0))
            ->setMtoIGV((float) ($data['mtoIGV'] ?? 0))
            ->setMtoIGVGratuitas((float) ($data['mtoIGVGratuitas'] ?? 0))
            ->setIcbper((float) ($data['icbper'] ?? 0))
            ->setTotalImpuestos((float) ($data['totalImpuestos'] ?? 0))
            ->setValorVenta((float) ($data['valorVenta'] ?? 0))
            ->setSubTotal((float) ($data['subTotal'] ?? 0))
            ->setRedondeo((float) ($data['redondeo'] ?? 0))
            ->setMtoImpVenta((float) ($data['mtoImpVenta'] ?? 0))
            ->setDetails($this->buildGreenterDetails((array) ($data['details'] ?? [])))
            ->setLegends($this->buildGreenterLegends((array) ($data['legends'] ?? [])));
    }

    private function buildGreenterNote(array $data)
    {
        $noteClass = 'Greenter\\Model\\Sale\\Note';
        $note = (new $noteClass())
            ->setUblVersion($data['ublVersion'] ?? '2.1')
            ->setTipoDoc($data['tipoDoc'] ?? '07')
            ->setSerie($data['serie'] ?? null)
            ->setCorrelativo($data['correlativo'] ?? null)
            ->setFechaEmision(new \DateTime($data['fechaEmision'] ?? 'now'))
            ->setTipDocAfectado($data['tipDocAfectado'] ?? null)
            ->setCodMotivo($data['codMotivo'] ?? null)
            ->setDesMotivo($data['desMotivo'] ?? null)
            ->setTipoMoneda($data['tipoMoneda'] ?? 'PEN')
            ->setCompany($this->buildGreenterCompany((array) ($data['company'] ?? [])))
            ->setClient($this->buildGreenterClient((array) ($data['client'] ?? [])))
            ->setMtoOperGravadas((float) ($data['mtoOperGravadas'] ?? 0))
            ->setMtoOperExoneradas((float) ($data['mtoOperExoneradas'] ?? 0))
            ->setMtoOperInafectas((float) ($data['mtoOperInafectadas'] ?? 0))
            ->setMtoOperExportacion((float) ($data['mtoOperExportacion'] ?? 0))
            ->setMtoOperGratuitas((float) ($data['mtoOperGratuitas'] ?? 0))
            ->setMtoIGV((float) ($data['mtoIGV'] ?? 0))
            ->setMtoIGVGratuitas((float) ($data['mtoIGVGratuitas'] ?? 0))
            ->setIcbper((float) ($data['icbper'] ?? 0))
            ->setTotalImpuestos((float) ($data['totalImpuestos'] ?? 0))
            ->setValorVenta((float) ($data['valorVenta'] ?? 0))
            ->setSubTotal((float) ($data['subTotal'] ?? 0))
            ->setRedondeo((float) ($data['redondeo'] ?? 0))
            ->setMtoImpVenta((float) ($data['mtoImpVenta'] ?? 0))
            ->setDetails($this->buildGreenterDetails((array) ($data['details'] ?? [])))
            ->setLegends($this->buildGreenterLegends((array) ($data['legends'] ?? [])));

        $affected = $data['numDocfectado'] ?? $data['numDocAfectado'] ?? null;
        if (method_exists($note, 'setNumDocfectado')) {
            $note->setNumDocfectado($affected);
        } elseif (method_exists($note, 'setNumDocAfectado')) {
            $note->setNumDocAfectado($affected);
        }

        return $note;
    }

    private function extractXmlHash($see): ?string
    {
        if (! class_exists('Greenter\\Report\\XmlUtils')) {
            return null;
        }

        $xmlUtilsClass = 'Greenter\\Report\\XmlUtils';
        $xmlUtils = new $xmlUtilsClass();

        return $xmlUtils->getHashSign($see->getFactory()->getLastXml());
    }
}
