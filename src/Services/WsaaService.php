<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use Resguar\AfipSdk\DTOs\TokenResponse;
use Resguar\AfipSdk\Exceptions\AfipAuthenticationException;
use Resguar\AfipSdk\Helpers\CmsHelper;
use Resguar\AfipSdk\Helpers\SoapHelper;
use Resguar\AfipSdk\Helpers\TraGenerator;
use Resguar\AfipSdk\Helpers\ValidatorHelper;
use SoapClient;
use SoapFault;

/**
 * Servicio de autenticación con AFIP (WSAA - Web Service de Autenticación y Autorización)
 *
 * Maneja la obtención y cacheo de tokens de autenticación
 */
class WsaaService
{
    /**
     * Create a new WsaaService instance.
     *
     * @param CertificateManager $certificateManager
     * @param string $environment Entorno (testing|production)
     * @param string $url URL del servicio WSAA
     * @param CacheRepository|null $cache Repositorio de cache (opcional)
     */
    public function __construct(
        private readonly CertificateManager $certificateManager,
        private readonly string $environment,
        private readonly string $url,
        private readonly ?CacheRepository $cache = null
    ) {
    }

    /**
     * Obtiene un token de autenticación para un servicio específico
     *
     * @param string $service Nombre del servicio (wsfe, wsmtxca, etc.)
     * @param string|null $cuit CUIT del contribuyente (opcional, usa config si no se proporciona)
     * @return TokenResponse Token y firma de autenticación
     * @throws AfipAuthenticationException
     */
    public function getToken(string $service, ?string $cuit = null): TokenResponse
    {
        // Obtener y validar CUIT
        $cuit = $this->resolveCuit($cuit);
        $cacheKey = $this->getCacheKey($service, $cuit);

        // Intentar obtener del cache si está habilitado
        if ($this->cache !== null && config('afip.cache.enabled', true)) {
            $cached = $this->cache->get($cacheKey);

            if ($cached instanceof TokenResponse && $cached->isValid()) {
                $this->log('info', "Token obtenido del cache para servicio: {$service}, CUIT: {$cuit}");
                return $cached;
            }
        }

        $this->log('info', "Generando nuevo token para servicio: {$service}, CUIT: {$cuit}");

        try {

            // 2. Generar TRA (Ticket de Requerimiento de Acceso)
            $this->log('debug', 'Generando TRA XML');
            $traXml = $this->environment === 'production'
                ? TraGenerator::generateForProduction($service, $cuit)
                : TraGenerator::generate($service, $cuit);

            // 3. Crear mensaje CMS (PKCS#7) con el TRA firmado
            $this->log('debug', 'Generando mensaje CMS (PKCS#7)');
            $password = config('afip.certificates.password');
            $cms = CmsHelper::createCms(
                $traXml,
                $this->certificateManager->getCertPath(),
                $this->certificateManager->getKeyPath(),
                $password
            );

            // 4. Enviar a WSAA vía SOAP
            $this->log('debug', 'Enviando solicitud a WSAA');
            $soapResponse = $this->sendToWsaa($cms);

            // 5. Procesar respuesta y extraer token y firma
            $this->log('debug', 'Procesando respuesta de WSAA');
            $tokenResponse = $this->parseWsaaResponse($soapResponse);

            // 6. Guardar en cache si está habilitado
            if ($this->cache !== null && config('afip.cache.enabled', true)) {
                $ttl = config('afip.cache.ttl', 43200); // 12 horas
                // Reducir TTL un poco para evitar usar tokens casi expirados
                $cacheTtl = min($ttl, $tokenResponse->getSecondsUntilExpiration() - 300);
                $this->cache->put($cacheKey, $tokenResponse, $cacheTtl);
                $this->log('info', "Token guardado en cache para servicio: {$service}, CUIT: {$cuit}");
            }

            return $tokenResponse;
        } catch (\Exception $e) {
            $this->log('error', "Error al obtener token para servicio {$service}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            throw new AfipAuthenticationException(
                "Error al obtener token de autenticación: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Obtiene la firma digital para el token
     *
     * @param string $service Nombre del servicio
     * @param string|null $cuit CUIT del contribuyente (opcional)
     * @return string Firma digital
     * @throws AfipAuthenticationException
     */
    public function getSignature(string $service, ?string $cuit = null): string
    {
        $tokenResponse = $this->getToken($service, $cuit);
        return $tokenResponse->signature;
    }

    /**
     * Obtiene el token y la firma como array
     *
     * @param string $service Nombre del servicio
     * @param string|null $cuit CUIT del contribuyente (opcional)
     * @return array{token: string, signature: string}
     * @throws AfipAuthenticationException
     */
    public function getTokenAndSignature(string $service, ?string $cuit = null): array
    {
        $tokenResponse = $this->getToken($service, $cuit);

        return [
            'token' => $tokenResponse->token,
            'signature' => $tokenResponse->signature,
        ];
    }

    /**
     * Verifica si hay un token válido en cache
     *
     * @param string $service Nombre del servicio
     * @param string|null $cuit CUIT del contribuyente (opcional)
     * @return bool
     */
    public function hasValidToken(string $service, ?string $cuit = null): bool
    {
        if ($this->cache === null || !config('afip.cache.enabled', true)) {
            return false;
        }

        $cuit = $this->resolveCuit($cuit);
        $cacheKey = $this->getCacheKey($service, $cuit);
        $cached = $this->cache->get($cacheKey);

        return $cached instanceof TokenResponse && $cached->isValid();
    }

    /**
     * Verifica si el servicio está autenticado
     *
     * @param string $service Nombre del servicio
     * @param string|null $cuit CUIT del contribuyente (opcional)
     * @return bool
     */
    public function isAuthenticated(string $service = 'wsfe', ?string $cuit = null): bool
    {
        return $this->hasValidToken($service, $cuit);
    }

    /**
     * Limpia el cache de tokens
     *
     * @param string|null $service Nombre del servicio (null para limpiar todos)
     * @param string|null $cuit CUIT del contribuyente (opcional, null para limpiar todos los CUITs)
     * @return void
     */
    public function clearTokenCache(?string $service = null, ?string $cuit = null): void
    {
        if ($this->cache === null) {
            return;
        }

        if ($service !== null && $cuit !== null) {
            $cuit = $this->resolveCuit($cuit);
            $cacheKey = $this->getCacheKey($service, $cuit);
            $this->cache->forget($cacheKey);
            $this->log('info', "Cache limpiado para servicio: {$service}, CUIT: {$cuit}");
        } else {
            // Limpiar todos los tokens (requiere conocer los servicios y CUITs)
            // Nota: Esto es una aproximación, ya que no conocemos todos los CUITs posibles
            $services = ['wsfe', 'wsmtxca', 'wsfev1'];
            $cuits = $cuit !== null ? [$this->resolveCuit($cuit)] : [config('afip.cuit', 'default')];
            
            foreach ($services as $svc) {
                foreach ($cuits as $c) {
                    $this->cache->forget($this->getCacheKey($svc, $c));
                }
            }
            $this->log('info', 'Cache limpiado para todos los servicios');
        }
    }

    /**
     * Obtiene la clave de cache para un servicio y CUIT
     *
     * @param string $service Nombre del servicio
     * @param string $cuit CUIT del contribuyente
     * @return string
     */
    protected function getCacheKey(string $service, string $cuit): string
    {
        $prefix = config('afip.cache.prefix', 'afip_token_');
        return "{$prefix}{$service}_{$cuit}_{$this->environment}";
    }

    /**
     * Resuelve el CUIT: limpia, valida y usa config si no se proporciona
     *
     * @param string|null $cuit CUIT proporcionado (opcional)
     * @return string CUIT limpio y validado
     * @throws AfipAuthenticationException Si el CUIT no es válido o no está configurado
     */
    protected function resolveCuit(?string $cuit = null): string
    {
        // Si no se proporciona CUIT, usar el de configuración
        if ($cuit === null || $cuit === '') {
            $cuit = config('afip.cuit');
            if (empty($cuit)) {
                throw new AfipAuthenticationException('CUIT no configurado en config/afip.php y no se proporcionó como parámetro');
            }
        }

        // Limpiar CUIT (remover guiones, espacios, etc.)
        $cleaned = ValidatorHelper::cleanCuit($cuit);

        // Validar que tenga 11 dígitos
        if (strlen($cleaned) !== 11) {
            throw new AfipAuthenticationException("El CUIT debe tener 11 dígitos. Recibido: {$cuit} (limpio: {$cleaned})");
        }

        return $cleaned;
    }

    /**
     * Envía el mensaje CMS a WSAA vía SOAP
     *
     * @param string $cms Mensaje CMS codificado en base64
     * @return mixed Respuesta de WSAA
     * @throws AfipAuthenticationException
     */
    protected function sendToWsaa(string $cms): mixed
    {
        try {
            // Crear cliente SOAP
            $client = SoapHelper::createClient($this->url);

            // Llamar método loginCms
            $response = SoapHelper::call(
                $client,
                'loginCms',
                ['in0' => $cms],
                config('afip.retry.max_attempts', 3)
            );

            return $response;
        } catch (SoapFault $e) {
            $this->log('error', 'Error SOAP al comunicarse con WSAA', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'faultcode' => $e->faultcode ?? null,
                'faultstring' => $e->faultstring ?? null,
            ]);

            throw new AfipAuthenticationException(
                "Error al comunicarse con WSAA: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $e->faultcode ?? null,
                $e->faultstring ?? null
            );
        } catch (\Exception $e) {
            $this->log('error', 'Error inesperado al comunicarse con WSAA', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw new AfipAuthenticationException(
                "Error inesperado al comunicarse con WSAA: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Parsea la respuesta de WSAA y extrae token y firma
     *
     * @param mixed $soapResponse Respuesta de WSAA
     * @return TokenResponse
     * @throws AfipAuthenticationException
     */
    protected function parseWsaaResponse(mixed $soapResponse): TokenResponse
    {
        // La respuesta de WSAA viene como string XML
        $xmlResponse = is_object($soapResponse) && isset($soapResponse->loginCmsReturn)
            ? $soapResponse->loginCmsReturn
            : (string) $soapResponse;

        if (empty($xmlResponse)) {
            throw new AfipAuthenticationException('Respuesta vacía de WSAA');
        }

        // Parsear XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlResponse);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $errorMsg = implode(', ', array_map(fn($e) => $e->message, $errors));
            throw new AfipAuthenticationException("Error al parsear respuesta XML de WSAA: {$errorMsg}");
        }

        // Extraer datos
        $token = (string) ($xml->credentials->token ?? '');
        $signature = (string) ($xml->credentials->sign ?? '');

        if (empty($token) || empty($signature)) {
            // Verificar si hay errores en la respuesta
            $errorMsg = (string) ($xml->header->source ?? '');
            if (!empty($errorMsg)) {
                throw new AfipAuthenticationException("Error de WSAA: {$errorMsg}");
            }

            throw new AfipAuthenticationException('Token o firma no encontrados en la respuesta de WSAA');
        }

        // Extraer fecha de expiración
        $expiration = (string) ($xml->header->expirationTime ?? '');
        $expirationDate = new \DateTime('+24 hours'); // Default

        if (!empty($expiration)) {
            // Formato: Y-m-d\TH:i:s.000-03:00
            $parsed = \DateTime::createFromFormat('Y-m-d\TH:i:s.000-03:00', $expiration);
            if ($parsed !== false) {
                $expirationDate = $parsed;
            }
        }

        // Extraer tiempo de generación
        $generationTime = (string) ($xml->header->generationTime ?? date('YmdHis'));

        return new TokenResponse(
            token: $token,
            signature: $signature,
            expirationDate: $expirationDate,
            generationTime: $generationTime
        );
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
            Log::channel($channel)->{$level}("[AFIP SDK] {$message}", $context);
        }
    }
}

