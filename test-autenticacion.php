<?php

/**
 * Script de prueba para verificar autenticación con AFIP
 * 
 * USO:
 * 1. Si estás en el proyecto que usa el SDK:
 *    php artisan tinker
 *    >>> require 'vendor/resguar/afip-sdk/test-autenticacion.php';
 * 
 * 2. O ejecuta directamente (si tienes acceso a Laravel):
 *    php test-autenticacion.php
 */

echo "🔍 Verificando configuración AFIP...\n\n";

// Verificar si estamos en un proyecto Laravel
if (!file_exists('vendor/autoload.php')) {
    echo "❌ Error: Este script debe ejecutarse desde un proyecto Laravel que use el SDK\n";
    echo "   Ve a tu proyecto Laravel y ejecuta: php artisan tinker\n";
    echo "   Luego copia y pega el código de abajo\n\n";
    exit(1);
}

require 'vendor/autoload.php';

// Intentar cargar Laravel
try {
    $app = require_once 'bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
} catch (\Exception $e) {
    echo "⚠️  No se pudo cargar Laravel. Ejecuta esto en tinker:\n\n";
    echo "use Resguar\AfipSdk\Facades\Afip;\n\n";
    echo "// 1. Verificar configuración\n";
    echo "echo 'CUIT: ' . config('afip.cuit') . PHP_EOL;\n";
    echo "echo 'Entorno: ' . config('afip.environment') . PHP_EOL;\n";
    echo "echo 'Ruta certificados: ' . config('afip.certificates.path') . PHP_EOL;\n\n";
    echo "// 2. Ejecutar diagnóstico\n";
    echo "\$diagnosis = Afip::diagnoseAuthenticationIssue();\n";
    echo "print_r(\$diagnosis);\n\n";
    echo "// 3. Probar autenticación\n";
    echo "try {\n";
    echo "    \$isAuth = Afip::isAuthenticated();\n";
    echo "    echo \$isAuth ? '✅ Autenticado' : '❌ No autenticado';\n";
    echo "} catch (\\Exception \$e) {\n";
    echo "    echo '❌ Error: ' . \$e->getMessage();\n";
    echo "}\n";
    exit(0);
}

use Resguar\AfipSdk\Facades\Afip;

echo "✅ Laravel cargado correctamente\n\n";

// 1. Verificar configuración
echo "📋 Configuración:\n";
echo "   CUIT: " . config('afip.cuit', 'NO CONFIGURADO') . "\n";
echo "   Entorno: " . config('afip.environment', 'NO CONFIGURADO') . "\n";
echo "   Ruta certificados: " . config('afip.certificates.path', 'NO CONFIGURADO') . "\n";
echo "   Certificado: " . config('afip.certificates.crt', 'NO CONFIGURADO') . "\n";
echo "   Clave privada: " . config('afip.certificates.key', 'NO CONFIGURADO') . "\n\n";

// 2. Ejecutar diagnóstico
echo "🔍 Ejecutando diagnóstico...\n";
try {
    $diagnosis = Afip::diagnoseAuthenticationIssue();
    
    echo "\n📊 Resultado del diagnóstico:\n";
    echo "   Config OK: " . ($diagnosis['config_ok'] ? '✅' : '❌') . "\n";
    echo "   Archivos OK: " . ($diagnosis['files_ok'] ? '✅' : '❌') . "\n";
    echo "   Certificado válido: " . ($diagnosis['certificate_valid'] ? '✅' : '❌') . "\n";
    echo "   Certificado coincide con clave: " . ($diagnosis['certificate_matches_key'] ? '✅' : '❌') . "\n";
    
    if (!empty($diagnosis['issues'])) {
        echo "\n⚠️  Problemas encontrados:\n";
        foreach ($diagnosis['issues'] as $issue) {
            echo "   - $issue\n";
        }
    }
    
    if (!empty($diagnosis['suggestions'])) {
        echo "\n💡 Sugerencias:\n";
        foreach ($diagnosis['suggestions'] as $suggestion) {
            echo "   - $suggestion\n";
        }
    }
    
    if (!empty($diagnosis['details'])) {
        echo "\n📝 Detalles:\n";
        foreach ($diagnosis['details'] as $key => $value) {
            if (is_array($value)) {
                echo "   $key: " . json_encode($value, JSON_PRETTY_PRINT) . "\n";
            } else {
                echo "   $key: $value\n";
            }
        }
    }
    
} catch (\Exception $e) {
    echo "❌ Error al ejecutar diagnóstico: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Probar autenticación
echo "🔐 Probando autenticación con AFIP...\n";
try {
    $isAuth = Afip::isAuthenticated();
    if ($isAuth) {
        echo "✅ ¡Autenticación exitosa! El token está en cache.\n";
    } else {
        echo "ℹ️  No hay token en cache. Intentando obtener nuevo token...\n";
        // Intentar obtener token (esto hará la llamada real a WSAA)
        try {
            $tokenResponse = Afip::getTokenAndSignature('wsfe');
            echo "✅ ¡Token obtenido exitosamente!\n";
            echo "   Token: " . substr($tokenResponse['token'], 0, 50) . "...\n";
        } catch (\Exception $e) {
            echo "❌ Error al obtener token: " . $e->getMessage() . "\n";
            echo "\n💡 Revisa los logs para más detalles:\n";
            echo "   tail -f storage/logs/laravel.log | grep AFIP\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n💡 Revisa los logs para más detalles:\n";
    echo "   tail -f storage/logs/laravel.log | grep AFIP\n";
}

echo "\n✅ Prueba completada\n";



