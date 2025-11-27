<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Services;

use Resguar\AfipSdk\Exceptions\AfipException;

/**
 * Gestor de certificados digitales
 *
 * Maneja la carga, validación y uso de certificados para comunicación con AFIP.
 * Soporta múltiples certificados para diferentes CUITs (multi-tenant).
 */
class CertificateManager
{
    /**
     * Paths dinámicos para certificados por CUIT
     */
    private ?string $dynamicCertPath = null;
    private ?string $dynamicKeyPath = null;
    
    /**
     * CUIT actualmente cargado
     */
    private ?string $currentCuit = null;

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
     * Establece paths de certificado dinámicos (para multi-CUIT)
     *
     * @param string $certPath Ruta completa al certificado
     * @param string $keyPath Ruta completa a la clave privada
     * @return self
     */
    public function setCertificatePaths(string $certPath, string $keyPath): self
    {
        $this->dynamicCertPath = $certPath;
        $this->dynamicKeyPath = $keyPath;
        return $this;
    }

    /**
     * Carga certificados para un CUIT específico desde la estructura de carpetas
     *
     * Estructura esperada:
     *   {basePath}/{cuit}/certificate.crt
     *   {basePath}/{cuit}/private.key
     *
     * @param string $cuit CUIT del contribuyente
     * @param string|null $basePath Ruta base (usa config si no se especifica)
     * @return self
     * @throws AfipException Si no se encuentran los certificados
     */
    public function loadForCuit(string $cuit, ?string $basePath = null): self
    {
        $basePath = $basePath ?? config('afip.certificates_base_path', $this->certificatesPath);
        
        $cuitPath = $basePath . DIRECTORY_SEPARATOR . $cuit;
        $certPath = $cuitPath . DIRECTORY_SEPARATOR . 'certificate.crt';
        $keyPath = $cuitPath . DIRECTORY_SEPARATOR . 'private.key';
        
        // Verificar que existan los archivos
        if (!file_exists($certPath)) {
            throw new AfipException(
                "Certificado no encontrado para CUIT {$cuit}: {$certPath}"
            );
        }
        
        if (!file_exists($keyPath)) {
            throw new AfipException(
                "Clave privada no encontrada para CUIT {$cuit}: {$keyPath}"
            );
        }
        
        $this->dynamicCertPath = $certPath;
        $this->dynamicKeyPath = $keyPath;
        $this->currentCuit = $cuit;
        
        return $this;
    }

    /**
     * Verifica si hay certificados cargados para un CUIT específico
     *
     * @param string $cuit CUIT a verificar
     * @param string|null $basePath Ruta base (usa config si no se especifica)
     * @return bool
     */
    public function hasCertificatesForCuit(string $cuit, ?string $basePath = null): bool
    {
        $basePath = $basePath ?? config('afip.certificates_base_path', $this->certificatesPath);
        
        $cuitPath = $basePath . DIRECTORY_SEPARATOR . $cuit;
        $certPath = $cuitPath . DIRECTORY_SEPARATOR . 'certificate.crt';
        $keyPath = $cuitPath . DIRECTORY_SEPARATOR . 'private.key';
        
        return file_exists($certPath) && file_exists($keyPath);
    }

    /**
     * Obtiene el CUIT actualmente cargado
     *
     * @return string|null
     */
    public function getCurrentCuit(): ?string
    {
        return $this->currentCuit;
    }

    /**
     * Limpia los paths dinámicos y vuelve a usar los de configuración
     *
     * @return self
     */
    public function resetToDefault(): self
    {
        $this->dynamicCertPath = null;
        $this->dynamicKeyPath = null;
        $this->currentCuit = null;
        return $this;
    }

    /**
     * Obtiene la ruta completa al archivo de clave privada
     *
     * @return string
     * @throws AfipException
     */
    public function getKeyPath(): string
    {
        // Usar path dinámico si está establecido
        if ($this->dynamicKeyPath !== null) {
            if (!file_exists($this->dynamicKeyPath)) {
                throw new AfipException("Clave privada no encontrada: {$this->dynamicKeyPath}");
            }
            return $this->dynamicKeyPath;
        }

        // Fallback a configuración original
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
        // Usar path dinámico si está establecido
        if ($this->dynamicCertPath !== null) {
            if (!file_exists($this->dynamicCertPath)) {
                throw new AfipException("Certificado no encontrado: {$this->dynamicCertPath}");
            }
            return $this->dynamicCertPath;
        }

        // Fallback a configuración original
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

