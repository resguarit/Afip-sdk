# Soporte para Múltiples CUITs

El SDK ahora soporta múltiples CUITs simultáneamente, permitiendo trabajar con diferentes contribuyentes en la misma aplicación.

## 🎯 Características

- ✅ **Parámetro CUIT opcional** en todos los métodos principales
- ✅ **Cache separado por CUIT**: Cada CUIT tiene su propio cache de tokens
- ✅ **Validación automática**: El CUIT se valida (11 dígitos) y limpia automáticamente
- ✅ **Backward compatible**: Si no se proporciona CUIT, usa `config('afip.cuit')`
- ✅ **Logging mejorado**: El CUIT se incluye en todos los logs para debugging

## 📝 Cambios en la API

### Métodos Modificados

Todos los métodos principales ahora aceptan un parámetro opcional `?string $cuit = null`:

```php
// Antes
Afip::authorizeInvoice($invoiceData);
Afip::getLastAuthorizedInvoice(1, 1);
Afip::isAuthenticated();

// Ahora (backward compatible)
Afip::authorizeInvoice($invoiceData); // Usa config('afip.cuit')
Afip::authorizeInvoice($invoiceData, '20123456789'); // Usa CUIT específico

Afip::getLastAuthorizedInvoice(1, 1); // Usa config('afip.cuit')
Afip::getLastAuthorizedInvoice(1, 1, '20123456789'); // Usa CUIT específico

Afip::isAuthenticated(); // Usa config('afip.cuit')
Afip::isAuthenticated('20123456789'); // Usa CUIT específico
```

## 🔧 Uso

### Ejemplo 1: Autorizar Factura con CUIT Específico

```php
use Resguar\AfipSdk\Facades\Afip;

// Usar CUIT de configuración (comportamiento anterior)
$result = Afip::authorizeInvoice($invoiceData);

// Usar CUIT específico
$result = Afip::authorizeInvoice($invoiceData, '20123456789');

// CUIT con guiones (se limpia automáticamente)
$result = Afip::authorizeInvoice($invoiceData, '20-12345678-9');
```

### Ejemplo 2: Consultar Último Comprobante por CUIT

```php
// Último comprobante para CUIT específico
$lastInvoice = Afip::getLastAuthorizedInvoice(
    pointOfSale: 1,
    invoiceType: 1,
    cuit: '20123456789'
);
```

### Ejemplo 3: Verificar Autenticación por CUIT

```php
// Verificar si hay token válido para un CUIT específico
if (Afip::isAuthenticated('20123456789')) {
    // Token válido en cache para este CUIT
}
```

### Ejemplo 4: Inyección de Dependencias

```php
use Resguar\AfipSdk\Contracts\AfipServiceInterface;

class InvoiceController
{
    public function __construct(
        private AfipServiceInterface $afipService
    ) {}

    public function authorize($invoiceData, $cuit = null)
    {
        // Si $cuit es null, usa config('afip.cuit')
        return $this->afipService->authorizeInvoice($invoiceData, $cuit);
    }
}
```

## 🔐 Cache de Tokens

### Clave de Cache

La clave de cache ahora incluye el CUIT:

```
Formato: {prefix}{service}_{cuit}_{environment}

Ejemplo:
- afip_token_wsfe_20123456789_testing
- afip_token_wsfe_20457809027_production
```

### Comportamiento

- **Cada CUIT tiene su propio cache**: Los tokens no se comparten entre CUITs
- **Cache independiente**: Un token para CUIT A no afecta a CUIT B
- **TTL por CUIT**: Cada CUIT mantiene su propio tiempo de expiración

### Limpiar Cache por CUIT

```php
use Resguar\AfipSdk\Services\WsaaService;

$wsaaService = app(WsaaService::class);

// Limpiar cache para un CUIT específico y servicio
$wsaaService->clearTokenCache('wsfe', '20123456789');

// Limpiar cache para todos los servicios de un CUIT
// (requiere llamar para cada servicio)
$wsaaService->clearTokenCache('wsfe', '20123456789');
$wsaaService->clearTokenCache('wsmtxca', '20123456789');
```

## ✅ Validación y Limpieza

El SDK automáticamente:

1. **Limpia el CUIT**: Remueve guiones, espacios y caracteres no numéricos
2. **Valida formato**: Verifica que tenga exactamente 11 dígitos
3. **Usa fallback**: Si no se proporciona, usa `config('afip.cuit')`

```php
// Todos estos son equivalentes (se limpian a '20123456789')
Afip::authorizeInvoice($data, '20123456789');
Afip::authorizeInvoice($data, '20-12345678-9');
Afip::authorizeInvoice($data, '20 12345678 9');
```

### Errores de Validación

Si el CUIT no es válido, se lanza una excepción:

```php
try {
    Afip::authorizeInvoice($data, '123'); // Error: debe tener 11 dígitos
} catch (\Resguar\AfipSdk\Exceptions\AfipException $e) {
    echo $e->getMessage(); // "El CUIT debe tener 11 dígitos..."
}
```

## 📊 Logging

El CUIT se incluye en todos los logs para facilitar el debugging:

```php
// Ejemplo de log
[AFIP SDK - WSFE] Iniciando autorización de comprobante
{
    "point_of_sale": 1,
    "invoice_type": 1,
    "cuit": "20123456789"  // ← CUIT incluido
}

[AFIP SDK] Token obtenido del cache para servicio: wsfe, CUIT: 20123456789
```

## 🔄 Backward Compatibility

**El SDK es 100% backward compatible**. Si no proporcionas el CUIT, funciona exactamente como antes:

```php
// Código existente sigue funcionando sin cambios
$result = Afip::authorizeInvoice($invoiceData); // ✅ Funciona
$last = Afip::getLastAuthorizedInvoice(1, 1); // ✅ Funciona
$auth = Afip::isAuthenticated(); // ✅ Funciona
```

## 🎯 Casos de Uso

### Caso 1: Multi-tenant con Diferentes CUITs

```php
class MultiTenantInvoiceService
{
    public function authorizeInvoiceForTenant($invoiceData, $tenant)
    {
        // Cada tenant tiene su propio CUIT
        $cuit = $tenant->afip_cuit;
        
        return Afip::authorizeInvoice($invoiceData, $cuit);
    }
}
```

### Caso 2: Sistema con Múltiples Contribuyentes

```php
class InvoiceService
{
    public function authorize($invoiceData, $contribuyente)
    {
        // Usar CUIT del contribuyente específico
        return Afip::authorizeInvoice(
            $invoiceData,
            $contribuyente->cuit
        );
    }
}
```

### Caso 3: Migración Gradual

```php
// Fase 1: Usar config (comportamiento actual)
$result = Afip::authorizeInvoice($data);

// Fase 2: Migrar a CUIT por parámetro
$result = Afip::authorizeInvoice($data, $sale->contribuyente->cuit);
```

## ⚠️ Consideraciones Importantes

1. **Certificados**: Cada CUIT debe tener sus propios certificados digitales configurados
2. **Configuración**: El CUIT de `config('afip.cuit')` se usa como fallback
3. **Cache**: Los tokens se cachean por CUIT, no se comparten
4. **Validación**: El CUIT se valida antes de usarlo (11 dígitos)

## 📚 Archivos Modificados

- `src/Services/WsaaService.php` - Acepta CUIT, cache por CUIT
- `src/Services/WsfeService.php` - Acepta CUIT, pasa a WSAA
- `src/Services/AfipService.php` - Acepta CUIT, pasa a WSFE
- `src/Facades/Afip.php` - DocBlocks actualizados
- `src/Contracts/AfipServiceInterface.php` - Interfaz actualizada
- `src/Helpers/ValidatorHelper.php` - Métodos `cleanCuit()` y `validateAndCleanCuit()`

## 🧪 Testing

```php
// Test con CUIT específico
$result = Afip::authorizeInvoice($testData, '20123456789');

// Test con CUIT de config (backward compatibility)
$result = Afip::authorizeInvoice($testData);

// Verificar que cada CUIT tiene su propio cache
Afip::isAuthenticated('20123456789'); // true
Afip::isAuthenticated('20457809027'); // false (diferente CUIT)
```

## 🔍 Debugging

Para ver qué CUIT se está usando:

```php
// Verificar logs
tail -f storage/logs/laravel.log | grep "CUIT"

// Verificar cache
$cache = app('cache.store');
$keys = $cache->get('*afip_token_*'); // Ver todas las claves de cache
```

---

**¿Preguntas?** Revisa los logs o consulta la documentación del SDK.

