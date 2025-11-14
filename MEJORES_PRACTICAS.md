# Mejores Prácticas Implementadas

Este documento describe las mejores prácticas implementadas en el SDK basadas en la experiencia de desarrolladores y la documentación oficial de AFIP.

## ✅ Prácticas Clave Implementadas

### 1. Desarrollo Modular e Independiente

✅ **Implementado**: El SDK está estructurado como un paquete Laravel independiente (`resguar/afip-sdk`) que puede ser reutilizado en múltiples proyectos.

**Beneficios:**
- Separación clara de responsabilidades
- Fácil mantenimiento y actualización
- Reutilizable en diferentes proyectos Laravel

### 2. Lógica de Autenticación (WSAA) Separada

✅ **Implementado**: `WsaaService` tiene una única responsabilidad: manejar la autenticación con WSAA.

**Características:**
- Método `getToken()` que retorna `TokenResponse`
- Generación de TRA XML
- Firma digital con CMS (PKCS#7)
- Comunicación SOAP con WSAA
- Parsing de respuesta

**Uso:**
```php
$wsaaService = app(\Resguar\AfipSdk\Services\WsaaService::class);
$tokenResponse = $wsaaService->getToken('wsfe');
```

### 3. Manejo de Expiración de Tokens (Caché)

✅ **Implementado**: Cache automático de tokens con Laravel Cache.

**Características:**
- Tokens válidos por **12 horas** (según especificación AFIP)
- Cache automático usando Laravel Cache
- Validación de expiración antes de usar token cacheado
- Limpieza automática cuando expiran

**Configuración:**
```php
// config/afip.php
'cache' => [
    'enabled' => true,
    'ttl' => 43200, // 12 horas en segundos
],
```

**Beneficios:**
- No se genera un nuevo token en cada factura
- Reduce llamadas innecesarias a WSAA
- Mejora el rendimiento

### 4. ⭐ PRÁCTICA CLAVE: Consultar Último Comprobante

✅ **Implementado**: El SDK **automáticamente** consulta el último comprobante autorizado ANTES de emitir uno nuevo.

**Por qué es crítico:**
- AFIP requiere que los números de comprobante sean **correlativos**
- Si intentas emitir el comprobante #101 pero el último autorizado es #100, AFIP lo rechazará
- El SDK consulta automáticamente y ajusta el número si es necesario

**Implementación:**
```php
// En WsfeService::authorizeInvoice()
// 1. Consulta último comprobante
$lastInvoice = $this->getLastAuthorizedInvoice($pointOfSale, $invoiceType);

// 2. Valida y ajusta número
$lastNumber = $lastInvoice['CbteNro'] ?? 0;
if ($requestedNumber <= $lastNumber) {
    $invoice['invoiceNumber'] = $lastNumber + 1;
}

// 3. Procede con la autorización
```

**Método implementado:**
```php
// Consulta manual si es necesario
$lastInvoice = Afip::getLastAuthorizedInvoice(1, 1);
// Retorna: ['CbteNro' => 100, 'CbteFch' => '20240101', ...]
```

**Beneficios:**
- ✅ Garantiza correlatividad automática
- ✅ Previene rechazos por números incorrectos
- ✅ No requiere gestión manual de números

### 5. Lógica de Emisión (WSFE) Separada

✅ **Implementado**: `WsfeService` maneja toda la lógica de facturación electrónica.

**Características:**
- Método `authorizeInvoice()` que orquesta todo el proceso
- Mapeo automático de datos al formato AFIP
- Llamada a `FECAESolicitar`
- Procesamiento de respuesta y extracción de CAE

**Flujo:**
1. Obtiene Token/Sign de WSAA
2. Consulta último comprobante (correlatividad)
3. Mapea datos al formato AFIP
4. Llama a FECAESolicitar
5. Procesa respuesta y retorna CAE

### 6. El SDK NO Genera PDF (Solo Devuelve CAE)

✅ **Correcto**: El SDK solo retorna el `CAE` y los datos necesarios. La generación del PDF es responsabilidad de la aplicación que usa el SDK.

**Qué retorna el SDK:**
```php
InvoiceResponse {
    cae: "12345678901234"           // CAE para incluir en el PDF
    caeExpirationDate: "20240115"   // Fecha de vencimiento
    invoiceNumber: 1                 // Número autorizado
    pointOfSale: 1
    invoiceType: 1
    observations: []                 // Observaciones de AFIP (si las hay)
}
```

**Qué NO hace el SDK:**
- ❌ No genera PDF
- ❌ No diseña el formato del comprobante
- ❌ No maneja la visualización

**Qué SÍ hace la aplicación:**
- ✅ Usa el CAE retornado
- ✅ Genera el PDF con los datos de la factura
- ✅ Incluye el CAE en el PDF según formato legal

## Arquitectura del SDK

```
┌─────────────────────────────────────────┐
│         AfipService (Orquestador)       │
│  - authorizeInvoice()                   │
│  - getLastAuthorizedInvoice()           │
└──────────────┬──────────────────────────┘
               │
       ┌───────┴────────┐
       │                │
┌──────▼──────┐  ┌──────▼──────┐
│ WsaaService │  │ WsfeService  │
│ (Auth)      │  │ (Facturación) │
└──────┬──────┘  └──────┬───────┘
       │                │
       │                │
┌──────▼──────┐  ┌──────▼──────────────┐
│ TraGenerator│  │ InvoiceMapper        │
│ CmsHelper   │  │ (Mapeo a formato AFIP)│
│ Certificate │  │                      │
│ Manager     │  └──────────────────────┘
└─────────────┘
```

## Flujo Completo con Mejores Prácticas

```
1. Usuario: Afip::authorizeInvoice($sale)
   ↓
2. InvoiceBuilder: Construye datos desde fuente
   ↓
3. ValidatorHelper: Valida datos
   ↓
4. WsfeService::authorizeInvoice()
   ↓
5. [AUTENTICACIÓN] WsaaService::getToken('wsfe')
   ├─ Genera TRA XML
   ├─ Firma con CMS (PKCS#7)
   ├─ Envía a WSAA
   ├─ Recibe Token + Sign
   └─ Cachea por 12 horas
   ↓
6. [CORRELATIVIDAD] WsfeService::getLastAuthorizedInvoice()
   ├─ Consulta último comprobante autorizado
   ├─ Obtiene último número (ej: 100)
   └─ Ajusta número si es necesario (101)
   ↓
7. [AUTORIZACIÓN] WsfeService continúa
   ├─ Mapea datos a formato AFIP
   ├─ Llama FECAESolicitar
   ├─ Procesa respuesta
   └─ Retorna InvoiceResponse con CAE
   ↓
8. Aplicación: Genera PDF con CAE
```

## Ventajas de esta Arquitectura

### ✅ Separación de Responsabilidades
- Cada servicio tiene una única responsabilidad
- Fácil de testear y mantener
- Cambios en un servicio no afectan a otros

### ✅ Reutilización
- Paquete independiente instalable via Composer
- Puede usarse en múltiples proyectos
- No acoplado a una aplicación específica

### ✅ Confiabilidad
- Consulta automática de último comprobante
- Validación de datos antes de enviar
- Manejo robusto de errores
- Retry logic para errores temporales

### ✅ Mantenibilidad
- Código bien estructurado y documentado
- Logging completo para debugging
- Excepciones descriptivas
- Type hints estrictos

### ✅ Performance
- Cache de tokens (evita llamadas innecesarias)
- Retry logic inteligente
- Validación temprana (evita llamadas a AFIP con datos inválidos)

## Comparación con Otros SDKs

| Característica | Este SDK | Otros SDKs |
|---------------|----------|------------|
| Consulta automática último comprobante | ✅ Automático | ⚠️ Manual |
| Cache de tokens | ✅ Laravel Cache | ⚠️ Variable |
| Validación de datos | ✅ Completa | ⚠️ Básica |
| Manejo de errores | ✅ Excepciones tipadas | ⚠️ Genérico |
| Logging | ✅ Integrado | ⚠️ Opcional |
| Type safety | ✅ PHP 8.1+ | ⚠️ Variable |
| Builder Pattern | ✅ Flexible | ❌ No siempre |
| DTOs | ✅ Tipados | ⚠️ Arrays |

## Notas Importantes

### Sobre la Correlatividad
- ⚠️ **CRÍTICO**: Los números de comprobante DEBEN ser correlativos
- ✅ El SDK lo maneja automáticamente
- ✅ Si no especificas número, usa el siguiente al último autorizado
- ✅ Si especificas un número menor/igual, lo ajusta automáticamente

### Sobre el Cache
- Los tokens son válidos por **12 horas** (no 24)
- El cache se limpia automáticamente al expirar
- Puedes limpiar manualmente: `$wsaaService->clearTokenCache()`

### Sobre el PDF
- El SDK **NO genera PDF**
- Solo retorna el CAE y datos necesarios
- La aplicación debe generar el PDF con el CAE incluido
- El formato del PDF debe cumplir con normativas legales

## Referencias

- Manual Desarrollador ARCA COMPG v4.0
- Manual WSAA
- Manual WSASS
- Mejores prácticas de la comunidad de desarrolladores AFIP

