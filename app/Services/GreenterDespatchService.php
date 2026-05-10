<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BillingCredential;
use App\Models\Company;
use App\Models\Transfer;
use Illuminate\Support\Facades\Storage;

/**
 * Envío de Guía de Remisión Electrónica (GRE-Remitente, código 09) a SUNAT
 * vía Greenter — REST + OAuth2 + ticket asíncrono.
 *
 * Diferencias clave con GreenterInvoiceService (SOAP/SOL):
 *  - Canal REST: requiere `client_id` + `client_secret` (OAuth2 client_credentials)
 *    además del usuario SOL secundario.
 *  - Asíncrono: el envío devuelve un `numTicket`. La aceptación/rechazo se
 *    consulta luego con `pollTicket()`.
 *  - El XML lo construye y firma Greenter localmente (no PSE externo).
 *
 * Alcance fase 1:
 *  - Motivo 04 (traslado entre establecimientos del mismo contribuyente).
 *  - Modalidad privada (transporte propio): vehículo + conductor del Transfer.
 *  - Destinatario = misma Company (mismo RUC).
 *  - Punto de partida = Warehouse del exit movement.
 *  - Punto de llegada = Warehouse del entry movement.
 *
 * Fuera de alcance fase 1 (a implementar después):
 *  - Motivo 01/02/13 (venta/compra/otros) — destinatario distinto.
 *  - Modalidad pública (motivo 02) — datos del transportista (RUC + razón).
 *  - GRE-Transportista (cód 31).
 */
class GreenterDespatchService
{
    private array $endpoints;

    public function __construct()
    {
        $this->endpoints = (array) config('services.greenter.gre_endpoints', [
            'auth' => 'https://api-seguridad.sunat.gob.pe/v1',
            'cpe' => 'https://api-cpe.sunat.gob.pe/v1',
        ]);
    }

    /**
     * Envía la GRE a SUNAT. Persiste `gre_ticket`, `gre_status` y el XML
     * firmado. La respuesta es asíncrona — luego hay que llamar `pollTicket`.
     */
    public function sendDespatch(Transfer $transfer): bool
    {
        $transfer->loadMissing([
            'company',
            'exitMovement.warehouse',
            'exitMovement.productables.productProduct.template',
            'exitMovement.productables.uom',
            'entryMovement.warehouse',
        ]);

        // Validaciones de pre-envío
        $error = $this->validateForSend($transfer);
        if ($error !== null) {
            $transfer->gre_status = 'error';
            $transfer->gre_response = [
                'accepted' => false,
                'error' => $error,
                'updated_at' => now()->toIso8601String(),
            ];
            $transfer->save();

            return false;
        }

        // Idempotencia: ya aceptada o ya en cola de ticket válida
        if (in_array($transfer->gre_status, ['accepted', 'ticket_pending', 'processing'], true)) {
            return true;
        }

        $transfer->gre_status = 'processing';
        $transfer->save();

        try {
            $credential = BillingCredential::query()->active()->first();
            if (! $credential) {
                throw new \RuntimeException('No hay BillingCredential activa para este tenant.');
            }

            $this->assertCredentialReady($credential);

            if (! class_exists('Greenter\\Api')) {
                throw new \RuntimeException('greenter/lite no está instalado.');
            }

            $apiClass = 'Greenter\\Api';
            /** @var \Greenter\Api $api */
            $api = new $apiClass($this->endpoints);
            $api->setCertificate(Storage::disk('local')->get($credential->cert_path));
            $api->setApiCredentials((string) $credential->client_id, (string) $credential->client_secret);
            $api->setClaveSOL((string) $transfer->company->ruc, (string) $credential->sol_user, (string) $credential->sol_pass);

            $despatch = $this->buildDespatch($transfer);
            $result = $api->send($despatch);

            // Archivar XML firmado siempre (incluso si SUNAT rechaza el ticket).
            $xmlPath = $this->archiveSignedXml($transfer, $api, $despatch);
            if ($xmlPath !== null) {
                $transfer->gre_signed_xml_path = $xmlPath;
            }

            // Result es BaseResult — para GRE devuelve isSuccess + getTicket via subclase.
            $sunatResponse = $this->extractSendResponse($result);
            $ticket = $sunatResponse['ticket'] ?? null;

            if (! ($sunatResponse['success'] ?? false) || empty($ticket)) {
                $transfer->gre_status = 'error';
                $transfer->gre_response = array_merge($sunatResponse, [
                    'updated_at' => now()->toIso8601String(),
                ]);
                $transfer->save();

                return false;
            }

            $transfer->gre_status = 'ticket_pending';
            $transfer->gre_ticket = (string) $ticket;
            $transfer->gre_response = array_merge($sunatResponse, [
                'updated_at' => now()->toIso8601String(),
            ]);
            $transfer->gre_sent_at = now();
            $transfer->save();

            return true;
        } catch (\Throwable $e) {
            $transfer->gre_status = 'error';
            $transfer->gre_response = [
                'accepted' => false,
                'error' => $e->getMessage(),
                'updated_at' => now()->toIso8601String(),
            ];
            $transfer->save();

            return false;
        }
    }

    /**
     * Consulta el estado del ticket en SUNAT. Cuando hay CDR, lo archiva y
     * actualiza `gre_status` a `accepted` o `rejected`.
     */
    public function pollTicket(Transfer $transfer): bool
    {
        $transfer->loadMissing(['company']);

        if (empty($transfer->gre_ticket)) {
            return false;
        }
        if ($transfer->gre_status === 'accepted') {
            return true;
        }

        try {
            $credential = BillingCredential::query()->active()->first();
            if (! $credential) {
                throw new \RuntimeException('No hay BillingCredential activa.');
            }
            $this->assertCredentialReady($credential);

            $apiClass = 'Greenter\\Api';
            /** @var \Greenter\Api $api */
            $api = new $apiClass($this->endpoints);
            $api->setCertificate(Storage::disk('local')->get($credential->cert_path));
            $api->setApiCredentials((string) $credential->client_id, (string) $credential->client_secret);
            $api->setClaveSOL((string) $transfer->company->ruc, (string) $credential->sol_user, (string) $credential->sol_pass);

            $status = $api->getStatus((string) $transfer->gre_ticket);

            if (! $status->isSuccess()) {
                $transfer->gre_response = [
                    'accepted' => false,
                    'channel' => 'gre',
                    'phase' => 'poll',
                    'error' => [
                        'code' => $status->getError()?->getCode(),
                        'message' => $status->getError()?->getMessage(),
                    ],
                    'updated_at' => now()->toIso8601String(),
                ];
                // Si vino "EN PROCESO" mantenemos ticket_pending; otros casos son rechazo.
                $errorCode = (string) ($status->getError()?->getCode() ?? '');
                if ($errorCode === '98') { // En proceso
                    $transfer->gre_status = 'ticket_pending';
                } else {
                    $transfer->gre_status = 'rejected';
                }
                $transfer->save();

                return false;
            }

            // CDR disponible → archivar y marcar aceptado
            $cdrPath = $this->archiveCdr($transfer, $status);

            $cdrResponse = $status->getCdrResponse();
            $cdrCode = $cdrResponse?->getCode();
            $accepted = is_numeric($cdrCode) && (int) $cdrCode === 0;

            $transfer->gre_status = $accepted ? 'accepted' : 'rejected';
            if ($cdrPath !== null) {
                $transfer->gre_cdr_zip_path = $cdrPath;
            }
            $transfer->gre_response = [
                'accepted' => $accepted,
                'channel' => 'gre',
                'phase' => 'poll',
                'cdrResponse' => [
                    'code' => is_numeric($cdrCode) ? (int) $cdrCode : null,
                    'description' => $cdrResponse?->getDescription(),
                    'notes' => $cdrResponse?->getNotes(),
                ],
                'updated_at' => now()->toIso8601String(),
            ];
            $transfer->save();

            return $accepted;
        } catch (\Throwable $e) {
            $transfer->gre_response = [
                'accepted' => false,
                'channel' => 'gre',
                'phase' => 'poll',
                'error' => $e->getMessage(),
                'updated_at' => now()->toIso8601String(),
            ];
            $transfer->save();

            return false;
        }
    }

    /**
     * Validación previa al envío. Devuelve null si todo está ok, o un
     * mensaje describiendo qué falta.
     */
    private function validateForSend(Transfer $transfer): ?string
    {
        $exit = $transfer->exitMovement;
        $entry = $transfer->entryMovement;

        if (! $exit || ! $entry) {
            return 'Transfer no tiene par exit/entry movement.';
        }
        if (! $exit->isPosted()) {
            return 'El movement de salida debe estar posted antes de emitir GRE.';
        }
        if (! $exit->warehouse || ! $entry->warehouse) {
            return 'Falta almacén de origen o destino.';
        }

        // Datos legales del almacén
        foreach (['exit' => $exit, 'entry' => $entry] as $key => $mov) {
            $w = $mov->warehouse;
            if (empty($w->ubigeo) || empty($w->address_line)) {
                return "Almacén {$key} requiere ubigeo + address_line para emitir GRE.";
            }
        }

        // Phase 1 supports only motive 04 + private modality
        if ((string) $transfer->gre_motive_code !== '04') {
            return 'Fase 1 GRE: sólo motivo 04 (traslado interno) está soportado.';
        }
        if ((string) $transfer->gre_modality !== 'private') {
            return 'Fase 1 GRE: sólo modalidad privada está soportada.';
        }

        // Datos de transporte privado obligatorios
        if (empty($transfer->gre_vehicle_plate)) {
            return 'Falta placa del vehículo.';
        }
        if (empty($transfer->gre_driver_doc_number) || empty($transfer->gre_driver_name)) {
            return 'Faltan datos del conductor (DNI y nombres).';
        }
        if (empty($transfer->gre_transfer_start_date)) {
            return 'Falta fecha de inicio del traslado.';
        }

        // Validación productable
        if ($exit->productables->isEmpty()) {
            return 'El movement de salida no tiene líneas de detalle.';
        }

        return null;
    }

    private function assertCredentialReady(BillingCredential $credential): void
    {
        if (! filled($credential->cert_path)
            || ! filled($credential->client_id)
            || ! filled($credential->client_secret)
            || ! filled($credential->sol_user)
            || ! filled($credential->sol_pass)) {
            throw new \RuntimeException('Credencial incompleta: GRE requiere cert + client_id + client_secret + SOL.');
        }
        if (! Storage::disk('local')->exists((string) $credential->cert_path)) {
            throw new \RuntimeException('No existe el certificado en cert_path.');
        }
    }

    /**
     * Construye el modelo Greenter\Despatch. Sólo motivo 04 + privada.
     */
    private function buildDespatch(Transfer $transfer)
    {
        $company = $transfer->company;
        $exit = $transfer->exitMovement;
        $entry = $transfer->entryMovement;
        $exitWh = $exit->warehouse;
        $entryWh = $entry->warehouse;

        $despatchClass = 'Greenter\\Model\\Despatch\\Despatch';
        $shipmentClass = 'Greenter\\Model\\Despatch\\Shipment';
        $directionClass = 'Greenter\\Model\\Despatch\\Direction';
        $vehicleClass = 'Greenter\\Model\\Despatch\\Vehicle';
        $driverClass = 'Greenter\\Model\\Despatch\\Driver';
        $detailClass = 'Greenter\\Model\\Despatch\\DespatchDetail';

        // Punto de partida: almacén origen (mismo RUC en motivo 04)
        $partida = (new $directionClass((string) $exitWh->ubigeo, (string) $exitWh->address_line))
            ->setCodLocal((string) ($exitWh->establishment_code ?: '0000'));

        // Punto de llegada: almacén destino (mismo RUC en motivo 04)
        $llegada = (new $directionClass((string) $entryWh->ubigeo, (string) $entryWh->address_line))
            ->setCodLocal((string) ($entryWh->establishment_code ?: '0000'));

        $vehicle = (new $vehicleClass())
            ->setPlaca((string) $transfer->gre_vehicle_plate);

        $driverNameParts = $this->splitDriverName((string) $transfer->gre_driver_name);
        $driver = (new $driverClass())
            ->setTipo('Principal')
            ->setTipoDoc((string) ($transfer->gre_driver_doc_type ?: '1'))
            ->setNroDoc((string) $transfer->gre_driver_doc_number)
            ->setLicencia((string) ($transfer->gre_driver_license ?? ''))
            ->setNombres($driverNameParts['nombres'])
            ->setApellidos($driverNameParts['apellidos']);

        $shipment = (new $shipmentClass())
            ->setCodTraslado('04')
            ->setDesTraslado('Traslado entre establecimientos de la misma empresa')
            ->setModTraslado('02') // 02 = privada
            ->setFecTraslado(new \DateTime(((string) $transfer->gre_transfer_start_date) ?: 'now'))
            ->setPesoTotal((float) ($transfer->gre_gross_weight ?: 0))
            ->setUndPesoTotal('KGM')
            ->setNumBultos((int) ($transfer->gre_packages ?: 0))
            ->setPartida($partida)
            ->setLlegada($llegada)
            ->setVehiculo($vehicle)
            ->setChoferes([$driver]);

        $details = [];
        foreach ($exit->productables as $line) {
            $product = $line->productProduct;
            $description = $product?->display_name ?? 'Producto';
            $unidad = $line->uom?->code ?: ($product?->template?->is_service ? 'ZZ' : 'NIU');
            $sku = $product?->sku ?: $product?->barcode ?: (string) $line->id;

            $details[] = (new $detailClass())
                ->setCantidad((float) ($line->quantity ?? 0))
                ->setUnidad((string) $unidad)
                ->setDescripcion((string) $description)
                ->setCodigo((string) $sku);
        }

        $companyClass = 'Greenter\\Model\\Company\\Company';
        $addressClass = 'Greenter\\Model\\Company\\Address';
        $clientClass = 'Greenter\\Model\\Client\\Client';

        // Resolver INEI: rellena departamento/provincia/distrito en el
        // Address SUNAT y mata los warnings 4096/4097/4098 del CDR.
        $resolver = app(UbigeoResolver::class);
        $companyUbigeo = (string) ($company->ubigeo ?? '');
        $tripleta = $resolver->resolve($companyUbigeo);

        $companyAddress = (new $addressClass())
            ->setUbigueo($companyUbigeo)
            ->setDepartamento($tripleta['department'] ?? '')
            ->setProvincia($tripleta['province'] ?? '')
            ->setDistrito($tripleta['district'] ?? '')
            ->setDireccion((string) ($company->address ?? ''))
            ->setCodLocal('0000');

        $companyModel = (new $companyClass())
            ->setRuc((string) $company->ruc)
            ->setRazonSocial((string) ($company->business_name ?? ''))
            ->setNombreComercial((string) ($company->trade_name ?? $company->business_name ?? ''))
            ->setAddress($companyAddress);

        // Destinatario: en motivo 04 mismo RUC que el remitente
        $destinatario = (new $clientClass())
            ->setTipoDoc('6')
            ->setNumDoc((string) $company->ruc)
            ->setRznSocial((string) ($company->business_name ?? ''));

        return (new $despatchClass())
            ->setVersion('2022')
            ->setTipoDoc('09')
            ->setSerie((string) $transfer->serie)
            ->setCorrelativo((string) $transfer->correlative)
            ->setFechaEmision(new \DateTime(($transfer->date ?? now())->format('Y-m-d\TH:i:sP')))
            ->setObservacion((string) ($transfer->observation ?? ''))
            ->setCompany($companyModel)
            ->setDestinatario($destinatario)
            ->setEnvio($shipment)
            ->setDetails($details);
    }

    /**
     * El nombre del conductor llega como string suelto. Greenter pide
     * nombres + apellidos por separado. Heurística: la primera mitad
     * va a nombres, la segunda a apellidos. Si el usuario sólo puso
     * un valor, replicar.
     */
    private function splitDriverName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $count = count($parts);
        if ($count === 0) {
            return ['nombres' => '', 'apellidos' => ''];
        }
        if ($count === 1) {
            return ['nombres' => $parts[0], 'apellidos' => $parts[0]];
        }
        $half = (int) ceil($count / 2);

        return [
            'nombres' => implode(' ', array_slice($parts, 0, $half)),
            'apellidos' => implode(' ', array_slice($parts, $half)),
        ];
    }

    /**
     * Greenter\Api->send() devuelve un BaseResult del paquete ws (subclase
     * con `getTicket()` cuando es GRE). Normalizamos la respuesta a array.
     */
    private function extractSendResponse($result): array
    {
        if ($result === null) {
            return [
                'success' => false,
                'channel' => 'gre',
                'phase' => 'send',
                'error' => 'Greenter Api->send() devolvió null.',
            ];
        }

        $success = $result->isSuccess();
        if (! $success) {
            return [
                'success' => false,
                'channel' => 'gre',
                'phase' => 'send',
                'accepted' => false,
                'error' => [
                    'code' => $result->getError()?->getCode(),
                    'message' => $result->getError()?->getMessage(),
                ],
            ];
        }

        $ticket = method_exists($result, 'getTicket') ? $result->getTicket() : null;

        return [
            'success' => true,
            'channel' => 'gre',
            'phase' => 'send',
            'ticket' => $ticket,
        ];
    }

    /**
     * Persiste el XML firmado en `billing/transfers/{id}/{baseName}.xml`.
     * Conservación obligatoria por SUNAT.
     */
    private function archiveSignedXml(Transfer $transfer, $api, $despatch): ?string
    {
        try {
            $xml = $api->getLastXml();
            if (empty($xml)) {
                return null;
            }
            $baseName = $despatch->getName(); // {RUC}-09-{serie}-{correlativo}
            $path = 'billing/transfers/'.$transfer->id.'/'.$baseName.'.xml';
            Storage::disk('local')->put($path, $xml);

            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Persiste la CDR ZIP. SUNAT la entrega como contenido binario en
     * `getCdrZip()` del StatusResult.
     */
    private function archiveCdr(Transfer $transfer, $status): ?string
    {
        try {
            $zip = method_exists($status, 'getCdrZip') ? $status->getCdrZip() : null;
            if (empty($zip)) {
                return null;
            }
            $ruc = (string) ($transfer->company->ruc ?? '');
            $baseName = $ruc.'-09-'.$transfer->serie.'-'.$transfer->correlative;
            $path = 'billing/transfers/'.$transfer->id.'/R-'.$baseName.'.zip';
            Storage::disk('local')->put($path, $zip);

            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
