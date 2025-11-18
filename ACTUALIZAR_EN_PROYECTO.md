# 🔄 Actualizar SDK en Proyecto que lo Usa

## ✅ Corrección Aplicada en el SDK

Se corrigió el error crítico en `CmsHelper.php`:
- ❌ **Antes:** Usaba `-nocerts` que excluía el certificado del CMS
- ✅ **Ahora:** El certificado se incluye en el CMS (AFIP lo requiere)

## 📋 Pasos para Actualizar en el Otro Proyecto

### Paso 1: Commit y Push en el SDK (Este Proyecto)

```bash
# En el proyecto SDK (afip-sdk-resguar)
cd "/Users/naimguarino/Documents/Resguar IT/POS/afip-sdk-resguar"

# Agregar cambios
git add src/Helpers/CmsHelper.php

# Commit
git commit -m "fix: Remover -nocerts de comandos OpenSSL para incluir certificado en CMS

- AFIP requiere el certificado en el CMS para validarlo
- Error anterior: ns1:cms.cert.notFound
- Solución: Remover flag -nocerts de createCms() y createCmsFromContent()"

# Push al repositorio
git push origin main
```

### Paso 2: Actualizar SDK en el Proyecto que lo Usa

```bash
# Ir al proyecto que usa el SDK (ej: apps/backend)
cd /ruta/a/tu/proyecto/apps/backend

# Opción 1: Actualizar forzando (recomendado)
composer update resguar/afip-sdk:dev-main --no-interaction

# Opción 2: Si falla, eliminar y reinstalar
composer remove resguar/afip-sdk
composer require resguar/afip-sdk:dev-main

# Opción 3: Si Composer detecta cambios locales y pregunta
# Responde: y (yes) para descartar cambios locales
```

### Paso 3: Limpiar Cache de Laravel

```bash
# En el proyecto que usa el SDK
php artisan config:clear
php artisan cache:clear
```

### Paso 4: Verificar que se Actualizó

```bash
# Ver la versión instalada
composer show resguar/afip-sdk

# Debe mostrar algo como:
# versions : dev-main [hash del commit]
```

### Paso 5: Verificar el Cambio en el Código

```bash
# Verificar que el archivo no tenga -nocerts
grep -n "nocerts" vendor/resguar/afip-sdk/src/Helpers/CmsHelper.php

# NO debe encontrar nada (o solo comentarios que dicen "NO usar -nocerts")
```

### Paso 6: Probar Autenticación

```bash
# Probar autenticación con AFIP
php artisan tinker
```

```php
use Resguar\AfipSdk\Facades\Afip;

// Intentar autenticación
try {
    $authenticated = Afip::isAuthenticated();
    echo $authenticated ? "✅ Autenticación exitosa\n" : "❌ No autenticado\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
```

## ✅ Verificación Final

Después de actualizar, el SDK debe:

1. ✅ **Incluir el certificado en el CMS** (sin `-nocerts`)
2. ✅ **Autenticarse correctamente con WSAA**
3. ✅ **No mostrar error:** `ns1:cms.cert.notFound`

## 🔍 Si Composer Detecta Cambios Locales

Si Composer pregunta:

```
resguar/afip-sdk has modified files:
M src/Helpers/CmsHelper.php
Discard changes [y,n,v,d,s,?]?
```

**Responde:** `y` (yes) para descartar los cambios locales y usar la versión del repositorio.

## 🚨 Si Persiste el Error

Si después de actualizar sigue apareciendo el error `ns1:cms.cert.notFound`:

1. **Verifica que el cambio esté en el código:**
   ```bash
   cat vendor/resguar/afip-sdk/src/Helpers/CmsHelper.php | grep -A 3 "openssl cms"
   ```
   
   Debe mostrar comandos **SIN** `-nocerts`

2. **Verifica que el certificado esté activado en ARCA:**
   - Ve a ARCA: https://www.afip.gob.ar/arqa/
   - Verifica que el certificado esté en estado "VALIDO"
   - Verifica que haya autorización para WSFE

3. **Limpia completamente el cache:**
   ```bash
   composer clear-cache
   rm -rf vendor/resguar/afip-sdk
   composer install
   php artisan config:clear
   php artisan cache:clear
   ```

## 📝 Resumen de Comandos

```bash
# 1. En el proyecto SDK: Commit y push
cd "/Users/naimguarino/Documents/Resguar IT/POS/afip-sdk-resguar"
git add src/Helpers/CmsHelper.php
git commit -m "fix: Remover -nocerts de comandos OpenSSL"
git push origin main

# 2. En el proyecto que usa el SDK: Actualizar
cd /ruta/a/tu/proyecto/apps/backend
composer update resguar/afip-sdk:dev-main --no-interaction

# 3. Limpiar cache
php artisan config:clear
php artisan cache:clear

# 4. Verificar
composer show resguar/afip-sdk
```

---

**¡Listo!** Después de estos pasos, el SDK en el otro proyecto tendrá la corrección y debería autenticarse correctamente con AFIP. 🚀

