# Mejoras en Métodos de Parámetros WSFE

## Resumen de Mejoras

Se han implementado mejores prácticas en los métodos `getAvailableReceiptTypes()` y `getAvailablePointsOfSale()` del servicio WSFE.

## Cambios Implementados

### 1. **Logging Detallado y Estructurado**

#### Antes:
- Logging mínimo solo en casos de error
- Sin información de timing
- Difícil debugging

#### Ahora:
- **Logging completo del ciclo de vida:**
  - Inicio de operación con contexto (CUIT, entorno)
  - Obtención desde cache o API
  - Timing detallado (total, llamada SOAP)
  - Conteo de resultados
  - Filtrado aplicado

- **Niveles de log apropiados:**
  - `info`: Operaciones principales y resultados
  - `debug`: Detalles técnicos, respuestas RAW (solo en modo debug)
  - `warning`: Situaciones anómalas pero no críticas
  - `error`: Errores con contexto completo

#### Ejemplo de logs:
```
[INFO] Obteniendo tipos de comprobantes disponibles (cuit=20123456789, environment=testing)
[DEBUG] Obteniendo autenticación de WSAA (cuit=20123456789)
[DEBUG] Creando cliente SOAP para WSFE (url=https://...)
[DEBUG] Llamando FEParamGetTiposCbte (cuit=20123456789)
[DEBUG] Respuesta recibida de FEParamGetTiposCbte (elapsed_ms=234.56)
[DEBUG] Procesando tipos de comprobantes (total_items=15)
[DEBUG] Tipos de comprobantes procesados (total_received=15, filtered_out=2, valid_results=13)
[INFO] Tipos de comprobantes obtenidos exitosamente (cuit=20123456789, count=13, total_elapsed_ms=456.78, soap_call_ms=234.56)
```

---

### 2. **Métricas de Performance**

#### Implementadas:
- **Timing total de operación** (desde inicio hasta resultado final)
- **Timing de llamada SOAP** (solo la comunicación con AFIP)
- **Métricas de cache hit/miss** con tiempos comparativos

#### Beneficios:
- Identificar cuellos de botella
- Validar efectividad del cache
- Monitorear degradación de performance
- Alertar sobre timeouts potenciales

---

### 3. **Validaciones Robustas**

#### Validación de CUIT:
```php
if (!ValidatorHelper::isValidCuit($cuit)) {
    throw new AfipException("CUIT inválido: {$cuit}");
}
```

#### Validación de estructura de respuesta:
- Verificación de campos obligatorios
- Manejo de respuestas vacías
- Detección de errores de AFIP
- Normalización de tipos (objeto único → array)

---

### 4. **Manejo de Errores Mejorado**

#### Errores de AFIP:
```php
if (isset($response->Errors)) {
    // Extrae y formatea errores de AFIP
    $messages[] = "[{$code}] {$msg}";
    throw new AfipException("Error de AFIP: {$msg}");
}
```

#### Excepciones detalladas:
- Contexto completo (CUIT, tipo de operación)
- Stack trace en logs
- Diferenciación entre errores de AFIP y errores técnicos

---

### 5. **Filtrado Inteligente**

#### Filtrado por vigencia:
```php
// No vigente aún
if ($fchDesde !== null && $now < $fchDesde) {
    $this->log('debug', 'Tipo de comprobante aún no vigente', [...]);
    continue;
}

// Expirado
if ($fchHasta !== null && $fchHasta > 0 && $now > $fchHasta) {
    $this->log('debug', 'Tipo de comprobante expirado', [...]);
    continue;
}
```

#### Filtrado de puntos bloqueados:
```php
if (strtoupper($blocked) === 'S') {
    $this->log('debug', 'Punto de venta bloqueado', [...]);
    continue;
}
```

#### Logging de filtrado:
- Reporta cuántos items fueron filtrados
- Detalla razón del filtrado
- Ayuda a diagnosticar problemas de habilitación

---

### 6. **Documentación PHPDoc Completa**

#### Antes:
```php
/**
 * Obtiene los tipos de comprobantes habilitados para un CUIT
 * @param string|null $cuit
 * @return array
 */
```

#### Ahora:
```php
/**
 * Obtiene los tipos de comprobantes habilitados para un CUIT (FEParamGetTiposCbte)
 *
 * Consulta a AFIP los tipos de comprobantes (facturas, notas de crédito, etc.) que el
 * contribuyente está habilitado a emitir. Los resultados se cachean automáticamente.
 *
 * @param string|null $cuit CUIT del contribuyente (opcional, usa config si no se proporciona)
 * @return array Lista normalizada de tipos de comprobantes con formato:
 *               [
 *                   'id' => int,
 *                   'code' => int,
 *                   'description' => string,
 *                   'from' => string|null (ISO date),
 *                   'to' => string|null (ISO date)
 *               ]
 * @throws AfipException Si hay error en la comunicación o respuesta inválida
 */
```

---

### 7. **Información Contextual en Respuestas**

#### Para puntos de venta:
```php
$this->log('info', 'Puntos de venta obtenidos exitosamente', [
    'cuit' => $cuit,
    'count' => count($normalized),
    'total_elapsed_ms' => $totalElapsed,
    'soap_call_ms' => $callElapsed,
    'pos_numbers' => array_column($normalized, 'number'), // Lista de números
]);
```

Esto facilita:
- Debugging rápido
- Auditoría de operaciones
- Detección de inconsistencias

---

## Uso

### Obtener Tipos de Comprobantes
```php
use Resguar\AfipSdk\Services\AfipService;

$afip = app(AfipService::class);

try {
    // Sin CUIT (usa el de configuración)
    $types = $afip->getAvailableReceiptTypes();
    
    // Con CUIT específico
    $types = $afip->getAvailableReceiptTypes('20-12345678-9');
    
    foreach ($types as $type) {
        echo "ID: {$type['id']}\n";
        echo "Descripción: {$type['description']}\n";
        echo "Vigencia: {$type['from']} → {$type['to']}\n";
    }
} catch (\Resguar\AfipSdk\Exceptions\AfipException $e) {
    // Manejo de error
    Log::error('Error al obtener tipos de comprobantes', [
        'message' => $e->getMessage(),
    ]);
}
```

### Obtener Puntos de Venta
```php
try {
    $pointsOfSale = $afip->getAvailablePointsOfSale('20-12345678-9');
    
    foreach ($pointsOfSale as $pos) {
        echo "Número: {$pos['number']}\n";
        echo "Tipo: {$pos['type']}\n";
        echo "Habilitado: " . ($pos['enabled'] ? 'Sí' : 'No') . "\n";
    }
} catch (\Resguar\AfipSdk\Exceptions\AfipException $e) {
    Log::error('Error al obtener puntos de venta', [
        'message' => $e->getMessage(),
    ]);
}
```

---

## Testing

### Script de prueba incluido
Se incluye `test-parametros.php` que:
- ✅ Prueba obtención de tipos de comprobantes
- ✅ Prueba obtención de puntos de venta  
- ✅ Verifica funcionamiento del cache
- ✅ Muestra métricas de performance
- ✅ Formatea salida con colores para facilitar lectura

### Ejecutar:
```bash
php test-parametros.php
```

---

## Mejoras Futuras Sugeridas

1. **Rate Limiting**: Implementar límites de llamadas a AFIP
2. **Retry Strategy**: Política de reintentos más sofisticada
3. **Circuit Breaker**: Prevenir cascadas de fallas
4. **Metrics Collection**: Integración con Prometheus/Grafana
5. **Cache Warming**: Pre-calentar cache en horarios de bajo tráfico

---

## Compatibilidad

- ✅ Backward compatible (no rompe código existente)
- ✅ Mantiene estructura de respuesta
- ✅ Respeta configuración de cache existente
- ✅ Compatible con Laravel 8.x, 9.x, 10.x, 11.x

---

## Changelog

### [Mejoras 2025-11-26]
- ✨ Logging estructurado y detallado
- ✨ Métricas de performance (timing)
- ✨ Validación robusta de CUIT
- ✨ Manejo mejorado de errores de AFIP
- ✨ Filtrado inteligente con logging
- ✨ Documentación PHPDoc completa
- ✨ Script de testing incluido
- 🐛 Fix: Detección correcta de puntos bloqueados
- 🐛 Fix: Normalización de respuestas vacías
