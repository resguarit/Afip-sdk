<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Services;

use Resguar\AfipSdk\DTOs\InvoiceResponse;
use Resguar\AfipSdk\Helpers\AfipQrHelper;

/**
 * Genera HTML de Ticket fiscal (térmico 58/80mm) y Factura A4
 * con QR según especificación AFIP.
 *
 * @see https://www.afip.gob.ar/fe/qr/documentos/QRespecificaciones.pdf
 */
class ReceiptRenderer
{
    private const TIPO_LETRAS = [
        1 => 'A',
        2 => 'A',
        3 => 'A',
        4 => 'A',
        6 => 'B',
        7 => 'B',
        8 => 'B',
        9 => 'B',
        11 => 'C',
        12 => 'C',
        13 => 'C',
        15 => 'C',
        51 => 'M',
        52 => 'M',
        53 => 'M',
        54 => 'M',
    ];

    private string $templatesPath;

    public function __construct(?string $templatesPath = null)
    {
        $this->templatesPath = $templatesPath ?? __DIR__ . '/../Resources/templates';
    }

    /**
     * Genera HTML para Ticket fiscal (formato térmico 58/80mm).
     *
     * @param array $invoice Datos del comprobante y emisor/receptor (ver docs)
     * @param InvoiceResponse $response Respuesta de AFIP con CAE
     * @param int $qrSize Tamaño del QR en píxeles (opcional, si hay endroid/qr-code)
     */
    public function renderTicketHtml(array $invoice, InvoiceResponse $response, int $qrSize = 180): string
    {
        $data = $this->buildTemplateData($invoice, $response, $qrSize);
        $data['tipo_letra'] = self::TIPO_LETRAS[$response->invoiceType] ?? 'B';
        $data['tipo_codigo'] = $response->invoiceType;
        $data['qr_src'] = $data['qr_data_uri'] ?? '';
        if ($data['qr_src'] === '' && !empty($data['qr_data_url'])) {
            $data['qr_src'] = ''; // Sin librería QR: el template puede dejar espacio o URL
        }
        return $this->renderTemplate('ticket.php', $data);
    }

    /**
     * Genera HTML para Factura A4 (formato oficial completo).
     *
     * @param array $invoice Datos del comprobante y emisor/receptor
     * @param InvoiceResponse $response Respuesta de AFIP con CAE
     * @param int $qrSize Tamaño del QR en píxeles
     */
    public function renderFacturaA4Html(array $invoice, InvoiceResponse $response, int $qrSize = 120): string
    {
        $data = $this->buildTemplateData($invoice, $response, $qrSize);
        $data['tipo_letra'] = self::TIPO_LETRAS[$response->invoiceType] ?? 'B';
        $data['tipo_codigo'] = $response->invoiceType;
        $data['qr_src'] = $data['qr_data_uri'] ?? '';
        return $this->renderTemplate('factura-a4.php', $data);
    }

    /**
     * Parámetros recomendados para generar PDF desde el HTML.
     * Ticket: 80mm, márgenes 0. Factura A4: hoja A4 (210×297mm), márgenes 20mm.
     *
     * El template factura-a4.php incluye @page { size: A4; margin: 20mm; }.
     * Usar estas opciones al configurar DomPDF/u otra librería para que coincidan.
     *
     * @return array{ ticket: array, factura_a4: array }
     */
    public static function getPdfOptions(): array
    {
        return [
            'ticket' => [
                'size' => [80, null],
                'width' => 80,
                'marginLeft' => 0,
                'marginRight' => 0,
                'marginTop' => 0,
                'marginBottom' => 0,
            ],
            'factura_a4' => [
                'size' => 'A4',
                'marginLeft' => 20,   // mm (20mm como en @page del template)
                'marginRight' => 20,
                'marginTop' => 20,
                'marginBottom' => 20,
            ],
        ];
    }

    private function buildTemplateData(array $invoice, InvoiceResponse $response, int $qrSize = 200): array
    {
        $fecha = $response->additionalData['CbteFch'] ?? $invoice['date'] ?? date('Ymd');
        // Asegurar formato fecha para visualización (dd/mm/yyyy)
        if (strlen($fecha) === 8 && is_numeric($fecha)) {
            $fechaFormatted = substr($fecha, 6, 2) . '/' . substr($fecha, 4, 2) . '/' . substr($fecha, 0, 4);
            $fechaQr = substr($fecha, 0, 4) . '-' . substr($fecha, 4, 2) . '-' . substr($fecha, 6, 2);
        } else {
            // Si viene con guiones o barras, intentar parsearlo
            $ts = strtotime(str_replace('/', '-', $fecha));
            if ($ts !== false) {
                $fechaFormatted = date('d/m/Y', $ts);
                $fechaQr = date('Y-m-d', $ts);
            } else {
                $fechaFormatted = $fecha;
                $fechaQr = date('Y-m-d'); // Fallback hoy
            }
        }
        $caeVto = $response->caeExpirationDate;
        if (strlen($caeVto) === 8 && is_numeric($caeVto)) {
            $caeVtoFormatted = substr($caeVto, 6, 2) . '/' . substr($caeVto, 4, 2) . '/' . substr($caeVto, 0, 4);
        } else {
            $caeVtoFormatted = $caeVto;
        }

        $cuit = $invoice['issuer']['cuit'] ?? $invoice['cuit'] ?? config('afip.cuit', '');
        $total = (float) ($invoice['total'] ?? $invoice['ImpTotal'] ?? 0);
        $moneda = $invoice['moneda'] ?? 'PES';
        $ctz = (float) ($invoice['cotizacionMoneda'] ?? $invoice['MonCotiz'] ?? 1);

        $qrParams = [
            'fecha' => $fechaQr,
            'cuit' => $cuit,
            'ptoVta' => $response->pointOfSale,
            'tipoCmp' => $response->invoiceType,
            'nroCmp' => $response->invoiceNumber,
            'importe' => $total,
            'moneda' => $moneda,
            'ctz' => $ctz,
            'tipoCodAut' => 'E',
            'codAut' => $response->cae ?: ($invoice['codAut'] ?? ''),
        ];
        if (!empty($invoice['customerDocumentType']) || !empty($invoice['DocTipo'])) {
            $qrParams['tipoDocRec'] = $invoice['customerDocumentType'] ?? $invoice['DocTipo'] ?? null;
        }
        if (!empty($invoice['customerDocumentNumber']) || !empty($invoice['DocNro'])) {
            $qrParams['nroDocRec'] = $invoice['customerDocumentNumber'] ?? $invoice['DocNro'] ?? null;
        }

        $qrDataUrl = AfipQrHelper::buildQrDataUrl($qrParams);
        $qrDataUri = AfipQrHelper::buildQrImageDataUri($qrDataUrl, $qrSize);

        $items = $invoice['items'] ?? [];
        if (empty($items) && !empty($invoice['Iva'])) {
            $items = [['description' => 'Detalle', 'quantity' => 1, 'unitPrice' => $total - ($invoice['totalIva'] ?? 0), 'taxRate' => '21', 'subtotal' => $total]];
        }

        return array_merge([
            'issuer' => [
                'razon_social' => $invoice['issuer']['razon_social'] ?? 'Razón Social',
                'domicilio' => $invoice['issuer']['domicilio_fiscal'] ?? $invoice['issuer']['domicilio'] ?? '',
                'cuit' => $cuit,
                'condicion_iva' => $invoice['issuer']['condicion_iva'] ?? 'Responsable Inscripto',
                'iibb' => $invoice['issuer']['iibb'] ?? '',
                'inicio_actividad' => $invoice['issuer']['inicio_actividad'] ?? '',
            ],
            'receiver' => [
                'tipo_doc' => $invoice['receiver']['tipo_doc'] ?? $invoice['customerDocumentType'] ?? 99,
                'nro_doc' => $invoice['receiver']['nro_doc'] ?? $invoice['customerDocumentNumber'] ?? '0',
                'nombre' => $invoice['receiver']['nombre'] ?? $invoice['receiverNombre'] ?? 'Consumidor Final',
                'condicion_iva' => $invoice['receiver']['condicion_iva'] ?? 'Consumidor final',
                'domicilio' => $invoice['receiver']['domicilio'] ?? '',
            ],
            'comprobante' => [
                'pto_vta' => str_pad((string) $response->pointOfSale, 5, '0', STR_PAD_LEFT),
                'nro' => str_pad((string) $response->invoiceNumber, 8, '0', STR_PAD_LEFT),
                'fecha' => $fechaFormatted,
                'concepto' => $invoice['concept'] ?? 1,
                'concepto_texto' => $this->conceptoTexto($invoice['concept'] ?? 1),
            ],
            'items' => $items,
            'subtotal' => $invoice['netAmount'] ?? $invoice['ImpNeto'] ?? $total - ($invoice['totalIva'] ?? 0),
            'iva_total' => $invoice['totalIva'] ?? $invoice['ImpIVA'] ?? 0,
            'otros_tributos' => $invoice['tributesTotal'] ?? $invoice['ImpTrib'] ?? 0,
            'total' => $total,
            'cae' => $response->cae,
            'cae_vencimiento' => $caeVtoFormatted,
            'condicion_venta' => $invoice['condicion_venta'] ?? 'Efectivo',
            'qr_data_url' => $qrDataUrl,
            'qr_data_uri' => $qrDataUri,
        ], $invoice);
    }

    private function conceptoTexto(int $concepto): string
    {
        return match ($concepto) {
            1 => 'Productos',
            2 => 'Servicios',
            3 => 'Productos y Servicios',
            default => 'Productos',
        };
    }

    private function renderTemplate(string $name, array $data): string
    {
        $path = $this->templatesPath . '/' . $name;
        if (!is_file($path)) {
            return $this->renderFallbackTemplate($name, $data);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    private function renderFallbackTemplate(string $name, array $data): string
    {
        $c = $data['comprobante'] ?? [];
        $i = $data['issuer'] ?? [];
        $r = $data['receiver'] ?? [];
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($name) . '</title></head><body>';
        $html .= '<p><strong>' . htmlspecialchars($i['razon_social'] ?? '') . '</strong></p>';
        $html .= '<p>P.V: ' . htmlspecialchars($c['pto_vta'] ?? '') . ' | Nro: ' . htmlspecialchars($c['nro'] ?? '') . '</p>';
        $html .= '<p>Fecha: ' . htmlspecialchars($c['fecha'] ?? '') . '</p>';
        $html .= '<p>Cliente: ' . htmlspecialchars($r['nombre'] ?? '') . '</p>';
        $html .= '<p><strong>Total: $ ' . number_format((float) ($data['total'] ?? 0), 2, ',', '.') . '</strong></p>';
        $html .= '<p>CAE: ' . htmlspecialchars($data['cae'] ?? '') . ' | Vto: ' . htmlspecialchars($data['cae_vencimiento'] ?? '') . '</p>';
        if (!empty($data['qr_src'])) {
            $html .= '<img src="' . htmlspecialchars($data['qr_src']) . '" alt="QR" width="120" />';
        }
        $html .= '</body></html>';
        return $html;
    }
}
