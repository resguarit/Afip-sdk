<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Helpers;

/**
 * Helper para mapear datos de comprobante al formato requerido por AFIP
 *
 * Convierte el formato interno del SDK al formato que espera el Web Service WSFE
 */
class InvoiceMapper
{
    /**
     * Mapea los datos del comprobante al formato FeCAERequest de AFIP
     *
     * @param array $invoice Datos del comprobante en formato interno
     * @param string $cuit CUIT del contribuyente
     * @return array Estructura FeCAERequest según especificación AFIP
     */
    public static function toFeCAERequest(array $invoice, string $cuit): array
    {
        // Estructura FeCabReq (Cabecera)
        $feCabReq = [
            'CantReg' => 1, // Cantidad de comprobantes (siempre 1 por solicitud)
            'PtoVta' => (int) ($invoice['pointOfSale'] ?? 0),
            'CbteTipo' => (int) ($invoice['invoiceType'] ?? 0),
        ];

        // Estructura FeDetReq (Detalle - Array de comprobantes)
        $feDetReq = [
            [
                'Concepto' => (int) ($invoice['concept'] ?? 1),
                'DocTipo' => (int) ($invoice['customerDocumentType'] ?? 99),
                'DocNro' => (int) str_replace('-', '', $invoice['customerDocumentNumber'] ?? $invoice['customerCuit'] ?? '0'),
                'CbteDesde' => (int) ($invoice['invoiceNumber'] ?? 0),
                'CbteHasta' => (int) ($invoice['invoiceNumber'] ?? 0),
                'CbteFch' => (string) ($invoice['date'] ?? date('Ymd')),
                'ImpTotal' => (float) ($invoice['total'] ?? 0),
                'ImpTotConc' => (float) ($invoice['totalNetoNoGravado'] ?? 0),
                'ImpNeto' => (float) ($invoice['totalNetoGravado'] ?? 0),
                'ImpOpEx' => (float) ($invoice['totalExento'] ?? 0),
                'ImpIVA' => (float) ($invoice['totalIva'] ?? 0),
                'ImpTrib' => (float) ($invoice['totalTributos'] ?? 0),
                'FchServDesde' => $invoice['fechaServicioDesde'] ?? null,
                'FchServHasta' => $invoice['fechaServicioHasta'] ?? null,
                'FchVtoPago' => $invoice['fechaVtoPago'] ?? null,
                'MonId' => $invoice['moneda'] ?? 'PES',
                'MonCotiz' => (float) ($invoice['cotizacionMoneda'] ?? 1),
            ],
        ];

        // Agregar items (AlicIva) si existen
        if (!empty($invoice['items']) && is_array($invoice['items'])) {
            $alicIva = [];
            foreach ($invoice['items'] as $item) {
                if (isset($item['taxRate']) && $item['taxRate'] > 0) {
                    $alicIva[] = [
                        'Id' => (int) ($item['taxId'] ?? 5), // 5 = IVA 21%
                        'BaseImp' => (float) ($item['baseImponible'] ?? ($item['quantity'] * $item['unitPrice'])),
                        'Alic' => (float) ($item['taxRate'] ?? 21),
                    ];
                }
            }
            if (!empty($alicIva)) {
                $feDetReq[0]['Iva'] = $alicIva;
            }
        }

        // Agregar tributos si existen
        if (!empty($invoice['tributos']) && is_array($invoice['tributos'])) {
            $tributos = [];
            foreach ($invoice['tributos'] as $tributo) {
                $tributos[] = [
                    'Id' => (int) ($tributo['id'] ?? 0),
                    'Desc' => (string) ($tributo['descripcion'] ?? ''),
                    'BaseImp' => (float) ($tributo['baseImponible'] ?? 0),
                    'Alic' => (float) ($tributo['alicuota'] ?? 0),
                    'Importe' => (float) ($tributo['importe'] ?? 0),
                ];
            }
            if (!empty($tributos)) {
                $feDetReq[0]['Tributos'] = $tributos;
            }
        }

        // Agregar opcionales según tipo de comprobante
        if (isset($invoice['numeroComprador'])) {
            $feDetReq[0]['Comprador'] = [
                'DocTipo' => (int) ($invoice['compradorDocumentType'] ?? 99),
                'DocNro' => (int) str_replace('-', '', $invoice['compradorDocumentNumber'] ?? '0'),
            ];
        }

        // Estructura completa FeCAERequest
        return [
            'FeCAEReq' => [
                'FeCabReq' => $feCabReq,
                'FeDetReq' => $feDetReq,
            ],
        ];
    }

    /**
     * Calcula los totales del comprobante desde los items
     *
     * @param array $items Array de items
     * @return array Totales calculados
     */
    public static function calculateTotals(array $items): array
    {
        $totals = [
            'totalNetoNoGravado' => 0,
            'totalNetoGravado' => 0,
            'totalExento' => 0,
            'totalIva' => 0,
            'total' => 0,
        ];

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unitPrice'] ?? 0);
            $subtotal = $quantity * $unitPrice;
            $taxRate = (float) ($item['taxRate'] ?? 0);

            if ($item['exento'] ?? false) {
                $totals['totalExento'] += $subtotal;
            } elseif ($taxRate > 0) {
                $totals['totalNetoGravado'] += $subtotal;
                $totals['totalIva'] += ($subtotal * $taxRate / 100);
            } else {
                $totals['totalNetoNoGravado'] += $subtotal;
            }

            $totals['total'] += $subtotal + ($subtotal * $taxRate / 100);
        }

        return $totals;
    }
}

