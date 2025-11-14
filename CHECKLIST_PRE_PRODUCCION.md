# Checklist: Qué Falta para Probar en Desarrollo

Después de completar toda la configuración en ARCA/AFIP, esto es lo que necesitas para empezar a probar el SDK.

## ✅ Lo que Ya Tienes (de ARCA/AFIP)

- [x] CUIT registrado en AFIP
- [x] Certificados digitales generados
- [x] Punto de venta habilitado
- [x] Servicio WSFE habilitado
- [x] Acceso al entorno de homologación

## 📋 Lo que Falta para Probar

### 1. Descargar Certificados de Homologación

**Desde el portal de AFIP:**

1. Ve a: "Administración de Certificados Digitales" en ARCA
2. Descarga tu certificado (botón "Descargar" en la tabla)
3. **IMPORTANTE**: Necesitas DOS archivos:
   - ✅ `certificado.crt` (o `.pem`) - **Descargado de AFIP** (ya lo tienes)
   - ⚠️ `clave_privada.key` (o `.pem`) - **Generado por ti** (NO se descarga)

**⚠️ CRÍTICO:**
- El certificado público se descarga de AFIP ✅
- La clave privada la generaste TÚ cuando creaste el certificado
- **Sin la clave privada, NO puedes usar el SDK**

**Ver guía completa:** [CONFIGURAR_CERTIFICADOS.md](CONFIGURAR_CERTIFICADOS.md)

### 2. Instalar el SDK en tu Proyecto Laravel

```bash
# Si el SDK está en un repositorio local
composer require resguar/afip-sdk:dev-main

# O si está en Packagist
composer require resguar/afip-sdk
```

### 3. Publicar Configuración

```bash
# Publicar archivo de configuración
php artisan vendor:publish --tag=afip-config

# Publicar migraciones (opcional, si quieres usar las tablas)
php artisan vendor:publish --tag=afip-migrations
php artisan migrate
```

### 4. Configurar Variables de Entorno

Edita tu archivo `.env`:

```env
# ============================================
# CONFIGURACIÓN AFIP - HOMOLOGACIÓN
# ============================================

# Entorno: 'testing' para homologación, 'production' para producción
AFIP_ENVIRONMENT=testing

# Tu CUIT (sin guiones)
AFIP_CUIT=20123456789

# Ruta donde guardaste los certificados
# Opción 1: Ruta absoluta
AFIP_CERTIFICATES_PATH=/ruta/completa/a/certificados

# Opción 2: Ruta relativa al storage
# AFIP_CERTIFICATES_PATH=storage/certificates

# Nombres de los archivos de certificado
AFIP_CERTIFICATE_KEY=clave_privada.key
AFIP_CERTIFICATE_CRT=certificado.crt

# Contraseña de la clave privada (si tiene)
# Si no tiene contraseña, déjalo vacío
AFIP_CERTIFICATE_PASSWORD=

# Cache (opcional, por defecto habilitado)
AFIP_CACHE_ENABLED=true
AFIP_CACHE_TTL=43200

# Logging (opcional, recomendado para desarrollo)
AFIP_LOGGING_ENABLED=true
AFIP_LOGGING_CHANNEL=daily
AFIP_LOGGING_LEVEL=debug
```

### 5. Organizar Certificados

Crea la estructura de directorios:

```bash
# Opción 1: En storage (recomendado)
mkdir -p storage/certificates
chmod 700 storage/certificates

# Copiar certificados
cp /ruta/descargados/certificado.crt storage/certificates/
cp /ruta/descargados/clave_privada.key storage/certificates/

# Permisos seguros
chmod 600 storage/certificates/clave_privada.key
chmod 644 storage/certificates/certificado.crt
```

**Estructura final:**
```
storage/
└── certificates/
    ├── certificado.crt
    └── clave_privada.key
```

### 6. Verificar Configuración

Crea un comando de prueba: `app/Console/Commands/TestAfipConfig.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Resguar\AfipSdk\Facades\Afip;

class TestAfipConfig extends Command
{
    protected $signature = 'afip:test-config';
    protected $description = 'Verifica la configuración de AFIP';

    public function handle()
    {
        $this->info('🔍 Verificando configuración de AFIP...');
        $this->newLine();

        // Verificar variables de entorno
        $this->info('📋 Variables de entorno:');
        $this->line('   Entorno: ' . config('afip.environment'));
        $this->line('   CUIT: ' . config('afip.cuit'));
        $this->line('   Ruta certificados: ' . config('afip.certificates.path'));
        $this->newLine();

        // Verificar archivos
        $certPath = config('afip.certificates.path');
        $keyFile = $certPath . '/' . config('afip.certificates.key');
        $crtFile = $certPath . '/' . config('afip.certificates.crt');

        $this->info('📁 Archivos de certificados:');
        $this->line('   Clave privada: ' . (file_exists($keyFile) ? '✅ Existe' : '❌ No encontrado'));
        $this->line('   Certificado: ' . (file_exists($crtFile) ? '✅ Existe' : '❌ No encontrado'));
        $this->newLine();

        // Probar autenticación
        $this->info('🔐 Probando autenticación...');
        try {
            $isAuth = Afip::isAuthenticated();
            $this->line('   Estado: ' . ($isAuth ? '✅ Autenticado' : '⚠️  No autenticado'));
            
            if (!$isAuth) {
                $this->line('   Intentando obtener token...');
                $wsaaService = app(\Resguar\AfipSdk\Services\WsaaService::class);
                $token = $wsaaService->getToken('wsfe');
                $this->line('   ✅ Token obtenido exitosamente!');
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Error: ' . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info('✅ Configuración verificada correctamente!');
        return 0;
    }
}
```

Ejecuta:
```bash
php artisan afip:test-config
```

### 7. Probar Autenticación (Primer Paso)

Crea un test simple: `routes/web.php` o `routes/api.php`

```php
use Resguar\AfipSdk\Facades\Afip;
use Illuminate\Support\Facades\Route;

Route::get('/test-afip-auth', function () {
    try {
        // Probar autenticación
        $isAuth = Afip::isAuthenticated();
        
        if (!$isAuth) {
            $wsaaService = app(\Resguar\AfipSdk\Services\WsaaService::class);
            $tokenResponse = $wsaaService->getToken('wsfe');
            
            return response()->json([
                'success' => true,
                'message' => 'Autenticación exitosa',
                'token' => substr($tokenResponse->token, 0, 20) . '...',
                'expires_at' => $tokenResponse->expirationDate->format('Y-m-d H:i:s'),
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Ya autenticado',
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'code' => method_exists($e, 'getAfipCode') ? $e->getAfipCode() : null,
        ], 500);
    }
});
```

Visita: `http://tu-app.test/test-afip-auth`

### 8. Probar Consulta de Último Comprobante

```php
Route::get('/test-afip-last-invoice', function () {
    try {
        $pointOfSale = 1; // Tu punto de venta
        $invoiceType = 1; // Tipo de comprobante (1 = Factura A)
        
        $lastInvoice = Afip::getLastAuthorizedInvoice($pointOfSale, $invoiceType);
        
        return response()->json([
            'success' => true,
            'data' => $lastInvoice,
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});
```

### 9. Probar Autorización de Comprobante (Último Paso)

```php
Route::post('/test-afip-invoice', function (Request $request) {
    try {
        // Datos de prueba (ajusta según tu caso)
        $invoice = [
            'pointOfSale' => 1,
            'invoiceType' => 1, // Factura A
            'invoiceNumber' => 0, // 0 = auto (se ajustará)
            'date' => date('Ymd'),
            'customerCuit' => '20123456789',
            'customerDocumentType' => 80, // CUIT
            'customerDocumentNumber' => '20123456789',
            'concept' => 1, // Productos
            'items' => [
                [
                    'description' => 'Producto de Prueba',
                    'quantity' => 1,
                    'unitPrice' => 100.00,
                    'taxRate' => 21, // IVA 21%
                ],
            ],
            'total' => 121.00,
            'totalNetoGravado' => 100.00,
            'totalIva' => 21.00,
            'totalNetoNoGravado' => 0,
            'totalExento' => 0,
            'totalTributos' => 0,
        ];
        
        $result = Afip::authorizeInvoice($invoice);
        
        return response()->json([
            'success' => true,
            'message' => 'Comprobante autorizado',
            'data' => [
                'cae' => $result->cae,
                'cae_expiration_date' => $result->caeExpirationDate,
                'invoice_number' => $result->invoiceNumber,
                'point_of_sale' => $result->pointOfSale,
                'invoice_type' => $result->invoiceType,
            ],
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'afip_code' => method_exists($e, 'getAfipCode') ? $e->getAfipCode() : null,
        ], 500);
    }
});
```

## 🔍 Verificar Logs

Durante las pruebas, revisa los logs:

```bash
# Ver logs en tiempo real
tail -f storage/logs/laravel.log

# O si usas canal 'daily'
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log

# Buscar solo logs de AFIP
grep "AFIP SDK" storage/logs/laravel.log
```

## ✅ Checklist Final

Antes de probar, verifica:

- [ ] Certificados descargados de homologación
- [ ] Certificados en la ruta correcta
- [ ] Permisos de archivos correctos (600 para .key, 644 para .crt)
- [ ] Variables de entorno configuradas en `.env`
- [ ] Configuración publicada (`php artisan config:clear`)
- [ ] SDK instalado via Composer
- [ ] Punto de venta habilitado en AFIP
- [ ] Servicio WSFE habilitado en AFIP

## 🚨 Errores Comunes y Soluciones

### Error: "Certificado no encontrado"
- Verifica la ruta en `AFIP_CERTIFICATES_PATH`
- Verifica que los nombres de archivo coincidan con `AFIP_CERTIFICATE_KEY` y `AFIP_CERTIFICATE_CRT`

### Error: "Error al cargar clave privada"
- Verifica la contraseña en `AFIP_CERTIFICATE_PASSWORD`
- Verifica el formato del archivo (debe ser PEM)

### Error: "CUIT no configurado"
- Verifica `AFIP_CUIT` en `.env`
- Ejecuta `php artisan config:clear`

### Error: "Token o firma no encontrados"
- Verifica que estés usando certificados de **homologación**
- Verifica que el CUIT sea correcto
- Verifica que el servicio WSFE esté habilitado en AFIP

## 📝 Orden de Pruebas Recomendado

1. ✅ **Test de configuración** (`afip:test-config`)
2. ✅ **Test de autenticación** (`/test-afip-auth`)
3. ✅ **Test de último comprobante** (`/test-afip-last-invoice`)
4. ✅ **Test de autorización** (`/test-afip-invoice`)

## 🎯 Siguiente Paso

Una vez que todas las pruebas pasen, puedes:
1. Integrar con tus modelos Eloquent
2. Completar el `InvoiceBuilder` para tu estructura de datos
3. Generar PDFs con el CAE obtenido
4. Implementar en tu flujo de negocio

¡Listo para probar! 🚀

