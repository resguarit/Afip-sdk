# 🔧 Instalación del SDK en Proyecto POS

## ❌ Problema 1: Error de Composer

```
Problem 1
- Root composer.json requires resguar/afip-sdk, it could not be found in any version
```

**Causa:** Falta agregar el repositorio en `composer.json`

## ✅ Solución: Agregar Repositorio

### Paso 1: Editar `composer.json`

Abre el archivo `apps/backend/composer.json` y agrega el repositorio:

```json
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
```

**O si ya tienes un `composer.json`, agrega solo la sección `repositories`:**

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/resguarit/Afip-sdk.git"
        }
    ],
    "require": {
        "php": "^8.1",
        "laravel/framework": "^11.0",
        "resguar/afip-sdk": "dev-main"
        // ... otros paquetes
    }
}
```

### Paso 2: Instalar

```bash
cd apps/backend
composer require resguar/afip-sdk:dev-main
```

### Paso 3: Publicar Configuración

```bash
php artisan vendor:publish --tag=afip-config
```

---

## ❌ Problema 2: Certificado No Encontrado

```
Error: Certificado no encontrado: storage/certificates/certificado.crt
```

**Causa:** Los nombres de archivo no coinciden con la configuración

## ✅ Solución: Configurar Nombres Correctos

### Opción A: Renombrar Archivos (Recomendado)

```bash
cd apps/backend

# Ver qué archivos tienes
ls -la storage/certificates/

# Renombrar (ajusta los nombres según lo que tengas)
mv storage/certificates/rggestion_348f6cb63d6dfe60.crt storage/certificates/certificado.crt
mv storage/certificates/1887Word storage/certificates/clave_privada.key

# Ajustar permisos
chmod 600 storage/certificates/clave_privada.key
chmod 644 storage/certificates/certificado.crt
```

### Opción B: Actualizar `.env`

Si prefieres mantener los nombres originales, edita `apps/backend/.env`:

```env
AFIP_CERTIFICATE_KEY=1887Word
AFIP_CERTIFICATE_CRT=rggestion_348f6cb63d6dfe60.crt
```

Luego:
```bash
php artisan config:clear
```

---

## ❌ Problema 3: "No se ha encontrado certificado de firmador"

Este error significa que **el certificado no está activado en ARCA** o **no tiene autorización para WSFE**.

## ✅ Solución: Activar en ARCA

### Paso 1: Verificar en ARCA

1. Ve a ARCA (homologación): https://www.afip.gob.ar/arqa/
2. Ingresa con tu CUIT: `20457809027`
3. Ve a **"Certificados"**
4. Busca tu certificado (serial: `1bfe290685dac75c` o `770c9971708cae1c`)
5. Verifica que esté en estado **"VALIDO"** ✅

### Paso 2: Verificar Autorización WSFE

1. En ARCA, ve a **"Autorizaciones"**
2. Debe aparecer una fila con:
   - Dador: `20457809027`
   - Servicio: **`wsfe`**
3. Si **NO aparece**, crea la autorización:
   - Ve a **"Crear autorización a servicio"**
   - Selecciona:
     - Nombre simbólico del DN: `rggestion`
     - CUIT del DN: `20457809027`
     - CUIT representada: `20457809027`
     - Nombre del servicio: **`wsfe - Facturacion Electronica`**
   - Haz clic en **"Crear autorización"**

### Paso 3: Activar Certificado (si no está activado)

1. En ARCA, ve a **"Certificados"**
2. Busca tu certificado
3. Si no está activado, haz clic en **"Activar"** o **"Agregar certificado a alias"**

---

## ❌ Problema 4: Método `diagnoseAuthenticationIssue()` No Encontrado

```
Error: Call to undefined method diagnoseAuthenticationIssue()
```

**Causa:** El SDK instalado es una versión antigua

## ✅ Solución: Actualizar SDK

```bash
cd apps/backend

# Actualizar a la última versión
composer update resguar/afip-sdk:dev-main

# Limpiar cache
php artisan config:clear
php artisan cache:clear
```

---

## 🧪 Verificar Instalación Completa

### Paso 1: Verificar que el SDK esté instalado

```bash
cd apps/backend
composer show resguar/afip-sdk
```

### Paso 2: Verificar Configuración

```bash
php artisan tinker
```

```php
// Verificar configuración
config('afip.cuit');           // Debe mostrar: 20457809027
config('afip.environment');    // Debe mostrar: testing
config('afip.certificates.path'); // Debe mostrar la ruta correcta

// Verificar que los archivos existan
$certPath = config('afip.certificates.path') . '/' . config('afip.certificates.crt');
$keyPath = config('afip.certificates.path') . '/' . config('afip.certificates.key');

file_exists($certPath);  // Debe ser true
file_exists($keyPath);   // Debe ser true
```

### Paso 3: Usar Diagnóstico (si está disponible)

```php
use Resguar\AfipSdk\Facades\Afip;

$diagnosis = Afip::diagnoseAuthenticationIssue();
print_r($diagnosis);
```

Esto te mostrará:
- ✅ Si los archivos existen
- ✅ Si el certificado es válido
- ✅ Si el certificado y la clave coinciden
- ✅ Problemas encontrados
- ✅ Sugerencias

---

## 📋 Checklist Completo

Antes de probar, verifica:

- [ ] **Repositorio agregado en `composer.json`**
- [ ] **SDK instalado:** `composer show resguar/afip-sdk`
- [ ] **Configuración publicada:** `config/afip.php` existe
- [ ] **Variables en `.env`:** CUIT, rutas, nombres de archivos
- [ ] **Archivos renombrados o `.env` actualizado**
- [ ] **Permisos correctos:** `chmod 600` para clave, `chmod 644` para certificado
- [ ] **Certificado activado en ARCA**
- [ ] **Autorización WSFE creada en ARCA**
- [ ] **Cache limpiado:** `php artisan config:clear`

---

## 🎯 Comandos Rápidos

```bash
# 1. Agregar repositorio (editar composer.json manualmente)
# 2. Instalar
cd apps/backend
composer require resguar/afip-sdk:dev-main

# 3. Publicar configuración
php artisan vendor:publish --tag=afip-config

# 4. Renombrar certificados
mv storage/certificates/rggestion_348f6cb63d6dfe60.crt storage/certificates/certificado.crt
mv storage/certificates/1887Word storage/certificates/clave_privada.key
chmod 600 storage/certificates/clave_privada.key
chmod 644 storage/certificates/certificado.crt

# 5. Limpiar cache
php artisan config:clear
php artisan cache:clear

# 6. Probar
php artisan afip:test
```

---

## ⚠️ Importante sobre ARCA

El error **"No se ha encontrado certificado de firmador"** significa que:

1. **El certificado NO está activado en ARCA** (más común)
   - Solución: Activar en ARCA → Certificados → Activar

2. **NO hay autorización para WSFE**
   - Solución: Crear autorización en ARCA → Crear autorización a servicio → Seleccionar "wsfe"

3. **El certificado corresponde a otro CUIT**
   - Solución: Verificar que el CUIT del certificado coincida con el configurado

4. **Estás usando certificado de producción en testing** (o viceversa)
   - Solución: Asegúrate de usar el entorno correcto

---

**¡Sigue estos pasos en orden y debería funcionar!** 🚀

