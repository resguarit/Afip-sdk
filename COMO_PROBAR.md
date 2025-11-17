# 🧪 Cómo Probar el SDK de AFIP

Guía paso a paso para probar el SDK en entorno de homologación (testing).

## ✅ Requisitos Previos

Antes de probar, asegúrate de tener:

1. ✅ **Certificados digitales de AFIP** (homologación)
   - Archivo `.key` (clave privada)
   - Archivo `.crt` (certificado público)
   - Ambos obtenidos desde ARCA en modo homologación

2. ✅ **Configuración en ARCA completada**
   - CUIT registrado
   - Punto de venta habilitado
   - Certificados generados y descargados

3. ✅ **Laravel 11+ con PHP 8.1+**
   - Extensiones: `openssl`, `soap`

## 🚀 Paso 1: Instalar el SDK

```bash
# En tu proyecto Laravel
composer require resguar/afip-sdk:dev-main

# Publicar configuración
php artisan vendor:publish --tag=afip-config
```

## ⚙️ Paso 2: Configurar el SDK

### 2.1. Colocar Certificados

Coloca tus certificados en una carpeta segura:

```bash
mkdir -p storage/certificates
# Copia tus archivos:
# - clave_privada.key
# - certificado.crt
```

### 2.2. Configurar `.env`

Edita tu archivo `.env`:

```env
# Entorno de homologación
AFIP_ENVIRONMENT=testing

# Tu CUIT (sin guiones)
AFIP_CUIT=20457809027

# Ruta de certificados
AFIP_CERTIFICATES_PATH=storage/certificates

# Nombres de archivos
AFIP_CERTIFICATE_KEY=clave_privada.key
AFIP_CERTIFICATE_CRT=certificado.crt

# Contraseña (si tu clave privada tiene contraseña)
AFIP_CERTIFICATE_PASSWORD=

# Punto de venta por defecto
AFIP_DEFAULT_POINT_OF_SALE=1
```

## 🧪 Paso 3: Crear Script de Prueba

Crea un archivo de prueba: `tests/test-afip.php` o un comando Artisan.

### Opción A: Script PHP Simple

Crea `test-afip.php` en la raíz del proyecto:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Config;
use Resguar\AfipSdk\Facades\Afip;

// Cargar configuración de Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🧪 Probando SDK de AFIP...\n\n";
    
    // 1. Verificar autenticación
    echo "1️⃣ Verificando autenticación...\n";
    $isAuthenticated = Afip::isAuthenticated();
    echo $isAuthenticated ? "✅ Autenticado\n" : "❌ No autenticado\n";
    echo "\n";
    
    // 2. Consultar último comprobante
    echo "2️⃣ Consultando último comprobante autorizado...\n";
    $lastInvoice = Afip::getLastAuthorizedInvoice(
        pointOfSale: 1,
        invoiceType: 1  // Factura A
    );
    echo "✅ Último comprobante: " . ($lastInvoice['CbteNro'] ?? 'N/A') . "\n";
    echo "   Fecha: " . ($lastInvoice['CbteFch'] ?? 'N/A') . "\n";
    echo "\n";
    
    // 3. Preparar datos de prueba
    echo "3️⃣ Preparando datos de factura de prueba...\n";
    $invoiceData = [
        'pointOfSale' => 1,
        'invoiceType' => 1,  // Factura A
        'invoiceNumber' => 0,  // Auto (se ajusta automáticamente)
        'date' => date('Ymd'),
        'customerCuit' => '20123456789',  // CUIT de prueba
        'customerDocumentType' => 80,  // CUIT
        'customerDocumentNumber' => '20123456789',
        'concept' => 1,  // Productos
        'items' => [
            [
                'description' => 'Producto de prueba',
                'quantity' => 1.0,
                'unitPrice' => 100.0,
                'taxRate' => 21.0,
            ],
        ],
        'netAmount' => 100.0,
        'ivaTotal' => 21.0,
        'total' => 121.0,
    ];
    echo "✅ Datos preparados\n\n";
    
    // 4. Autorizar factura
    echo "4️⃣ Autorizando factura con AFIP...\n";
    $result = Afip::authorizeInvoice($invoiceData);
    
    echo "✅ Factura autorizada exitosamente!\n";
    echo "   CAE: " . $result->cae . "\n";
    echo "   Número: " . $result->invoiceNumber . "\n";
    echo "   Vencimiento CAE: " . $result->caeExpirationDate . "\n";
    echo "\n";
    
    echo "🎉 ¡Prueba exitosa!\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getAfipCode')) {
        echo "   Código AFIP: " . $e->getAfipCode() . "\n";
    }
    echo "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
```

Ejecutar:

```bash
php test-afip.php
```

### Opción B: Comando Artisan

Crea `app/Console/Commands/TestAfip.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Resguar\AfipSdk\Facades\Afip;

class TestAfip extends Command
{
    protected $signature = 'afip:test';
    protected $description = 'Probar SDK de AFIP';

    public function handle()
    {
        $this->info('🧪 Probando SDK de AFIP...');
        
        try {
            // 1. Verificar autenticación
            $this->info('1️⃣ Verificando autenticación...');
            $isAuthenticated = Afip::isAuthenticated();
            $this->{$isAuthenticated ? 'info' : 'error'}(
                $isAuthenticated ? '✅ Autenticado' : '❌ No autenticado'
            );
            
            // 2. Consultar último comprobante
            $this->info('2️⃣ Consultando último comprobante...');
            $lastInvoice = Afip::getLastAuthorizedInvoice(1, 1);
            $this->info("✅ Último: {$lastInvoice['CbteNro']} (Fecha: {$lastInvoice['CbteFch']})");
            
            // 3. Autorizar factura de prueba
            $this->info('3️⃣ Autorizando factura de prueba...');
            $invoiceData = [
                'pointOfSale' => 1,
                'invoiceType' => 1,
                'invoiceNumber' => 0,
                'date' => date('Ymd'),
                'customerCuit' => '20123456789',
                'customerDocumentType' => 80,
                'customerDocumentNumber' => '20123456789',
                'concept' => 1,
                'items' => [
                    [
                        'description' => 'Producto de prueba',
                        'quantity' => 1.0,
                        'unitPrice' => 100.0,
                        'taxRate' => 21.0,
                    ],
                ],
                'netAmount' => 100.0,
                'ivaTotal' => 21.0,
                'total' => 121.0,
            ];
            
            $result = Afip::authorizeInvoice($invoiceData);
            
            $this->info('✅ Factura autorizada!');
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['CAE', $result->cae],
                    ['Número', $result->invoiceNumber],
                    ['Vencimiento CAE', $result->caeExpirationDate],
                ]
            );
            
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            if (method_exists($e, 'getAfipCode')) {
                $this->error('Código AFIP: ' . $e->getAfipCode());
            }
            return 1;
        }
    }
}
```

Ejecutar:

```bash
php artisan afip:test
```

## 🔍 Paso 4: Verificar Logs

Revisa los logs de Laravel para ver detalles:

```bash
tail -f storage/logs/laravel.log | grep -i afip
```

O en el código:

```php
\Log::info('Test AFIP', ['data' => $result->toArray()]);
```

## ✅ Pruebas Paso a Paso

### Prueba 1: Verificar Autenticación

```php
use Resguar\AfipSdk\Facades\Afip;

$isAuthenticated = Afip::isAuthenticated();
var_dump($isAuthenticated); // Debe ser true si hay token válido
```

### Prueba 2: Consultar Último Comprobante

```php
$lastInvoice = Afip::getLastAuthorizedInvoice(
    pointOfSale: 1,
    invoiceType: 1
);

print_r($lastInvoice);
// Debe retornar: ['CbteNro' => X, 'CbteFch' => 'YYYYMMDD', ...]
```

### Prueba 3: Autorizar Factura Mínima

```php
$invoiceData = [
    'pointOfSale' => 1,
    'invoiceType' => 1,
    'invoiceNumber' => 0,  // Auto
    'date' => date('Ymd'),
    'customerCuit' => '20123456789',
    'customerDocumentType' => 80,
    'customerDocumentNumber' => '20123456789',
    'concept' => 1,
    'items' => [
        [
            'description' => 'Test',
            'quantity' => 1.0,
            'unitPrice' => 100.0,
            'taxRate' => 21.0,
        ],
    ],
    'total' => 121.0,
];

$result = Afip::authorizeInvoice($invoiceData);
echo "CAE: " . $result->cae . "\n";
```

## ⚠️ Errores Comunes

### Error: "CUIT no configurado"

**Solución:** Verifica que `AFIP_CUIT` esté en tu `.env`

### Error: "Error al cargar clave privada"

**Solución:** 
- Verifica que el archivo `.key` exista
- Verifica la ruta en `AFIP_CERTIFICATES_PATH`
- Verifica la contraseña si tu clave tiene una

### Error: "Error SOAP al llamar..."

**Solución:**
- Verifica tu conexión a internet
- Verifica que estés en entorno `testing` (homologación)
- Revisa los logs para más detalles

### Error: "El CUIT debe tener 11 dígitos"

**Solución:** Asegúrate de que el CUIT tenga exactamente 11 dígitos (sin guiones)

## 📊 Verificar Resultados

Después de autorizar una factura, puedes verificar en ARCA:

1. Ingresa a ARCA (homologación)
2. Ve a "Consultas" → "Comprobantes Emitidos"
3. Busca el número de comprobante autorizado
4. Verifica que el CAE coincida

## 🎯 Checklist de Prueba

- [ ] SDK instalado correctamente
- [ ] Certificados colocados en la ruta correcta
- [ ] Variables de entorno configuradas
- [ ] Autenticación funciona (`isAuthenticated()`)
- [ ] Consulta último comprobante funciona
- [ ] Autorización de factura funciona
- [ ] CAE recibido correctamente
- [ ] Logs muestran información útil

## 📝 Notas Importantes

1. **Entorno de Homologación**: Usa `AFIP_ENVIRONMENT=testing` para pruebas
2. **Números de Comprobante**: El SDK ajusta automáticamente (usa `invoiceNumber => 0`)
3. **Cache de Tokens**: Los tokens se cachean por 12 horas
4. **Logs**: Revisa siempre los logs si algo falla

## 🆘 ¿Problemas?

1. Revisa los logs: `storage/logs/laravel.log`
2. Verifica certificados: Que existan y sean válidos
3. Verifica configuración: `.env` y `config/afip.php`
4. Consulta la documentación: [README.md](README.md)

---

**¡Listo para probar!** 🚀

