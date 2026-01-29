# Generación de Ticket fiscal y Factura A4

El SDK permite generar **HTML** para:

- **Ticket fiscal** — formato térmico 58mm u 80mm (para impresora térmica o PDF).
- **Factura A4** — formato oficial completo para imprimir o descargar como PDF.

Ambos incluyen el **código QR** según la [especificación AFIP](https://www.afip.gob.ar/fe/qr/documentos/QRespecificaciones.pdf).

---

## Requisitos opcionales

Para que el QR se genere como **imagen** (Data URI) dentro del HTML, se recomienda instalar:

```bash
composer require endroid/qr-code
```

Si no lo instalás, el SDK igualmente construye la **URL del QR** (para que tu app la use con otra librería o servicio). Los templates muestran la imagen del QR solo si está disponible.

---

## Uso básico

Después de autorizar una factura con AFIP, usá la respuesta (`InvoiceResponse`) y los datos del comprobante para generar el HTML.

### Ticket fiscal (térmico 58/80mm)

```php
use Resguar\AfipSdk\Facades\Afip;

// $invoice = datos con los que autorizaste (o un array con issuer, receiver, items, total)
// $response = Afip::authorizeInvoice($invoice);

$html = Afip::renderTicketHtml($invoice, $response);

// Guardar HTML, enviar a impresora térmica o convertir a PDF (ver más abajo)
```

### Factura A4

```php
$html = Afip::renderFacturaA4Html($invoice, $response);
```

### Opciones para generar PDF

Las opciones coinciden con el **CSS de referencia** (no modificar medidas):

- **Ticket**: `@page` size 80mm, margin 0; contenido en `.ticket-wrapper` 60mm de ancho, margen izquierdo 10mm.
- **Factura A4**: hoja A4 por defecto, `body` margin 20px.

```php
$options = Afip::getReceiptPdfOptions();

// Ticket: size 80mm, márgenes 0
$ticketOptions = $options['ticket'];  // size, width (mm), marginLeft/Right/Top/Bottom

// Factura A4: A4, márgenes 20px
$facturaOptions = $options['factura_a4'];
```

Ejemplo con **Dompdf** (no incluido en el SDK):

```php
use Dompdf\Dompdf;

$html = Afip::renderTicketHtml($invoice, $response);
$opts = Afip::getReceiptPdfOptions()['ticket'];

$dompdf = new Dompdf();
// Ticket 80mm: ancho 80mm → pt (1 mm ≈ 2.83465 pt), alto ej. 297mm
$widthPt = $opts['width'] * 2.83465;
$dompdf->setPaper([$widthPt, 841.89], 'portrait'); // 297mm ≈ 841.89 pt
$dompdf->set_option('isRemoteEnabled', true);
$dompdf->loadHtml($html);
$dompdf->render();
$dompdf->stream('ticket.pdf');
```

Para factura A4 con Dompdf suele usarse `setPaper('A4')` y márgenes según `$options['factura_a4']` (20px o el valor que acepte la librería).

---

## Estructura del array `$invoice`

El array debe incluir al menos los datos que van en el comprobante y en el QR. Ejemplo mínimo:

```php
$invoice = [
    'issuer' => [
        'razon_social' => 'Mi Empresa S.A.',
        'domicilio' => 'Calle Falsa 123',
        'cuit' => '30123456789',
        'condicion_iva' => 'Responsable Inscripto',
        'iibb' => '12345678',           // opcional
        'inicio_actividad' => '01/01/2020', // opcional
    ],
    'receiver' => [
        'nombre' => 'Consumidor Final',
        'nro_doc' => '0',
        'condicion_iva' => 'Consumidor final',
    ],
    'items' => [
        [
            'description' => 'Producto 1',
            'quantity' => 1,
            'unitPrice' => 100.00,
            'taxRate' => '21',
            'subtotal' => 121.00,
        ],
    ],
    'total' => 121.00,
    'netAmount' => 100.00,
    'totalIva' => 21.00,
    'date' => '20260128',  // Ymd (se usa para el QR)
    'concept' => 1,
    'condicion_venta' => 'Efectivo',
];
```

Si ya tenés el payload que enviaste a `authorizeInvoice`, podés reutilizarlo y completar `issuer` y `receiver` si no estaban.

---

## Generación del QR

El SDK genera el QR de AFIP en dos pasos:

1. **URL del QR** — Siempre se construye con `AfipQrHelper::buildQrDataUrl()`. Es la URL que debe codificarse en el código QR según [QRespecificaciones.pdf](https://www.afip.gob.ar/fe/qr/documentos/QRespecificaciones.pdf):  
   `https://www.afip.gob.ar/fe/qr/?p={base64(json)}`  
   El JSON (versión 1) incluye: `ver`, `fecha`, `cuit`, `ptoVta`, `tipoCmp`, `nroCmp`, `importe`, `moneda`, `ctz`, `tipoCodAut`, `codAut`, y opcionalmente `tipoDocRec`, `nroDocRec`. La fecha se normaliza a `YYYY-MM-DD`.

2. **Imagen del QR** — Si está instalado **endroid/qr-code**, el SDK llama a `AfipQrHelper::buildQrImageDataUri()` y genera un PNG en Data URI. Ese valor se pasa a los templates como `qr_src`.  
   - **Ticket**: tamaño por defecto 180 px (tercer parámetro de `renderTicketHtml($invoice, $response, 180)`).  
   - **Factura A4**: tamaño por defecto 120 px (`renderFacturaA4Html(..., 120)`).

En los datos del template siempre tenés:

- `qr_data_url`: la URL del QR (para otra librería o para mostrar/enlace).
- `qr_data_uri`: Data URI de la imagen PNG, o `null` si no está endroid/qr-code.
- `qr_src`: lo que deben usar los templates para el `<img src="...">`; si no hay librería QR, queda vacío y el template no muestra imagen (podés usar `qr_data_url` para generar el QR por tu cuenta).

Uso directo del helper (sin renderizar ticket/factura):

```php
use Resguar\AfipSdk\Helpers\AfipQrHelper;

$params = [
    'fecha' => '2026-01-28',      // Y-m-d o Ymd
    'cuit' => '30123456789',
    'ptoVta' => 1,
    'tipoCmp' => 6,
    'nroCmp' => 1234,
    'importe' => 121.00,
    'moneda' => 'PES',
    'ctz' => 1,
    'tipoCodAut' => 'E',
    'codAut' => '75123456789012',
];
$url = AfipQrHelper::buildQrDataUrl($params);
// Opcional: imagen PNG (requiere endroid/qr-code)
$dataUri = AfipQrHelper::buildQrImageDataUri($url, 120);
```

---

## Medidas de referencia (CSS)

Los templates usan estas medidas; **no modificar** para mantener compatibilidad con impresoras térmicas y PDF.

| Formato       | @page / página | Contenido              | Body / márgenes   |
|---------------|----------------|------------------------|-------------------|
| Ticket 80mm   | size 80mm auto, margin 0 | .ticket-wrapper 60mm, margin-left 10mm | font 9px DejaVu Sans |
| Factura A4    | A4 por defecto (210×297mm) | header, tables estándar | margin 20px, font Arial 12px |

Detalle: ticket — margen izquierdo 10mm para salvar el corte de la impresora; ancho útil 60mm. Factura A4 — logo max 120×80px, celda logo 180px.

---

*Resguar IT — AFIP SDK*
