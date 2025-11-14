<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Services;

use Resguar\AfipSdk\Exceptions\AfipException;

/**
 * Gestor de certificados digitales
 *
 * Maneja la carga, validación y uso de certificados para comunicación con AFIP
 */
class CertificateManager
{
    /**
     * Create a new CertificateManager instance.
     *
     * @param string $certificatesPath Ruta base donde se almacenan los certificados
     * @param string $keyFileName Nombre del archivo de clave privada
     * @param string $certFileName Nombre del archivo de certificado
     */
    public function __construct(
        private readonly string $certificatesPath,
        private readonly string $keyFileName,
        private readonly string $certFileName
    ) {
    }

    /**
     * Obtiene la ruta completa al archivo de clave privada
     *
     * @return string
     * @throws AfipException
     */
    public function getKeyPath(): string
    {
        $path = $this->certificatesPath . DIRECTORY_SEPARATOR . $this->keyFileName;

        if (!file_exists($path)) {
            throw new AfipException("Certificado de clave privada no encontrado: {$path}");
        }

        return $path;
    }

    /**
     * Obtiene la ruta completa al archivo de certificado
     *
     * @return string
     * @throws AfipException
     */
    public function getCertPath(): string
    {
        $path = $this->certificatesPath . DIRECTORY_SEPARATOR . $this->certFileName;

        if (!file_exists($path)) {
            throw new AfipException("Certificado no encontrado: {$path}");
        }

        return $path;
    }

    /**
     * Lee el contenido del archivo de clave privada
     *
     * @return string
     * @throws AfipException
     */
    public function getKeyContent(): string
    {
        $content = file_get_contents($this->getKeyPath());

        if ($content === false) {
            throw new AfipException("No se pudo leer el archivo de clave privada");
        }

        return $content;
    }

    /**
     * Lee el contenido del archivo de certificado
     *
     * @return string
     * @throws AfipException
     */
    public function getCertContent(): string
    {
        $content = file_get_contents($this->getCertPath());

        if ($content === false) {
            throw new AfipException("No se pudo leer el archivo de certificado");
        }

        return $content;
    }

    /**
     * Valida que el certificado sea válido
     *
     * @return bool
     * @throws AfipException
     */
    public function validateCertificate(): bool
    {
        // TODO: Implementar validación de certificado
        // - Verificar que no esté vencido
        // - Verificar que corresponda al CUIT configurado
        // - Verificar formato

        return true;
    }

    /**
     * Firma un mensaje con la clave privada usando OpenSSL
     *
     * @param string $message Mensaje a firmar
     * @return string Firma digital en base64
     * @throws AfipException
     */
    public function sign(string $message): string
    {
        $keyPath = $this->getKeyPath();
        $password = config('afip.certificates.password');

        // Cargar clave privada
        $privateKey = openssl_pkey_get_private(
            file_get_contents($keyPath),
            $password
        );

        if ($privateKey === false) {
            $error = openssl_error_string() ?: 'Error desconocido';
            throw new AfipException("Error al cargar clave privada: {$error}");
        }

        // Firmar mensaje con SHA256
        $signature = '';
        if (!openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            $error = openssl_error_string() ?: 'Error desconocido';
            openssl_free_key($privateKey);
            throw new AfipException("Error al firmar mensaje: {$error}");
        }

        openssl_free_key($privateKey);

        // Retornar firma en base64
        return base64_encode($signature);
    }
}

