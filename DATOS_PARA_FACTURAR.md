# 📋 Datos Necesarios para Facturar con AFIP

Esta guía detalla todos los datos que necesitas para autorizar una factura electrónica con el SDK de AFIP.

## 🎯 Estructura de Datos Completa

### Datos Requeridos (Obligatorios)

Estos campos son **obligatorios** y el SDK los valida antes de enviar a AFIP:

```php
$invoiceData = [
    // ============================================
    // DATOS DEL COMPROBANTE (Obligatorios)
    // ============================================
    
    'pointOfSale' => 1,              // Punto de venta (1-99999)
    'invoiceType' => 1,              // Tipo de comprobante (ver tabla abajo)
    'invoiceNumber' => 0,            // Número de comprobante (0 = auto, se ajusta automáticamente)
    'date' => '20240101',            // Fecha del comprobante (formato Ymd: YYYYMMDD)
    
    // ============================================
    // DATOS DEL CLIENTE (Obligatorios)
    // ============================================
    
    'customerCuit' => '20123456789', // CUIT del cliente (11 dígitos, sin guiones)
    'customerDocumentType' => 80,   // Tipo de documento (ver tabla abajo)
    'customerDocumentNumber' => '20123456789', // Número de documento (mismo que CUIT si es CUIT)
    
    // ============================================
    // CONCEPTO Y ITEMS (Obligatorios)
    // ============================================
    
    'concept' => 1,                  // Concepto: 1=Productos, 2=Servicios, 3=Productos y Servicios
    'items' => [                     // Array de items (mínimo 1 item)
        [
            'code' => 'PROD001',              // Código del producto (opcional, max 50 caracteres)
            'description' => 'Producto ejemplo', // Descripción (obligatorio, max 250 caracteres)
            'quantity' => 1.0,                // Cantidad (obligatorio, mínimo 0.01)
            'unitPrice' => 100.0,             // Precio unitario (obligatorio, mínimo 0)
            'taxRate' => 21.0,                // Tasa de IVA (opcional, 0-100)
        ],
        // ... más items
    ],
    
    // ============================================
    // TOTALES (Obligatorios)
    // ============================================
    
    'total' => 121.0,                // Total del comprobante (obligatorio, mínimo 0)
];
```

## 📊 Campos Detallados

### 1. Punto de Venta (`pointOfSale`)

**Tipo:** `integer`  
**Rango:** 1-99999  
**Descripción:** Número del punto de venta habilitado en AFIP

```php
'pointOfSale' => 1  // Ejemplo: Punto de venta 1
```

### 2. Tipo de Comprobante (`invoiceType`)

**Tipo:** `integer`  
**Valores comunes:**

| Código | Descripción | Uso |
|--------|-------------|-----|
| 1 | Factura A | Responsables Inscriptos |
| 6 | Factura B | Consumidor Final / Monotributistas |
| 11 | Factura C | Exentos |
| 12 | Nota de Débito A | Ajustes Factura A |
| 13 | Nota de Débito B | Ajustes Factura B |
| 8 | Nota de Crédito A | Devoluciones Factura A |
| 3 | Nota de Crédito B | Devoluciones Factura B |

```php
'invoiceType' => 1  // Factura A
```

### 3. Número de Comprobante (`invoiceNumber`)

**Tipo:** `integer`  
**Importante:** 
- Si envías `0`, el SDK **automáticamente** consulta el último autorizado y ajusta al siguiente número
- Si envías un número específico, el SDK lo valida y ajusta si es necesario

```php
'invoiceNumber' => 0  // Auto (recomendado)
// o
'invoiceNumber' => 105  // Número específico
```

### 4. Fecha del Comprobante (`date`)

**Tipo:** `string`  
**Formato:** `Ymd` (YYYYMMDD)  
**Ejemplo:** `20240101` = 1 de enero de 2024

```php
'date' => '20240101'  // 1 de enero de 2024
// o desde Carbon/DateTime
'date' => now()->format('Ymd')
```

### 5. CUIT del Cliente (`customerCuit`)

**Tipo:** `string`  
**Formato:** 11 dígitos, sin guiones  
**Validación:** El SDK valida que tenga exactamente 11 dígitos

```php
'customerCuit' => '20123456789'  // Sin guiones
// o con guiones (se limpia automáticamente)
'customerCuit' => '20-12345678-9'
```

### 6. Tipo de Documento del Cliente (`customerDocumentType`)

**Tipo:** `integer`  
**Valores comunes:**

| Código | Descripción |
|--------|-------------|
| 80 | CUIT |
| 86 | CUIL |
| 87 | CDI |
| 89 | LE (Libreta de Enrolamiento) |
| 90 | LC (Libreta Cívica) |
| 96 | DNI |
| 99 | Consumidor Final |

```php
'customerDocumentType' => 80  // CUIT
// o
'customerDocumentType' => 99  // Consumidor Final
```

### 7. Número de Documento del Cliente (`customerDocumentNumber`)

**Tipo:** `string`  
**Descripción:** Número de documento del cliente (sin guiones)

```php
'customerDocumentNumber' => '20123456789'  // Mismo que CUIT si es CUIT
// o
'customerDocumentNumber' => '12345678'  // DNI si es DNI
```

### 8. Concepto (`concept`)

**Tipo:** `integer`  
**Valores:**

| Código | Descripción |
|--------|-------------|
| 1 | Productos |
| 2 | Servicios |
| 3 | Productos y Servicios |

```php
'concept' => 1  // Productos
```

### 9. Items (`items`)

**Tipo:** `array`  
**Mínimo:** 1 item  
**Estructura de cada item:**

```php
'items' => [
    [
        'code' => 'PROD001',              // Opcional: Código del producto (max 50 caracteres)
        'description' => 'Producto ejemplo', // Obligatorio: Descripción (max 250 caracteres)
        'quantity' => 1.0,                // Obligatorio: Cantidad (mínimo 0.01)
        'unitPrice' => 100.0,             // Obligatorio: Precio unitario (mínimo 0)
        'taxRate' => 21.0,                // Opcional: Tasa de IVA (0-100)
    ],
    [
        'description' => 'Otro producto',
        'quantity' => 2.5,
        'unitPrice' => 50.0,
        'taxRate' => 10.5,  // IVA 10.5%
    ],
]
```

### 10. Total (`total`)

**Tipo:** `float`  
**Descripción:** Total del comprobante (incluye IVA y todos los impuestos)  
**Mínimo:** 0

```php
'total' => 121.0  // $100 + $21 IVA = $121
```

## 📝 Campos Opcionales (Recomendados)

Estos campos son opcionales pero recomendados para facturas más completas:

```php
$invoiceData = [
    // ... campos obligatorios ...
    
    // ============================================
    // TOTALES DETALLADOS (Opcionales pero recomendados)
    // ============================================
    
    'netAmount' => 100.0,            // Base neta gravada (sin IVA)
    'ivaTotal' => 21.0,              // Total de IVA
    'nonTaxedTotal' => 0.0,          // Total no gravado
    'exemptAmount' => 0.0,           // Total exento
    'tributesTotal' => 0.0,          // Total de tributos (IIBB, etc.)
    
    // ============================================
    // IVA POR TASA (Opcional pero recomendado)
    // ============================================
    
    'ivaItems' => [
        [
            'id' => 5,                // ID de AFIP: 3=0%, 4=10.5%, 5=21%, 6=27%
            'baseAmount' => 100.0,    // Base imponible
            'amount' => 21.0,         // Importe de IVA
        ],
    ],
    
    // ============================================
    // FECHAS DE SERVICIO (Opcional)
    // ============================================
    
    'serviceStartDate' => '20240101', // Fecha inicio servicio (formato Ymd)
    'serviceEndDate' => '20240131',   // Fecha fin servicio (formato Ymd)
    'paymentDueDate' => '20240215',  // Fecha vencimiento pago (formato Ymd)
    
    // ============================================
    // MONEDA (Opcional, default: PES)
    // ============================================
    
    'currencyId' => 'PES',           // Código de moneda (PES, USD, EUR, etc.)
    'currencyExchange' => 1.0,       // Cotización de moneda (default: 1.0)
    
    // ============================================
    // TRIBUTOS (Opcional)
    // ============================================
    
    'tributes' => [
        [
            'id' => 7,                // ID de tributo (7=IIBB, etc.)
            'description' => 'Ingresos Brutos',
            'baseAmount' => 121.0,
            'aliquot' => 3.0,
            'amount' => 3.63,
        ],
    ],
    
    // ============================================
    // COMPROBANTES ASOCIADOS (Opcional)
    // ============================================
    
    'associatedVouchers' => [
        [
            'type' => 8,              // Tipo de comprobante asociado
            'pointOfSale' => 1,       // Punto de venta
            'number' => 100,           // Número de comprobante
        ],
    ],
];
```

## 🎯 Ejemplo Completo Mínimo

```php
use Resguar\AfipSdk\Facades\Afip;

$invoiceData = [
    // Datos del comprobante
    'pointOfSale' => 1,
    'invoiceType' => 1,  // Factura A
    'invoiceNumber' => 0,  // Auto (se ajusta automáticamente)
    'date' => now()->format('Ymd'),
    
    // Datos del cliente
    'customerCuit' => '20123456789',
    'customerDocumentType' => 80,  // CUIT
    'customerDocumentNumber' => '20123456789',
    
    // Concepto
    'concept' => 1,  // Productos
    
    // Items
    'items' => [
        [
            'description' => 'Producto de ejemplo',
            'quantity' => 1,
            'unitPrice' => 100.0,
            'taxRate' => 21.0,
        ],
    ],
    
    // Total
    'total' => 121.0,  // $100 + $21 IVA
];

// Autorizar
$result = Afip::authorizeInvoice($invoiceData);
```

## 🎯 Ejemplo Completo con Todos los Campos

```php
use Resguar\AfipSdk\Facades\Afip;

$invoiceData = [
    // ============================================
    // DATOS DEL COMPROBANTE
    // ============================================
    'pointOfSale' => 1,
    'invoiceType' => 1,  // Factura A
    'invoiceNumber' => 0,  // Auto
    'date' => '20240115',
    
    // ============================================
    // DATOS DEL CLIENTE
    // ============================================
    'customerCuit' => '20123456789',
    'customerDocumentType' => 80,  // CUIT
    'customerDocumentNumber' => '20123456789',
    
    // ============================================
    // CONCEPTO
    // ============================================
    'concept' => 1,  // Productos
    
    // ============================================
    // ITEMS
    // ============================================
    'items' => [
        [
            'code' => 'PROD001',
            'description' => 'Producto A',
            'quantity' => 2,
            'unitPrice' => 100.0,
            'taxRate' => 21.0,
        ],
        [
            'code' => 'PROD002',
            'description' => 'Producto B',
            'quantity' => 1,
            'unitPrice' => 50.0,
            'taxRate' => 10.5,
        ],
    ],
    
    // ============================================
    // TOTALES
    // ============================================
    'netAmount' => 250.0,      // Base neta: (2*100) + (1*50) = 250
    'ivaTotal' => 47.25,       // IVA: (200*0.21) + (50*0.105) = 42 + 5.25 = 47.25
    'total' => 297.25,         // Total: 250 + 47.25 = 297.25
    'nonTaxedTotal' => 0.0,
    'exemptAmount' => 0.0,
    'tributesTotal' => 0.0,
    
    // ============================================
    // IVA POR TASA
    // ============================================
    'ivaItems' => [
        [
            'id' => 5,          // 21%
            'baseAmount' => 200.0,
            'amount' => 42.0,
        ],
        [
            'id' => 4,          // 10.5%
            'baseAmount' => 50.0,
            'amount' => 5.25,
        ],
    ],
    
    // ============================================
    // FECHAS DE SERVICIO (Opcional)
    // ============================================
    'serviceStartDate' => '20240101',
    'serviceEndDate' => '20240131',
    'paymentDueDate' => '20240215',
];

// Autorizar
$result = Afip::authorizeInvoice($invoiceData);

// El resultado contiene:
// - $result->cae (Código de Autorización Electrónico)
// - $result->caeExpirationDate (Fecha de vencimiento)
// - $result->invoiceNumber (Número de comprobante autorizado)
```

## 📋 Tabla de Referencia Rápida

### Tipos de Comprobante Comunes

| Código | Nombre | Para |
|--------|--------|------|
| 1 | Factura A | Responsables Inscriptos |
| 6 | Factura B | Consumidor Final |
| 11 | Factura C | Exentos |

### Tipos de Documento Comunes

| Código | Nombre |
|--------|--------|
| 80 | CUIT |
| 96 | DNI |
| 99 | Consumidor Final |

### Conceptos

| Código | Nombre |
|--------|--------|
| 1 | Productos |
| 2 | Servicios |
| 3 | Productos y Servicios |

### IDs de IVA (Alicuotas)

| ID | Tasa |
|----|------|
| 3 | 0% (Exento) |
| 4 | 10.5% |
| 5 | 21% |
| 6 | 27% |

## ⚠️ Validaciones del SDK

El SDK valida automáticamente:

- ✅ `pointOfSale`: Entre 1 y 99999
- ✅ `invoiceType`: Mayor a 0
- ✅ `invoiceNumber`: Mayor a 0
- ✅ `date`: Formato Ymd (YYYYMMDD)
- ✅ `customerCuit`: Exactamente 11 dígitos
- ✅ `customerDocumentType`: Entre 80 y 99
- ✅ `concept`: 1, 2 o 3
- ✅ `items`: Mínimo 1 item
- ✅ `items[].description`: Máximo 250 caracteres
- ✅ `items[].quantity`: Mínimo 0.01
- ✅ `items[].unitPrice`: Mínimo 0
- ✅ `total`: Mínimo 0

## 🔄 Cálculo Automático de Totales

Si no proporcionas los totales detallados, el SDK puede calcularlos desde los items:

```php
// Opción 1: Proporcionar totales manualmente (recomendado)
$invoiceData = [
    'items' => [...],
    'netAmount' => 100.0,
    'ivaTotal' => 21.0,
    'total' => 121.0,
];

// Opción 2: Dejar que el SDK calcule (si InvoiceBuilder está completo)
// Por ahora, siempre proporciona los totales manualmente
```

## 💡 Consejos

1. **Número de comprobante**: Usa `0` para que el SDK lo ajuste automáticamente
2. **Fecha**: Siempre en formato `Ymd` (sin guiones ni separadores)
3. **CUIT**: Puedes enviarlo con guiones, el SDK lo limpia automáticamente
4. **Items**: Mínimo 1 item, pero puedes tener tantos como necesites
5. **Totales**: Calcula los totales antes de enviar (el SDK valida que coincidan)

## ❓ Preguntas Frecuentes

**P: ¿Puedo enviar el número de comprobante como 0?**
R: Sí, el SDK automáticamente consulta el último autorizado y ajusta al siguiente número.

**P: ¿El CUIT puede tener guiones?**
R: Sí, el SDK lo limpia automáticamente. `'20-12345678-9'` se convierte a `'20123456789'`.

**P: ¿Cuántos items puedo tener?**
R: Mínimo 1, no hay máximo (pero AFIP tiene límites prácticos).

**P: ¿Necesito calcular los totales manualmente?**
R: Sí, por ahora debes calcularlos. El SDK valida que los totales sean correctos.

**P: ¿Qué pasa si falta un campo obligatorio?**
R: El SDK lanza una excepción de validación con el campo faltante.

---

**¿Necesitas más ayuda?** Revisa los ejemplos en [INTEGRACION_POS.md](INTEGRACION_POS.md) o consulta la documentación oficial de AFIP.

