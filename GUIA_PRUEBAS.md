# Guía de Pruebas - AFIP SDK

Esta guía te ayudará a probar el SDK paso a paso, desde la configuración inicial hasta la autorización de comprobantes.

## 📋 Requisitos Previos

### 1. Certificados Digitales de AFIP

Necesitas tener:
- ✅ Certificado digital (.crt) descargado de AFIP
- ✅ Clave privada (.key) generada durante el proceso
- ✅ Contraseña de la clave privada

**Importante**: Para testing, usa los certificados del entorno de **homologación** (homo).

### 2. Configuración en AFIP

- ✅ CUIT registrado en AFIP
- ✅ Punto de venta habilitado
- ✅ Servicio WSFE habilitado
- ✅ Acceso al entorno de homologación

## 🚀 Configuración Inicial

### Paso 1: Instalar el SDK

```bash
# Si es un paquete local, agregar al composer.json del proyecto:
composer require resguar/afip-sdk

# O si estás desarrollando el SDK:
composer install
```

### Paso 2: Publicar Configuración

```bash
php artisan vendor:publish --tag=afip-config
php artisan vendor:publish --tag=afip-migrations
```

### Paso 3: Configurar Variables de Entorno

Edita tu archivo `.env`:

```env
# Entorno (testing para homologación, production para producción)
AFIP_ENVIRONMENT=testing

# CUIT del contribuyente (sin guiones)
AFIP_CUIT=20123456789

# Ruta donde están los certificados
AFIP_CERTIFICATES_PATH=/ruta/a/tus/certificados

# Nombres de los archivos
AFIP_CERTIFICATE_KEY=private_key.key
AFIP_CERTIFICATE_CRT=certificate.crt

# Contraseña de la clave privada (si tiene)
AFIP_CERTIFICATE_PASSWORD=tu_password

# Cache (opcional, por defecto habilitado)
AFIP_CACHE_ENABLED=true
AFIP_CACHE_TTL=43200

# Logging (opcional)
AFIP_LOGGING_ENABLED=true
AFIP_LOGGING_CHANNEL=daily
AFIP_LOGGING_LEVEL=debug
```

### Paso 4: Estructura de Certificados

Asegúrate de tener esta estructura:

```
/ruta/a/tus/certificados/
├── private_key.key
└── certificate.crt
```

**Permisos recomendados:**
```bash
chmod 600 /ruta/a/tus/certificados/private_key.key
chmod 644 /ruta/a/tus/certificados/certificate.crt
```

## 🧪 Pruebas Básicas

### Test 1: Verificar Autenticación (WSAA)

Crea un archivo de prueba: `tests/test-autenticacion.php`

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Resguar\AfipSdk\Facades\Afip;

// Cargar configuración de Laravel (si es necesario)
// O configurar manualmente

try {
    // Verificar si está autenticado
    $isAuthenticated = Afip::isAuthenticated();
    echo "Autenticado: " . ($isAuthenticated ? "Sí" : "No") . "\n";
    
    // Obtener token (esto forzará la autenticación)
    $wsaaService = app(\Resguar\AfipSdk\Services\WsaaService::class);
    $tokenResponse = $wsaaService->getToken('wsfe');
    
    echo "Token obtenido: " . substr($tokenResponse->token, 0, 20) . "...\n";
    echo "Válido hasta: " . $tokenResponse->expirationDate->format('Y-m-d H:i:s') . "\n";
    echo "✅ Autenticación exitosa!\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Código AFIP: " . ($e->getAfipCode() ?? 'N/A') . "\n";
}
```

**Ejecutar:**
```bash
php tests/test-autenticacion.php
```

### Test 2: Consultar Último Comprobante

Crea: `tests/test-ultimo-comprobante.php`

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Resguar\AfipSdk\Facades\Afip;

try {
    $pointOfSale = 1; // Tu punto de venta
    $invoiceType = 1; // Tipo de comprobante (1 = Factura A)
    
    $lastInvoice = Afip::getLastAuthorizedInvoice($pointOfSale, $invoiceType);
    
    echo "✅ Último comprobante encontrado:\n";
    echo "   Número: " . $lastInvoice['CbteNro'] . "\n";
    echo "   Fecha: " . $lastInvoice['CbteFch'] . "\n";
    echo "   Punto de Venta: " . $lastInvoice['PtoVta'] . "\n";
    echo "   Tipo: " . $lastInvoice['CbteTipo'] . "\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getAfipCode')) {
        echo "Código AFIP: " . $e->getAfipCode() . "\n";
    }
}
```

### Test 3: Autorizar Comprobante (Completo)

Crea: `tests/test-autorizar-comprobante.php`

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Resguar\AfipSdk\Facades\Afip;

try {
    // Datos del comprobante de prueba
    $invoice = [
        'pointOfSale' => 1,
        'invoiceType' => 1, // Factura A
        'invoiceNumber' => 0, // 0 = auto (se ajustará al siguiente)
        'date' => date('Ymd'),
        'customerCuit' => '20123456789',
        'customerDocumentType' => 80, // CUIT
        'customerDocumentNumber' => '20123456789',
        'concept' => 1, // Productos
        'items' => [
            [
                'code' => 'PROD001',
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
    
    echo "📝 Iniciando autorización de comprobante...\n";
    echo "   Punto de Venta: " . $invoice['pointOfSale'] . "\n";
    echo "   Tipo: " . $invoice['invoiceType'] . "\n";
    echo "   Cliente: " . $invoice['customerCuit'] . "\n";
    echo "   Total: $" . $invoice['total'] . "\n\n";
    
    // Autorizar
    $result = Afip::authorizeInvoice($invoice);
    
    echo "✅ Comprobante autorizado exitosamente!\n\n";
    echo "📄 Detalles:\n";
    echo "   CAE: " . $result->cae . "\n";
    echo "   Vencimiento CAE: " . $result->caeExpirationDate . "\n";
    echo "   Número de Comprobante: " . $result->invoiceNumber . "\n";
    echo "   Punto de Venta: " . $result->pointOfSale . "\n";
    echo "   Tipo: " . $result->invoiceType . "\n";
    
    if (!empty($result->observations ?? [])) {
        echo "\n⚠️  Observaciones:\n";
        foreach ($result->observations as $obs) {
            echo "   - " . ($obs['code'] ?? '') . ": " . ($obs['msg'] ?? '') . "\n";
        }
    }
    
} catch (\Resguar\AfipSdk\Exceptions\AfipException $e) {
    echo "❌ Error de AFIP:\n";
    echo "   Mensaje: " . $e->getMessage() . "\n";
    if ($e->getAfipCode()) {
        echo "   Código AFIP: " . $e->getAfipCode() . "\n";
    }
    if ($e->getAfipMessage()) {
        echo "   Mensaje AFIP: " . $e->getAfipMessage() . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Error inesperado: " . $e->getMessage() . "\n";
    echo "   Archivo: " . $e->getFile() . "\n";
    echo "   Línea: " . $e->getLine() . "\n";
}
```

## 🔧 Pruebas con Laravel (Tinker)

### Opción 1: Usando Tinker

```bash
php artisan tinker
```

```php
// En Tinker:

use Resguar\AfipSdk\Facades\Afip;

// 1. Verificar autenticación
Afip::isAuthenticated();

// 2. Consultar último comprobante
$last = Afip::getLastAuthorizedInvoice(1, 1);
print_r($last);

// 3. Autorizar comprobante
$invoice = [
    'pointOfSale' => 1,
    'invoiceType' => 1,
    'invoiceNumber' => 0,
    'date' => date('Ymd'),
    'customerCuit' => '20123456789',
    'customerDocumentType' => 80,
    'concept' => 1,
    'items' => [
        [
            'description' => 'Producto Test',
            'quantity' => 1,
            'unitPrice' => 100,
            'taxRate' => 21,
        ],
    ],
    'total' => 121,
    'totalNetoGravado' => 100,
    'totalIva' => 21,
];

$result = Afip::authorizeInvoice($invoice);
echo $result->cae;
```

### Opción 2: Crear un Artisan Command

Crea: `app/Console/Commands/TestAfip.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Resguar\AfipSdk\Facades\Afip;

class TestAfip extends Command
{
    protected $signature = 'afip:test';
    protected $description = 'Prueba la integración con AFIP';

    public function handle()
    {
        $this->info('Probando autenticación...');
        
        try {
            // Test autenticación
            $isAuth = Afip::isAuthenticated();
            $this->info('Autenticado: ' . ($isAuth ? 'Sí' : 'No'));
            
            // Test último comprobante
            $this->info('Consultando último comprobante...');
            $last = Afip::getLastAuthorizedInvoice(1, 1);
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['Número', $last['CbteNro']],
                    ['Fecha', $last['CbteFch']],
                ]
            );
            
            $this->info('✅ Pruebas completadas exitosamente!');
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
        }
    }
}
```

## 🐛 Solución de Problemas Comunes

### Error: "CUIT no configurado"

**Solución:**
```bash
# Verificar .env
grep AFIP_CUIT .env

# O verificar config
php artisan config:show afip.cuit
```

### Error: "Certificado no encontrado"

**Solución:**
```bash
# Verificar que los archivos existan
ls -la /ruta/a/tus/certificados/

# Verificar permisos
chmod 600 private_key.key
chmod 644 certificate.crt
```

### Error: "Error al cargar clave privada"

**Posibles causas:**
1. Contraseña incorrecta
2. Formato de archivo incorrecto
3. Archivo corrupto

**Solución:**
```bash
# Verificar formato del certificado
openssl x509 -in certificate.crt -text -noout

# Verificar clave privada
openssl rsa -in private_key.key -check
```

### Error: "Token o firma no encontrados en la respuesta"

**Posibles causas:**
1. Certificado no válido para el entorno
2. CUIT incorrecto
3. Servicio no habilitado en AFIP

**Solución:**
- Verificar que estés usando certificados de **homologación** para testing
- Verificar que el CUIT sea correcto
- Verificar en el portal de AFIP que el servicio WSFE esté habilitado

### Error: "Error al consultar último comprobante"

**Posibles causas:**
1. Punto de venta no habilitado
2. Tipo de comprobante incorrecto
3. No hay comprobantes previos (retorna 0)

**Solución:**
- Verificar punto de venta en portal AFIP
- Si es el primer comprobante, el número será 1

## 📊 Verificar Logs

El SDK registra todas las operaciones. Para ver los logs:

```bash
# Ver logs de Laravel
tail -f storage/logs/laravel.log

# O si usas canal 'daily'
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log

# Buscar logs de AFIP
grep "AFIP SDK" storage/logs/laravel.log
```

## ✅ Checklist de Pruebas

Antes de pasar a producción, verifica:

- [ ] Autenticación funciona (obtiene token)
- [ ] Cache de tokens funciona (no genera token en cada llamada)
- [ ] Consulta último comprobante funciona
- [ ] Autorización de comprobante funciona
- [ ] CAE se obtiene correctamente
- [ ] Números de comprobante son correlativos
- [ ] Manejo de errores funciona
- [ ] Logs se generan correctamente

## 🔄 Pruebas en Producción

**⚠️ IMPORTANTE**: Antes de probar en producción:

1. Cambiar `AFIP_ENVIRONMENT=production` en `.env`
2. Usar certificados de **producción** (no de homologación)
3. Verificar que el CUIT sea el correcto
4. Probar primero con un comprobante de prueba
5. Verificar que los números sean correlativos

## 📝 Ejemplo Completo de Uso

```php
<?php

use Resguar\AfipSdk\Facades\Afip;

// 1. Construir comprobante
$invoice = [
    'pointOfSale' => 1,
    'invoiceType' => 1,
    'date' => date('Ymd'),
    'customerCuit' => '20123456789',
    'customerDocumentType' => 80,
    'concept' => 1,
    'items' => [
        [
            'description' => 'Producto 1',
            'quantity' => 2,
            'unitPrice' => 50.00,
            'taxRate' => 21,
        ],
    ],
    'total' => 121.00,
    'totalNetoGravado' => 100.00,
    'totalIva' => 21.00,
];

// 2. Autorizar (el SDK hace todo automáticamente)
try {
    $result = Afip::authorizeInvoice($invoice);
    
    // 3. Usar el CAE
    $cae = $result->cae;
    $invoiceNumber = $result->invoiceNumber;
    
    // 4. Generar PDF (responsabilidad de tu aplicación)
    // ... tu código para generar PDF con el CAE
    
} catch (\Resguar\AfipSdk\Exceptions\AfipException $e) {
    // Manejar error
    logger()->error('Error AFIP', [
        'message' => $e->getMessage(),
        'code' => $e->getAfipCode(),
    ]);
}
```

## 🎯 Próximos Pasos

Una vez que las pruebas básicas funcionen:

1. Integrar con tu modelo de datos (Eloquent)
2. Implementar generación de PDF
3. Agregar manejo de errores en tu aplicación
4. Implementar reintentos en caso de fallos temporales
5. Agregar tests unitarios e integración

