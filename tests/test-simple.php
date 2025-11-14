<?php

/**
 * Script de prueba simple para el SDK de AFIP
 * 
 * USO:
 * 1. Configura las variables de entorno en .env o aquí mismo
 * 2. Ejecuta: php tests/test-simple.php
 */

require __DIR__ . '/../vendor/autoload.php';

// Si estás usando Laravel, carga el framework
if (file_exists(__DIR__ . '/../bootstrap/app.php')) {
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}

// Configuración manual (si no usas Laravel)
if (!function_exists('config')) {
    function config($key, $default = null) {
        // Configuración básica para pruebas
        $config = [
            'afip.environment' => 'testing',
            'afip.cuit' => getenv('AFIP_CUIT') ?: '20123456789',
            'afip.certificates.path' => getenv('AFIP_CERTIFICATES_PATH') ?: __DIR__ . '/../certificates',
            'afip.certificates.key' => getenv('AFIP_CERTIFICATE_KEY') ?: 'private_key.key',
            'afip.certificates.crt' => getenv('AFIP_CERTIFICATE_CRT') ?: 'certificate.crt',
            'afip.certificates.password' => getenv('AFIP_CERTIFICATE_PASSWORD') ?: null,
            'afip.cache.enabled' => true,
            'afip.logging.enabled' => true,
        ];
        
        return $config[$key] ?? $default;
    }
}

echo "🧪 Prueba Simple del SDK de AFIP\n";
echo str_repeat("=", 50) . "\n\n";

// Verificar configuración
$cuit = config('afip.cuit');
$certPath = config('afip.certificates.path');
$certKey = config('afip.certificates.key');
$certCrt = config('afip.certificates.crt');

echo "📋 Configuración:\n";
echo "   CUIT: {$cuit}\n";
echo "   Entorno: " . config('afip.environment') . "\n";
echo "   Ruta certificados: {$certPath}\n";
echo "   Archivo clave: {$certKey}\n";
echo "   Archivo certificado: {$certCrt}\n\n";

// Verificar que existan los certificados
$keyPath = $certPath . '/' . $certKey;
$crtPath = $certPath . '/' . $certCrt;

if (!file_exists($keyPath)) {
    echo "❌ ERROR: No se encuentra la clave privada en: {$keyPath}\n";
    echo "   Verifica la configuración de AFIP_CERTIFICATES_PATH y AFIP_CERTIFICATE_KEY\n";
    exit(1);
}

if (!file_exists($crtPath)) {
    echo "❌ ERROR: No se encuentra el certificado en: {$crtPath}\n";
    echo "   Verifica la configuración de AFIP_CERTIFICATES_PATH y AFIP_CERTIFICATE_CRT\n";
    exit(1);
}

echo "✅ Certificados encontrados\n\n";

// Si estás usando Laravel, usar el Facade
if (class_exists(\Resguar\AfipSdk\Facades\Afip::class)) {
    try {
        echo "🔐 Probando autenticación...\n";
        
        $wsaaService = app(\Resguar\AfipSdk\Services\WsaaService::class);
        $tokenResponse = $wsaaService->getToken('wsfe');
        
        echo "✅ Autenticación exitosa!\n";
        echo "   Token: " . substr($tokenResponse->token, 0, 30) . "...\n";
        echo "   Válido hasta: " . $tokenResponse->expirationDate->format('Y-m-d H:i:s') . "\n\n";
        
        echo "📄 Consultando último comprobante...\n";
        $pointOfSale = 1;
        $invoiceType = 1;
        
        $lastInvoice = \Resguar\AfipSdk\Facades\Afip::getLastAuthorizedInvoice($pointOfSale, $invoiceType);
        
        echo "✅ Último comprobante:\n";
        echo "   Número: " . ($lastInvoice['CbteNro'] ?? 0) . "\n";
        echo "   Fecha: " . ($lastInvoice['CbteFch'] ?? 'N/A') . "\n\n";
        
        echo "🎉 Pruebas completadas exitosamente!\n";
        
    } catch (\Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        if (method_exists($e, 'getAfipCode') && $e->getAfipCode()) {
            echo "   Código AFIP: " . $e->getAfipCode() . "\n";
        }
        if (method_exists($e, 'getAfipMessage') && $e->getAfipMessage()) {
            echo "   Mensaje AFIP: " . $e->getAfipMessage() . "\n";
        }
        echo "\n   Archivo: " . $e->getFile() . "\n";
        echo "   Línea: " . $e->getLine() . "\n";
        exit(1);
    }
} else {
    echo "⚠️  Laravel no está disponible. Usa este script en un proyecto Laravel.\n";
    echo "   O configura manualmente los servicios del SDK.\n";
}

