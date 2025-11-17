# 🔢 Correlatividad de Facturas: ¿Cómo Funciona?

## ❓ Tu Pregunta

> "¿Para hacer el número de factura, tenés en cuenta la última factura hecha en ARCA? ¿Estaría bien eso?"

## ✅ Respuesta Corta

**El SDK consulta el Web Service WSFE de AFIP (no el portal ARCA), y eso es lo correcto.**

## 🔍 Explicación Detallada

### ARCA y AFIP: Aclaración

**ARCA** (Administración de Relaciones con Contribuyentes de AFIP) es parte de AFIP, pero es el **portal web administrativo** donde gestionas:
- ✅ Certificados digitales
- ✅ Configuración de puntos de venta
- ✅ Datos del contribuyente
- ❌ **NO almacena facturas autorizadas**

**WSFE** (Web Service de Facturación Electrónica) también es parte de AFIP, pero es el **Web Service** donde:
- ✅ Se autorizan las facturas
- ✅ Se almacenan las facturas autorizadas
- ✅ Se consultan los últimos comprobantes autorizados

### ¿Qué Consulta el SDK?

El SDK consulta el **Web Service WSFE de AFIP** usando el método `FECompUltimoAutorizado`:

**Importante:**
- ❌ **ARCA NO tiene un Web Service** para consultar facturas
- ✅ **WSFE es el Web Service correcto** para facturación electrónica
- ✅ El método `FECompUltimoAutorizado` es parte de **WSFE**, no de ARCA

```php
// El SDK hace esto automáticamente:
$lastInvoice = $wsfeClient->FECompUltimoAutorizado([
    'Auth' => ['Token' => $token, 'Sign' => $signature, 'Cuit' => $cuit],
    'PtoVta' => 1,
    'CbteTipo' => 1
]);

// Retorna el último comprobante autorizado en AFIP:
// [
//     'CbteNro' => 105,        // Último número autorizado en AFIP
//     'CbteFch' => '20240101',
//     'PtoVta' => 1,
//     'CbteTipo' => 1
// ]
```

## ✅ ¿Por Qué Es Correcto Consultar WSFE?

### 1. **WSFE es la Fuente de Verdad para Facturas**

- ✅ WSFE es quien **autoriza** las facturas
- ✅ WSFE es quien **almacena** las facturas autorizadas
- ✅ WSFE es quien **valida** la correlatividad

### 2. **ARCA No Tiene Esa Información**

- ❌ ARCA (portal administrativo) no almacena facturas
- ❌ ARCA solo gestiona certificados y configuraciones
- ❌ Consultar ARCA para números de factura sería incorrecto

### 3. **Garantiza Correlatividad Real**

Al consultar WSFE (Web Service de AFIP):
- ✅ Obtienes el **último número realmente autorizado**
- ✅ Evitas duplicados o saltos en la numeración
- ✅ Cumples con los requisitos de AFIP

## 🔄 Flujo Completo

```
1. Tú llamas: Afip::authorizeInvoice($invoiceData)
   ↓
2. SDK consulta WSFE (Web Service de AFIP): FECompUltimoAutorizado
   → Obtiene: "Último autorizado: 105"
   ↓
3. SDK ajusta número automáticamente:
   - Si enviaste 100 → Ajusta a 106
   - Si enviaste 105 → Ajusta a 106
   - Si enviaste 106 → Usa 106 (correcto)
   ↓
4. SDK autoriza con WSFE: FECAESolicitar
   → WSFE valida correlatividad
   → WSFE autoriza y retorna CAE
   ↓
5. Factura queda registrada en AFIP (a través de WSFE)
```

## 📊 Ejemplo Práctico

### Escenario: Última Factura Autorizada en AFIP es 105

```php
// Caso 1: Envías número 0 (auto)
$invoiceData = ['invoiceNumber' => 0, ...];
$result = Afip::authorizeInvoice($invoiceData);
// SDK consulta WSFE → Último: 105
// SDK ajusta a: 106
// Resultado: $result->invoiceNumber = 106 ✅

// Caso 2: Envías número 100 (menor al último)
$invoiceData = ['invoiceNumber' => 100, ...];
$result = Afip::authorizeInvoice($invoiceData);
// SDK consulta WSFE → Último: 105
// SDK ajusta a: 106 (porque 100 < 105)
// Resultado: $result->invoiceNumber = 106 ✅

// Caso 3: Envías número 106 (correcto)
$invoiceData = ['invoiceNumber' => 106, ...];
$result = Afip::authorizeInvoice($invoiceData);
// SDK consulta WSFE → Último: 105
// SDK usa: 106 (porque 106 > 105)
// Resultado: $result->invoiceNumber = 106 ✅

// Caso 4: Envías número 110 (muy adelante)
$invoiceData = ['invoiceNumber' => 110, ...];
$result = Afip::authorizeInvoice($invoiceData);
// SDK consulta WSFE → Último: 105
// SDK usa: 110 (porque 110 > 105)
// ⚠️ ADVERTENCIA: Esto puede causar problemas si hay facturas intermedias
```

## ⚠️ Importante: ¿Qué Pasa Si Hay Facturas Intermedias?

Si autorizaste facturas fuera del SDK (por ejemplo, desde otro sistema o manualmente):

```php
// Situación:
// - Última en WSFE (AFIP): 105
// - Pero en tu sistema local tienes: 110
// - Facturas 106-109 fueron autorizadas por otro sistema

// Si envías 110:
$invoiceData = ['invoiceNumber' => 110, ...];
$result = Afip::authorizeInvoice($invoiceData);
// SDK consulta WSFE → Último: 105
// SDK usa: 110 (porque 110 > 105)
// ⚠️ WSFE puede rechazar si 106-109 ya fueron autorizadas
```

**Solución:** Siempre deja que el SDK ajuste automáticamente usando `invoiceNumber => 0`.

## 🎯 Mejores Prácticas

### ✅ Recomendado

```php
// Siempre usa 0 para que el SDK ajuste automáticamente
$invoiceData = [
    'invoiceNumber' => 0,  // ← Auto (recomendado)
    // ... otros datos
];

$result = Afip::authorizeInvoice($invoiceData);
// El SDK:
// 1. Consulta último en WSFE (Web Service de AFIP)
// 2. Ajusta al siguiente número
// 3. Autoriza
```

### ⚠️ Usar con Precaución

```php
// Solo si estás 100% seguro del número
$invoiceData = [
    'invoiceNumber' => 106,  // ← Solo si sabes que es correcto
    // ... otros datos
];
```

## 🔍 Verificar Último Comprobante Manualmente

Si quieres verificar antes de autorizar:

```php
use Resguar\AfipSdk\Facades\Afip;

// Consultar último comprobante autorizado
$lastInvoice = Afip::getLastAuthorizedInvoice(
    pointOfSale: 1,
    invoiceType: 1,
    cuit: '20123456789'  // Opcional
);

echo "Último autorizado: " . $lastInvoice['CbteNro'] . "\n";
echo "Fecha: " . $lastInvoice['CbteFch'] . "\n";
// Retorna: ['CbteNro' => 105, 'CbteFch' => '20240101', ...]
```

## 📝 Resumen

| Aspecto | ARCA (Portal Web) | WSFE (Web Service) |
|---------|-------------------|---------------------|
| **Parte de AFIP** | ✅ Sí | ✅ Sí |
| **Función** | Portal administrativo | Web Service de facturación |
| **Almacena facturas** | ❌ No | ✅ Sí |
| **Autoriza facturas** | ❌ No | ✅ Sí |
| **Consulta última factura** | ❌ No disponible | ✅ Sí (FECompUltimoAutorizado) |
| **Fuente de verdad para facturas** | ❌ No | ✅ Sí |

**Conclusión:** 
- ARCA y WSFE son **ambos parte de AFIP**, pero tienen funciones diferentes
- ARCA es el **portal web administrativo** (certificados, configuraciones)
- WSFE es el **Web Service de facturación** (autorización de facturas)
- El SDK consulta **WSFE** (no el portal ARCA), y eso es **correcto y necesario** para garantizar la correlatividad real de las facturas

## ❓ Preguntas Frecuentes

**P: ¿Por qué no consulta mi base de datos local?**
R: Porque tu base de datos puede no estar sincronizada con AFIP. Si autorizaste facturas desde otro sistema o manualmente, tu BD local puede tener números incorrectos.

**P: ¿Qué pasa si tengo facturas en mi BD que no están en AFIP?**
R: El SDK siempre usa el último autorizado en AFIP. Si hay facturas en tu BD que no fueron autorizadas, no se consideran.

**P: ¿Puedo confiar en que el SDK ajuste automáticamente?**
R: Sí, es la forma más segura. Siempre usa `invoiceNumber => 0` y deja que el SDK ajuste.

**P: ¿El SDK consulta ARCA en algún momento?**
R: No, ARCA es el portal web administrativo de AFIP (para gestionar certificados y configuraciones). El SDK consulta WSFE (Web Service de AFIP) para números de factura, no el portal ARCA.

**P: ¿ARCA y AFIP son lo mismo?**
R: ARCA es parte de AFIP. ARCA es el portal web administrativo, mientras que WSFE es el Web Service de facturación. Ambos son sistemas de AFIP pero con funciones diferentes.

**P: ¿No debería usar un Web Service de ARCA para ver la última factura?**
R: No, ARCA **no tiene un Web Service** para consultar facturas. ARCA es solo un portal web administrativo. El Web Service correcto es **WSFE** (Web Service de Facturación Electrónica), que es parte de AFIP y tiene el método `FECompUltimoAutorizado` para consultar el último comprobante autorizado. El SDK usa WSFE correctamente.

---

**¿Tienes más dudas?** Revisa [EXPLICACION_FUNCIONES_SDK.md](EXPLICACION_FUNCIONES_SDK.md) para ver el flujo completo paso a paso.

