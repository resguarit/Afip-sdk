<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Services;

use Illuminate\Support\Facades\Log;
use Resguar\AfipSdk\DTOs\InvoiceResponse;
use Resguar\AfipSdk\Exceptions\AfipAuthorizationException;
use Resguar\AfipSdk\Exceptions\AfipException;
use Resguar\AfipSdk\Helpers\InvoiceMapper;
use Resguar\AfipSdk\Helpers\SoapHelper;
use Resguar\AfipSdk\Helpers\ValidatorHelper;
use SoapClient;
use SoapFault;

/**
 * Servicio de Facturación Electrónica con AFIP (WSFE - Web Service de Facturación Electrónica)
 *
 * Maneja la autorización de comprobantes y obtención de CAE
 */
class WsfeService
{
    /**
     * Create a new WsfeService instance.
     *
     * @param CertificateManager $certificateManager
     * @param WsaaService $wsaaService
     * @param string $environment Entorno (testing|production)
     * @param string $url URL del servicio WSFE
     */
    public function __construct(
        private readonly CertificateManager $certificateManager,
        private readonly WsaaService $wsaaService,
        private readonly string $environment,
        private readonly string $url
    ) {
    }

    /**
     * Autoriza una factura electrónica y obtiene el CAE
     *
     * @param array $invoice Datos del comprobante
     * @param string|null $cuit CUIT del contribuyente (opcional, usa config si no se proporciona)
     * @return InvoiceResponse Resultado con CAE y datos de la factura autorizada
     * @throws AfipAuthorizationException
     */
    public function authorizeInvoice(array $invoice, ?string $cuit = null): InvoiceResponse
    {
        // Resolver y validar CUIT
        $cuit = $this->resolveCuit($cuit);

        $this->log('info', 'Iniciando autorización de comprobante', [
            'point_of_sale' => $invoice['pointOfSale'] ?? null,
            'invoice_type' => $invoice['invoiceType'] ?? null,
            'cuit' => $cuit,
        ]);

        try {
            // 1. Obtener token y firma de WSAA (con CUIT específico)
            $this->log('debug', 'Obteniendo token y firma de WSAA', ['cuit' => $cuit]);
            $auth = $this->wsaaService->getTokenAndSignature('wsfe', $cuit);

            // 3. PRÁCTICA CLAVE: Consultar último comprobante autorizado para asegurar correlatividad
            $pointOfSale = (int) ($invoice['pointOfSale'] ?? 0);
            $invoiceType = (int) ($invoice['invoiceType'] ?? 0);
            
            $this->log('debug', "Consultando último comprobante autorizado (PtoVta: {$pointOfSale}, Tipo: {$invoiceType}, CUIT: {$cuit})");
            $lastInvoice = $this->getLastAuthorizedInvoice($pointOfSale, $invoiceType, $cuit);
            
            // Validar y ajustar número de comprobante
            $lastNumber = (int) ($lastInvoice['CbteNro'] ?? 0);
            $requestedNumber = (int) ($invoice['invoiceNumber'] ?? 0);
            
            // Si no se especificó número o es menor/igual al último, usar el siguiente
            if ($requestedNumber <= $lastNumber) {
                $nextNumber = $lastNumber + 1;
                $this->log('info', "Ajustando número de comprobante: {$requestedNumber} → {$nextNumber} (último autorizado: {$lastNumber})");
                $invoice['invoiceNumber'] = $nextNumber;
            } else {
                $this->log('debug', "Usando número de comprobante solicitado: {$requestedNumber}");
            }

            // 4. Mapear datos del comprobante al formato AFIP (FeCAERequest)
            $this->log('debug', 'Mapeando datos del comprobante al formato AFIP');
            $feCAERequest = InvoiceMapper::toFeCAERequest($invoice, $cuit);

            // 5. Crear cliente SOAP para WSFE
            $this->log('debug', 'Creando cliente SOAP para WSFE');
            $client = SoapHelper::createClient($this->url);

            // 6. Preparar parámetros para FECAESolicitar
            $params = [
                'Auth' => [
                    'Token' => $auth['token'],
                    'Sign' => $auth['signature'],
                    'Cuit' => (float) str_replace('-', '', $cuit),
                ],
                'FeCAEReq' => $feCAERequest['FeCAEReq'],
            ];

            // 7. Llamar método FECAESolicitar
            $this->log('debug', 'Enviando solicitud FECAESolicitar a WSFE');
            $soapResponse = SoapHelper::call(
                $client,
                'FECAESolicitar',
                $params,
                config('afip.retry.max_attempts', 3)
            );

            // 8. Procesar respuesta y extraer CAE
            $this->log('debug', 'Procesando respuesta de WSFE');
            $invoiceResponse = $this->parseFECAEResponse($soapResponse, $invoice);

            $this->log('info', 'Comprobante autorizado exitosamente', [
                'cae' => $invoiceResponse->cae,
                'invoice_number' => $invoiceResponse->invoiceNumber,
                'cuit' => $cuit,
            ]);

            return $invoiceResponse;
        } catch (AfipException $e) {
            $this->log('error', 'Error al autorizar comprobante', [
                'message' => $e->getMessage(),
                'afip_code' => $e->getAfipCode(),
                'exception' => $e,
            ]);

            throw $e;
        } catch (\Exception $e) {
            $this->log('error', 'Error inesperado al autorizar comprobante', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw new AfipAuthorizationException(
                "Error al autorizar comprobante: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Obtiene el último comprobante autorizado
     *
     * PRÁCTICA CLAVE: Este método debe llamarse ANTES de autorizar un nuevo comprobante
     * para asegurar la correlatividad de los números de comprobante.
     *
     * @param int $pointOfSale Punto de venta
     * @param int $invoiceType Tipo de comprobante
     * @param string|null $cuit CUIT del contribuyente (opcional, usa config si no se proporciona)
     * @return array Datos del último comprobante con estructura:
     *               ['CbteNro' => int, 'CbteFch' => string, 'PtoVta' => int, 'CbteTipo' => int]
     * @throws AfipException
     */
    public function getLastAuthorizedInvoice(int $pointOfSale, int $invoiceType, ?string $cuit = null): array
    {
        // Resolver y validar CUIT
        $cuit = $this->resolveCuit($cuit);

        $this->log('info', "Consultando último comprobante autorizado", [
            'point_of_sale' => $pointOfSale,
            'invoice_type' => $invoiceType,
            'cuit' => $cuit,
        ]);

        try {
            // 1. Obtener token y firma de WSAA (con CUIT específico)
            $auth = $this->wsaaService->getTokenAndSignature('wsfe', $cuit);

            // 3. Crear cliente SOAP para WSFE
            $client = SoapHelper::createClient($this->url);

            // 4. Preparar parámetros para FECompUltimoAutorizado
            $params = [
                'Auth' => [
                    'Token' => $auth['token'],
                    'Sign' => $auth['signature'],
                    'Cuit' => (float) str_replace('-', '', $cuit),
                ],
                'PtoVta' => $pointOfSale,
                'CbteTipo' => $invoiceType,
            ];

            // 5. Llamar método FECompUltimoAutorizado
            $this->log('debug', 'Enviando solicitud FECompUltimoAutorizado a WSFE', ['cuit' => $cuit]);
            $soapResponse = SoapHelper::call(
                $client,
                'FECompUltimoAutorizado',
                $params,
                config('afip.retry.max_attempts', 3)
            );

            // 6. Procesar respuesta
            return $this->parseLastInvoiceResponse($soapResponse);
        } catch (SoapFault $e) {
            $errorMsg = $e->getMessage() ?: ($e->faultstring ?? 'Error desconocido de SOAP');
            $faultCode = $e->faultcode ?? null;
            $faultString = $e->faultstring ?? null;
            
            $this->log('error', 'Error SOAP al consultar último comprobante', [
                'message' => $errorMsg,
                'faultcode' => $faultCode,
                'faultstring' => $faultString,
                'cuit' => $cuit,
                'exception_class' => get_class($e),
            ]);

            throw new AfipException(
                "Error al consultar último comprobante: {$errorMsg}",
                (int) $e->getCode(),
                $e,
                $faultCode,
                $faultString
            );
        } catch (AfipException $e) {
            // Re-lanzar excepciones de AFIP sin modificar
            throw $e;
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage() ?: 'Error desconocido';
            
            $this->log('error', 'Error inesperado al consultar último comprobante', [
                'message' => $errorMsg,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'cuit' => $cuit,
            ]);

            throw new AfipException(
                "Error al consultar último comprobante: {$errorMsg}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Resuelve el CUIT: limpia, valida y usa config si no se proporciona
     *
     * @param string|null $cuit CUIT proporcionado (opcional)
     * @return string CUIT limpio y validado
     * @throws AfipException Si el CUIT no es válido o no está configurado
     */
    protected function resolveCuit(?string $cuit = null): string
    {
        // Si no se proporciona CUIT, usar el de configuración
        if ($cuit === null || $cuit === '') {
            $cuit = config('afip.cuit');
            if (empty($cuit)) {
                throw new AfipException('CUIT no configurado en config/afip.php y no se proporcionó como parámetro');
            }
        }

        // Limpiar CUIT (remover guiones, espacios, etc.)
        $cleaned = ValidatorHelper::cleanCuit($cuit);

        // Validar que tenga 11 dígitos
        if (strlen($cleaned) !== 11) {
            throw new AfipException("El CUIT debe tener 11 dígitos. Recibido: {$cuit} (limpio: {$cleaned})");
        }

        return $cleaned;
    }

    /**
     * Parsea la respuesta de FECompUltimoAutorizado
     *
     * @param mixed $soapResponse Respuesta de WSFE
     * @return array Datos del último comprobante
     * @throws AfipException
     */
    protected function parseLastInvoiceResponse(mixed $soapResponse): array
    {
        // La respuesta viene como objeto con estructura FECompUltimoAutorizadoResponse
        $response = is_object($soapResponse) && isset($soapResponse->FECompUltimoAutorizadoResult)
            ? $soapResponse->FECompUltimoAutorizadoResult
            : $soapResponse;

        // Log de la respuesta para debugging
        $this->log('debug', 'Respuesta de FECompUltimoAutorizado', [
            'response_type' => gettype($response),
            'response_preview' => is_object($response) ? json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR) : $response,
        ]);

        // Verificar errores de forma robusta
        $errors = [];
        if (isset($response->Errors)) {
            $rawErrors = $response->Errors;
            
            // Normalizar a array si es un solo objeto
            if (is_object($rawErrors)) {
                // A veces Errors es un objeto contenedor de Err
                $rawErrors = $rawErrors->Err ?? $rawErrors;
            }
            
            if (!is_array($rawErrors)) {
                $rawErrors = [$rawErrors];
            }

            foreach ($rawErrors as $error) {
                $code = trim((string) ($error->Code ?? ''));
                $msg = trim((string) ($error->Msg ?? ''));
                if ($code !== '' || $msg !== '') {
                    $errors[] = "{$code}: {$msg}";
                }
            }
        }

        if (!empty($errors)) {
            $errorMsg = implode('; ', $errors);
            
            $this->log('error', 'Error en FECompUltimoAutorizado', [
                'errors' => $errors,
                'error_msg' => $errorMsg,
            ]);

            throw new AfipException(
                "Error al consultar último comprobante: {$errorMsg}",
                0,
                null,
                null, // No tenemos un código único fácil de extraer aquí
                $errorMsg
            );
        }

        // Extraer datos del último comprobante
        return [
            'CbteNro' => (int) ($response->CbteNro ?? 0),
            'CbteFch' => (string) ($response->CbteFch ?? ''),
            'PtoVta' => (int) ($response->PtoVta ?? 0),
            'CbteTipo' => (int) ($response->CbteTipo ?? 0),
        ];
    }

    /**
     * Obtiene los tipos de comprobantes disponibles
     *
     * @return array Lista de tipos de comprobantes
     * @throws AfipException
     */
    public function getInvoiceTypes(): array
    {
        // TODO: Implementar consulta de tipos de comprobantes
        return [];
    }

    /**
     * Obtiene los puntos de venta habilitados
     *
     * @return array Lista de puntos de venta
     * @throws AfipException
     */
    public function getPointOfSales(): array
    {
        // TODO: Implementar consulta de puntos de venta
        return [];
    }

    /**
     * Obtiene el estado del contribuyente
     *
     * @param string $cuit CUIT del contribuyente
     * @return array Estado del contribuyente
     * @throws AfipException
     */
    public function getTaxpayerStatus(string $cuit): array
    {
        $this->log('info', "Consultando estado del contribuyente: {$cuit}");

        try {
            // TODO: Implementar consulta de estado del contribuyente
            // - Obtener token de WSAA
            // - Crear cliente SOAP
            // - Llamar método correspondiente
            // - Procesar respuesta

            return [];
        } catch (\Exception $e) {
            $this->log('error', "Error al consultar estado del contribuyente {$cuit}", [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw new AfipException(
                "Error al consultar estado del contribuyente: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Parsea la respuesta de FECAESolicitar y extrae el CAE
     *
     * @param mixed $soapResponse Respuesta de WSFE
     * @param array $originalInvoice Datos originales del comprobante
     * @return InvoiceResponse
     * @throws AfipAuthorizationException
     */
    protected function parseFECAEResponse(mixed $soapResponse, array $originalInvoice): InvoiceResponse
    {
        // La respuesta viene como objeto con estructura FeCAEResponse
        $response = is_object($soapResponse) && isset($soapResponse->FECAESolicitarResult)
            ? $soapResponse->FECAESolicitarResult
            : $soapResponse;

        // Log para debugging
        $this->log('debug', 'Respuesta RAW de WSFE', [
            'response' => json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR)
        ]);

        // Verificar resultado
        if (!isset($response->FeCabResp) || !isset($response->FeDetResp)) {
            throw new AfipAuthorizationException('Respuesta inválida de WSFE: estructura incorrecta');
        }

        $feCabResp = $response->FeCabResp;
        $feDetResp = $response->FeDetResp;

        // Helper para extraer mensajes (Errores u Observaciones)
        $extractMessages = function($container, $wrapper) {
            $messages = [];
            if (!isset($container)) return $messages;

            $items = $container;
            // Si es objeto, buscar el wrapper interno (ej: Errors->Err o Observaciones->Obs)
            if (is_object($items)) {
                $items = $items->{$wrapper} ?? $items;
            }
            
            if (!is_array($items)) {
                $items = [$items];
            }

            foreach ($items as $item) {
                $code = trim((string) ($item->Code ?? ''));
                $msg = trim((string) ($item->Msg ?? ''));
                if ($code !== '' || $msg !== '') {
                    $messages[] = [
                        'code' => $code,
                        'msg' => $msg,
                    ];
                }
            }
            return $messages;
        };

        // Verificar resultado de la cabecera
        $resultado = (string) ($feCabResp->Resultado ?? '');
        
        if ($resultado !== 'A') {
            // Recolectar todos los errores posibles
            $errors = [];
            
            // 1. Buscar en Errors globales (FeCabResp->Errors->Err)
            if (isset($feCabResp->Errors)) {
                $errors = array_merge($errors, $extractMessages($feCabResp->Errors, 'Err'));
            }

            // 2. Buscar en Observaciones del detalle (FeDetResp->FECAEDetResponse->Observaciones->Obs)
            // A veces el rechazo está aquí si es específico del comprobante
            $detalle = is_array($feDetResp->FECAEDetResponse) 
                ? $feDetResp->FECAEDetResponse[0] 
                : $feDetResp->FECAEDetResponse;
                
            if (isset($detalle->Observaciones)) {
                $errors = array_merge($errors, $extractMessages($detalle->Observaciones, 'Obs'));
            }

            $errorMsg = !empty($errors)
                ? implode('; ', array_map(fn($e) => "{$e['code']}: {$e['msg']}", $errors))
                : "Error desconocido en la respuesta de WSFE (Resultado: {$resultado})";

            throw new AfipAuthorizationException(
                "Error al autorizar comprobante: {$errorMsg}",
                0,
                null,
                $errors[0]['code'] ?? null,
                $errors[0]['msg'] ?? null
            );
        }

        // Obtener detalle del comprobante (primer elemento del array)
        $detalle = is_array($feDetResp->FECAEDetResponse) 
            ? $feDetResp->FECAEDetResponse[0] 
            : $feDetResp->FECAEDetResponse;

        // Verificar resultado del detalle
        $detalleResultado = (string) ($detalle->Resultado ?? '');
        if ($detalleResultado !== 'A') {
            // Si la cabecera dio A pero el detalle dio R, buscamos observaciones
            $errors = [];
            if (isset($detalle->Observaciones)) {
                $errors = $extractMessages($detalle->Observaciones, 'Obs');
            }

            $errorMsg = !empty($errors)
                ? implode('; ', array_map(fn($e) => "{$e['code']}: {$e['msg']}", $errors))
                : 'Error en el detalle del comprobante';

            throw new AfipAuthorizationException(
                "Error al autorizar comprobante: {$errorMsg}",
                0,
                null,
                $errors[0]['code'] ?? null,
                $errors[0]['msg'] ?? null
            );
        }

        // Extraer CAE y datos del comprobante autorizado
        $cae = (string) ($detalle->CAE ?? '');
        $caeFchVto = (string) ($detalle->CAEFchVto ?? '');

        if (empty($cae)) {
            throw new AfipAuthorizationException('CAE no encontrado en la respuesta de WSFE');
        }

        // Extraer observaciones (informativas) si las hay
        $observations = [];
        if (isset($detalle->Observaciones)) {
            $observations = $extractMessages($detalle->Observaciones, 'Obs');
        }

        return InvoiceResponse::fromArray([
            'CAE' => $cae,
            'CAEFchVto' => $caeFchVto,
            'CbteDesde' => (int) ($detalle->CbteDesde ?? $originalInvoice['invoiceNumber'] ?? 0),
            'PtoVta' => (int) ($feCabResp->PtoVta ?? $originalInvoice['pointOfSale'] ?? 0),
            'CbteTipo' => (int) ($feCabResp->CbteTipo ?? $originalInvoice['invoiceType'] ?? 0),
            'Observaciones' => $observations,
        ]);
    }

    /**
     * Registra un mensaje en el log si está habilitado
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (!config('afip.logging.enabled', true)) {
            return;
        }

        $channel = config('afip.logging.channel', 'daily');
        $minLevel = config('afip.logging.level', 'info');

        $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        $currentLevelIndex = array_search($level, $levels);
        $minLevelIndex = array_search($minLevel, $levels);

        if ($currentLevelIndex !== false && $minLevelIndex !== false && $currentLevelIndex >= $minLevelIndex) {
            Log::channel($channel)->{$level}("[AFIP SDK - WSFE] {$message}", $context);
        }
    }
}

