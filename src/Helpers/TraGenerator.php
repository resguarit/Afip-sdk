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

        $uniqueId = $dom->createElement('uniqueId', (string) time());
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

        $uniqueId = $dom->createElement('uniqueId', (string) time());
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
            // El orden debe ser: serialNumber, CN, O, C (según especificación AFIP)
            $dnParts = [];
            
            // serialNumber (si existe)
            if (isset($subject['serialNumber'])) {
                $dnParts[] = 'serialNumber=' . $subject['serialNumber'];
            }
            
            // CN (Common Name)
            if (isset($subject['CN'])) {
                $dnParts[] = 'CN=' . $subject['CN'];
            }
            
            // O (Organization) - si no existe en el certificado, usar AFIP
            if (isset($subject['O'])) {
                $dnParts[] = 'O=' . $subject['O'];
            } else {
                $dnParts[] = 'O=AFIP';
            }
            
            // C (Country) - si no existe en el certificado, usar AR
            if (isset($subject['C'])) {
                $dnParts[] = 'C=' . $subject['C'];
            } else {
                $dnParts[] = 'C=AR';
            }

            return implode(',', $dnParts);
        } catch (\Exception $e) {
            // En caso de error, usar formato estándar
            return 'CN=' . $cuit . ',O=AFIP,C=AR,serialNumber=CUIT ' . $cuit;
        }
    }
}

