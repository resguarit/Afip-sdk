<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Helpers;

use DOMDocument;
use DOMElement;

/**
 * Helper para generar el TRA (Ticket de Requerimiento de Acceso) según especificación AFIP
 *
 * El TRA es un XML que se firma digitalmente antes de enviarlo a WSAA
 */
class TraGenerator
{
    /**
     * Genera el XML del TRA según la especificación de AFIP
     *
     * @param string $service Nombre del servicio (wsfe, wsmtxca, etc.)
     * @param string $cuit CUIT del contribuyente
     * @param string|null $certPath Ruta al certificado para extraer el DN (opcional)
     * @return string XML del TRA
     */
    public static function generate(string $service, string $cuit, ?string $certPath = null): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Elemento raíz
        $loginTicketRequest = $dom->createElement('loginTicketRequest');
        $loginTicketRequest->setAttribute('version', '1.0');
        $dom->appendChild($loginTicketRequest);

        // Header
        $header = $dom->createElement('header');
        $loginTicketRequest->appendChild($header);

        // Extraer DN del certificado si está disponible
        $sourceDn = self::getSourceDn($cuit, $certPath);
        $source = $dom->createElement('source', $sourceDn);
        $header->appendChild($source);

        $destination = $dom->createElement('destination', 'CN=wsaahomo, O=AFIP, C=AR, SERIALNUMBER=CUIT 33693450239');
        $header->appendChild($destination);

        // Generar uniqueId único usando microsegundos para evitar colisiones
        // AFIP requiere que cada TRA tenga un uniqueId único, incluso si se generan en el mismo segundo
        $uniqueIdValue = (int)(microtime(true) * 1000000);
        $uniqueId = $dom->createElement('uniqueId', (string) $uniqueIdValue);
        $header->appendChild($uniqueId);

        $generationTime = $dom->createElement('generationTime', date('Y-m-d\TH:i:s.000-03:00'));
        $header->appendChild($generationTime);

        $expirationTime = $dom->createElement('expirationTime', date('Y-m-d\TH:i:s.000-03:00', strtotime('+1 day')));
        $header->appendChild($expirationTime);

        // Service (nombre del servicio)
        $serviceElement = $dom->createElement('service', $service);
        $loginTicketRequest->appendChild($serviceElement);

        return $dom->saveXML();
    }

    /**
     * Genera el TRA para producción
     *
     * @param string $service
     * @param string $cuit
     * @param string|null $certPath Ruta al certificado para extraer el DN (opcional)
     * @return string
     */
    public static function generateForProduction(string $service, string $cuit, ?string $certPath = null): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $loginTicketRequest = $dom->createElement('loginTicketRequest');
        $loginTicketRequest->setAttribute('version', '1.0');
        $dom->appendChild($loginTicketRequest);

        $header = $dom->createElement('header');
        $loginTicketRequest->appendChild($header);

        // Extraer DN del certificado si está disponible
        $sourceDn = self::getSourceDn($cuit, $certPath);
        $source = $dom->createElement('source', $sourceDn);
        $header->appendChild($source);

        // Para producción, el destination es diferente
        $destination = $dom->createElement('destination', 'CN=wsaa, O=AFIP, C=AR, SERIALNUMBER=CUIT 33693450239');
        $header->appendChild($destination);

        // Generar uniqueId único usando microsegundos para evitar colisiones
        // AFIP requiere que cada TRA tenga un uniqueId único, incluso si se generan en el mismo segundo
        $uniqueIdValue = (int)(microtime(true) * 1000000);
        $uniqueId = $dom->createElement('uniqueId', (string) $uniqueIdValue);
        $header->appendChild($uniqueId);

        $generationTime = $dom->createElement('generationTime', date('Y-m-d\TH:i:s.000-03:00'));
        $header->appendChild($generationTime);

        $expirationTime = $dom->createElement('expirationTime', date('Y-m-d\TH:i:s.000-03:00', strtotime('+1 day')));
        $header->appendChild($expirationTime);

        $serviceElement = $dom->createElement('service', $service);
        $loginTicketRequest->appendChild($serviceElement);

        return $dom->saveXML();
    }

    /**
     * Obtiene el DN (Distinguished Name) para el elemento source del TRA
     * 
     * Si se proporciona la ruta del certificado, extrae el DN del certificado.
     * Si no, genera un DN estándar usando el CUIT.
     *
     * @param string $cuit CUIT del contribuyente
     * @param string|null $certPath Ruta al certificado (opcional)
     * @return string DN formateado para el TRA
     */
    private static function getSourceDn(string $cuit, ?string $certPath = null): string
    {
        // Si no hay certificado, usar formato estándar (backward compatibility)
        if ($certPath === null || !file_exists($certPath)) {
            return 'CN=' . $cuit . ',O=AFIP,C=AR,serialNumber=CUIT ' . $cuit;
        }

        try {
            // Leer certificado
            $certContent = file_get_contents($certPath);
            if ($certContent === false) {
                // Fallback a formato estándar si no se puede leer
                return 'CN=' . $cuit . ',O=AFIP,C=AR,serialNumber=CUIT ' . $cuit;
            }

            // Parsear certificado
            $certInfo = openssl_x509_parse($certContent);
            if ($certInfo === false || !isset($certInfo['subject'])) {
                // Fallback a formato estándar si no se puede parsear
                return 'CN=' . $cuit . ',O=AFIP,C=AR,serialNumber=CUIT ' . $cuit;
            }

            $subject = $certInfo['subject'];
            
            // Construir DN desde el certificado
            // Según el error de AFIP, el formato esperado es:
            // 2.5.4.5=#<hex_encoded>,cn=<value> (sin O ni C si no están en el certificado)
            $dnParts = [];
            
            // serialNumber - AFIP espera formato OID (2.5.4.5) con valor codificado
            // IMPORTANTE: Debe ir PRIMERO según el error de AFIP
            if (isset($subject['serialNumber'])) {
                $serialValue = $subject['serialNumber'];
                // Codificar el valor en formato DER para el OID
                // El formato es: 2.5.4.5=#<hex_encoded_value>
                $encoded = self::encodeDerString($serialValue);
                $dnParts[] = '2.5.4.5=#' . $encoded;
            }
            
            // CN (Common Name) - AFIP espera en minúsculas
            // IMPORTANTE: Debe ir DESPUÉS del serialNumber
            if (isset($subject['CN'])) {
                $dnParts[] = 'cn=' . strtolower($subject['CN']);
            }

            // NO agregar O y C si no están en el certificado original
            // El error de AFIP indica que no los espera si no están en el certificado
            // Solo agregar si realmente existen en el certificado
            if (isset($subject['O']) && !empty($subject['O'])) {
                $dnParts[] = 'o=' . strtolower($subject['O']);
            }
            
            if (isset($subject['C']) && !empty($subject['C'])) {
                $dnParts[] = 'c=' . strtolower($subject['C']);
            }

            // Si no se pudo construir el DN desde el certificado, lanzar excepción
            // en lugar de usar fallback, para que el error sea más claro
            if (empty($dnParts)) {
                throw new \Exception('No se pudo extraer DN del certificado');
            }

            return implode(',', $dnParts);
        } catch (\Exception $e) {
            // En caso de error, usar formato estándar
            return 'CN=' . $cuit . ',O=AFIP,C=AR,serialNumber=CUIT ' . $cuit;
        }
    }

    /**
     * Codifica un string en formato DER para usar en OID
     * 
     * AFIP requiere que el serialNumber se codifique como PrintableString (tag 0x13),
     * no como UTF8String (tag 0x0C). Esto es crítico para que WSAA acepte el TRA.
     * 
     * @param string $value Valor a codificar
     * @return string Valor codificado en hexadecimal
     */
    private static function encodeDerString(string $value): string
    {
        // Codificar el string en formato DER PrintableString
        // El formato DER para PrintableString es: 0x13 (tag) + length + value
        // IMPORTANTE: AFIP requiere PrintableString (0x13), no UTF8String (0x0C)
        $length = strlen($value);
        $encoded = '';
        
        // Tag para PrintableString (AFIP requiere este tag, no UTF8String)
        $encoded .= '13';
        
        // Longitud (si es menor a 128, usar un byte)
        if ($length < 128) {
            $encoded .= sprintf('%02x', $length);
        } else {
            // Para longitudes mayores, usar formato largo
            $lengthBytes = '';
            $tempLength = $length;
            while ($tempLength > 0) {
                $lengthBytes = sprintf('%02x', $tempLength & 0xFF) . $lengthBytes;
                $tempLength >>= 8;
            }
            $encoded .= sprintf('%02x', 0x80 | strlen($lengthBytes)) . $lengthBytes;
        }
        
        // Valor codificado en hexadecimal
        for ($i = 0; $i < $length; $i++) {
            $encoded .= sprintf('%02x', ord($value[$i]));
        }
        
        return $encoded;
    }
}

