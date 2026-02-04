<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Helpers;

use Resguar\AfipSdk\Exceptions\AfipValidationException;

/**
 * Helper para mapear datos de comprobante al formato requerido por AFIP
 *
 * Convierte el formato interno del SDK al formato que espera el Web Service WSFE.
 *
 * ┌─────────────────────┬─────────────────────────────────────────────────────┐
 * │                     │                    RECEPTOR                         │
 * │      EMISOR         ├─────────────────┬─────────────────┬─────────────────┤
 * │                     │ CF / Exento     │ Monotributista  │ Resp. Inscripto │
 * ├─────────────────────┼─────────────────┼─────────────────┼─────────────────┤
 * │ Resp. Inscripto     │ Factura B       │ Factura A       │ Factura A       │
 * ├─────────────────────┼─────────────────┼─────────────────┼─────────────────┤
 * │ Monotrib. / Exento  │ Factura C       │ Factura C       │ Factura C       │
 * └─────────────────────┴─────────────────┴─────────────────┴─────────────────┘
 */
class InvoiceMapper
{
    // ═══════════════════════════════════════════════════════════════════════════
    // CONSTANTES: Condición IVA del Receptor (RG 5616)
    // ═══════════════════════════════════════════════════════════════════════════
    public const CONDICION_IVA_RESPONSABLE_INSCRIPTO = 1;
    public const CONDICION_IVA_RESPONSABLE_NO_INSCRIPTO = 2;
    public const CONDICION_IVA_EXENTO = 4;
    public const CONDICION_IVA_CONSUMIDOR_FINAL = 5;
    public const CONDICION_IVA_MONOTRIBUTO = 6;
    public const CONDICION_IVA_MONOTRIBUTO_SOCIAL = 13;

    // ═══════════════════════════════════════════════════════════════════════════
    // CONSTANTES: Tipos de Documento
    // ═══════════════════════════════════════════════════════════════════════════
    public const DOC_TIPO_CUIT = 80;
    public const DOC_TIPO_CUIL = 86;
    public const DOC_TIPO_CDI = 87;
    public const DOC_TIPO_DNI = 96;
    public const DOC_TIPO_PASAPORTE = 94;
    public const DOC_TIPO_SIN_IDENTIFICAR = 99;

    // ═══════════════════════════════════════════════════════════════════════════
    // CONSTANTES: Tipos de Comprobante
    // ═══════════════════════════════════════════════════════════════════════════
    public const COMPROBANTE_FACTURA_A = 1;
    public const COMPROBANTE_NOTA_DEBITO_A = 2;
    public const COMPROBANTE_NOTA_CREDITO_A = 3;
    public const COMPROBANTE_FACTURA_B = 6;
    public const COMPROBANTE_NOTA_DEBITO_B = 7;
    public const COMPROBANTE_NOTA_CREDITO_B = 8;
    public const COMPROBANTE_FACTURA_C = 11;
    public const COMPROBANTE_NOTA_DEBITO_C = 12;
    public const COMPROBANTE_NOTA_CREDITO_C = 13;

    // ═══════════════════════════════════════════════════════════════════════════
    // CONSTANTES: Alícuotas de IVA (Id AFIP)
    // ═══════════════════════════════════════════════════════════════════════════
    public const IVA_0 = 3;
    public const IVA_10_5 = 4;
    public const IVA_21 = 5;
    public const IVA_27 = 6;
    public const IVA_5 = 8;
    public const IVA_2_5 = 9;

    // ═══════════════════════════════════════════════════════════════════════════
    // CONSTANTES: Conceptos
    // ═══════════════════════════════════════════════════════════════════════════
    public const CONCEPTO_PRODUCTOS = 1;
    public const CONCEPTO_SERVICIOS = 2;
    public const CONCEPTO_PRODUCTOS_Y_SERVICIOS = 3;

    // ═══════════════════════════════════════════════════════════════════════════
    // REGLAS DE VALIDACIÓN: Receptores permitidos por tipo de comprobante
    // ═══════════════════════════════════════════════════════════════════════════

    /** Factura A: RI emite a RI o Monotributista */
    private const RECEPTORES_FACTURA_A = [
        self::CONDICION_IVA_RESPONSABLE_INSCRIPTO,
        self::CONDICION_IVA_MONOTRIBUTO,
        self::CONDICION_IVA_MONOTRIBUTO_SOCIAL,
    ];

    /** Factura B: RI emite a Consumidor Final o Exento */
    private const RECEPTORES_FACTURA_B = [
        self::CONDICION_IVA_CONSUMIDOR_FINAL,
        self::CONDICION_IVA_EXENTO,
    ];

    // Nota: Factura C no tiene restricción de receptor (Mono/Exento puede emitir a cualquiera)

    /**
     * Valida que tipo de comprobante + condición IVA del receptor cumplan las reglas AFIP.
     * Útil para validar antes de realizar llamadas a la API.
     *
     * @param array $invoice Datos del comprobante
     * @throws AfipValidationException Si la combinación es inválida
     */
    public static function validateInvoice(array $invoice): void
    {
        $invoiceType = (int) ($invoice['invoiceType'] ?? self::COMPROBANTE_FACTURA_B);
        $condicionReceptor = self::resolveCondicionIvaReceptor($invoice, $invoiceType);
        self::validateInvoiceTypeAndReceiver($invoiceType, $condicionReceptor);
    }

    /**
     * Mapea los datos del comprobante al formato FeCAERequest de AFIP
     *
     * @param array $invoice Datos del comprobante en formato interno
     * @param string $cuit CUIT del contribuyente
     * @return array Estructura FeCAERequest según especificación AFIP
     */
    public static function toFeCAERequest(array $invoice, string $cuit): array
    {
        $invoiceType = (int) ($invoice['invoiceType'] ?? self::COMPROBANTE_FACTURA_B);
        $condicionReceptor = self::resolveCondicionIvaReceptor($invoice, $invoiceType);

        self::validateInvoiceTypeAndReceiver($invoiceType, $condicionReceptor);

        $feCabReq = self::buildFeCabReq($invoice, $invoiceType);
        $feDetReq = self::buildFeDetReq($invoice, $invoiceType);

        return [
            'FeCAEReq' => [
                'FeCabReq' => self::removeNullValues($feCabReq),
                'FeDetReq' => self::removeNullValues($feDetReq),
            ],
        ];
    }

    /**
     * Construye la cabecera del comprobante (FeCabReq)
     */
    private static function buildFeCabReq(array $invoice, int $invoiceType): array
    {
        return [
            'CantReg' => 1,
            'PtoVta' => (int) ($invoice['pointOfSale'] ?? 0),
            'CbteTipo' => $invoiceType,
        ];
    }

    /**
     * Construye el detalle del comprobante (FeDetReq)
     */
    private static function buildFeDetReq(array $invoice, int $invoiceType): array
    {
        $totals = self::extractTotals($invoice);
        $receiver = $invoice['receiver'] ?? [];

        $feDetReqItem = [
            'Concepto' => (int) ($invoice['concept'] ?? self::CONCEPTO_PRODUCTOS),
            'DocTipo' => self::resolveDocumentType($invoice, $receiver),
            'DocNro' => self::resolveDocumentNumber($invoice, $receiver),
            'CondicionIVAReceptorId' => self::resolveCondicionIvaReceptor($invoice, $invoiceType),
            'CbteDesde' => (int) ($invoice['invoiceNumber'] ?? 0),
            'CbteHasta' => (int) ($invoice['invoiceNumber'] ?? 0),
            'CbteFch' => (string) ($invoice['date'] ?? date('Ymd')),
            'ImpTotal' => (float) ($invoice['total'] ?? 0),
            'ImpTotConc' => (float) $totals['netoNoGravado'],
            'ImpNeto' => (float) $totals['netoGravado'],
            'ImpOpEx' => (float) $totals['exento'],
            'ImpIVA' => (float) $totals['iva'],
            'ImpTrib' => (float) $totals['tributos'],
            'MonId' => $invoice['moneda'] ?? 'PES',
            'MonCotiz' => (float) ($invoice['cotizacionMoneda'] ?? 1),
        ];

        // Fechas de servicio (solo para conceptos 2 y 3)
        self::addServiceDates($feDetReqItem, $invoice);

        $feDetReq = [$feDetReqItem];

        // Agregar alícuotas de IVA
        $alicIva = self::buildAlicuotasIva($invoice);
        if (!empty($alicIva)) {
            $feDetReq[0]['Iva'] = $alicIva;
        }

        // Agregar tributos
        $tributos = self::buildTributos($invoice);
        if (!empty($tributos)) {
            $feDetReq[0]['Tributos'] = $tributos;
        }

        // Agregar comprador opcional
        if (isset($invoice['numeroComprador'])) {
            $feDetReq[0]['Comprador'] = [
                'DocTipo' => (int) ($invoice['compradorDocumentType'] ?? self::DOC_TIPO_SIN_IDENTIFICAR),
                'DocNro' => (int) str_replace('-', '', $invoice['compradorDocumentNumber'] ?? '0'),
            ];
        }

        return $feDetReq;
    }

    /**
     * Extrae los totales del comprobante desde múltiples fuentes posibles
     */
    private static function extractTotals(array $invoice): array
    {
        return [
            'netoGravado' => $invoice['totalNetoGravado'] ?? $invoice['netAmount'] ?? 0,
            'iva' => $invoice['totalIva'] ?? $invoice['ivaTotal'] ?? 0,
            'netoNoGravado' => $invoice['totalNetoNoGravado'] ?? $invoice['nonTaxedTotal'] ?? 0,
            'exento' => $invoice['totalExento'] ?? $invoice['exemptAmount'] ?? 0,
            'tributos' => $invoice['totalTributos'] ?? $invoice['tributesTotal'] ?? 0,
        ];
    }

    /**
     * Resuelve el tipo de documento del receptor
     */
    private static function resolveDocumentType(array $invoice, array $receiver): int
    {
        return (int) (
            $invoice['customerDocumentType']
            ?? $receiver['tipo_doc']
            ?? $receiver['document_type']
            ?? self::DOC_TIPO_SIN_IDENTIFICAR
        );
    }

    /**
     * Resuelve el número de documento del receptor
     */
    private static function resolveDocumentNumber(array $invoice, array $receiver): int
    {
        $docNumber = $invoice['customerDocumentNumber']
            ?? $invoice['customerCuit']
            ?? $receiver['nro_doc']
            ?? $receiver['document_number']
            ?? '0';

        return (int) str_replace(['-', '.', ' '], '', (string) $docNumber);
    }

    /**
     * Resuelve la condición IVA del receptor (obligatorio por RG 5616)
     *
     * Busca en múltiples fuentes posibles y convierte texto a código AFIP.
     * ARCA permite Factura A a Monotributistas (código 6).
     */
    private static function resolveCondicionIvaReceptor(array $invoice, int $invoiceType): int
    {
        $receiver = $invoice['receiver'] ?? [];

        // Buscar código numérico directo
        $condicionId = $invoice['receiverConditionIVA']
            ?? $invoice['condicionIVAReceptorId']
            ?? $receiver['condicion_iva_id']
            ?? $receiver['condicionIVAId']
            ?? $receiver['iva_condition_id']
            ?? null;

        if ($condicionId !== null) {
            return (int) $condicionId;
        }

        // Intentar resolver desde texto descriptivo
        $condicionTexto = $receiver['condicion_iva']
            ?? $receiver['iva_condition']
            ?? $invoice['receiverCondition']
            ?? null;

        $resolved = self::resolveCondicionIvaFromDescription($condicionTexto);
        if ($resolved !== null) {
            return $resolved;
        }

        // Default según tipo de comprobante
        return self::isFacturaA($invoiceType)
            ? self::CONDICION_IVA_RESPONSABLE_INSCRIPTO
            : self::CONDICION_IVA_CONSUMIDOR_FINAL;
    }

    /**
     * Determina si el tipo de comprobante es Factura A o variante
     */
    private static function isFacturaA(int $invoiceType): bool
    {
        return in_array($invoiceType, [
            self::COMPROBANTE_FACTURA_A,
            self::COMPROBANTE_NOTA_DEBITO_A,
            self::COMPROBANTE_NOTA_CREDITO_A,
        ], true);
    }

    /**
     * Determina si el tipo de comprobante es Factura B o variante
     */
    private static function isFacturaB(int $invoiceType): bool
    {
        return in_array($invoiceType, [
            self::COMPROBANTE_FACTURA_B,
            self::COMPROBANTE_NOTA_DEBITO_B,
            self::COMPROBANTE_NOTA_CREDITO_B,
        ], true);
    }

    /**
     * Determina si el tipo de comprobante es Factura C o variante
     */
    private static function isFacturaC(int $invoiceType): bool
    {
        return in_array($invoiceType, [
            self::COMPROBANTE_FACTURA_C,
            self::COMPROBANTE_NOTA_DEBITO_C,
            self::COMPROBANTE_NOTA_CREDITO_C,
        ], true);
    }

    /**
     * Valida que la combinación tipo de comprobante + condición IVA del receptor
     * cumpla con las reglas de facturación AFIP.
     *
     * Reglas según tipo de EMISOR:
     * - RI emite Factura A → solo a RI o Monotributista
     * - RI emite Factura B → solo a Consumidor Final o Exento
     * - Monotributista/Exento emite Factura C → a cualquier receptor
     *
     * @throws AfipValidationException Si la combinación es inválida
     */
    private static function validateInvoiceTypeAndReceiver(int $invoiceType, int $condicionReceptor): void
    {
        if (self::isFacturaA($invoiceType)) {
            self::assertReceptorPermitido(
                $condicionReceptor,
                self::RECEPTORES_FACTURA_A,
                'Factura A solo puede emitirse a Responsable Inscripto o Monotributista. '
                . 'Para Consumidor Final o Exento use Factura B.'
            );
            return;
        }

        if (self::isFacturaB($invoiceType)) {
            self::assertReceptorPermitido(
                $condicionReceptor,
                self::RECEPTORES_FACTURA_B,
                'Factura B solo puede emitirse a Consumidor Final o Exento. '
                . 'Para RI o Monotributista use Factura A.'
            );
            return;
        }

        // Factura C: Monotributista/Exento puede emitir a cualquier receptor (sin restricción)
    }

    /**
     * Valida que el receptor esté en la lista de permitidos.
     *
     * @param int $condicionReceptor Código de condición IVA del receptor
     * @param array<int> $permitidos Lista de códigos permitidos
     * @param string $mensaje Mensaje de error si no es válido
     * @throws AfipValidationException
     */
    private static function assertReceptorPermitido(int $condicionReceptor, array $permitidos, string $mensaje): void
    {
        if (!in_array($condicionReceptor, $permitidos, true)) {
            throw new AfipValidationException($mensaje);
        }
    }

    /**
     * Resuelve el código de condición IVA desde una descripción textual
     *
     * @param string|null $descripcion Texto descriptivo de la condición
     * @return int|null Código AFIP o null si no se puede determinar
     */
    private static function resolveCondicionIvaFromDescription(?string $descripcion): ?int
    {
        if ($descripcion === null || $descripcion === '') {
            return null;
        }

        $normalized = strtolower(trim($descripcion));

        // Mapeo de palabras clave a códigos AFIP (orden de prioridad)
        $mappings = [
            'monotribut' => self::CONDICION_IVA_MONOTRIBUTO,
            'responsable inscripto' => self::CONDICION_IVA_RESPONSABLE_INSCRIPTO,
            'consumidor final' => self::CONDICION_IVA_CONSUMIDOR_FINAL,
            'consumidor' => self::CONDICION_IVA_CONSUMIDOR_FINAL,
            'exento' => self::CONDICION_IVA_EXENTO,
            'no inscripto' => self::CONDICION_IVA_RESPONSABLE_NO_INSCRIPTO,
            'inscripto' => self::CONDICION_IVA_RESPONSABLE_INSCRIPTO,
        ];

        foreach ($mappings as $keyword => $code) {
            if (str_contains($normalized, $keyword)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Agrega fechas de servicio al detalle (requeridas para concepto 2 y 3)
     */
    private static function addServiceDates(array &$feDetReqItem, array $invoice): void
    {
        $fechaDesde = $invoice['fechaServicioDesde'] ?? $invoice['serviceStartDate'] ?? null;
        $fechaHasta = $invoice['fechaServicioHasta'] ?? $invoice['serviceEndDate'] ?? null;
        $fechaVto = $invoice['fechaVtoPago'] ?? $invoice['paymentDueDate'] ?? null;

        if (!empty($fechaDesde)) {
            $feDetReqItem['FchServDesde'] = (string) $fechaDesde;
        }
        if (!empty($fechaHasta)) {
            $feDetReqItem['FchServHasta'] = (string) $fechaHasta;
        }
        if (!empty($fechaVto)) {
            $feDetReqItem['FchVtoPago'] = (string) $fechaVto;
        }
    }

    /**
     * Construye el array de alícuotas de IVA para AFIP
     */
    private static function buildAlicuotasIva(array $invoice): array
    {
        $alicIva = [];

        // Opción 1: ivaItems directos
        if (!empty($invoice['ivaItems']) && is_array($invoice['ivaItems'])) {
            foreach ($invoice['ivaItems'] as $ivaItem) {
                $alicIva[] = self::buildAlicuotaFromIvaItem($ivaItem);
            }
            return $alicIva;
        }

        // Opción 2: Extraer de items
        if (!empty($invoice['items']) && is_array($invoice['items'])) {
            foreach ($invoice['items'] as $item) {
                $taxRate = (float) ($item['taxRate'] ?? 0);
                if ($taxRate > 0) {
                    $alicIva[] = self::buildAlicuotaFromItem($item, $taxRate);
                }
            }
        }

        return $alicIva;
    }

    /**
     * Construye una alícuota desde un ivaItem
     */
    private static function buildAlicuotaFromIvaItem(array $ivaItem): array
    {
        $alicuota = self::resolveAlicuota($ivaItem);
        $baseImp = (float) ($ivaItem['baseAmount'] ?? $ivaItem['baseImponible'] ?? 0);
        $importe = (float) ($ivaItem['amount'] ?? ($baseImp * $alicuota / 100));

        return [
            'Id' => (int) ($ivaItem['id'] ?? self::IVA_21),
            'BaseImp' => $baseImp,
            'Alic' => $alicuota,
            'Importe' => $importe,
        ];
    }

    /**
     * Construye una alícuota desde un item de factura
     */
    private static function buildAlicuotaFromItem(array $item, float $taxRate): array
    {
        $quantity = (float) ($item['quantity'] ?? 1);
        $unitPrice = (float) ($item['unitPrice'] ?? 0);
        $baseImp = (float) ($item['baseImponible'] ?? ($quantity * $unitPrice));
        $importe = (float) ($item['taxAmount'] ?? ($baseImp * $taxRate / 100));

        return [
            'Id' => (int) ($item['taxId'] ?? self::IVA_21),
            'BaseImp' => $baseImp,
            'Alic' => $taxRate,
            'Importe' => $importe,
        ];
    }

    /**
     * Resuelve la alícuota desde múltiples fuentes
     */
    private static function resolveAlicuota(array $ivaItem): float
    {
        if (isset($ivaItem['alicuota']) && $ivaItem['alicuota'] > 0) {
            return (float) $ivaItem['alicuota'];
        }

        if (isset($ivaItem['amount'], $ivaItem['baseAmount'])
            && $ivaItem['amount'] > 0
            && $ivaItem['baseAmount'] > 0
        ) {
            return (float) (($ivaItem['amount'] / $ivaItem['baseAmount']) * 100);
        }

        return 21.0; // Default
    }

    /**
     * Construye el array de tributos para AFIP
     */
    private static function buildTributos(array $invoice): array
    {
        if (empty($invoice['tributos']) || !is_array($invoice['tributos'])) {
            return [];
        }

        $tributos = [];
        foreach ($invoice['tributos'] as $tributo) {
            $tributoItem = [
                'Id' => (int) ($tributo['id'] ?? 0),
                'Importe' => (float) ($tributo['importe'] ?? 0),
            ];

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

        return $tributos;
    }

    /**
     * Elimina valores null de un array recursivamente
     *
     * SOAP no acepta valores null, por lo que deben eliminarse antes de enviar.
     *
     * @param array $data Array a limpiar
     * @return array Array sin valores null
     */
    private static function removeNullValues(array $data): array
    {
        $cleaned = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $cleanedValue = self::removeNullValues($value);
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
            'totalNetoNoGravado' => 0.0,
            'totalNetoGravado' => 0.0,
            'totalExento' => 0.0,
            'totalIva' => 0.0,
            'total' => 0.0,
        ];

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unitPrice'] ?? 0);
            $subtotal = $quantity * $unitPrice;
            $taxRate = (float) ($item['taxRate'] ?? 0);
            $isExento = $item['exento'] ?? false;

            if ($isExento) {
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
