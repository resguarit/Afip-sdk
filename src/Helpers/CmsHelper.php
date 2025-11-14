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

        // Crear archivos temporales
        $tempDir = sys_get_temp_dir();
        $tempTraFile = $tempDir . DIRECTORY_SEPARATOR . 'tra_' . uniqid() . '.xml';
        $tempCmsFile = $tempDir . DIRECTORY_SEPARATOR . 'cms_' . uniqid() . '.p7m';

        try {
            // Guardar TRA en archivo temporal
            file_put_contents($tempTraFile, $traXml);

            // Generar CMS usando OpenSSL
            $command = sprintf(
                'openssl cms -sign -in %s -out %s -signer %s -inkey %s -outform DER -nodetach -nocerts',
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
            $command = sprintf(
                'openssl cms -sign -in %s -out %s -signer %s -inkey %s -outform DER -nodetach -nocerts',
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
}

