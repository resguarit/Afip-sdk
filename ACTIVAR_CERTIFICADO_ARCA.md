# 🔐 Guía: Activar Certificado en ARCA (Homologación)

## 🎯 Problema Actual

El error que estás viendo:
```
Código de error AFIP: ns1:cms.cert.notFound
Mensaje AFIP: No se ha encontrado certificado de firmador
```

**Significa:** El certificado existe en tu sistema, pero **NO está activado en ARCA** para el servicio WSFE.

## ✅ Solución: Activar Certificado en ARCA

### Paso 1: Acceder a ARCA (Homologación)

1. Ve a: **https://www.afip.gob.ar/arqa/**
2. Ingresa con tu CUIT: `20457809027`
3. Ingresa tu clave fiscal

### Paso 2: Verificar Certificados

1. En el menú, ve a **"Certificados"**
2. Busca tu certificado (puede aparecer con el serial o nombre)
3. Verifica que el estado sea **"VALIDO"** ✅

**Si el certificado NO aparece o está "INVALIDO":**
- Necesitas subir/activar el certificado primero
- Ve a "Certificados" → "Activar certificado"
- Sube el archivo `certificado.crt`

### Paso 3: Verificar Autorización para WSFE

1. En el menú, ve a **"Autorizaciones"**
2. Busca en la tabla si existe una fila con:
   - **Dador:** `20457809027`
   - **Servicio:** `wsfe` o `wsfe - Facturacion Electronica`
   - **Estado:** `VIGENTE` o `ACTIVO`

**Si NO existe la autorización:**

### Paso 4: Crear Autorización para WSFE

1. En ARCA, ve a **"Crear autorización a servicio"** (o "Autorizaciones" → "Nueva autorización")
2. Completa el formulario:
   - **Nombre simbólico del DN:** `rggestion` (o el nombre que prefieras)
   - **CUIT del DN:** `20457809027`
   - **CUIT representada:** `20457809027` (si representas a otra empresa, pon ese CUIT)
   - **Nombre del servicio:** Selecciona `wsfe - Facturacion Electronica`
   - **Entorno:** `Homologación` (testing)
3. Haz clic en **"Crear autorización"** o **"Confirmar"**

### Paso 5: Esperar Activación

- La autorización puede tardar unos minutos en activarse
- Refresca la página de "Autorizaciones" para verificar que aparezca como **"VIGENTE"**

## 🔍 Verificación Completa

Después de activar, verifica que tengas:

1. ✅ **Certificado activado:**
   - ARCA → Certificados
   - Estado: **VALIDO**

2. ✅ **Autorización WSFE creada:**
   - ARCA → Autorizaciones
   - Servicio: `wsfe`
   - Estado: **VIGENTE**

3. ✅ **CUIT correcto:**
   - El CUIT del certificado debe ser: `20457809027`
   - El CUIT en tu `.env` debe ser: `20457809027`

4. ✅ **Entorno correcto:**
   - Estás en ARCA **homologación** (testing)
   - Tu `.env` tiene: `AFIP_ENVIRONMENT=testing`

## 🧪 Probar Después de Activar

Una vez que hayas activado el certificado en ARCA:

```bash
cd "/Users/naimguarino/Documents/Resguar IT/POS/pos-system/apps/backend"

# Limpiar cache
php artisan config:clear
php artisan cache:clear

# Probar
php artisan afip:test
```

O usar el diagnóstico:

```bash
php artisan tinker
```

```php
use Resguar\AfipSdk\Facades\Afip;

$diagnosis = Afip::diagnoseAuthenticationIssue();
print_r($diagnosis);
```

## ⚠️ Problemas Comunes

### 1. "El certificado no aparece en ARCA"

**Causa:** El certificado no fue subido a ARCA.

**Solución:**
- Ve a ARCA → Certificados → "Activar certificado"
- Sube el archivo `certificado.crt`
- Espera a que se active (puede tardar unos minutos)

### 2. "La autorización no se crea"

**Causa:** Puede haber un problema con el certificado o el CUIT.

**Solución:**
- Verifica que el certificado esté activado primero
- Verifica que el CUIT del certificado coincida con el configurado
- Intenta crear la autorización de nuevo

### 3. "La autorización aparece pero sigue fallando"

**Causa:** Puede haber un delay en la propagación.

**Solución:**
- Espera 5-10 minutos después de crear la autorización
- Limpia el cache: `php artisan config:clear && php artisan cache:clear`
- Prueba de nuevo

### 4. "Estoy en producción pero el certificado está en homologación"

**Causa:** Estás usando el certificado de testing en producción (o viceversa).

**Solución:**
- Verifica que el entorno en `.env` coincida con ARCA:
  - `AFIP_ENVIRONMENT=testing` → ARCA homologación
  - `AFIP_ENVIRONMENT=production` → ARCA producción

## 📋 Checklist Final

Antes de probar, verifica:

- [ ] Certificado activado en ARCA (estado: VALIDO)
- [ ] Autorización WSFE creada (estado: VIGENTE)
- [ ] CUIT correcto en `.env` (`20457809027`)
- [ ] Entorno correcto (`testing` = ARCA homologación)
- [ ] Archivos de certificado correctos:
  - `storage/certificates/certificado.crt`
  - `storage/certificates/clave_privada.key`
- [ ] Permisos correctos:
  - `chmod 600 storage/certificates/clave_privada.key`
  - `chmod 644 storage/certificates/certificado.crt`
- [ ] Cache limpiado:
  - `php artisan config:clear`
  - `php artisan cache:clear`

## 🎯 Resultado Esperado

Después de activar correctamente, deberías ver:

```
✅ Token de autenticación obtenido exitosamente
✅ Último comprobante consultado: X
✅ Factura autorizada con CAE: XXXXXXXXXX
```

---

**¿Necesitas ayuda?** Ejecuta el diagnóstico y comparte los resultados:
```php
Afip::diagnoseAuthenticationIssue();
```

