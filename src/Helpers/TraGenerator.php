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
     * @return string XML del TRA
     */
    public static function generate(string $service, string $cuit): string
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

        $source = $dom->createElement('source', 'CN=' . $cuit . ',O=AFIP,C=AR,serialNumber=CUIT ' . $cuit);
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
     * @return string
     */
    public static function generateForProduction(string $service, string $cuit): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $loginTicketRequest = $dom->createElement('loginTicketRequest');
        $loginTicketRequest->setAttribute('version', '1.0');
        $dom->appendChild($loginTicketRequest);

        $header = $dom->createElement('header');
        $loginTicketRequest->appendChild($header);

        $source = $dom->createElement('source', 'CN=' . $cuit . ',O=AFIP,C=AR,serialNumber=CUIT ' . $cuit);
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
}

