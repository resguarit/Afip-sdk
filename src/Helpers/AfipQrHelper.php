<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Helpers;

/**
 * Helper para generar el código QR de comprobantes electrónicos AFIP.
 *
 * Según especificación: https://www.afip.gob.ar/fe/qr/documentos/QRespecificaciones.pdf
 * Formato: https://www.afip.gob.ar/fe/qr/?p={DATOS_CMP_BASE64}
 */
class AfipQrHelper
{
    private const QR_BASE_URL = 'https://www.afip.gob.ar/fe/qr/';

    /**
     * Construye la URL completa del QR para AFIP (para usar en el atributo del código QR).
     *
     * @param array $params Datos del comprobante según especificación AFIP:
     *   - fecha (string): YYYY-MM-DD
     *   - cuit (int|string): 11 dígitos emisor
     *   - ptoVta (int): punto de venta
     *   - tipoCmp (int): tipo comprobante (1, 6, 11, etc.)
     *   - nroCmp (int): número de comprobante
     *   - importe (float): importe total
     *   - moneda (string): "PES", "DOL", etc.
     *   - ctz (float): cotización (1 para pesos)
     *   - tipoCodAut (string): "E" (CAE) o "A" (CAEA)
     *   - codAut (string): CAE/CAEA 14 dígitos
     *   - tipoDocRec (int|null): opcional, tipo doc receptor
     *   - nroDocRec (int|string|null): opcional, número doc receptor
     * @return string URL completa para el QR
     */
    public static function buildQrDataUrl(array $params): string
    {
        $json = self::buildQrJson($params);
        $base64 = base64_encode($json);

        return self::QR_BASE_URL . '?p=' . $base64;
    }

    /**
     * Construye el JSON de datos del comprobante para el QR (versión 1).
     */
    public static function buildQrJson(array $params): string
    {
        $data = [
            'ver' => 1,
            'fecha' => self::formatDate($params['fecha'] ?? ''),
            'cuit' => (int) preg_replace('/\D/', '', (string) ($params['cuit'] ?? '')),
            'ptoVta' => (int) ($params['ptoVta'] ?? 0),
            'tipoCmp' => (int) ($params['tipoCmp'] ?? 0),
            'nroCmp' => (int) ($params['nroCmp'] ?? 0),
            'importe' => (float) ($params['importe'] ?? 0),
            'moneda' => (string) ($params['moneda'] ?? 'PES'),
            'ctz' => (float) ($params['ctz'] ?? 1),
            'tipoCodAut' => (string) ($params['tipoCodAut'] ?? 'E'),
            'codAut' => (string) ($params['codAut'] ?? ''),
        ];

        if (isset($params['tipoDocRec']) && $params['tipoDocRec'] !== null && $params['tipoDocRec'] !== '') {
            $data['tipoDocRec'] = (int) $params['tipoDocRec'];
        }
        if (isset($params['nroDocRec']) && $params['nroDocRec'] !== null && $params['nroDocRec'] !== '') {
            $data['nroDocRec'] = (int) preg_replace('/\D/', '', (string) $params['nroDocRec']);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Genera la imagen del QR como Data URI (PNG) si endroid/qr-code está instalado.
     *
     * @param string $qrDataUrl URL completa del QR (resultado de buildQrDataUrl)
     * @param int $size Tamaño en píxeles (por defecto 200)
     * @return string|null Data URI (data:image/png;base64,...) o null si la librería no está disponible
     */
    public static function buildQrImageDataUri(string $qrDataUrl, int $size = 200): ?string
    {
        if (!class_exists(\Endroid\QrCode\QrCode::class)) {
            return null;
        }
        try {
            $qrCode = new \Endroid\QrCode\QrCode(data: $qrDataUrl);
            if (method_exists($qrCode, 'setSize')) {
                $qrCode->setSize($size);
            }
            if (method_exists($qrCode, 'setMargin')) {
                $qrCode->setMargin(10);
            }
            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrCode);
            return $result->getDataUri();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function formatDate(string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        $dt = \DateTime::createFromFormat('Ymd', $date);
        if ($dt !== false) {
            return $dt->format('Y-m-d');
        }
        $dt = \DateTime::createFromFormat('d/m/Y', $date);
        if ($dt !== false) {
            return $dt->format('Y-m-d');
        }
        return date('Y-m-d');
    }
}
