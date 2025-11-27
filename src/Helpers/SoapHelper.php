<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Helpers;

use SoapClient;
use SoapFault;

/**
 * Helper para operaciones con SOAP
 */
class SoapHelper
{
    /**
     * Crea un cliente SOAP con configuración estándar para AFIP
     *
     * @param string $wsdl URL del WSDL
     * @param array $options Opciones adicionales para SoapClient
     * @return SoapClient
     * @throws \SoapFault
     */
    public static function createClient(string $wsdl, array $options = []): SoapClient
    {
        $defaultOptions = [
            'soap_version' => SOAP_1_2,
            'exceptions' => true,
            'trace' => true,
            'cache_wsdl' => WSDL_CACHE_BOTH,
            'stream_context' => stream_context_create([
                'http' => [
                    'timeout' => config('afip.timeout', 30),
                    'user_agent' => 'Resguar AFIP SDK',
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    // Configuración para compatibilidad con servidores AFIP
                    // Soluciona errores "dh key too small" y "Could not connect to host"
                    'ciphers' => config('afip.ssl.ciphers', 'DEFAULT:!DH'),
                    'security_level' => (int) config('afip.ssl.security_level', 1),
                ],
            ]),
        ];

        $mergedOptions = array_merge($defaultOptions, $options);

        return new SoapClient($wsdl, $mergedOptions);
    }

    /**
     * Ejecuta una llamada SOAP con manejo de errores
     *
     * @param SoapClient $client Cliente SOAP
     * @param string $method Método a llamar
     * @param array $params Parámetros del método
     * @param int $maxRetries Número máximo de reintentos
     * @return mixed
     * @throws \SoapFault
     */
    public static function call(SoapClient $client, string $method, array $params, int $maxRetries = 3): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                return $client->__soapCall($method, [$params]);
            } catch (SoapFault $e) {
                $lastException = $e;
                $attempt++;

                // Si es un error de timeout o conexión, reintentar
                if ($attempt < $maxRetries && self::isRetryableError($e)) {
                    $delay = self::calculateDelay($attempt);
                    usleep($delay * 1000); // Convertir a microsegundos
                    continue;
                }

                throw $e;
            }
        }

        throw $lastException ?? new SoapFault('Unknown', 'Failed to execute SOAP call');
    }

    /**
     * Verifica si un error es recuperable (puede reintentarse)
     *
     * @param SoapFault $exception
     * @return bool
     */
    protected static function isRetryableError(SoapFault $exception): bool
    {
        $retryableMessages = [
            'timeout',
            'connection',
            'timed out',
            'could not connect',
            'network',
        ];

        $message = strtolower($exception->getMessage());

        foreach ($retryableMessages as $retryable) {
            if (str_contains($message, $retryable)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calcula el delay para reintentos (exponential backoff)
     *
     * @param int $attempt Número de intento
     * @return int Delay en milisegundos
     */
    protected static function calculateDelay(int $attempt): int
    {
        $baseDelay = config('afip.retry.delay', 1000);
        $maxDelay = 10000; // 10 segundos máximo

        $delay = $baseDelay * (2 ** ($attempt - 1));
        return min($delay, $maxDelay);
    }

    /**
     * Obtiene la última solicitud SOAP
     *
     * @param SoapClient $client
     * @return string
     */
    public static function getLastRequest(SoapClient $client): string
    {
        return $client->__getLastRequest();
    }

    /**
     * Obtiene la última respuesta SOAP
     *
     * @param SoapClient $client
     * @return string
     */
    public static function getLastResponse(SoapClient $client): string
    {
        return $client->__getLastResponse();
    }
}

