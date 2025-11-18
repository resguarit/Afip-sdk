# Análisis de Configuración AFIP - Verificación de Código

## ✅ Verificaciones Realizadas

### 1. URLs de Homologación ✅ CORRECTO

Las URLs configuradas en `config/afip.php` son correctas:

- **WSAA (Testing):** `https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl` ✅
- **WSFE (Testing):** `https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL` ✅

**Ubicación:** `config/afip.php` líneas 39 y 46

### 2. Servicio en TRA ✅ CORRECTO

El servicio `wsfe` se está configurando correctamente en el TRA:

- **TraGenerator.php** línea 54: El elemento `<service>` recibe el valor correcto
- **WsaaService.php** línea 102-103: Se genera el TRA con el servicio pasado como parámetro
- **WsfeService.php** línea 62: Se llama con `'wsfe'` explícitamente

**Verificación del XML generado:**
```xml
<loginTicketRequest version="1.0">
    <header>
        ...
    </header>
    <service>wsfe</service>  <!-- ✅ Correcto -->
</loginTicketRequest>
```

### 3. Manejo de Certificados ✅ CORRECTO

El código maneja correctamente:
- ✅ Carga de certificado (`.crt`)
- ✅ Carga de clave privada (`.key`)
- ✅ Validación de coincidencia entre certificado y clave
- ✅ Validación de expiración
- ✅ Verificación de CUIT

**Ubicación:** `src/Services/CertificateManager.php` y `src/Helpers/CmsHelper.php`

### 4. Generación de CMS ⚠️ POSIBLE PROBLEMA

**Problema detectado:** El comando OpenSSL usa `-nocerts` que excluye el certificado del CMS.

**Ubicación:** `src/Helpers/CmsHelper.php` líneas 57 y 128

**Comando actual:**
```bash
openssl cms -sign -in %s -out %s -signer %s -inkey %s -outform DER -nodetach -nocerts
```

**Análisis:**
- Según la documentación de AFIP, el CMS debe incluir el certificado público
- El flag `-nocerts` excluye el certificado del mensaje CMS
- Esto puede causar errores como "Certificado no encontrado" o "CMS inválido"

**Solución sugerida:** Remover `-nocerts` o usar `-certfile` para incluir explícitamente el certificado.

---

## 🔍 Puntos a Verificar Manualmente

### 1. Archivos de Certificado

Verifica que tengas **ambos** archivos:

```bash
# Verificar que existan los archivos
ls -la storage/certificates/clave_privada.key
ls -la storage/certificates/certificado.crt

# Verificar permisos (la clave privada debe ser 600)
chmod 600 storage/certificates/clave_privada.key
chmod 644 storage/certificates/certificado.crt
```

**⚠️ IMPORTANTE:** 
- El archivo `.key` (clave privada) **NO** se descarga de ARCA
- Se genera en tu computadora cuando creas el CSR (Certificate Signing Request)
- Si perdiste la `.key`, debes crear un certificado nuevo desde cero

### 2. Configuración en `.env`

Verifica tu archivo `.env`:

```env
AFIP_ENVIRONMENT=testing
AFIP_CUIT=20457809027  # Tu CUIT (sin guiones)
AFIP_CERTIFICATES_PATH=storage/certificates
AFIP_CERTIFICATE_KEY=clave_privada.key
AFIP_CERTIFICATE_CRT=certificado.crt
AFIP_CERTIFICATE_PASSWORD=  # Dejar vacío si no tiene contraseña
```

### 3. Verificar Certificado y Clave Privada

Ejecuta este comando para verificar que coincidan:

```bash
openssl x509 -noout -modulus -in storage/certificates/certificado.crt | openssl md5
openssl rsa -noout -modulus -in storage/certificates/clave_privada.key | openssl md5
```

**Ambos comandos deben devolver el mismo hash.** Si no coinciden, el certificado y la clave privada no son del mismo par.

### 4. Verificar Autorización en ARCA

En ARCA (ambiente de Testing), verifica:
- ✅ Certificado activado
- ✅ Autorización creada para el servicio `wsfe`
- ✅ CUIT correcto vinculado

---

## 🛠️ Correcciones Sugeridas

### Corrección 1: Incluir Certificado en CMS

**Archivo:** `src/Helpers/CmsHelper.php`

**Cambio sugerido:** Remover `-nocerts` del comando OpenSSL para incluir el certificado en el CMS.

**Antes:**
```php
'openssl cms -sign -in %s -out %s -signer %s -inkey %s -outform DER -nodetach -nocerts',
```

**Después:**
```php
'openssl cms -sign -in %s -out %s -signer %s -inkey %s -outform DER -nodetach',
```

**Razón:** AFIP requiere que el CMS incluya el certificado público para validar la firma.

---

## 🧪 Pruebas Recomendadas

### Test 1: Verificar Autenticación

```php
use Resguar\AfipSdk\Facades\Afip;

try {
    $isAuth = Afip::isAuthenticated();
    echo $isAuth ? "✅ Autenticado" : "❌ No autenticado";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
```

### Test 2: Obtener Token Manualmente

```php
use Resguar\AfipSdk\Services\WsaaService;

// Ver logs para debugging
// Revisa storage/logs/laravel.log para ver detalles del proceso
```

### Test 3: Diagnóstico Completo

```php
use Resguar\AfipSdk\Facades\Afip;

$diagnosis = Afip::diagnoseAuthenticationIssue();
print_r($diagnosis);
```

---

## 📋 Checklist de Verificación

Antes de reportar un error, verifica:

- [ ] Certificado (`.crt`) descargado de ARCA
- [ ] Clave privada (`.key`) generada localmente (NO descargada)
- [ ] Ambos archivos en la ruta configurada
- [ ] Permisos correctos (600 para `.key`, 644 para `.crt`)
- [ ] Certificado y clave privada coinciden (verificar con openssl)
- [ ] CUIT configurado correctamente en `.env`
- [ ] Entorno configurado como `testing` (no `production`)
- [ ] Certificado activado en ARCA (ambiente Testing)
- [ ] Autorización creada para `wsfe` en ARCA
- [ ] CUIT del certificado coincide con el configurado

---

## 🐛 Errores Comunes y Soluciones

### Error: "Certificado no emitido por AC de confianza"

**Causa:** Estás usando certificado de Testing contra URL de Producción (o viceversa).

**Solución:** Verifica `AFIP_ENVIRONMENT=testing` en `.env`

### Error: "CMS inválido" o "Firma inválida"

**Causas posibles:**
1. El certificado y la clave privada no coinciden
2. El CMS no incluye el certificado (ver Corrección 1)
3. El certificado está corrupto

**Solución:**
1. Verificar coincidencia con comandos openssl (ver sección 3)
2. Aplicar Corrección 1
3. Regenerar certificado desde ARCA

### Error: "Computador no autorizado a acceder al servicio"

**Causa:** El certificado no está activado o autorizado en ARCA para el servicio `wsfe`.

**Solución:** 
1. Ir a ARCA (Testing)
2. Verificar que el certificado esté activado
3. Verificar que exista autorización para `wsfe`
4. Verificar que el CUIT coincida

### Error: "Error al cargar clave privada"

**Causas posibles:**
1. Contraseña incorrecta
2. Archivo corrupto
3. Permisos incorrectos

**Solución:**
1. Verificar `AFIP_CERTIFICATE_PASSWORD` en `.env`
2. Verificar permisos: `chmod 600 storage/certificates/clave_privada.key`
3. Regenerar certificado si está corrupto

---

## 📞 Siguiente Paso

Si después de verificar todo lo anterior sigue fallando:

1. **Ejecuta el diagnóstico:**
   ```php
   $diagnosis = Afip::diagnoseAuthenticationIssue();
   ```

2. **Revisa los logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep AFIP
   ```

3. **Comparte:**
   - Resultado del diagnóstico
   - Últimas líneas del log
   - Mensaje de error exacto
   - Configuración (sin datos sensibles)

---

## 📚 Referencias

- [Documentación AFIP](https://www.afip.gob.ar/fe/)
- [Manual WSAA](documentacion_afip/WSAAmanualDev.pdf)
- [Manual ARCA](documentacion_afip/manual-desarrollador-ARCA-COMPG-v4-0.pdf)




