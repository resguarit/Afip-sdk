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
    public function renderFacturaA4Html(array $invoice, InvoiceResponse $response, int $qrSize = 150): string
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
            'codAut' => $response->cae ?: ($invoice['codAut'] ?? $invoice['cae'] ?? $invoice['CAE'] ?? ''),
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

        // Determinar tipo de factura (A, B, C, M)
        $tipoLetra = self::TIPO_LETRAS[$response->invoiceType] ?? 'B';
        $esFacturaA = in_array($tipoLetra, ['A', 'M']);
        $esFacturaB = $tipoLetra === 'B';
        $esFacturaC = $tipoLetra === 'C';

        // Calcular datos específicos según tipo de factura
        $ivaContenido = 0.0;
        $importeNetoGravado = 0.0;
        $ivaDesglose = [];
        $itemsProcessed = [];

        if ($esFacturaB || $esFacturaC) {
            // FACTURA B/C: IVA contenido (Ley 27.743 - Régimen de Transparencia Fiscal)
            // El precio unitario YA INCLUYE IVA
            // IVA contenido = total - (total / 1.21) para alícuota 21%
            $ivaContenido = $this->calcularIvaContenido($items, $total);
            $importeNetoGravado = $total; // En Factura B, subtotal = total

            foreach ($items as $item) {
                $cant = (float) ($item['quantity'] ?? $item['cantidad'] ?? 1);
                $pu = (float) ($item['unitPrice'] ?? $item['precio_unitario'] ?? 0);
                $st = isset($item['subtotal']) ? (float) $item['subtotal'] : ($cant * $pu);

                $itemsProcessed[] = array_merge($item, [
                    'cantidad_calc' => $cant,
                    'precio_unitario_calc' => $pu, // Precio CON IVA
                    'subtotal_calc' => $st,
                ]);
            }
        } else {
            // FACTURA A: Desglose de IVA por alícuota
            // El precio unitario es SIN IVA
            $importeNetoGravado = (float) ($invoice['netAmount'] ?? $invoice['ImpNeto'] ?? 0);

            foreach ($items as $item) {
                $cant = (float) ($item['quantity'] ?? $item['cantidad'] ?? 1);
                $puSinIva = (float) ($item['unitPrice'] ?? $item['precio_unitario'] ?? 0);
                $alicuota = (float) ($item['taxRate'] ?? $item['iva_pct'] ?? $item['alicuota'] ?? 21);
                $subtotalSinIva = $cant * $puSinIva;
                $ivaItem = round($subtotalSinIva * ($alicuota / 100), 2);
                $subtotalConIva = round($subtotalSinIva + $ivaItem, 2);

                // Agrupar IVA por alícuota
                $alicuotaKey = number_format($alicuota, 1, '.', '');
                if (!isset($ivaDesglose[$alicuotaKey])) {
                    $ivaDesglose[$alicuotaKey] = 0.0;
                }
                $ivaDesglose[$alicuotaKey] += $ivaItem;

                $itemsProcessed[] = array_merge($item, [
                    'cantidad_calc' => $cant,
                    'precio_unitario_calc' => $puSinIva, // Precio SIN IVA
                    'subtotal_calc' => round($subtotalSinIva, 2),
                    'alicuota_iva' => $alicuota,
                    'iva_item' => $ivaItem,
                    'subtotal_con_iva' => $subtotalConIva,
                ]);
            }

            // Si no hay items procesados, usar datos de la factura directamente
            if (empty($itemsProcessed) && $importeNetoGravado > 0) {
                $ivaTotal = (float) ($invoice['totalIva'] ?? $invoice['ImpIVA'] ?? 0);
                $ivaDesglose['21.0'] = $ivaTotal;
            }

            // Redondear valores del desglose
            foreach ($ivaDesglose as $key => $value) {
                $ivaDesglose[$key] = round($value, 2);
            }

            // Ordenar por alícuota descendente (27%, 21%, 10.5%, 5%, 2.5%, 0%)
            krsort($ivaDesglose);
        }

        $ivaTotal = (float) ($invoice['totalIva'] ?? $invoice['ImpIVA'] ?? array_sum($ivaDesglose));
        $otrosTributos = (float) ($invoice['tributesTotal'] ?? $invoice['ImpTrib'] ?? 0);

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
            'items' => !empty($itemsProcessed) ? $itemsProcessed : $items,
            'subtotal' => $esFacturaB || $esFacturaC ? $total : $importeNetoGravado,
            'iva_total' => $ivaTotal,
            'otros_tributos' => $otrosTributos,
            'total' => $total,
            'cae' => $response->cae,
            'cae_vencimiento' => $caeVtoFormatted,
            'condicion_venta' => $invoice['condicion_venta'] ?? 'Efectivo',
            'qr_data_url' => $qrDataUrl,
            'qr_data_uri' => $qrDataUri,
            // Datos específicos por tipo de factura
            'es_factura_a' => $esFacturaA,
            'es_factura_b' => $esFacturaB,
            'es_factura_c' => $esFacturaC,
            'iva_contenido' => round($ivaContenido, 2),
            'importe_neto_gravado' => round($importeNetoGravado, 2),
            'iva_desglose' => $ivaDesglose,
        ], $invoice);
    }

    /**
     * Calcula el IVA contenido para Factura B/C (Ley 27.743).
     * El IVA contenido se calcula sobre cada ítem según su alícuota.
     */
    private function calcularIvaContenido(array $items, float $total): float
    {
        if (empty($items)) {
            // Fallback: asumir 21% si no hay items
            return round($total - ($total / 1.21), 2);
        }

        $ivaContenido = 0.0;
        foreach ($items as $item) {
            $alicuota = (float) ($item['taxRate'] ?? $item['iva_pct'] ?? $item['alicuota'] ?? 21);
            $cant = (float) ($item['quantity'] ?? $item['cantidad'] ?? 1);
            $pu = (float) ($item['unitPrice'] ?? $item['precio_unitario'] ?? 0);
            $subtotal = isset($item['subtotal']) ? (float) $item['subtotal'] : ($cant * $pu);

            // IVA contenido = subtotal - (subtotal / (1 + alicuota/100))
            $divisor = 1 + ($alicuota / 100);
            $ivaContenido += $subtotal - ($subtotal / $divisor);
        }

        return round($ivaContenido, 2);
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
