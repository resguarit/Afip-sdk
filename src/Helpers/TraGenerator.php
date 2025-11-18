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
        $dom->formatOutput = false; // Sin formato para evitar espacios extra

        // Elemento raíz (sin namespace - el TRA no debe tener namespace cuando se envía como CMS)
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

        // Destination sin espacios después de las comas (requerido por el esquema XML de AFIP)
        $destination = $dom->createElement('destination', 'CN=wsaahomo,O=AFIP,C=AR,SERIALNUMBER=CUIT 33693450239');
        $header->appendChild($destination);

        // Generar uniqueId único usando timestamp + componente aleatorio
        // AFIP requiere que cada TRA tenga un uniqueId único
        // Usamos timestamp en segundos + número aleatorio de 3 dígitos para garantizar unicidad
        $uniqueIdValue = time() . sprintf('%03d', mt_rand(0, 999));
        $uniqueId = $dom->createElement('uniqueId', $uniqueIdValue);
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
        $dom->formatOutput = false; // Sin formato para evitar espacios extra

        // Elemento raíz (sin namespace - el TRA no debe tener namespace cuando se envía como CMS)
        $loginTicketRequest = $dom->createElement('loginTicketRequest');
        $loginTicketRequest->setAttribute('version', '1.0');
        $dom->appendChild($loginTicketRequest);

        $header = $dom->createElement('header');
        $loginTicketRequest->appendChild($header);

        // Extraer DN del certificado si está disponible
        $sourceDn = self::getSourceDn($cuit, $certPath);
        $source = $dom->createElement('source', $sourceDn);
        $header->appendChild($source);

        // Para producción, el destination es diferente (sin espacios después de las comas)
        $destination = $dom->createElement('destination', 'CN=wsaa,O=AFIP,C=AR,SERIALNUMBER=CUIT 33693450239');
        $header->appendChild($destination);

        // Generar uniqueId único usando timestamp + componente aleatorio
        // AFIP requiere que cada TRA tenga un uniqueId único
        // Usamos timestamp en segundos + número aleatorio de 3 dígitos para garantizar unicidad
        $uniqueIdValue = time() . sprintf('%03d', mt_rand(0, 999));
        $uniqueId = $dom->createElement('uniqueId', $uniqueIdValue);
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
        // Usar formato estándar simple que AFIP acepta
        // IMPORTANTE: Usar SERIALNUMBER en mayúsculas para consistencia con destination
        // El formato debe ser: CN=<cuit>,O=AFIP,C=AR,SERIALNUMBER=CUIT <cuit>
        return 'CN=' . $cuit . ',O=AFIP,C=AR,SERIALNUMBER=CUIT ' . $cuit;
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

