<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Helpers;

use Resguar\AfipSdk\Exceptions\AfipException;

/**
 * Helper para generar mensajes CMS (PKCS#7) según especificación AFIP
 *
 * El CMS es un mensaje PKCS#7 que contiene:
 * - El TRA (Ticket de Requerimiento de Acceso) firmado
 * - El certificado público
 * - La firma digital
 */
class CmsHelper
{
    /**
     * Genera un mensaje CMS (PKCS#7) firmado para enviar a WSAA
     *
     * @param string $traXml XML del TRA a firmar
     * @param string $certPath Ruta al archivo de certificado (.crt)
     * @param string $keyPath Ruta al archivo de clave privada (.key)
     * @param string|null $password Contraseña de la clave privada (opcional)
     * @return string Mensaje CMS codificado en base64
     * @throws AfipException
     */
    public static function createCms(
        string $traXml,
        string $certPath,
        string $keyPath,
        ?string $password = null
    ): string {
        if (!file_exists($certPath)) {
            throw new AfipException("Certificado no encontrado: {$certPath}");
        }

        if (!file_exists($keyPath)) {
            throw new AfipException("Clave privada no encontrada: {$keyPath}");
        }

        // Validar certificado antes de firmar
        self::validateCertificateBeforeSigning($certPath, $keyPath, config('afip.cuit'));

        // Crear archivos temporales
        $tempDir = sys_get_temp_dir();
        $tempTraFile = $tempDir . DIRECTORY_SEPARATOR . 'tra_' . uniqid() . '.xml';
        $tempCmsFile = $tempDir . DIRECTORY_SEPARATOR . 'cms_' . uniqid() . '.p7m';

        try {
            // Guardar TRA en archivo temporal
            // Asegurar que el XML esté en formato UTF-8 sin BOM y sin espacios extra
            $traXml = trim($traXml);
            // Remover cualquier BOM UTF-8 si existe
            $traXml = preg_replace('/^\xEF\xBB\xBF/', '', $traXml);
            file_put_contents($tempTraFile, $traXml);

            // Generar CMS usando OpenSSL
            // NOTA: NO usar -nocerts porque AFIP requiere el certificado en el CMS para validarlo
            // NOTA: Usar -binary para evitar que OpenSSL normalice saltos de línea y rompa el hash
            $command = sprintf(
                'openssl cms -sign -in %s -out %s -signer %s -inkey %s -outform DER -nodetach -binary',
                escapeshellarg($tempTraFile),
                escapeshellarg($tempCmsFile),
                escapeshellarg($certPath),
                escapeshellarg($keyPath)
            );

            // Agregar contraseña si existe
            if ($password !== null && $password !== '') {
                $command .= ' -passin pass:' . escapeshellarg($password);
            }

            // Ejecutar comando
            $output = [];
            $returnVar = 0;
            exec($command . ' 2>&1', $output, $returnVar);

            if ($returnVar !== 0 || !file_exists($tempCmsFile)) {
                $error = implode("\n", $output);
                throw new AfipException("Error al generar CMS: {$error}");
            }

            // Leer CMS y codificar en base64
            $cmsContent = file_get_contents($tempCmsFile);
            if ($cmsContent === false) {
                throw new AfipException("Error al leer archivo CMS generado");
            }

            return base64_encode($cmsContent);
        } finally {
            // Limpiar archivos temporales
            if (file_exists($tempTraFile)) {
                @unlink($tempTraFile);
            }
            if (file_exists($tempCmsFile)) {
                @unlink($tempCmsFile);
            }
        }
    }

    /**
     * Genera un mensaje CMS usando OpenSSL directamente (método alternativo)
     *
     * @param string $traXml XML del TRA
     * @param string $certContent Contenido del certificado
     * @param string $keyContent Contenido de la clave privada
     * @param string|null $password Contraseña de la clave privada
     * @return string Mensaje CMS codificado en base64
     * @throws AfipException
     */
    public static function createCmsFromContent(
        string $traXml,
        string $certContent,
        string $keyContent,
        ?string $password = null
    ): string {
        // Crear archivos temporales
        $tempDir = sys_get_temp_dir();
        $tempTraFile = $tempDir . DIRECTORY_SEPARATOR . 'tra_' . uniqid() . '.xml';
        $tempCertFile = $tempDir . DIRECTORY_SEPARATOR . 'cert_' . uniqid() . '.crt';
        $tempKeyFile = $tempDir . DIRECTORY_SEPARATOR . 'key_' . uniqid() . '.key';
        $tempCmsFile = $tempDir . DIRECTORY_SEPARATOR . 'cms_' . uniqid() . '.p7m';

        try {
            // Guardar contenido en archivos temporales
            file_put_contents($tempTraFile, $traXml);
            file_put_contents($tempCertFile, $certContent);
            file_put_contents($tempKeyFile, $keyContent);

            // Generar CMS
            // NOTA: NO usar -nocerts porque AFIP requiere el certificado en el CMS para validarlo
            // NOTA: Usar -binary para evitar que OpenSSL normalice saltos de línea y rompa el hash
            $command = sprintf(
                'openssl cms -sign -in %s -out %s -signer %s -inkey %s -outform DER -nodetach -binary',
                escapeshellarg($tempTraFile),
                escapeshellarg($tempCmsFile),
                escapeshellarg($tempCertFile),
                escapeshellarg($tempKeyFile)
            );

            if ($password !== null && $password !== '') {
                $command .= ' -passin pass:' . escapeshellarg($password);
            }

            $output = [];
            $returnVar = 0;
            exec($command . ' 2>&1', $output, $returnVar);

            if ($returnVar !== 0 || !file_exists($tempCmsFile)) {
                $error = implode("\n", $output);
                throw new AfipException("Error al generar CMS: {$error}");
            }

            $cmsContent = file_get_contents($tempCmsFile);
            if ($cmsContent === false) {
                throw new AfipException("Error al leer archivo CMS generado");
            }

            return base64_encode($cmsContent);
        } finally {
            // Limpiar archivos temporales
            @unlink($tempTraFile);
            @unlink($tempCertFile);
            @unlink($tempKeyFile);
            if (file_exists($tempCmsFile)) {
                @unlink($tempCmsFile);
            }
        }
    }

    /**
     * Valida el certificado antes de firmar
     *
     * Verifica:
     * - Que el certificado no haya expirado
     * - Que el certificado y la clave privada coincidan
     * - (Opcional) Que el CUIT del certificado coincida con el configurado
     *
     * @param string $certPath Ruta al certificado
     * @param string $keyPath Ruta a la clave privada
     * @param string|null $expectedCuit CUIT esperado (opcional)
     * @return void
     * @throws AfipException Si hay problemas con el certificado
     */
    private static function validateCertificateBeforeSigning(
        string $certPath,
        string $keyPath,
        ?string $expectedCuit = null
    ): void {
        // Leer certificado
        $certContent = file_get_contents($certPath);
        if ($certContent === false) {
            throw new AfipException("No se pudo leer el certificado: {$certPath}");
        }

        // Parsear certificado
        $certInfo = openssl_x509_parse($certContent);
        if ($certInfo === false) {
            throw new AfipException("El certificado no es válido o está corrupto: {$certPath}");
        }

        // 1. Verificar que el certificado no haya expirado
        $validTo = $certInfo['validTo_time_t'] ?? null;
        if ($validTo !== null) {
            $now = time();
            if ($now > $validTo) {
                $expirationDate = date('Y-m-d H:i:s', $validTo);
                throw new AfipException(
                    "El certificado expiró el {$expirationDate}. " .
                    "Genera y descarga un nuevo certificado desde ARCA."
                );
            }
        }

        // 2. Verificar que el certificado y la clave privada coincidan
        $keyContent = file_get_contents($keyPath);
        if ($keyContent === false) {
            throw new AfipException("No se pudo leer la clave privada: {$keyPath}");
        }

        $password = config('afip.certificates.password');
        $privateKey = openssl_pkey_get_private($keyContent, $password);
        if ($privateKey === false) {
            throw new AfipException(
                "Error al cargar la clave privada. " .
                "Verifica que la contraseña sea correcta o que el archivo no esté corrupto."
            );
        }

        $keyDetails = openssl_pkey_get_details($privateKey);
        $certPublicKey = openssl_pkey_get_public($certContent);
        
        if ($certPublicKey === false) {
            throw new AfipException("No se pudo extraer la clave pública del certificado.");
        }

        $certKeyDetails = openssl_pkey_get_details($certPublicKey);
        
        // Comparar módulos (RSA) o parámetros (EC)
        $keyMatches = false;
        if (isset($keyDetails['rsa']['n']) && isset($certKeyDetails['rsa']['n'])) {
            // Comparar módulo RSA
            $keyMatches = ($keyDetails['rsa']['n'] === $certKeyDetails['rsa']['n']);
        } elseif (isset($keyDetails['ec']) && isset($certKeyDetails['ec'])) {
            // Comparar parámetros EC
            $keyMatches = ($keyDetails['ec'] === $certKeyDetails['ec']);
        } else {
            // Si no podemos comparar, intentar verificar con openssl_x509_check_private_key
            $keyMatches = openssl_x509_check_private_key($certContent, $privateKey);
        }

        if (!$keyMatches) {
            throw new AfipException(
                "El certificado y la clave privada no coinciden. " .
                "Verifica que sean del mismo par de claves generado en ARCA."
            );
        }

        // 3. (Opcional) Verificar que el CUIT del certificado coincida con el configurado
        if ($expectedCuit !== null && !empty($expectedCuit)) {
            $certCuit = self::extractCuitFromCertificate($certInfo);
            if ($certCuit !== null) {
                $cleanedExpected = preg_replace('/[^0-9]/', '', $expectedCuit);
                $cleanedCert = preg_replace('/[^0-9]/', '', $certCuit);
                
                if ($cleanedExpected !== $cleanedCert) {
                    // Solo log warning, no lanzar excepción (puede ser válido en algunos casos)
                    \Log::warning('El CUIT del certificado no coincide con el configurado', [
                        'cuit_configurado' => $cleanedExpected,
                        'cuit_certificado' => $cleanedCert,
                        'cert_path' => $certPath,
                    ]);
                }
            }
        }
    }

    /**
     * Extrae el CUIT del certificado desde el subject
     *
     * @param array $certInfo Información del certificado parseado
     * @return string|null CUIT extraído o null si no se encuentra
     */
    private static function extractCuitFromCertificate(array $certInfo): ?string
    {
        $subject = $certInfo['subject'] ?? [];
        
        // Buscar en serialNumber (formato: "CUIT 20457809027")
        if (isset($subject['serialNumber'])) {
            $serialNumber = $subject['serialNumber'];
            if (preg_match('/CUIT\s*(\d{11})/i', $serialNumber, $matches)) {
                return $matches[1];
            }
        }

        // Buscar en CN (formato: "CN=20457809027, O=...")
        if (isset($subject['CN'])) {
            $cn = $subject['CN'];
            if (preg_match('/(\d{11})/', $cn, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}

