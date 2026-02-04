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
        // Mapear alias comunes para compatibilidad
        $totalNetoGravado = $invoice['totalNetoGravado'] ?? $invoice['netAmount'] ?? 0;
        $totalIva = $invoice['totalIva'] ?? $invoice['ivaTotal'] ?? 0;
        $totalNetoNoGravado = $invoice['totalNetoNoGravado'] ?? $invoice['nonTaxedTotal'] ?? 0;
        $totalExento = $invoice['totalExento'] ?? $invoice['exemptAmount'] ?? 0;
        $totalTributos = $invoice['totalTributos'] ?? $invoice['tributesTotal'] ?? 0;
        
        // Determinar CondicionIVAReceptorId (obligatorio por RG 5616)
        // Códigos AFIP: 1=RI, 2=No Inscripto, 4=Exento, 5=Consumidor Final, 6=Monotributo, etc.
        // ARCA permite Factura A a Monotributistas (código 6)
        $invoiceType = (int) ($invoice['invoiceType'] ?? 1);
        $defaultCondicionIVA = ($invoiceType === 1) ? 1 : 5;
        $condicionIVAReceptor = (int) (
            $invoice['receiverConditionIVA']
            ?? $invoice['condicionIVAReceptorId']
            ?? $invoice['receiver']['condicion_iva_id']
            ?? $invoice['receiver']['condicionIVAId']
            ?? self::resolveCondicionIvaFromDescription($invoice['receiver']['condicion_iva'] ?? null)
            ?? $defaultCondicionIVA
        );

        $feDetReqItem = [
            'Concepto' => (int) ($invoice['concept'] ?? 1),
            'DocTipo' => (int) ($invoice['customerDocumentType'] ?? $invoice['receiver']['tipo_doc'] ?? 99),
            'DocNro' => (int) str_replace('-', '', $invoice['customerDocumentNumber'] ?? $invoice['customerCuit'] ?? $invoice['receiver']['nro_doc'] ?? '0'),
            'CondicionIVAReceptorId' => $condicionIVAReceptor,
            'CbteDesde' => (int) ($invoice['invoiceNumber'] ?? 0),
            'CbteHasta' => (int) ($invoice['invoiceNumber'] ?? 0),
            'CbteFch' => (string) ($invoice['date'] ?? date('Ymd')),
            'ImpTotal' => (float) ($invoice['total'] ?? 0),
            'ImpTotConc' => (float) $totalNetoNoGravado,
            'ImpNeto' => (float) $totalNetoGravado,
            'ImpOpEx' => (float) $totalExento,
            'ImpIVA' => (float) $totalIva,
            'ImpTrib' => (float) $totalTributos,
            'MonId' => $invoice['moneda'] ?? 'PES',
            'MonCotiz' => (float) ($invoice['cotizacionMoneda'] ?? 1),
        ];

        // Agregar campos opcionales solo si tienen valor (SOAP no acepta null)
        // Mapear alias para fechas de servicio
        $fechaServicioDesde = $invoice['fechaServicioDesde'] ?? $invoice['serviceStartDate'] ?? null;
        $fechaServicioHasta = $invoice['fechaServicioHasta'] ?? $invoice['serviceEndDate'] ?? null;
        $fechaVtoPago = $invoice['fechaVtoPago'] ?? $invoice['paymentDueDate'] ?? null;
        
        if (!empty($fechaServicioDesde)) {
            $feDetReqItem['FchServDesde'] = (string) $fechaServicioDesde;
        }
        if (!empty($fechaServicioHasta)) {
            $feDetReqItem['FchServHasta'] = (string) $fechaServicioHasta;
        }
        if (!empty($fechaVtoPago)) {
            $feDetReqItem['FchVtoPago'] = (string) $fechaVtoPago;
        }

        $feDetReq = [$feDetReqItem];

        // Agregar items (AlicIva) si existen
            $alicIva = [];
        
        // Procesar ivaItems si vienen directamente (formato: [['id' => 5, 'baseAmount' => 100, 'amount' => 21]])
        if (!empty($invoice['ivaItems']) && is_array($invoice['ivaItems'])) {
            foreach ($invoice['ivaItems'] as $ivaItem) {
                // Calcular alícuota si no viene directamente
                $alicuota = null;
                if (isset($ivaItem['alicuota']) && $ivaItem['alicuota'] > 0) {
                    $alicuota = (float) $ivaItem['alicuota'];
                } elseif (isset($ivaItem['amount']) && isset($ivaItem['baseAmount']) 
                    && $ivaItem['amount'] > 0 && $ivaItem['baseAmount'] > 0) {
                    $alicuota = (float) (($ivaItem['amount'] / $ivaItem['baseAmount']) * 100);
                } else {
                    $alicuota = 21.0; // Valor por defecto
                }
                
                $baseImp = (float) ($ivaItem['baseAmount'] ?? $ivaItem['baseImponible'] ?? 0);
                $importe = (float) ($ivaItem['amount'] ?? ($baseImp * $alicuota / 100));
                
                $alicIva[] = [
                    'Id' => (int) ($ivaItem['id'] ?? 5),
                    'BaseImp' => $baseImp,
                    'Alic' => $alicuota,
                    'Importe' => $importe, // Campo requerido por AFIP
                ];
            }
        }
        
        // Procesar items para extraer información de IVA (si no se pasaron ivaItems)
        if (empty($alicIva) && !empty($invoice['items']) && is_array($invoice['items'])) {
            foreach ($invoice['items'] as $item) {
                if (isset($item['taxRate']) && $item['taxRate'] > 0) {
                    $baseImp = (float) ($item['baseImponible'] ?? ($item['quantity'] * $item['unitPrice']));
                    $alicuota = (float) ($item['taxRate'] ?? 21);
                    $importe = (float) ($item['taxAmount'] ?? ($baseImp * $alicuota / 100));
                    
                    $alicIva[] = [
                        'Id' => (int) ($item['taxId'] ?? 5), // 5 = IVA 21%
                        'BaseImp' => $baseImp,
                        'Alic' => $alicuota,
                        'Importe' => $importe, // Campo requerido por AFIP
                    ];
                }
            }
        }
        
            if (!empty($alicIva)) {
                $feDetReq[0]['Iva'] = $alicIva;
        }

        // Agregar tributos si existen
        if (!empty($invoice['tributos']) && is_array($invoice['tributos'])) {
            $tributos = [];
            foreach ($invoice['tributos'] as $tributo) {
                $tributoItem = [
                    'Id' => (int) ($tributo['id'] ?? 0),
                    'Importe' => (float) ($tributo['importe'] ?? 0),
                ];
                
                // Agregar campos opcionales solo si tienen valor
                if (!empty($tributo['descripcion'])) {
                    $tributoItem['Desc'] = (string) $tributo['descripcion'];
                }
                if (isset($tributo['baseImponible']) && $tributo['baseImponible'] > 0) {
                    $tributoItem['BaseImp'] = (float) $tributo['baseImponible'];
                }
                if (isset($tributo['alicuota']) && $tributo['alicuota'] > 0) {
                    $tributoItem['Alic'] = (float) $tributo['alicuota'];
                }
                
                $tributos[] = $tributoItem;
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

        // Limpiar valores null antes de enviar a SOAP (SOAP no acepta null)
        $feCabReq = self::removeNullValues($feCabReq);
        $feDetReq = self::removeNullValues($feDetReq);

        // Estructura completa FeCAERequest
        return [
            'FeCAEReq' => [
                'FeCabReq' => $feCabReq,
                'FeDetReq' => $feDetReq,
            ],
        ];
    }

    /**
     * Resuelve el código de condición IVA desde una descripción textual
     * Códigos AFIP: 1=RI, 2=No Inscripto, 4=Exento, 5=Consumidor Final, 6=Monotributo
     *
     * @param string|null $descripcion
     * @return int|null Código AFIP o null si no se puede determinar
     */
    private static function resolveCondicionIvaFromDescription(?string $descripcion): ?int
    {
        if ($descripcion === null || $descripcion === '') {
            return null;
        }
        $d = strtolower(trim($descripcion));
        if (str_contains($d, 'monotribut') || str_contains($d, 'monotributo')) {
            return 6;
        }
        if (str_contains($d, 'responsable inscripto') || str_contains($d, 'inscripto')) {
            return 1;
        }
        if (str_contains($d, 'consumidor final') || str_contains($d, 'consumidor')) {
            return 5;
        }
        if (str_contains($d, 'exento')) {
            return 4;
        }
        if (str_contains($d, 'no inscripto')) {
            return 2;
        }
        return null;
    }

    /**
     * Elimina valores null de un array recursivamente
     * SOAP no acepta valores null, por lo que deben eliminarse antes de enviar
     *
     * @param array $data Array a limpiar
     * @return array Array sin valores null
     */
    private static function removeNullValues(array $data): array
    {
        $cleaned = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                // Omitir valores null
                continue;
            }
            
            if (is_array($value)) {
                // Limpiar recursivamente arrays anidados
                $cleanedValue = self::removeNullValues($value);
                // Solo agregar si el array no está vacío después de limpiar
                if (!empty($cleanedValue)) {
                    $cleaned[$key] = $cleanedValue;
                }
            } else {
                $cleaned[$key] = $value;
            }
        }
        
        return $cleaned;
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

