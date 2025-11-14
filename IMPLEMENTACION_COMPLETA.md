# Implementación Completa - Fases 1 y 2

Este documento describe la implementación completa del proceso de facturación electrónica según el video y la documentación oficial de AFIP.

## ✅ Fase 1: Autenticación (WSAA) - COMPLETADA

### Proceso Implementado:

1. **Generar TRA XML** ✅
   - `TraGenerator::generate()` crea el XML con:
     - `uniqueId`: ID único generado
     - `generationTime`: Fecha/hora de generación
     - `expirationTime`: Fecha/hora de expiración (+1 día)
     - `service`: Nombre del servicio (`wsfe`)
     - `source`: CUIT del contribuyente
     - `destination`: CUIT de WSAA (diferente para testing/producción)

2. **Firmar TRA con OpenSSL (Crear CMS PKCS#7)** ✅
   - `CmsHelper::createCms()` usa OpenSSL para:
     - Firmar el TRA XML con la clave privada
     - Crear mensaje CMS (PKCS#7) que incluye:
       - El TRA original
       - La firma digital
       - El certificado público
     - Codificar todo en base64

3. **Llamar al Web Service `loginCms`** ✅
   - `WsaaService::sendToWsaa()`:
     - Crea cliente SOAP con `SoapHelper`
     - Llama al método `loginCms` con el CMS
     - Maneja errores SOAP con retry logic

4. **Recibir y Guardar el Ticket (Token y Sign)** ✅
   - `WsaaService::parseWsaaResponse()`:
     - Parsea la respuesta XML de WSAA
     - Extrae `Token` y `Sign` (Firma)
     - Extrae fecha de expiración
     - Crea `TokenResponse` DTO
   - Cache automático (12 horas según especificación)

### Archivos Implementados:
- ✅ `src/Helpers/TraGenerator.php` - Generación de TRA XML
- ✅ `src/Helpers/CmsHelper.php` - Generación de CMS PKCS#7
- ✅ `src/Services/CertificateManager.php` - Firma digital
- ✅ `src/Services/WsaaService.php` - Autenticación completa

## ✅ Fase 2: Facturación (WSFE) - COMPLETADA

### Proceso Implementado:

1. **⭐ PRÁCTICA CLAVE: Consultar Último Comprobante** ✅
   - `WsfeService::getLastAuthorizedInvoice()`:
     - Consulta último comprobante autorizado (FECompUltimoAutorizado)
     - Obtiene último número de comprobante
     - Ajusta automáticamente el número si es necesario
   - **Se ejecuta automáticamente** antes de autorizar

2. **Llamar al Web Service `FECAESolicitar`** ✅
   - `WsfeService::authorizeInvoice()`:
     - Obtiene Token y Sign de WSAA (Fase 1)
     - Consulta último comprobante (correlatividad)
     - Crea cliente SOAP para WSFE
     - Prepara parámetros según especificación

3. **Enviar la Autenticación y la Factura** ✅
   - `InvoiceMapper::toFeCAERequest()` mapea datos al formato AFIP:
     - `Auth`: Token, Sign, CUIT
     - `FeCAEReq`:
       - `FeCabReq`: PuntoVta, CbteTipo, CantReg
       - `FeDetReq`: Array con todos los datos del comprobante
         - Concepto, DocTipo, DocNro
         - CbteDesde, CbteHasta, CbteFch
         - Importes (Total, Neto, IVA, etc.)
         - Items con IVA (AlicIva)
         - Tributos si aplica

4. **Recibir el CAE** ✅
   - `WsfeService::parseFECAEResponse()`:
     - Verifica resultado (A = Aprobado)
     - Extrae CAE y fecha de vencimiento
     - Extrae observaciones si las hay
     - Maneja errores y rechazos
     - Crea `InvoiceResponse` DTO

### Archivos Implementados:
- ✅ `src/Helpers/InvoiceMapper.php` - Mapeo a formato AFIP
- ✅ `src/Services/WsfeService.php` - Autorización completa
  - `authorizeInvoice()` - Autorización con correlatividad automática
  - `getLastAuthorizedInvoice()` - Consulta último comprobante (FECompUltimoAutorizado)
  - `parseFECAEResponse()` - Procesamiento de respuesta
  - `parseLastInvoiceResponse()` - Procesamiento de última factura

## Flujo Completo del SDK

```
Usuario llama: Afip::authorizeInvoice($sale)
    ↓
AfipService::authorizeInvoice()
    ↓
InvoiceBuilder::from($sale)->build()
    ↓
ValidatorHelper::validateInvoice()
    ↓
WsfeService::authorizeInvoice()
    ↓
┌─────────────────────────────────────┐
│ FASE 1: Autenticación (WSAA)       │
├─────────────────────────────────────┤
│ 1. WsaaService::getToken('wsfe')   │
│    ↓                                │
│ 2. TraGenerator::generate()        │
│    ↓                                │
│ 3. CmsHelper::createCms()          │
│    ↓                                │
│ 4. SoapHelper::call('loginCms')    │
│    ↓                                │
│ 5. parseWsaaResponse()             │
│    ↓                                │
│ 6. TokenResponse (Token + Sign)    │
│    (Cacheado por 12 horas)         │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ FASE 2: Facturación (WSFE)         │
├─────────────────────────────────────┤
│ 1. getLastAuthorizedInvoice()      │
│    (FECompUltimoAutorizado)        │
│    ↓                                │
│ 2. Ajustar número si necesario     │
│    (Correlatividad automática)      │
│    ↓                                │
│ 3. InvoiceMapper::toFeCAERequest() │
│    ↓                                │
│ 4. SoapHelper::createClient(WSFE)  │
│    ↓                                │
│ 5. SoapHelper::call('FECAESolicitar')│
│    Parámetros:                      │
│    - Auth: Token, Sign, CUIT        │
│    - FeCAEReq: Datos comprobante    │
│    ↓                                │
│ 6. parseFECAEResponse()            │
│    ↓                                │
│ 7. InvoiceResponse (CAE + datos)   │
└─────────────────────────────────────┘
    ↓
Retorna InvoiceResponse con CAE válido
```

## Estructura de Datos

### Entrada (Formato Interno del SDK):
```php
[
    'pointOfSale' => 1,
    'invoiceType' => 1,
    'invoiceNumber' => 1,
    'date' => '20240101',
    'customerCuit' => '20123456789',
    'customerDocumentType' => 80,
    'concept' => 1,
    'items' => [
        [
            'description' => 'Producto 1',
            'quantity' => 1,
            'unitPrice' => 100,
            'taxRate' => 21,
        ]
    ],
    'total' => 121,
    'totalIva' => 21,
    'totalNetoGravado' => 100,
]
```

### Salida (Formato AFIP - FeCAERequest):
```php
[
    'Auth' => [
        'Token' => '...',
        'Sign' => '...',
        'Cuit' => 20123456789.0,
    ],
    'FeCAEReq' => [
        'FeCabReq' => [
            'CantReg' => 1,
            'PtoVta' => 1,
            'CbteTipo' => 1,
        ],
        'FeDetReq' => [
            [
                'Concepto' => 1,
                'DocTipo' => 80,
                'DocNro' => 123456789,
                'CbteDesde' => 1,
                'CbteHasta' => 1,
                'CbteFch' => '20240101',
                'ImpTotal' => 121.0,
                'ImpNeto' => 100.0,
                'ImpIVA' => 21.0,
                'Iva' => [
                    [
                        'Id' => 5,
                        'BaseImp' => 100.0,
                        'Alic' => 21.0,
                    ]
                ],
            ]
        ],
    ],
]
```

### Respuesta (InvoiceResponse DTO):
```php
InvoiceResponse {
    cae: "12345678901234"
    caeExpirationDate: "20240115"
    invoiceNumber: 1
    pointOfSale: 1
    invoiceType: 1
    observations: []
}
```

## Características Implementadas

### ✅ Autenticación (WSAA)
- Generación de TRA XML según especificación
- Firma digital con OpenSSL (CMS PKCS#7)
- Comunicación SOAP con WSAA
- Parsing de respuesta XML
- Cache de tokens (12 horas)
- Manejo de errores completo

### ✅ Facturación (WSFE)
- Mapeo de datos al formato AFIP
- Construcción de FeCAERequest
- Comunicación SOAP con WSFE
- Llamada a FECAESolicitar
- Parsing de respuesta y extracción de CAE
- Manejo de errores y observaciones
- Validación de resultados

### ✅ Helpers y Utilidades
- `TraGenerator`: Generación de TRA XML
- `CmsHelper`: Generación de CMS PKCS#7
- `InvoiceMapper`: Mapeo a formato AFIP
- `SoapHelper`: Cliente SOAP con retry
- `ValidatorHelper`: Validación de datos

## Configuración Requerida

```env
AFIP_ENVIRONMENT=testing
AFIP_CUIT=20123456789
AFIP_CERTIFICATES_PATH=/ruta/a/certificados
AFIP_CERTIFICATE_KEY=private_key.key
AFIP_CERTIFICATE_CRT=certificate.crt
AFIP_CERTIFICATE_PASSWORD=tu_password
```

## Uso del SDK

```php
use Resguar\AfipSdk\Facades\Afip;

// Autorizar factura
$result = Afip::authorizeInvoice([
    'pointOfSale' => 1,
    'invoiceType' => 1,
    'invoiceNumber' => 1,
    'date' => date('Ymd'),
    'customerCuit' => '20123456789',
    'customerDocumentType' => 80,
    'concept' => 1,
    'items' => [
        [
            'description' => 'Producto 1',
            'quantity' => 1,
            'unitPrice' => 100,
            'taxRate' => 21,
        ]
    ],
    'total' => 121,
    'totalIva' => 21,
    'totalNetoGravado' => 100,
]);

// El CAE está en $result->cae
echo "CAE: " . $result->cae;
echo "Vencimiento: " . $result->caeExpirationDate;
```

## Estado Final

✅ **Fase 1 (Autenticación)**: 100% Implementada
✅ **Fase 2 (Facturación)**: 100% Implementada
✅ **Helpers y Utilidades**: Completos
✅ **Manejo de Errores**: Completo
✅ **Logging**: Implementado
✅ **Cache**: Configurado (12 horas)

**El SDK está listo para facturar electrónicamente con AFIP** 🎉

