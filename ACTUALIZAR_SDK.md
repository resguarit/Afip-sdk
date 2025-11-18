# 🔄 Cómo Actualizar el SDK Correctamente

## ❌ Problema: Composer Detecta Cambios Locales

```
resguar/afip-sdk has modified files:
M src/Builders/InvoiceBuilder.php
M src/Helpers/CmsHelper.php
Discard changes [y,n,v,d,s,?]? n
Update aborted
```

**Causa:** Composer detectó que hay cambios locales en el SDK instalado y pregunta si descartarlos.

## ✅ Solución: Descartar Cambios Locales

Cuando Composer pregunte si descartar cambios, responde **`y`** (yes):

```bash
cd apps/backend
composer require resguar/afip-sdk:dev-main

# Cuando pregunte:
# Discard changes [y,n,v,d,s,?]? 
# Responde: y
```

O fuerza la actualización sin preguntar:

```bash
cd apps/backend

# Opción 1: Forzar actualización
composer update resguar/afip-sdk:dev-main --no-interaction

# Opción 2: Eliminar y reinstalar
composer remove resguar/afip-sdk
composer require resguar/afip-sdk:dev-main

# Opción 3: Limpiar cache de Composer primero
composer clear-cache
composer require resguar/afip-sdk:dev-main
```

## 🔍 Verificar que se Actualizó

```bash
# Ver la versión instalada
composer show resguar/afip-sdk

# Debe mostrar algo como:
# versions : dev-main ae9ef56
```

O verificar en código:

```bash
php artisan tinker
```

```php
use Resguar\AfipSdk\Facades\Afip;

// Verificar que el método de diagnóstico existe
method_exists(Afip::getFacadeRoot(), 'diagnoseAuthenticationIssue'); // Debe ser true
```

---

## 🔍 Problema Principal: Certificado No Activado en ARCA

El error **"No se ha encontrado certificado de firmador"** significa que:

**El certificado NO está activado en ARCA para el servicio WSFE**

Aunque veas el certificado en ARCA, debe estar:
1. ✅ **Activado** (estado "VALIDO")
2. ✅ **Con autorización para WSFE**

## ✅ Solución: Activar en ARCA

### Paso 1: Verificar Certificado

1. Ve a ARCA: https://www.afip.gob.ar/arqa/
2. Ingresa con tu CUIT: `20457809027`
3. Ve a **"Certificados"**
4. Busca tu certificado (serial: `1bfe290685dac75c` o `770c9971708cae1c`)
5. Verifica que esté en estado **"VALIDO"** ✅

### Paso 2: Verificar/Crear Autorización WSFE

1. En ARCA, ve a **"Autorizaciones"**
2. Busca si hay una fila con:
   - Servicio: **`wsfe`**
   - Dador: `20457809027`
3. **Si NO aparece**, crea la autorización:
   - Ve a **"Crear autorización a servicio"**
   - Completa el formulario:
     - **Nombre simbólico del DN autorizado:** `rggestion`
     - **CUIT del DN:** `20457809027`
     - **CUIT representada:** `20457809027`
     - **Nombre del servicio:** Selecciona **`wsfe - Facturacion Electronica`**
   - Haz clic en **"Crear autorización"**

### Paso 3: Activar Certificado (si no está activado)

Si el certificado aparece pero no está activado:

1. Ve a **"Agregar certificado a alias"**
2. Selecciona:
   - **Alias del DN:** `rggestion`
   - **Certificado:** Selecciona tu certificado
3. Haz clic en **"Agregar"**

---

## 🧪 Usar Diagnóstico Mejorado

Después de actualizar el SDK, usa el método de diagnóstico:

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
- ✅ **Problemas encontrados**
- ✅ **Sugerencias específicas**

---

## 📋 Checklist Completo

- [ ] **SDK actualizado:** `composer show resguar/afip-sdk` muestra versión reciente
- [ ] **Certificados renombrados:** `certificado.crt` y `clave_privada.key` ✅
- [ ] **Permisos correctos:** `chmod 600` para clave, `chmod 644` para certificado
- [ ] **Certificado activado en ARCA:** Estado "VALIDO" ✅
- [ ] **Autorización WSFE creada:** Aparece en tabla de autorizaciones ✅
- [ ] **Cache limpiado:** `php artisan config:clear`

---

## 🎯 Comandos Rápidos

```bash
# 1. Actualizar SDK (forzar)
cd apps/backend
composer update resguar/afip-sdk:dev-main --no-interaction

# Si falla, eliminar y reinstalar
composer remove resguar/afip-sdk
composer require resguar/afip-sdk:dev-main

# 2. Limpiar cache
php artisan config:clear
php artisan cache:clear

# 3. Verificar versión
composer show resguar/afip-sdk

# 4. Probar diagnóstico
php artisan tinker
# Luego: Afip::diagnoseAuthenticationIssue()

# 5. Probar
php artisan afip:test
```

---

## ⚠️ Importante

**El error "No se ha encontrado certificado de firmador" NO es un problema del SDK.**

Es un problema de **configuración en ARCA**:

1. El certificado debe estar **activado** en ARCA
2. Debe haber una **autorización** para el servicio **WSFE**
3. El CUIT del certificado debe coincidir con el configurado

**El SDK solo envía el certificado a AFIP. Si AFIP dice "no encontrado", significa que no está activado en su sistema (ARCA).**

---

**Sigue estos pasos y debería funcionar!** 🚀

