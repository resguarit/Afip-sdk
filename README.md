# AFIP SDK para Laravel

[![PHP Version](https://img.shields.io/badge/php-8.1%2B-blue.svg)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-11%2B-red.svg)](https://laravel.com/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

SDK independiente y reutilizable para integración con AFIP (Administración Federal de Ingresos Públicos de Argentina) - Facturación Electrónica.

## 📦 Instalación

```bash
composer require resguar/afip-sdk
```

## Características

- ✅ Integración completa con Web Services de AFIP (WSAA, WSFE)
- ✅ **Correlatividad automática**: Consulta último comprobante antes de autorizar
- ✅ Builder Pattern para construcción flexible de comprobantes
- ✅ Soporte para múltiples fuentes de datos (Eloquent, arrays, objetos)
- ✅ **Cache automático de tokens de autenticación** (12 horas, según especificación)
- ✅ **Logging integrado** con niveles configurables
- ✅ **Retry logic con exponential backoff** para errores temporales
- ✅ **Validación de datos** con reglas de negocio
- ✅ **DTOs (Data Transfer Objects)** para respuestas estructuradas
- ✅ **Helpers para SOAP** con manejo de errores mejorado
- ✅ Manejo robusto de errores con excepciones personalizadas
- ✅ Soporte para entornos de testing y producción
- ✅ Gestión de certificados digitales
- ✅ Compatible con Laravel 11+
- ✅ PHP 8.1+
- ✅ **PSR-12** y mejores prácticas de programación

## 📥 Instalación

### Requisitos

- PHP 8.1 o superior
- Laravel 11 o superior
- Extensiones PHP: `openssl`, `soap`
- Certificados digitales de AFIP

### Opción 1: Desde GitHub (Recomendado)

```bash
# Agregar al composer.json de tu proyecto:
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

# Luego instalar:
composer require resguar/afip-sdk:dev-main
```

### Opción 2: Desde Repositorio Local (Desarrollo)

```bash
# Agregar al composer.json:
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

# Instalar:
composer require resguar/afip-sdk:@dev
```

### Opción 3: Desde Packagist (Cuando esté publicado)

```bash
composer require resguar/afip-sdk
```

**📖 Ver [Guía de Uso Completa](GUIA_USO_LARAVEL.md) para más detalles**

### Publicar configuración y migraciones

```bash
# Publicar configuración
php artisan vendor:publish --tag=afip-config

# Publicar migraciones
php artisan vendor:publish --tag=afip-migrations

# Ejecutar migraciones
php artisan migrate
```

## Configuración

### Variables de entorno

Agrega las siguientes variables a tu archivo `.env`:

```env
AFIP_ENVIRONMENT=testing
AFIP_CUIT=20123456789
AFIP_CERTIFICATES_PATH=/ruta/a/certificados
AFIP_CERTIFICATE_KEY=private_key.key
AFIP_CERTIFICATE_CRT=certificate.crt
AFIP_CERTIFICATE_PASSWORD=tu_password
AFIP_DEFAULT_POINT_OF_SALE=1
```

### Configuración de certificados

1. Coloca tus certificados digitales en la ruta especificada en `AFIP_CERTIFICATES_PATH`
2. Asegúrate de que los archivos tengan los nombres correctos (`private_key.key` y `certificate.crt`)

## 📖 Guías de Uso

- **[Guía de Uso en Laravel](GUIA_USO_LARAVEL.md)** ⭐ **EMPIEZA AQUÍ** - Instalación y uso completo
- **[Integración en Sistema POS](INTEGRACION_POS.md)** 🎯 **PARA POS** - Guía específica para sistemas POS
- [Checklist Pre-Producción](CHECKLIST_PRE_PRODUCCION.md) - Qué necesitas antes de probar
- [Guía de Pruebas](GUIA_PRUEBAS.md) - Ejemplos y scripts de prueba
- [Configurar Certificados](CONFIGURAR_CERTIFICADOS.md) - Guía de certificados
- [Instalación Rápida](INSTALACION_RAPIDA.md) - Setup en 5 minutos

## 🧪 Pruebas Rápidas

```bash
# 1. Verificar configuración
php artisan tinker
# Luego: config('afip.cuit')

# 2. Probar autenticación
use Resguar\AfipSdk\Facades\Afip;
Afip::isAuthenticated()
```

## Uso

### Opción 1: Usando la Facade

```php
use Resguar\AfipSdk\Facades\Afip;

// Autorizar una factura desde un modelo Eloquent
// El SDK automáticamente consulta el último comprobante y ajusta el número
$result = Afip::authorizeInvoice($sale);

// El resultado es un InvoiceResponse DTO
echo $result->cae; // Código de Autorización Electrónico
echo $result->caeExpirationDate; // Fecha de vencimiento
echo $result->invoiceNumber; // Número de comprobante

// Verificar si el CAE está vigente
if ($result->isCaeValid()) {
    // CAE válido
}

// Autorizar desde un array
$invoice = [
    'pointOfSale' => 1,
    'invoiceType' => 1,
    'customerCuit' => '20123456789',
    // ... más datos
];
$result = Afip::authorizeInvoice($invoice);

// Obtener último comprobante autorizado (se consulta automáticamente antes de autorizar)
$lastInvoice = Afip::getLastAuthorizedInvoice(1, 1);
// Retorna: ['CbteNro' => 100, 'CbteFch' => '20240101', 'PtoVta' => 1, 'CbteTipo' => 1]

// Obtener tipos de comprobantes
$invoiceTypes = Afip::getInvoiceTypes();

// Obtener puntos de venta
$pointsOfSale = Afip::getPointOfSales();
```

### Opción 2: Inyección de dependencias

```php
use Resguar\AfipSdk\Contracts\AfipServiceInterface;

class InvoiceController
{
    public function __construct(
        private AfipServiceInterface $afipService
    ) {}

    public function authorize($sale)
    {
        $result = $this->afipService->authorizeInvoice($sale);
        
        // Procesar resultado
        return $result;
    }
}
```

### Opción 3: Usando el InvoiceBuilder

```php
use Resguar\AfipSdk\Builders\InvoiceBuilder;

// Construir desde un modelo
$invoice = InvoiceBuilder::from($sale)
    ->pointOfSale(1)
    ->invoiceType(1)
    ->date(now())
    ->build();

// Construir desde un array
$invoice = InvoiceBuilder::from($data)
    ->customerCuit('20123456789')
    ->addItem(['description' => 'Producto 1', 'quantity' => 1, 'price' => 100])
    ->total(121)
    ->build();
```

## Estructura del Proyecto

```
afip-sdk-php/
├── src/
│   ├── Services/
│   │   ├── AfipService.php
│   │   ├── WsaaService.php
│   │   ├── WsfeService.php
│   │   └── CertificateManager.php
│   ├── Builders/
│   │   └── InvoiceBuilder.php
│   ├── Models/
│   │   ├── AfipConfiguration.php
│   │   └── PointOfSale.php
│   ├── Exceptions/
│   │   ├── AfipException.php
│   │   ├── AfipAuthenticationException.php
│   │   └── AfipAuthorizationException.php
│   ├── Contracts/
│   │   └── AfipServiceInterface.php
│   ├── Facades/
│   │   └── Afip.php
│   └── AfipServiceProvider.php
├── config/
│   └── afip.php
├── database/
│   └── migrations/
├── tests/
└── README.md
```

## Modelos

### AfipConfiguration

Almacena la configuración de AFIP para diferentes contribuyentes o entornos.

```php
use Resguar\AfipSdk\Models\AfipConfiguration;

$config = AfipConfiguration::create([
    'name' => 'Configuración Principal',
    'cuit' => '20123456789',
    'environment' => 'testing',
    'is_active' => true,
]);
```

### PointOfSale

Gestiona los puntos de venta habilitados.

```php
use Resguar\AfipSdk\Models\PointOfSale;

$pos = PointOfSale::create([
    'afip_configuration_id' => $config->id,
    'number' => 1,
    'name' => 'Punto de Venta Principal',
    'is_active' => true,
]);
```

## Características Avanzadas

### Cache de Tokens

El SDK cachea automáticamente los tokens de autenticación para evitar solicitudes innecesarias a AFIP. Los tokens son válidos por 24 horas.

```php
// El cache se maneja automáticamente
$result = Afip::authorizeInvoice($sale); // Primera llamada: obtiene token nuevo
$result2 = Afip::authorizeInvoice($sale2); // Segunda llamada: usa token del cache

// Limpiar cache manualmente si es necesario
$wsaaService = app(\Resguar\AfipSdk\Services\WsaaService::class);
$wsaaService->clearTokenCache('wsfe'); // Limpiar cache de un servicio
$wsaaService->clearTokenCache(); // Limpiar todo el cache
```

### Logging

El SDK registra automáticamente todas las operaciones importantes:

```php
// Configurar logging en config/afip.php
'logging' => [
    'enabled' => true,
    'channel' => 'daily', // Canal de Laravel
    'level' => 'info', // Nivel mínimo
],
```

Los logs incluyen:
- Autenticaciones y obtención de tokens
- Autorizaciones de comprobantes
- Errores y excepciones
- Operaciones de cache

### Validación de Datos

El SDK valida automáticamente los datos antes de enviarlos a AFIP:

```php
use Resguar\AfipSdk\Helpers\ValidatorHelper;

// Validar CUIT
if (ValidatorHelper::validateCuit('20123456789')) {
    // CUIT válido
}

// Formatear CUIT
$formatted = ValidatorHelper::formatCuit('20123456789'); // 20-12345678-9
```

### Retry Logic

El SDK incluye lógica de reintentos automáticos para errores temporales (timeouts, problemas de conexión):

```php
// Configurar en config/afip.php
'retry' => [
    'enabled' => true,
    'max_attempts' => 3,
    'delay' => 1000, // milisegundos (exponential backoff)
],
```

### DTOs (Data Transfer Objects)

Las respuestas se devuelven como DTOs tipados:

```php
$response = Afip::authorizeInvoice($sale);

// Propiedades tipadas
$response->cae; // string
$response->caeExpirationDate; // string (Ymd)
$response->invoiceNumber; // int
$response->pointOfSale; // int

// Métodos útiles
$response->isCaeValid(); // bool
$response->toArray(); // array
```

## Manejo de Errores

El SDK utiliza excepciones personalizadas para un mejor manejo de errores:

```php
use Resguar\AfipSdk\Exceptions\AfipException;
use Resguar\AfipSdk\Exceptions\AfipAuthenticationException;
use Resguar\AfipSdk\Exceptions\AfipAuthorizationException;

try {
    $result = Afip::authorizeInvoice($sale);
} catch (AfipAuthenticationException $e) {
    // Error de autenticación
    logger()->error('Error de autenticación AFIP', [
        'message' => $e->getMessage(),
        'afip_code' => $e->getAfipCode(),
    ]);
} catch (AfipAuthorizationException $e) {
    // Error de autorización
    logger()->error('Error de autorización AFIP', [
        'message' => $e->getMessage(),
        'afip_code' => $e->getAfipCode(),
    ]);
} catch (AfipException $e) {
    // Otro error de AFIP
    logger()->error('Error AFIP', ['message' => $e->getMessage()]);
}
```

## Testing

```bash
# Ejecutar tests
composer test

# Con coverage
composer test -- --coverage
```

## Entornos

### Testing (Homologación)

- URL WSAA: `https://wsaahomo.afip.gov.ar/ws/services/LoginCms`
- URL WSFE: `https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL`

### Producción

- URL WSAA: `https://wsaa.afip.gov.ar/ws/services/LoginCms`
- URL WSFE: `https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL`

## Seguridad

⚠️ **IMPORTANTE**: Nunca subas tus certificados digitales al repositorio. Asegúrate de que estén en `.gitignore` y se manejen de forma segura.

## Contribuir

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📚 Documentación Adicional

- [Guía de Pruebas](GUIA_PRUEBAS.md) - Cómo probar el SDK
- [Mejores Prácticas](MEJORES_PRACTICAS.md) - Prácticas implementadas
- [Implementación Completa](IMPLEMENTACION_COMPLETA.md) - Detalles técnicos
- [Contribuir](CONTRIBUTING.md) - Guía para contribuir
- [Política de Seguridad](SECURITY.md) - Reportar vulnerabilidades

## 🤝 Contribuir

Las contribuciones son bienvenidas! Por favor lee [CONTRIBUTING.md](CONTRIBUTING.md) para detalles sobre nuestro código de conducta y el proceso para enviar pull requests.

## 📝 Changelog

Ver [CHANGELOG.md](CHANGELOG.md) para una lista de cambios.

## 🔒 Seguridad

Si descubres una vulnerabilidad de seguridad, por favor envía un email a security@resguar.com en lugar de usar el issue tracker. Ver [SECURITY.md](SECURITY.md) para más detalles.

## 📄 Licencia

Este proyecto está licenciado bajo la [MIT License](LICENSE).

## 👥 Autores

**Resguar IT**
- Email: info@resguar.com

## 🙏 Agradecimientos

- AFIP por la documentación oficial
- Comunidad de desarrolladores de Argentina
- Todos los contribuidores

## Soporte

Para soporte, por favor abre un issue en el repositorio o contacta a [info@resguar.com](mailto:info@resguar.com).

## Documentación Adicional

- [Documentación oficial de AFIP](https://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf)
- [Web Services de AFIP](https://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf)

