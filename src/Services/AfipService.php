<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Services;

use Resguar\AfipSdk\Builders\InvoiceBuilder;
use Resguar\AfipSdk\Contracts\AfipServiceInterface;
use Resguar\AfipSdk\DTOs\InvoiceResponse;
use Resguar\AfipSdk\Exceptions\AfipException;
use Resguar\AfipSdk\Helpers\ValidatorHelper;

/**
 * Servicio principal de AFIP
 *
 * Orquesta las operaciones con los diferentes Web Services de AFIP
 */
class AfipService implements AfipServiceInterface
{
    /**
     * Create a new AfipService instance.
     *
     * @param WsaaService $wsaaService
     * @param WsfeService $wsfeService
     * @param CertificateManager $certificateManager
     */
    public function __construct(
        private readonly WsaaService $wsaaService,
        private readonly WsfeService $wsfeService,
        private readonly CertificateManager $certificateManager
    ) {
    }

    /**
     * Autoriza una factura electrónica y obtiene el CAE
     *
     * @param mixed $source Fuente de datos (Eloquent Model, array, objeto)
     * @param string|null $cuit CUIT del contribuyente (opcional, usa config si no se proporciona)
     * @return InvoiceResponse Resultado con CAE y datos de la factura autorizada
     * @throws AfipException
     */
    public function authorizeInvoice(mixed $source, ?string $cuit = null): InvoiceResponse
    {
        // Construir el comprobante desde la fuente de datos
        $invoice = InvoiceBuilder::from($source)->build();

        // Validar datos del comprobante
        ValidatorHelper::validateInvoice($invoice);

        // Autorizar mediante WsfeService (con CUIT opcional)
        return $this->wsfeService->authorizeInvoice($invoice, $cuit);
    }

    /**
     * Obtiene el último comprobante autorizado
     *
     * @param int $pointOfSale Punto de venta
     * @param int $invoiceType Tipo de comprobante
     * @param string|null $cuit CUIT del contribuyente (opcional, usa config si no se proporciona)
     * @return array Datos del último comprobante
     * @throws AfipException
     */
    public function getLastAuthorizedInvoice(int $pointOfSale, int $invoiceType, ?string $cuit = null): array
    {
        return $this->wsfeService->getLastAuthorizedInvoice($pointOfSale, $invoiceType, $cuit);
    }

    /**
     * Obtiene los tipos de comprobantes disponibles (compatibilidad hacia atrás)
     *
     * @return array Lista de tipos de comprobantes
     * @throws AfipException
     */
    public function getInvoiceTypes(): array
    {
        // Mantener compatibilidad con la interfaz antigua.
        // Se devuelve la lista para el CUIT por defecto configurado.
        return $this->getAvailableReceiptTypes(null);
    }

    /**
     * Obtiene los tipos de comprobantes habilitados para un CUIT
     *
     * @param string|null $cuit CUIT del contribuyente (opcional, usa config si no se proporciona)
     * @return array Lista de tipos de comprobantes normalizada
     * @throws AfipException
     */
    public function getAvailableReceiptTypes(?string $cuit = null): array
    {
        return $this->wsfeService->getAvailableReceiptTypes($cuit);
    }

    /**
     * Obtiene los puntos de venta habilitados (compatibilidad hacia atrás)
     *
     * @return array Lista de puntos de venta
     * @throws AfipException
     */
    public function getPointOfSales(): array
    {
        // Mantener compatibilidad con la interfaz antigua.
        // Se devuelve la lista para el CUIT por defecto configurado.
        return $this->getAvailablePointsOfSale(null);
    }

    /**
     * Obtiene los puntos de venta habilitados para un CUIT
     *
     * @param string|null $cuit CUIT del contribuyente (opcional, usa config si no se proporciona)
     * @return array Lista de puntos de venta normalizada
     * @throws AfipException
     */
    public function getAvailablePointsOfSale(?string $cuit = null): array
    {
        return $this->wsfeService->getAvailablePointsOfSale($cuit);
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
        return $this->wsfeService->getTaxpayerStatus($cuit);
    }

    /**
     * Limpia el cache de parámetros de WSFE (tipos de comprobante, puntos de venta)
     *
     * @param string|null $cuit CUIT del contribuyente (opcional, si es null no hace nada específico)
     * @return void
     */
    public function clearParamCache(?string $cuit = null): void
    {
        $this->wsfeService->clearParamCache($cuit);
    }

    /**
     * Verifica si el servicio está autenticado
     *
     * @param string|null $cuit CUIT del contribuyente (opcional, usa config si no se proporciona)
     * @return bool
     */
    public function isAuthenticated(?string $cuit = null): bool
    {
        return $this->wsaaService->isAuthenticated('wsfe', $cuit);
    }

    /**
     * Diagnostica problemas de autenticación y configuración
     *
     * Verifica:
     * - Configuración (CUIT, entorno, rutas)
     * - Archivos de certificados (existencia, permisos)
     * - Validez del certificado (expiración, formato)
     * - Coincidencia entre certificado y clave privada
     * - CUIT del certificado vs configurado
     *
     * @param string|null $cuit CUIT del contribuyente (opcional)
     * @return array Diagnóstico completo con problemas y sugerencias
     */
    public function diagnoseAuthenticationIssue(?string $cuit = null): array
    {
        $issues = [];
        $suggestions = [];
        $details = [];

        // Resolver CUIT
        try {
            $cuit = $cuit ?? config('afip.cuit');
            if (empty($cuit)) {
                $issues[] = 'CUIT no configurado';
                $suggestions[] = 'Configura AFIP_CUIT en tu archivo .env';
            }
        } catch (\Exception $e) {
            $issues[] = 'Error al obtener CUIT: ' . $e->getMessage();
        }

        $details['cuit_configurado'] = $cuit ?? 'No configurado';
        $details['entorno'] = config('afip.environment', 'testing');

        // Verificar archivos
        $filesOk = true;
        try {
            $certPath = $this->certificateManager->getCertPath();
            $keyPath = $this->certificateManager->getKeyPath();
            $details['cert_path'] = $certPath;
            $details['key_path'] = $keyPath;

            if (!file_exists($certPath)) {
                $issues[] = "Certificado no encontrado: {$certPath}";
                $filesOk = false;
            }

            if (!file_exists($keyPath)) {
                $issues[] = "Clave privada no encontrada: {$keyPath}";
                $filesOk = false;
            }

            // Verificar permisos
            if (file_exists($keyPath)) {
                $perms = substr(sprintf('%o', fileperms($keyPath)), -4);
                if ($perms !== '0600' && $perms !== '0400') {
                    $suggestions[] = "Permisos de clave privada recomendados: 600 (actual: {$perms})";
                }
            }
        } catch (\Exception $e) {
            $issues[] = 'Error al verificar archivos: ' . $e->getMessage();
            $filesOk = false;
        }

        // Verificar certificado
        $certificateValid = false;
        $certificateMatchesKey = false;

        try {
            $certPath = $this->certificateManager->getCertPath();
            $keyPath = $this->certificateManager->getKeyPath();

            if (file_exists($certPath) && file_exists($keyPath)) {
                $certContent = file_get_contents($certPath);
                $certInfo = openssl_x509_parse($certContent);

                if ($certInfo === false) {
                    $issues[] = 'El certificado no es válido o está corrupto';
                } else {
                    $certificateValid = true;

                    // Extraer serial number del certificado (para comparar con ARCA)
                    // Usar comando openssl para obtener el serial en formato hexadecimal (como lo muestra ARCA)
                    $serialNumber = null;
                    try {
                        $command = sprintf(
                            'openssl x509 -in %s -serial -noout 2>/dev/null',
                            escapeshellarg($certPath)
                        );
                        $output = [];
                        exec($command, $output, $returnVar);
                        
                        if ($returnVar === 0 && !empty($output)) {
                            // El formato es: serial=HEXADECIMAL (ej: serial=1BFE290685DAC75C)
                            $line = trim($output[0]);
                            if (preg_match('/serial=([0-9A-Fa-f]+)/', $line, $matches)) {
                                $serialNumber = strtolower($matches[1]);
                            }
                        }
                    } catch (\Exception $e) {
                        // Si falla, intentar con openssl_x509_parse
                        if (isset($certInfo['serialNumber'])) {
                            $serial = $certInfo['serialNumber'];
                            if (is_string($serial)) {
                                $serialNumber = strtolower($serial);
                            } else {
                                $serialNumber = strtolower(dechex($serial));
                            }
                        } elseif (isset($certInfo['serialNumberHex'])) {
                            $serialNumber = strtolower($certInfo['serialNumberHex']);
                        }
                    }
                    
                    if ($serialNumber) {
                        $details['certificate_serial'] = $serialNumber;
                        $suggestions[] = "Verifica en ARCA que el certificado con serial '{$serialNumber}' tenga autorización para 'wsfe'";
                    }

                    // Verificar expiración
                    $validTo = $certInfo['validTo_time_t'] ?? null;
                    if ($validTo !== null) {
                        $details['certificate_expires'] = date('Y-m-d H:i:s', $validTo);
                        if (time() > $validTo) {
                            $issues[] = 'El certificado ha expirado';
                            $suggestions[] = 'Genera un nuevo certificado desde ARCA';
                        }
                    }

                    // Verificar coincidencia con clave privada
                    $keyContent = file_get_contents($keyPath);
                    $password = config('afip.certificates.password');
                    $privateKey = openssl_pkey_get_private($keyContent, $password);

                    if ($privateKey !== false) {
                        $certificateMatchesKey = openssl_x509_check_private_key($certContent, $privateKey);
                        // openssl_free_key() is deprecated in PHP 8.0+, keys are freed automatically
                        if (PHP_VERSION_ID < 80000) {
                            openssl_free_key($privateKey);
                        }

                        if (!$certificateMatchesKey) {
                            $issues[] = 'El certificado y la clave privada no coinciden';
                            $suggestions[] = 'Asegúrate de descargar ambos archivos juntos desde ARCA';
                        }
                    }

                    // Extraer CUIT del certificado
                    $subject = $certInfo['subject'] ?? [];
                    $certCuit = null;

                    if (isset($subject['serialNumber'])) {
                        if (preg_match('/CUIT\s*(\d{11})/i', $subject['serialNumber'], $matches)) {
                            $certCuit = $matches[1];
                        }
                    }

                    if ($certCuit && $cuit) {
                        $cleanedCert = preg_replace('/[^0-9]/', '', $certCuit);
                        $cleanedConfig = preg_replace('/[^0-9]/', '', $cuit);
                        $details['certificate_cuit'] = $certCuit;

                        if ($cleanedCert !== $cleanedConfig) {
                            $issues[] = "El CUIT del certificado ({$certCuit}) no coincide con el configurado ({$cuit})";
                            $suggestions[] = 'Verifica que estés usando el certificado correcto para este CUIT';
                        }
                    }

                    $details['certificate_subject'] = $subject;
                }
            }
        } catch (\Exception $e) {
            $issues[] = 'Error al validar certificado: ' . $e->getMessage();
        }

        // Verificar configuración
        $configOk = !empty($cuit) && !empty($details['entorno']);

        return [
            'config_ok' => $configOk,
            'files_ok' => $filesOk,
            'certificate_valid' => $certificateValid,
            'certificate_matches_key' => $certificateMatchesKey,
            'issues' => $issues,
            'suggestions' => $suggestions,
            'details' => $details,
        ];
    }
}

