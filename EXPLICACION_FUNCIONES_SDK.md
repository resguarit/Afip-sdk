# Explicación Detallada: Funciones del SDK que Usas

Esta guía explica **paso a paso** qué hace cada función del SDK que estás usando en tu integración.

## 🎯 Función Principal: `Afip::authorizeInvoice()`

### ¿Qué hace?

Esta es la función **principal** que autoriza una factura con AFIP. Internamente ejecuta un flujo completo de 8 pasos:

```php
$result = Afip::authorizeInvoice($invoiceData);
```

### Flujo Interno Completo (Paso a Paso)

#### **Paso 1: Construcción del Comprobante** (`InvoiceBuilder`)
```php
// El SDK toma tus datos y los estructura
$invoice = InvoiceBuilder::from($invoiceData)->build();
```
- Valida que los datos estén en formato correcto
- Normaliza campos si es necesario
- Prepara estructura interna

#### **Paso 2: Validación de Datos** (`ValidatorHelper`)
```php
ValidatorHelper::validateInvoice($invoice);
```
- ✅ Valida que `pointOfSale` esté entre 1-99999
- ✅ Valida que `invoiceType` sea válido
- ✅ Valida que `invoiceNumber` sea positivo
- ✅ Valida formato de fecha (`Ymd`)
- ✅ Valida CUIT del cliente (11 dígitos)
- ✅ Valida que haya al menos 1 item
- ✅ Valida totales y montos
- ❌ **Lanza excepción** si algo falla

#### **Paso 3: Autenticación con WSAA** (`WsaaService::getTokenAndSignature()`)
```php
$auth = $wsaaService->getTokenAndSignature('wsfe');
```

**¿Qué hace internamente?**

1. **Verifica cache**: Busca token válido en cache (válido 12 horas)
   - ✅ Si existe y no expiró → Lo retorna (no hace llamada a AFIP)
   - ❌ Si no existe o expiró → Continúa

2. **Genera TRA (Ticket de Requerimiento de Acceso)**:
   ```xml
   <loginTicketRequest version="1.0">
     <header>
       <source>CN=TU_CUIT,O=AFIP,C=AR,serialNumber=CUIT TU_CUIT</source>
       <destination>CN=wsaa, O=AFIP, C=AR, SERIALNUMBER=CUIT 33693450239</destination>
       <uniqueId>1234567890</uniqueId>
       <generationTime>2024-01-01T10:00:00.000-03:00</generationTime>
       <expirationTime>2024-01-01T22:00:00.000-03:00</expirationTime>
     </header>
     <service>wsfe</service>
   </loginTicketRequest>
   ```

3. **Firma Digitalmente el TRA** (PKCS#7/CMS):
   - Usa tu certificado privado (`.key`)
   - Crea mensaje CMS firmado
   - Codifica en base64

4. **Envía a WSAA** (Web Service de Autenticación y Autorización):
   - Crea cliente SOAP
   - Llama método `loginCms` con el CMS firmado
   - Recibe respuesta con token y firma

5. **Parsea Respuesta**:
   ```php
   TokenResponse {
     token: "PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4K...",
     signature: "abc123...",
     expirationDate: DateTime("2024-01-01 22:00:00"),
     generationTime: "2024-01-01T10:00:00.000-03:00"
   }
   ```

6. **Guarda en Cache** (12 horas):
   - Clave: `afip_token_wsfe`
   - TTL: Hasta 5 minutos antes de expiración

**Retorna:**
```php
[
    'token' => 'PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4K...',
    'signature' => 'abc123...'
]
```

#### **Paso 4: Consulta Último Comprobante Autorizado** ⚠️ **CRÍTICO**
```php
$lastInvoice = $this->getLastAuthorizedInvoice($pointOfSale, $invoiceType);
```

**¿Por qué es crítico?**
- AFIP **exige correlatividad**: Los números de comprobante deben ser secuenciales
- Si intentas autorizar el número 100 pero el último autorizado fue 105, AFIP rechazará
- El SDK **automáticamente** consulta y ajusta el número

**¿Qué hace?**
1. Crea cliente SOAP para WSFE
2. Llama método `FECompUltimoAutorizado`:
   ```php
   $params = [
       'Auth' => ['Token' => $token, 'Sign' => $signature, 'Cuit' => $cuit],
       'PtoVta' => 1,
       'CbteTipo' => 1
   ];
   $response = $client->FECompUltimoAutorizado($params);
   ```
3. Retorna:
   ```php
   [
       'CbteNro' => 105,        // Último número autorizado
       'CbteFch' => '20240101', // Fecha del último
       'PtoVta' => 1,
       'CbteTipo' => 1
   ]
   ```

#### **Paso 5: Ajuste Automático del Número** 🔄
```php
$lastNumber = (int) ($lastInvoice['CbteNro'] ?? 0);
$requestedNumber = (int) ($invoice['invoiceNumber'] ?? 0);

if ($requestedNumber <= $lastNumber) {
    $nextNumber = $lastNumber + 1;
    $invoice['invoiceNumber'] = $nextNumber; // Ajusta automáticamente
}
```

**Ejemplo:**
- Último autorizado: 105
- Tú enviaste: 100
- SDK ajusta a: **106** (automáticamente)

#### **Paso 6: Mapeo al Formato AFIP** (`InvoiceMapper`)
```php
$feCAERequest = InvoiceMapper::toFeCAERequest($invoice, $cuit);
```

**Convierte tus datos a formato AFIP:**
```php
[
    'FeCAEReq' => [
        'FeCabReq' => [
            'CantReg' => 1,
            'PtoVta' => 1,
            'CbteTipo' => 1
        ],
        'FeDetReq' => [
            'FECAEDetRequest' => [
                'Concepto' => 1,
                'DocTipo' => 80,
                'DocNro' => 20123456789.0,
                'CbteDesde' => 106,
                'CbteHasta' => 106,
                'CbteFch' => '20240101',
                'ImpTotal' => 121.0,
                'ImpNeto' => 100.0,
                'ImpIVA' => 21.0,
                'Iva' => [
                    'AlicIva' => [
                        [
                            'Id' => 5,        // 21%
                            'BaseImp' => 100.0,
                            'Importe' => 21.0
                        ]
                    ]
                ]
            ]
        ]
    ]
]
```

#### **Paso 7: Llamada a WSFE** (`SoapHelper::call()`)
```php
$soapResponse = SoapHelper::call(
    $client,
    'FECAESolicitar',
    $params,
    maxAttempts: 3
);
```

**¿Qué hace?**
1. Crea cliente SOAP con configuración optimizada
2. Llama método `FECAESolicitar` con:
   - Token y firma de autenticación
   - CUIT
   - Datos del comprobante mapeados
3. **Retry automático** (hasta 3 intentos):
   - Si falla por conexión/timeout → Reintenta con exponential backoff
   - Si falla por error de AFIP → Lanza excepción inmediatamente

**Parámetros enviados:**
```php
[
    'Auth' => [
        'Token' => 'PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4K...',
        'Sign' => 'abc123...',
        'Cuit' => 20457809027.0
    ],
    'FeCAEReq' => [/* datos del comprobante */]
]
```

#### **Paso 8: Procesamiento de Respuesta** (`parseFECAEResponse()`)
```php
$invoiceResponse = $this->parseFECAEResponse($soapResponse, $invoice);
```

**¿Qué hace?**
1. Extrae datos de la respuesta SOAP:
   ```php
   $response->FeCAEResult->FeCabResp->Cuit
   $response->FeCAEResult->FeDetResp->FECAEDetResponse[0]->CAE
   $response->FeCAEResult->FeDetResp->FECAEDetResponse[0]->CAEFchVto
   ```

2. Valida errores:
   - Si `Resultado !== 'A'` → Lanza `AfipAuthorizationException`
   - Si hay observaciones → Las incluye en el DTO

3. Crea y retorna `InvoiceResponse` DTO:
   ```php
   InvoiceResponse {
       cae: "71000001234567",
       caeExpirationDate: "20240201",
       invoiceNumber: 106,
       pointOfSale: 1,
       invoiceType: 1,
       observations: []
   }
   ```

### ¿Qué Retorna?

**Siempre retorna un objeto `InvoiceResponse` (DTO)**, nunca un array:

```php
InvoiceResponse {
    public string $cae;                    // "71000001234567"
    public string $caeExpirationDate;     // "20240201" (formato Ymd)
    public int $invoiceNumber;             // 106
    public int $pointOfSale;               // 1
    public int $invoiceType;               // 1
    public array $observations;            // []
    public array $additionalData;          // {}
}
```

**Propiedades públicas:**
- `$result->cae` → Código de Autorización Electrónico
- `$result->caeExpirationDate` → Fecha de vencimiento (formato `Ymd`)
- `$result->invoiceNumber` → Número de comprobante autorizado
- `$result->pointOfSale` → Punto de venta
- `$result->invoiceType` → Tipo de comprobante
- `$result->observations` → Observaciones de AFIP (si las hay)

**Métodos útiles:**
- `$result->toArray()` → Convierte a array
- `$result->isCaeValid()` → Verifica si el CAE está vigente

---

## 🔍 Otras Funciones que Podrías Usar

### `Afip::getLastAuthorizedInvoice($pointOfSale, $invoiceType)`

**¿Qué hace?**
- Consulta el último comprobante autorizado en AFIP
- Útil para verificar correlatividad manualmente

**Retorna:**
```php
[
    'CbteNro' => 105,
    'CbteFch' => '20240101',
    'PtoVta' => 1,
    'CbteTipo' => 1
]
```

### `Afip::isAuthenticated()`

**¿Qué hace?**
- Verifica si hay un token válido en cache
- No hace llamada a AFIP, solo verifica cache

**Retorna:**
```php
true  // Si hay token válido en cache
false // Si no hay token o expiró
```

---

## ✅ Simplificación de Tu Código

El SDK **siempre retorna un DTO `InvoiceResponse`**, no un array. Puedes simplificar tu código:

### ❌ Código Actual (Innecesariamente Complejo)

```php
$result = Afip::authorizeInvoice($invoiceData);

// Manejo innecesario de array/objeto
$cae = $result['cae'] ?? $result->cae ?? null;
$caeExpirationDate = isset($result['cae_expiration_date']) 
    ? Carbon::createFromFormat('Ymd', $result['cae_expiration_date'])
    : (isset($result->caeExpirationDate) 
        ? Carbon::createFromFormat('Ymd', $result->caeExpirationDate)
        : null);
```

### ✅ Código Simplificado (Recomendado)

```php
use Resguar\AfipSdk\Facades\Afip;
use Resguar\AfipSdk\DTOs\InvoiceResponse;

$result = Afip::authorizeInvoice($invoiceData);

// El SDK SIEMPRE retorna InvoiceResponse DTO
DB::transaction(function () use ($sale, $result) {
    $sale->update([
        'cae' => $result->cae,
        'cae_expiration_date' => Carbon::createFromFormat('Ymd', $result->caeExpirationDate),
        'receipt_number' => str_pad($result->invoiceNumber, 8, '0', STR_PAD_LEFT),
    ]);
});

// Retornar array si necesitas
return [
    'cae' => $result->cae,
    'cae_expiration_date' => $result->caeExpirationDate,
    'invoice_number' => $result->invoiceNumber,
];
```

### ✅ Versión Aún Más Simple (Usando `toArray()`)

```php
$result = Afip::authorizeInvoice($invoiceData);

DB::transaction(function () use ($sale, $result) {
    $sale->update([
        'cae' => $result->cae,
        'cae_expiration_date' => Carbon::createFromFormat('Ymd', $result->caeExpirationDate),
        'receipt_number' => str_pad($result->invoiceNumber, 8, '0', STR_PAD_LEFT),
    ]);
});

// Si necesitas retornar array
return $result->toArray();
```

---

## 📊 Resumen del Flujo Completo

```
1. Tú llamas: Afip::authorizeInvoice($invoiceData)
   ↓
2. SDK valida datos
   ↓
3. SDK verifica cache de token
   ├─ Si existe → Usa token del cache (NO llama a AFIP)
   └─ Si no existe → Genera TRA → Firma → Llama WSAA → Obtiene token → Guarda en cache
   ↓
4. SDK consulta último comprobante autorizado (FECompUltimoAutorizado)
   ↓
5. SDK ajusta número si es necesario (automáticamente)
   ↓
6. SDK mapea datos al formato AFIP
   ↓
7. SDK llama FECAESolicitar (con retry automático)
   ↓
8. SDK procesa respuesta y extrae CAE
   ↓
9. Retorna InvoiceResponse DTO con CAE y datos
```

---

## 🎯 Puntos Clave

1. ✅ **El SDK maneja TODO automáticamente**: autenticación, cache, correlatividad, retry
2. ✅ **Siempre retorna `InvoiceResponse` DTO**, nunca array
3. ✅ **No necesitas manejar tokens manualmente** (se cachean 12 horas)
4. ✅ **No necesitas consultar último comprobante manualmente** (se hace automáticamente)
5. ✅ **El número se ajusta automáticamente** si es necesario
6. ✅ **Retry automático** en errores de conexión

---

## 🔧 Tu Código Optimizado

```php
use Resguar\AfipSdk\Facades\Afip;
use Resguar\AfipSdk\Exceptions\AfipException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

public function authorizeWithAfip(SaleHeader $sale): array
{
    try {
        // Cargar relaciones necesarias
        $sale->load([
            'receiptType',
            'customer.person',
            'items.product.iva',
            'saleIvas.iva',
            'branch'
        ]);

        // Validaciones
        if ($sale->receiptType && $sale->receiptType->afip_code === '016') {
            throw new \Exception('Los presupuestos no se pueden autorizar con AFIP');
        }

        if (!$sale->customer || !$sale->customer->person) {
            throw new \Exception('La venta debe tener un cliente asociado');
        }

        // Preparar datos
        $invoiceData = $this->prepareInvoiceDataForAfip($sale);

        // Autorizar con AFIP (el SDK hace TODO automáticamente)
        $result = Afip::authorizeInvoice($invoiceData);

        // Actualizar venta (el SDK siempre retorna InvoiceResponse DTO)
        DB::transaction(function () use ($sale, $result) {
            $sale->update([
                'cae' => $result->cae,
                'cae_expiration_date' => Carbon::createFromFormat('Ymd', $result->caeExpirationDate),
                'receipt_number' => str_pad($result->invoiceNumber, 8, '0', STR_PAD_LEFT),
            ]);
        });

        Log::info('Venta autorizada con AFIP', [
            'sale_id' => $sale->id,
            'cae' => $result->cae,
            'invoice_number' => $result->invoiceNumber,
        ]);

        // Retornar array (opcional, si necesitas compatibilidad)
        return $result->toArray();

    } catch (AfipException $e) {
        Log::error('Error de AFIP al autorizar venta', [
            'sale_id' => $sale->id,
            'error' => $e->getMessage(),
            'afip_code' => $e->getAfipCode(),
        ]);
        throw new \Exception("Error al autorizar con AFIP: {$e->getMessage()}", 0, $e);
    } catch (\Exception $e) {
        Log::error('Error inesperado al autorizar venta con AFIP', [
            'sale_id' => $sale->id,
            'error' => $e->getMessage(),
        ]);
        throw $e;
    }
}
```

---

## ❓ Preguntas Frecuentes

**P: ¿El SDK siempre retorna un DTO?**
R: Sí, siempre retorna `InvoiceResponse` DTO. Nunca retorna array directamente.

**P: ¿Puedo convertir el DTO a array?**
R: Sí, usa `$result->toArray()`.

**P: ¿Necesito manejar el cache de tokens?**
R: No, el SDK lo maneja automáticamente por 12 horas.

**P: ¿Necesito consultar el último comprobante manualmente?**
R: No, el SDK lo hace automáticamente antes de autorizar.

**P: ¿Qué pasa si el número que envío ya existe?**
R: El SDK automáticamente consulta el último y ajusta al siguiente número disponible.

