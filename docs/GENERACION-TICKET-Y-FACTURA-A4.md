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

Si usás Dompdf u otra librería que convierta HTML a PDF:

```php
$options = Afip::getReceiptPdfOptions();

// Para ticket (80mm): ancho 3.1", márgenes 0.1"
$ticketOptions = $options['ticket'];

// Para ticket 58mm: usar width => 2.28 en lugar de 3.1
// Para factura A4: ancho 8", márgenes 0.4"
$facturaOptions = $options['factura_a4'];
```

Ejemplo con **Dompdf** (no incluido en el SDK):

```php
use Dompdf\Dompdf;

$html = Afip::renderTicketHtml($invoice, $response);
$options = Afip::getReceiptPdfOptions()['ticket'];

$dompdf = new Dompdf();
$dompdf->setPaper([$options['width'] * 25.4, 297], 'portrait'); // width en mm, A4 alto
$dompdf->loadHtml($html);
$dompdf->render();
$dompdf->stream('ticket.pdf');
```

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

## QR según AFIP

El SDK arma el contenido del QR según [QRespecificaciones.pdf](https://www.afip.gob.ar/fe/qr/documentos/QRespecificaciones.pdf):

- URL: `https://www.afip.gob.ar/fe/qr/?p={base64(json)}`
- JSON (versión 1): fecha, cuit, ptoVta, tipoCmp, nroCmp, importe, moneda, ctz, tipoCodAut, codAut, y opcionalmente tipoDocRec, nroDocRec.

Si instalás **endroid/qr-code**, el SDK genera la imagen del QR (PNG en Data URI) y la incrusta en el HTML. Si no, solo tenés la URL; podés generar el QR con otra librería o servicio.

---

## Anchos recomendados para PDF

| Formato   | Ancho (pulgadas) | Uso        |
|-----------|-------------------|------------|
| Ticket 80mm | 3.1              | Térmico 80mm |
| Ticket 58mm | 2.28             | Térmico 58mm |
| Factura A4  | 8                | Hoja A4    |

---

*Resguar IT — AFIP SDK*
