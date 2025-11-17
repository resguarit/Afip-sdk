# 🔢 Correlatividad de Facturas: ¿Cómo Funciona?

## ❓ Tu Pregunta

> "¿Para hacer el número de factura, tenés en cuenta la última factura hecha en ARCA? ¿Estaría bien eso?"

## ✅ Respuesta Corta

**No, el SDK NO consulta ARCA. Consulta directamente a AFIP, y eso es lo correcto.**

## 🔍 Explicación Detallada

### ¿Qué es ARCA?

**ARCA** (Administración de Relaciones con Contribuyentes de AFIP) es el sistema de AFIP para:
- ✅ Gestionar certificados digitales
- ✅ Configurar puntos de venta
- ✅ Administrar datos del contribuyente
- ❌ **NO almacena facturas autorizadas**

### ¿Dónde se Almacenan las Facturas?

Las facturas se autorizan y almacenan en **AFIP directamente** a través del Web Service **WSFE** (Web Service de Facturación Electrónica).

### ¿Qué Consulta el SDK?

El SDK consulta **directamente a AFIP** usando el método `FECompUltimoAutorizado` del Web Service WSFE:

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

## ✅ ¿Por Qué Es Correcto Consultar AFIP Directamente?

### 1. **AFIP es la Fuente de Verdad**

- ✅ AFIP es quien **autoriza** las facturas
- ✅ AFIP es quien **almacena** las facturas autorizadas
- ✅ AFIP es quien **valida** la correlatividad

### 2. **ARCA No Tiene Esa Información**

- ❌ ARCA no almacena facturas
- ❌ ARCA solo gestiona certificados y configuraciones
- ❌ Consultar ARCA para números de factura sería incorrecto

### 3. **Garantiza Correlatividad Real**

Al consultar AFIP directamente:
- ✅ Obtienes el **último número realmente autorizado**
- ✅ Evitas duplicados o saltos en la numeración
- ✅ Cumples con los requisitos de AFIP

## 🔄 Flujo Completo

```
1. Tú llamas: Afip::authorizeInvoice($invoiceData)
   ↓
2. SDK consulta AFIP: FECompUltimoAutorizado
   → Obtiene: "Último autorizado: 105"
   ↓
3. SDK ajusta número automáticamente:
   - Si enviaste 100 → Ajusta a 106
   - Si enviaste 105 → Ajusta a 106
   - Si enviaste 106 → Usa 106 (correcto)
   ↓
4. SDK autoriza con AFIP: FECAESolicitar
   → AFIP valida correlatividad
   → AFIP autoriza y retorna CAE
   ↓
5. Factura queda registrada en AFIP
```

## 📊 Ejemplo Práctico

### Escenario: Última Factura Autorizada en AFIP es 105

```php
// Caso 1: Envías número 0 (auto)
$invoiceData = ['invoiceNumber' => 0, ...];
$result = Afip::authorizeInvoice($invoiceData);
// SDK consulta AFIP → Último: 105
// SDK ajusta a: 106
// Resultado: $result->invoiceNumber = 106 ✅

// Caso 2: Envías número 100 (menor al último)
$invoiceData = ['invoiceNumber' => 100, ...];
$result = Afip::authorizeInvoice($invoiceData);
// SDK consulta AFIP → Último: 105
// SDK ajusta a: 106 (porque 100 < 105)
// Resultado: $result->invoiceNumber = 106 ✅

// Caso 3: Envías número 106 (correcto)
$invoiceData = ['invoiceNumber' => 106, ...];
$result = Afip::authorizeInvoice($invoiceData);
// SDK consulta AFIP → Último: 105
// SDK usa: 106 (porque 106 > 105)
// Resultado: $result->invoiceNumber = 106 ✅

// Caso 4: Envías número 110 (muy adelante)
$invoiceData = ['invoiceNumber' => 110, ...];
$result = Afip::authorizeInvoice($invoiceData);
// SDK consulta AFIP → Último: 105
// SDK usa: 110 (porque 110 > 105)
// ⚠️ ADVERTENCIA: Esto puede causar problemas si hay facturas intermedias
```

## ⚠️ Importante: ¿Qué Pasa Si Hay Facturas Intermedias?

Si autorizaste facturas fuera del SDK (por ejemplo, desde otro sistema o manualmente):

```php
// Situación:
// - Última en AFIP: 105
// - Pero en tu sistema local tienes: 110
// - Facturas 106-109 fueron autorizadas por otro sistema

// Si envías 110:
$invoiceData = ['invoiceNumber' => 110, ...];
$result = Afip::authorizeInvoice($invoiceData);
// SDK consulta AFIP → Último: 105
// SDK usa: 110 (porque 110 > 105)
// ⚠️ AFIP puede rechazar si 106-109 ya fueron autorizadas
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
// 1. Consulta último en AFIP
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

| Aspecto | ARCA | AFIP (WSFE) |
|---------|------|-------------|
| **Almacena facturas** | ❌ No | ✅ Sí |
| **Autoriza facturas** | ❌ No | ✅ Sí |
| **Consulta última factura** | ❌ No disponible | ✅ Sí (FECompUltimoAutorizado) |
| **Fuente de verdad** | ❌ No | ✅ Sí |

**Conclusión:** El SDK consulta **AFIP directamente** (no ARCA), y eso es **correcto y necesario** para garantizar la correlatividad real de las facturas.

## ❓ Preguntas Frecuentes

**P: ¿Por qué no consulta mi base de datos local?**
R: Porque tu base de datos puede no estar sincronizada con AFIP. Si autorizaste facturas desde otro sistema o manualmente, tu BD local puede tener números incorrectos.

**P: ¿Qué pasa si tengo facturas en mi BD que no están en AFIP?**
R: El SDK siempre usa el último autorizado en AFIP. Si hay facturas en tu BD que no fueron autorizadas, no se consideran.

**P: ¿Puedo confiar en que el SDK ajuste automáticamente?**
R: Sí, es la forma más segura. Siempre usa `invoiceNumber => 0` y deja que el SDK ajuste.

**P: ¿El SDK consulta ARCA en algún momento?**
R: No, ARCA solo se usa para gestionar certificados. El SDK no consulta ARCA para números de factura.

---

**¿Tienes más dudas?** Revisa [EXPLICACION_FUNCIONES_SDK.md](EXPLICACION_FUNCIONES_SDK.md) para ver el flujo completo paso a paso.

