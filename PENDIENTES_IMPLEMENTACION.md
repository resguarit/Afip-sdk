# Pendientes de Implementación

Este documento lista todos los componentes que faltan implementar para completar el SDK.

## 🔴 Críticos (Necesarios para funcionamiento básico)

### 1. CertificateManager - Firma Digital
**Archivo:** `src/Services/CertificateManager.php`

```php
// Método: sign()
// Falta:
- [ ] Cargar clave privada con OpenSSL
- [ ] Firmar mensaje XML con SHA256
- [ ] Codificar firma en base64
- [ ] Manejar contraseña del certificado si existe
```

**Implementación sugerida:**
```php
public function sign(string $message): string
{
    $keyPath = $this->getKeyPath();
    $password = config('afip.certificates.password');
    
    // Cargar clave privada
    $privateKey = openssl_pkey_get_private(
        file_get_contents($keyPath),
        $password
    );
    
    if (!$privateKey) {
        throw new AfipException('Error al cargar clave privada');
    }
    
    // Firmar
    $signature = '';
    if (!openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new AfipException('Error al firmar mensaje');
    }
    
    openssl_free_key($privateKey);
    
    return base64_encode($signature);
}
```

### 2. CertificateManager - Validación de Certificado
**Archivo:** `src/Services/CertificateManager.php`

```php
// Método: validateCertificate()
// Falta:
- [ ] Verificar que el certificado no esté vencido
- [ ] Verificar que corresponda al CUIT configurado
- [ ] Verificar formato del certificado
- [ ] Validar cadena de certificados
```

### 3. WsaaService - Autenticación Completa
**Archivo:** `src/Services/WsaaService.php`

```php
// Método: getToken()
// Falta:
- [ ] Generar TRA usando TraGenerator
- [ ] Firmar TRA con CertificateManager
- [ ] Crear mensaje CMS (PKCS#7) con el TRA firmado
- [ ] Enviar a WSAA vía SOAP usando SoapHelper
- [ ] Parsear respuesta XML para extraer token y firma
- [ ] Crear TokenResponse con datos reales
```

**Flujo completo:**
1. Generar TRA XML
2. Firmar TRA
3. Crear CMS (PKCS#7)
4. Enviar a WSAA
5. Procesar respuesta

### 4. WsfeService - Autorización de Comprobantes
**Archivo:** `src/Services/WsfeService.php`

```php
// Método: authorizeInvoice()
// ✅ IMPLEMENTADO COMPLETAMENTE:
- [x] Consulta último comprobante (correlatividad automática)
- [x] Crear cliente SOAP para WSFE
- [x] Mapear datos del comprobante al formato AFIP
- [x] Construir estructura FECAERequest según especificación
- [x] Llamar método FECAESolicitar
- [x] Procesar respuesta y extraer CAE
- [x] Manejar errores y observaciones de AFIP
- [x] Validar respuesta antes de crear InvoiceResponse
```

**Estructura requerida según ARCA:**
- `FeCAEReq` con `FeCabReq` y `FeDetReq`
- `FeCabReq`: PuntoVta, CbteTipo
- `FeDetReq`: Array de comprobantes con todos los campos

### 5. WsfeService - Métodos de Consulta
**Archivo:** `src/Services/WsfeService.php`

```php
// Métodos implementados:
- [x] getLastAuthorizedInvoice() - FECompUltimoAutorizado ✅ IMPLEMENTADO
  - Se ejecuta automáticamente antes de autorizar
  - Asegura correlatividad de números

// Métodos faltantes:
- [ ] getInvoiceTypes() - FEParamGetTiposCbte
- [ ] getPointOfSales() - FEParamGetPtosVenta
- [ ] getTaxpayerStatus() - FEParamGetTiposDoc (o método específico)
```

## 🟡 Importantes (Mejoran funcionalidad)

### 6. InvoiceBuilder - Construcción desde Modelos
**Archivo:** `src/Builders/InvoiceBuilder.php`

```php
// Método: buildFromModel()
// Falta:
- [ ] Extraer datos del modelo Eloquent
- [ ] Procesar relaciones (customer, items, etc.)
- [ ] Mapear campos del modelo a formato AFIP
- [ ] Validar que el modelo tenga los campos necesarios
```

### 7. InvoiceBuilder - Construcción desde Array
**Archivo:** `src/Builders/InvoiceBuilder.php`

```php
// Método: buildFromArray()
// Falta:
- [ ] Validar estructura del array
- [ ] Mapear campos a formato AFIP
- [ ] Validar tipos de datos
- [ ] Aplicar transformaciones necesarias
```

### 8. InvoiceBuilder - Construcción desde Objeto
**Archivo:** `src/Builders/InvoiceBuilder.php`

```php
// Método: buildFromObject()
// Falta:
- [ ] Extraer propiedades públicas
- [ ] Procesar métodos getter si existen
- [ ] Mapear a formato AFIP
- [ ] Validar datos requeridos
```

## 🟢 Opcionales (Mejoras adicionales)

### 9. Helpers Adicionales

#### CmsHelper (PKCS#7)
```php
// Crear helper para generar mensajes CMS
// - Crear mensaje PKCS#7 con el TRA firmado
// - Codificar en base64
// - Preparar para envío a WSAA
```

#### InvoiceMapper
```php
// Helper para mapear datos de comprobante
// - Convertir formato interno a formato AFIP
// - Aplicar transformaciones de campos
// - Validar estructura antes de enviar
```

### 10. Tests

```php
// Tests unitarios faltantes:
- [ ] Tests para CertificateManager
- [ ] Tests para WsaaService
- [ ] Tests para WsfeService
- [ ] Tests para InvoiceBuilder
- [ ] Tests para ValidatorHelper
- [ ] Tests para TraGenerator
- [ ] Tests de integración (mocks de SOAP)
```

### 11. Documentación Adicional

- [ ] Ejemplos de uso completos
- [ ] Guía de troubleshooting
- [ ] Documentación de errores comunes
- [ ] Guía de migración desde otros SDKs

## 📋 Prioridad de Implementación

### Fase 1 (Crítico - Funcionalidad básica)
1. ✅ Estructura base (COMPLETADO)
2. 🔴 CertificateManager::sign() - Firma digital
3. 🔴 WsaaService::getToken() - Autenticación completa
4. 🔴 WsfeService::authorizeInvoice() - Autorización básica

### Fase 2 (Importante - Funcionalidad completa)
5. 🟡 WsfeService - Métodos de consulta
6. 🟡 InvoiceBuilder - Construcción completa
7. 🟡 CertificateManager::validateCertificate()

### Fase 3 (Opcional - Mejoras)
8. 🟢 Helpers adicionales
9. 🟢 Tests completos
10. 🟢 Documentación avanzada

## 🔧 Componentes Necesarios para Implementar

### Dependencias PHP
- ✅ `ext-openssl` - Para firma digital
- ✅ `ext-soap` - Para comunicación SOAP
- ✅ `ext-xml` - Para procesamiento XML (ya incluido en PHP)

### Librerías Externas (Opcionales)
- Considerar `robrichards/xmlseclibs` para firma XML avanzada
- Considerar `phpseclib/phpseclib` para operaciones criptográficas avanzadas

## 📝 Notas de Implementación

### Firma Digital (PKCS#7)
La firma digital para AFIP requiere:
1. Generar TRA XML
2. Firmar el XML con clave privada
3. Crear mensaje CMS (PKCS#7) que incluye:
   - El TRA original
   - La firma digital
   - El certificado público
4. Codificar todo en base64

### Comunicación SOAP
- Usar `SoapHelper::createClient()` para crear cliente
- Usar `SoapHelper::call()` para llamadas con retry
- Manejar errores SOAP específicos de AFIP
- Loggear requests/responses para debugging

### Mapeo de Datos
El formato interno del SDK debe mapearse al formato AFIP:
- `pointOfSale` → `PtoVta`
- `invoiceType` → `CbteTipo`
- `invoiceNumber` → `CbteDesde` / `CbteHasta`
- `date` → `FchVto` (formato Ymd)
- `customerCuit` → `DocNro`
- etc.

## ✅ Estado Actual

**Estructura:** ✅ 100% Completa
**Implementación Lógica:** ⚠️ 0% (solo estructura base)
**Tests:** ⚠️ 0% (solo estructura base)
**Documentación:** ✅ 80% (README completo, falta guías avanzadas)

**Total SDK:** ~40% completo (estructura lista, lógica pendiente)

