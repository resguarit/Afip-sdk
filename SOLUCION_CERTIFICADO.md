# 🔧 Solución: Error "No se ha encontrado certificado de firmador"

## 🔍 Problema Identificado

Tu configuración muestra:
- ✅ Certificado encontrado: `rggestion_348f6cb63d6dfe60.crt`
- ✅ Clave privada encontrada: `1887Word`
- ❌ **Pero el SDK busca:** `certificado.crt` y `clave_privada.key`

**Los nombres no coinciden con la configuración del `.env`**

## ✅ Solución Rápida

### Opción 1: Renombrar los Archivos (Recomendado)

```bash
cd "/Users/naimguarino/Documents/Resguar IT/POS/pos-system/apps/backend"

# Renombrar certificado
mv storage/certificates/rggestion_348f6cb63d6dfe60.crt storage/certificates/certificado.crt

# Renombrar clave privada
mv storage/certificates/1887Word storage/certificates/clave_privada.key

# Ajustar permisos (IMPORTANTE)
chmod 600 storage/certificates/clave_privada.key
chmod 644 storage/certificates/certificado.crt
```

### Opción 2: Actualizar el `.env`

Si prefieres mantener los nombres originales, actualiza tu `.env`:

```env
# Cambiar estos valores:
AFIP_CERTIFICATE_KEY=1887Word
AFIP_CERTIFICATE_CRT=rggestion_348f6cb63d6dfe60.crt
```

Luego:
```bash
php artisan config:clear
```

## 🔍 Verificar que el Certificado Esté Activado en ARCA

Según las imágenes que compartiste, veo que:

1. ✅ **Tienes certificados válidos** en ARCA (válidos hasta 2027)
2. ✅ **Tienes autorización para WSFE** (se ve en la tabla de autorizaciones)
3. ✅ **El CUIT coincide** (20457809027)

**IMPORTANTE:** Asegúrate de que el certificado que estás usando esté **activado** en ARCA:

1. Ve a ARCA (homologación): https://www.afip.gob.ar/arqa/
2. Ingresa con tu CUIT
3. Ve a **"Certificados"**
4. Verifica que el certificado con serial `1bfe290685dac75c` o `770c9971708cae1c` esté **"VALIDO"** ✅
5. Ve a **"Autorizaciones"**
6. Verifica que haya una autorización para el servicio **"wsfe"** ✅

## 🧪 Usar el Método de Diagnóstico

El SDK ahora tiene un método de diagnóstico. Úsalo para verificar todo:

```bash
php artisan tinker
```

```php
use Resguar\AfipSdk\Facades\Afip;

// Diagnosticar problemas
$diagnosis = Afip::diagnoseAuthenticationIssue();

// Ver resultados
print_r($diagnosis);
```

Esto te mostrará:
- ✅ Si los archivos existen
- ✅ Si el certificado es válido
- ✅ Si el certificado y la clave coinciden
- ✅ Si el CUIT coincide
- ✅ Problemas encontrados
- ✅ Sugerencias

## 📋 Checklist Completo

Antes de probar de nuevo, verifica:

- [ ] **Archivos renombrados correctamente:**
  - `certificado.crt` (o actualizado `.env`)
  - `clave_privada.key` (o actualizado `.env`)

- [ ] **Permisos correctos:**
  ```bash
  chmod 600 storage/certificates/clave_privada.key
  chmod 644 storage/certificates/certificado.crt
  ```

- [ ] **Configuración en `.env`:**
  ```env
  AFIP_ENVIRONMENT=testing
  AFIP_CUIT=20457809027
  AFIP_CERTIFICATES_PATH=storage/certificates
  AFIP_CERTIFICATE_KEY=clave_privada.key  # o 1887Word si no renombraste
  AFIP_CERTIFICATE_CRT=certificado.crt   # o rggestion_348f6cb63d6dfe60.crt
  AFIP_CERTIFICATE_PASSWORD=  # Dejar vacío si no tiene contraseña
  ```

- [ ] **Certificado activado en ARCA:**
  - Ve a ARCA (homologación)
  - Verifica que el certificado esté "VALIDO"
  - Verifica que haya autorización para "wsfe"

- [ ] **Limpiar cache:**
  ```bash
  php artisan config:clear
  php artisan cache:clear
  ```

## 🎯 Después de Corregir

Ejecuta el diagnóstico:

```bash
php artisan tinker
```

```php
use Resguar\AfipSdk\Facades\Afip;

$diagnosis = Afip::diagnoseAuthenticationIssue();

if (empty($diagnosis['issues'])) {
    echo "✅ Todo está correcto!\n";
} else {
    echo "❌ Problemas encontrados:\n";
    foreach ($diagnosis['issues'] as $issue) {
        echo "  - {$issue}\n";
    }
}
```

Luego prueba de nuevo:

```bash
php artisan afip:test
```

## ⚠️ Nota Importante sobre ARCA

El error "No se ha encontrado certificado de firmador" puede significar:

1. **El certificado no está activado en ARCA** (más común)
   - Solución: Activar el certificado en ARCA → Certificados → Activar

2. **El certificado no tiene autorización para WSFE**
   - Solución: Crear autorización en ARCA → Crear autorización a servicio → Seleccionar "wsfe"

3. **El certificado corresponde a otro CUIT**
   - Solución: Verificar que el CUIT del certificado coincida con el configurado

4. **Estás usando certificado de producción en entorno testing** (o viceversa)
   - Solución: Asegúrate de usar el entorno correcto

## 🔍 Verificar en ARCA

1. **Verificar certificado activado:**
   - ARCA → Certificados
   - Busca tu certificado (serial: `1bfe290685dac75c` o `770c9971708cae1c`)
   - Debe estar en estado **"VALIDO"**

2. **Verificar autorización WSFE:**
   - ARCA → Autorizaciones
   - Debe aparecer una fila con:
     - Dador: 20457809027
     - Servicio: **wsfe**
   - Si no aparece, crea la autorización:
     - ARCA → Crear autorización a servicio
     - Selecciona: Servicio = "wsfe", CUIT = 20457809027

## 📞 Si Sigue Fallando

1. Ejecuta el diagnóstico:
   ```php
   Afip::diagnoseAuthenticationIssue();
   ```

2. Revisa los logs:
   ```bash
   tail -f storage/logs/laravel.log | grep -i afip
   ```

3. Verifica que el certificado y la clave privada coincidan:
   ```bash
   openssl x509 -noout -modulus -in storage/certificates/certificado.crt | openssl md5
   openssl rsa -noout -modulus -in storage/certificates/clave_privada.key | openssl md5
   ```
   
   **Si los hashes NO coinciden** = El certificado y la clave no son del mismo par.

---

**¡Prueba renombrando los archivos primero!** Esa es la causa más probable del problema.

