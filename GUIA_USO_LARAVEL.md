# Guía Completa: Usar el SDK en Proyectos Laravel

Esta guía te muestra paso a paso cómo instalar, configurar y usar el SDK de AFIP en tus proyectos Laravel.

## 📦 Opción 1: Instalar desde Repositorio Local (Desarrollo)

Si estás desarrollando el SDK y quieres probarlo en un proyecto:

### Paso 1: Agregar al `composer.json` de tu Proyecto

Edita `composer.json` de tu proyecto Laravel:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../afip-sdk-resguar"
        }
    ],
    "require": {
        "resguar/afip-sdk": "@dev"
    }
}
```

**Ajusta la ruta** según donde esté tu SDK:
- Si está en la misma carpeta padre: `../afip-sdk-resguar`
- Si está en otra ubicación: `/ruta/completa/a/afip-sdk-resguar`

### Paso 2: Instalar

```bash
cd /ruta/a/tu/proyecto-laravel
composer update resguar/afip-sdk
```

## 📦 Opción 2: Instalar desde GitHub (Producción)

### Paso 1: Subir SDK a GitHub

Si aún no lo subiste:
```bash
cd /ruta/a/afip-sdk-resguar
git remote add origin https://github.com/resguarit/Afip-sdk.git
git push -u origin main
```

### Paso 2: Agregar al `composer.json` de tu Proyecto

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/resguarit/Afip-sdk.git"
        }
    ],
    "require": {
        "resguar/afip-sdk": "dev-main"
    }
}
```

### Paso 3: Instalar

```bash
cd /ruta/a/tu/proyecto-laravel
composer require resguar/afip-sdk:dev-main
```

## 📦 Opción 3: Instalar desde Packagist (Recomendado para Producción)

Si publicaste el paquete en Packagist:

```bash
composer require resguar/afip-sdk
```

## ⚙️ Configuración Inicial en tu Proyecto Laravel

### Paso 1: Publicar Configuración

```bash
php artisan vendor:publish --tag=afip-config
```

Esto crea `config/afip.php` en tu proyecto.

### Paso 2: Configurar Variables de Entorno

Edita `.env`:

```env
# ============================================
# CONFIGURACIÓN AFIP
# ============================================

# Entorno: 'testing' para homologación, 'production' para producción
AFIP_ENVIRONMENT=testing

# Tu CUIT (sin guiones)
AFIP_CUIT=20457809027

# Ruta donde están los certificados
AFIP_CERTIFICATES_PATH=storage/certificates

# Nombres de los archivos
AFIP_CERTIFICATE_KEY=clave_privada.key
AFIP_CERTIFICATE_CRT=certificado.crt

# Contraseña de la clave privada (si tiene)
AFIP_CERTIFICATE_PASSWORD=

# Cache (opcional)
AFIP_CACHE_ENABLED=true
AFIP_CACHE_TTL=43200

# Logging (recomendado)
AFIP_LOGGING_ENABLED=true
AFIP_LOGGING_CHANNEL=daily
AFIP_LOGGING_LEVEL=debug
```

### Paso 3: Colocar Certificados

```bash
# Crear directorio
mkdir -p storage/certificates

# Copiar certificados
cp /ruta/a/certificado.crt storage/certificates/
cp /ruta/a/clave_privada.key storage/certificates/

# Permisos seguros
chmod 600 storage/certificates/clave_privada.key
chmod 644 storage/certificates/certificado.crt
```

### Paso 4: Limpiar Cache

```bash
php artisan config:clear
php artisan cache:clear
```

## 🧪 Probar que Funciona

### Test 1: Verificar Autenticación

Crea una ruta de prueba en `routes/web.php`:

```php
use Resguar\AfipSdk\Facades\Afip;
use Illuminate\Support\Facades\Route;

Route::get('/test-afip', function () {
    try {
        // Probar autenticación
        $wsaa = app(\Resguar\AfipSdk\Services\WsaaService::class);
        $token = $wsaa->getToken('wsfe');
        
        return response()->json([
            'success' => true,
            'message' => 'Autenticación exitosa',
            'token_preview' => substr($token->token, 0, 20) . '...',
            'expires_at' => $token->expirationDate->format('Y-m-d H:i:s'),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});
```

Visita: `http://tu-app.test/test-afip`

## 💼 Uso en tu Aplicación

### Ejemplo 1: Facturar desde un Controlador

```php
<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use Illuminate\Http\Request;
use Resguar\AfipSdk\Facades\Afip;
use Resguar\AfipSdk\Exceptions\AfipAuthorizationException;

class VentaController extends Controller
{
    public function facturar(Venta $venta)
    {
        try {
            // Preparar datos de la factura
            $invoiceData = [
                'pointOfSale' => 1,
                'invoiceType' => 1, // 1 = Factura A
                'invoiceNumber' => 0, // 0 = auto (se ajusta automáticamente)
                'date' => $venta->fecha->format('Ymd'),
                'customerCuit' => $venta->cliente->cuit,
                'customerDocumentType' => 80, // CUIT
                'customerDocumentNumber' => $venta->cliente->cuit,
                'concept' => 1, // Productos
                'items' => $venta->items->map(function ($item) {
                    return [
                        'code' => $item->codigo,
                        'description' => $item->descripcion,
                        'quantity' => $item->cantidad,
                        'unitPrice' => $item->precio_unitario,
                        'taxRate' => $item->iva_porcentaje ?? 21,
                    ];
                })->toArray(),
                'total' => $venta->total,
                'totalNetoGravado' => $venta->subtotal,
                'totalIva' => $venta->iva,
                'totalNetoNoGravado' => 0,
                'totalExento' => 0,
                'totalTributos' => 0,
            ];

            // Autorizar factura (el SDK hace todo automáticamente)
            $result = Afip::authorizeInvoice($invoiceData);

            // Guardar CAE en la venta
            $venta->update([
                'cae' => $result->cae,
                'cae_vto' => $result->caeExpirationDate,
                'numero_factura' => $result->invoiceNumber,
                'estado' => 'autorizada',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Factura autorizada exitosamente',
                'data' => [
                    'cae' => $result->cae,
                    'numero' => $result->invoiceNumber,
                    'vencimiento_cae' => $result->caeExpirationDate,
                ],
            ]);

        } catch (AfipAuthorizationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de AFIP: ' . $e->getMessage(),
                'afip_code' => $e->getAfipCode(),
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Error al facturar', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al procesar factura: ' . $e->getMessage(),
            ], 500);
        }
    }
}
```

### Ejemplo 2: Usar con Modelo Eloquent (Cuando InvoiceBuilder esté completo)

```php
// El SDK automáticamente extrae datos del modelo
$result = Afip::authorizeInvoice($venta); // Pasa el modelo directamente
```

### Ejemplo 3: Consultar Último Comprobante

```php
use Resguar\AfipSdk\Facades\Afip;

// Consultar último comprobante autorizado
$lastInvoice = Afip::getLastAuthorizedInvoice(
    pointOfSale: 1,
    invoiceType: 1
);

// Retorna:
// [
//     'CbteNro' => 100,
//     'CbteFch' => '20240101',
//     'PtoVta' => 1,
//     'CbteTipo' => 1,
// ]
```

### Ejemplo 4: Usar con Dependency Injection

```php
<?php

namespace App\Services;

use Resguar\AfipSdk\Contracts\AfipServiceInterface;
use Resguar\AfipSdk\DTOs\InvoiceResponse;

class FacturacionService
{
    public function __construct(
        private AfipServiceInterface $afipService
    ) {}

    public function facturar(array $datos): InvoiceResponse
    {
        return $this->afipService->authorizeInvoice($datos);
    }
}
```

## 🔄 Usar en Múltiples Proyectos

### Método 1: Repositorio Local (Desarrollo)

Si trabajas en varios proyectos locales:

```json
// En cada proyecto, agrega al composer.json:
{
    "repositories": [
        {
            "type": "path",
            "url": "/ruta/comun/afip-sdk-resguar"
        }
    ],
    "require": {
        "resguar/afip-sdk": "@dev"
    }
}
```

**Ventajas:**
- Cambios en el SDK se reflejan inmediatamente
- Útil para desarrollo

### Método 2: GitHub (Recomendado)

1. **Sube el SDK a GitHub** (una sola vez)
2. **En cada proyecto**, agrega:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/resguarit/Afip-sdk.git"
        }
    ],
    "require": {
        "resguar/afip-sdk": "dev-main"
    }
}
```

3. **Instala en cada proyecto:**
```bash
composer require resguar/afip-sdk:dev-main
```

**Ventajas:**
- Fácil de compartir
- Versionado
- Actualizaciones simples (`composer update`)

### Método 3: Packagist (Producción)

1. **Publica en Packagist** (una sola vez)
2. **En cada proyecto:**
```bash
composer require resguar/afip-sdk
```

**Ventajas:**
- Más profesional
- Instalación más simple
- Disponible públicamente (o privado con token)

## 📝 Estructura de un Proyecto con el SDK

```
tu-proyecto-laravel/
├── .env                    ← Configuración AFIP aquí
├── composer.json           ← SDK agregado aquí
├── config/
│   └── afip.php           ← Config publicada (opcional)
├── storage/
│   └── certificates/      ← Certificados aquí
│       ├── certificado.crt
│       └── clave_privada.key
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── VentaController.php  ← Usa Afip::authorizeInvoice()
│   └── Models/
│       └── Venta.php
└── routes/
    └── web.php            ← Rutas que usan el SDK
```

## 🎯 Flujo Completo de Uso

### 1. Instalar SDK
```bash
composer require resguar/afip-sdk
```

### 2. Configurar
```bash
php artisan vendor:publish --tag=afip-config
# Editar .env
# Colocar certificados
```

### 3. Usar en Código
```php
use Resguar\AfipSdk\Facades\Afip;

$result = Afip::authorizeInvoice($datosFactura);
```

### 4. Procesar Resultado
```php
$venta->cae = $result->cae;
$venta->numero_factura = $result->invoiceNumber;
$venta->save();
```

## 🔍 Verificar Instalación

```bash
# Verificar que el paquete está instalado
composer show resguar/afip-sdk

# Verificar configuración
php artisan tinker
```

```php
// En Tinker
config('afip.cuit')
config('afip.environment')
config('afip.certificates.path')

// Probar autenticación
use Resguar\AfipSdk\Facades\Afip;
Afip::isAuthenticated()
```

## 📚 Recursos Adicionales

- [Guía de Pruebas](GUIA_PRUEBAS.md) - Cómo probar el SDK
- [Checklist Pre-Producción](CHECKLIST_PRE_PRODUCCION.md) - Qué necesitas antes de probar
- [Configurar Certificados](CONFIGURAR_CERTIFICADOS.md) - Guía de certificados
- [Ubicar Certificados](UBICAR_CERTIFICADOS.md) - Dónde colocar archivos

## 🚨 Troubleshooting

### Error: "Package not found"

```bash
# Verificar que el repositorio está configurado
composer config repositories

# Actualizar
composer update resguar/afip-sdk
```

### Error: "Class not found"

```bash
# Regenerar autoload
composer dump-autoload
```

### Error: "Service provider not found"

```bash
# Verificar que está en composer.json
composer show resguar/afip-sdk

# Limpiar cache
php artisan config:clear
php artisan cache:clear
```

## ✅ Checklist de Instalación

- [ ] SDK instalado via Composer
- [ ] Configuración publicada (`php artisan vendor:publish --tag=afip-config`)
- [ ] Variables de entorno configuradas en `.env`
- [ ] Certificados colocados en `storage/certificates/`
- [ ] Permisos correctos en certificados
- [ ] Cache limpiado (`php artisan config:clear`)
- [ ] Autenticación probada y funcionando

¡Listo para facturar! 🚀

