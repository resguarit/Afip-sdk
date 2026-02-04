# Códigos de tipos de comprobantes AFIP (CbteTipo)

Referencia de los códigos numéricos usados en facturación electrónica (WSFE). El campo se llama **CbteTipo** en los servicios de AFIP.

---

## Tabla oficial AFIP

La tabla oficial completa y actualizada se descarga desde AFIP:

- **Tipos de Comprobantes (Excel):**  
  https://www.afip.gob.ar/fe/documentos/TABLACOMPROBANTES.xls

- **Todas las tablas de FE:**  
  https://www.afip.gob.ar/fe/ayuda/tablas.asp

---

## Códigos más usados (referencia)

| Código | Descripción |
|--------|-------------|
| **Facturas y comprobantes tipo A** | |
| 1 | Factura A |
| 2 | Nota de Débito A |
| 3 | Nota de Crédito A |
| 4 | Recibo A |
| **Facturas y comprobantes tipo B** | |
| 6 | Factura B |
| 7 | Nota de Débito B |
| 8 | Nota de Crédito B |
| 9 | Recibo B |
| **Facturas y comprobantes tipo C** | |
| 11 | Factura C |
| 12 | Nota de Débito C |
| 13 | Nota de Crédito C |
| 15 | Recibo C |
| **Facturas y comprobantes tipo M** | |
| 51 | Factura M |
| 52 | Nota de Débito M |
| 53 | Nota de Crédito M |
| 54 | Recibo M |
| **Facturación electrónica MiPyMEs (FCE) tipo A** | |
| 201 | Factura de Crédito electrónica MiPyMEs (FCE) A |
| 202 | Nota de Débito electrónica MiPyMEs (FCE) A |
| 203 | Nota de Crédito electrónica MiPyMEs (FCE) A |
| **Facturación electrónica MiPyMEs (FCE) tipo B** | |
| 206 | Factura de Crédito electrónica MiPyMEs (FCE) B |
| 207 | Nota de Débito electrónica MiPyMEs (FCE) B |
| 208 | Nota de Crédito electrónica MiPyMEs (FCE) B |
| **Facturación electrónica MiPyMEs (FCE) tipo C** | |
| 211 | Factura de Crédito electrónica MiPyMEs (FCE) C |
| 212 | Nota de Débito electrónica MiPyMEs (FCE) C |
| 213 | Nota de Crédito electrónica MiPyMEs (FCE) C |

---

## Uso según condición IVA del emisor

- **Responsable Inscripto (IVA):** suele usar tipos A, B, M y FCE A/B (ej. 1, 6, 51, 201, 206).
- **Monotributista / Exento:** suele usar tipos C y FCE C (ej. 11, 12, 13, 15, 211, 212, 213).

---

## Reglas de emisión: tipo de comprobante y receptor

El SDK valida que la combinación **tipo de comprobante + condición IVA del receptor** sea correcta:

```
┌─────────────────────┬─────────────────────────────────────────────────────┐
│                     │                    RECEPTOR                         │
│      EMISOR         ├─────────────────┬─────────────────┬─────────────────┤
│                     │ CF / Exento     │ Monotributista  │ Resp. Inscripto │
├─────────────────────┼─────────────────┼─────────────────┼─────────────────┤
│ Resp. Inscripto     │ Factura B       │ Factura A       │ Factura A       │
├─────────────────────┼─────────────────┼─────────────────┼─────────────────┤
│ Monotrib. / Exento  │ Factura C       │ Factura C       │ Factura C       │
└─────────────────────┴─────────────────┴─────────────────┴─────────────────┘
```

| Comprobante | Emisor | Receptores permitidos |
|-------------|--------|----------------------|
| **Factura A** (1, 2, 3) | RI | RI o Monotributista |
| **Factura B** (6, 7, 8) | RI | Consumidor Final o Exento |
| **Factura C** (11, 12, 13) | Monotributista/Exento | Cualquier receptor |

Si se envía una combinación inválida (ej. Factura A a consumidor final), el SDK lanza `AfipValidationException` antes de llamar a AFIP.

Los tipos habilitados para cada CUIT se pueden consultar con el SDK:

```php
$tipos = Afip::getReceiptTypesForCuit();
// $tipos['receipt_types'] tiene id y description por cada tipo habilitado
```

---

## Exportación y otros

Para **Factura E** (exportación) y demás códigos vigentes, consultar siempre la tabla oficial en el Excel de AFIP, ya que puede haber códigos adicionales o cambios.

---

*Referencia basada en documentación AFIP y en el mapeo del SDK. Para valor oficial, usar la tabla descargable de AFIP.*
