<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Entorno de AFIP
    |--------------------------------------------------------------------------
    |
    | Especifica el entorno en el que se trabajará:
    | - 'testing': Entorno de homologación/pruebas
    | - 'production': Entorno de producción
    |
    */

    'environment' => env('AFIP_ENVIRONMENT', 'testing'),

    /*
    |--------------------------------------------------------------------------
    | CUIT del Contribuyente
    |--------------------------------------------------------------------------
    |
    | CUIT del contribuyente que realizará las operaciones con AFIP
    |
    */

    'cuit' => env('AFIP_CUIT'),

    /*
    |--------------------------------------------------------------------------
    | URLs de los Web Services
    |--------------------------------------------------------------------------
    |
    | URLs de los diferentes servicios web de AFIP según el entorno
    |
    */

    'wsaa' => [
        'url' => [
            'testing' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl',
            'production' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl',
        ],
    ],

    'wsfe' => [
        'url' => [
            'testing' => 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL',
            'production' => 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Certificados
    |--------------------------------------------------------------------------
    |
    | Ruta y nombres de los archivos de certificados digitales
    |
    | Para sistemas multi-CUIT, se soportan dos modos:
    |
    | 1. Modo simple (un solo CUIT):
    |    - Usa 'certificates.path' + 'certificates.key' + 'certificates.crt'
    |
    | 2. Modo multi-CUIT:
    |    - Usa 'certificates_base_path' + estructura de carpetas por CUIT
    |    - Estructura: {certificates_base_path}/{cuit}/certificate.crt
    |                  {certificates_base_path}/{cuit}/private.key
    |
    */

    'certificates' => [
        'path' => env('AFIP_CERTIFICATES_PATH', storage_path('app/afip/certificates')),
        'key' => env('AFIP_CERTIFICATE_KEY', 'private_key.key'),
        'crt' => env('AFIP_CERTIFICATE_CRT', 'certificate.crt'),
        'password' => env('AFIP_CERTIFICATE_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ruta Base para Certificados Multi-CUIT
    |--------------------------------------------------------------------------
    |
    | Si tu sistema maneja múltiples CUITs, puedes organizar los certificados
    | en subcarpetas por CUIT. Ejemplo:
    |
    |   storage/certificates/
    |   ├── 20123456789/
    |   │   ├── certificate.crt
    |   │   └── private.key
    |   ├── 30987654321/
    |   │   ├── certificate.crt
    |   │   └── private.key
    |   └── ...
    |
    | El SDK detectará automáticamente si existe una carpeta para el CUIT
    | y usará esos certificados. Si no existe, usará los de 'certificates'.
    |
    */

    'certificates_base_path' => env('AFIP_CERTIFICATES_BASE_PATH', storage_path('certificates')),

    /*
    |--------------------------------------------------------------------------
    | Configuración de Cache
    |--------------------------------------------------------------------------
    |
    | Configuración para el cacheo de tokens de autenticación
    | Los tokens de AFIP son válidos por 12 horas según especificación oficial
    |
    */

    'cache' => [
        'enabled' => env('AFIP_CACHE_ENABLED', true),
        'prefix' => env('AFIP_CACHE_PREFIX', 'afip_token_'),
        'ttl' => env('AFIP_CACHE_TTL', 43200), // 12 horas en segundos (según especificación AFIP)
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache de Parámetros de WSFE (Tipos de Comprobante, Puntos de Venta)
    |--------------------------------------------------------------------------
    |
    | Estos parámetros no cambian frecuentemente, por lo que se pueden cachear
    | por CUIT y entorno para mejorar el rendimiento.
    |
    */

    'param_cache' => [
        'enabled' => env('AFIP_PARAM_CACHE_ENABLED', true),
        // TTL en segundos (por defecto 6 horas)
        'ttl' => env('AFIP_PARAM_CACHE_TTL', 21600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Reintentos
    |--------------------------------------------------------------------------
    |
    | Configuración para reintentos automáticos en caso de errores temporales
    |
    */

    'retry' => [
        'enabled' => env('AFIP_RETRY_ENABLED', true),
        'max_attempts' => env('AFIP_RETRY_MAX_ATTEMPTS', 3),
        'delay' => env('AFIP_RETRY_DELAY', 1000), // milisegundos
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Logging
    |--------------------------------------------------------------------------
    |
    | Configuración para el registro de operaciones y errores
    |
    */

    'logging' => [
        'enabled' => env('AFIP_LOGGING_ENABLED', true),
        'channel' => env('AFIP_LOGGING_CHANNEL', 'daily'),
        'level' => env('AFIP_LOGGING_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout de Conexión
    |--------------------------------------------------------------------------
    |
    | Timeout en segundos para las conexiones con los servicios de AFIP
    |
    */

    'timeout' => env('AFIP_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Configuración de Puntos de Venta
    |--------------------------------------------------------------------------
    |
    | Configuración por defecto para puntos de venta
    |
    */

    'default_point_of_sale' => env('AFIP_DEFAULT_POINT_OF_SALE', 1),
];

