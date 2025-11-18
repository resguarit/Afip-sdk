# 🔍 Verificar Certificado Asociado al Alias en ARCA

## 🎯 Problema

Aunque la configuración en ARCA parece correcta:
- ✅ Alias `rggestion` existe
- ✅ Autorización `wsfe` para alias `rggestion` existe

El error `ns1:cms.cert.notFound` persiste, lo que indica que el certificado que usa el SDK no coincide con el asociado al alias en ARCA.

## ✅ Solución: Verificar Certificado Asociado al Alias

### Paso 1: Ver Detalles del Certificado en ARCA

1. En ARCA → **"Certificados"**
2. Haz clic en **"Ver"** en la fila del alias `rggestion`
3. Esto te mostrará los **certificados asociados** a ese alias
4. Anota el **serial number** de cada certificado que aparezca

### Paso 2: Comparar con el Certificado del SDK

El SDK está usando el certificado con serial: `348f6cb63d6dfe60`

**Verifica:**
- ¿El serial `348f6cb63d6dfe60` aparece en la lista de certificados asociados al alias `rggestion`?
- Si **NO aparece**, ese es el problema

### Paso 3: Agregar Certificado al Alias (si no está asociado)

Si el certificado `348f6cb63d6dfe60` **NO** está asociado al alias `rggestion`:

1. En ARCA, ve a **"Agregar certificado a alias"**
2. Selecciona:
   - **Alias:** `rggestion`
   - **Certificado:** El certificado con serial `348f6cb63d6dfe60`
3. Haz clic en **"Agregar"** o **"Confirmar"**

### Paso 4: Verificar que el Certificado Esté Activo

1. En ARCA → **"Certificados"** → Haz clic en **"Ver"** en `rggestion`
2. En la sección **"Certificados asociados"**, verifica:
   - Que el certificado `348f6cb63d6dfe60` aparezca
   - Que su estado sea **"VALIDO"** ✅

## 🔍 Verificación Completa

Después de agregar el certificado al alias, verifica:

1. ✅ **Certificado asociado al alias:**
   - ARCA → Certificados → Ver `rggestion`
   - El certificado `348f6cb63d6dfe60` debe aparecer en "Certificados asociados"

2. ✅ **Autorización correcta:**
   - ARCA → Autorizaciones
   - Debe aparecer: Alias `rggestion`, Servicio `wsfe`

3. ✅ **Certificado en el SDK:**
   - El archivo `certificado.crt` debe ser el mismo que el certificado `348f6cb63d6dfe60`

## 🧪 Probar Después de Corregir

```bash
cd "/Users/naimguarino/Documents/Resguar IT/POS/pos-system/apps/backend"

# Limpiar cache
php artisan config:clear
php artisan cache:clear

# Probar
php artisan afip:test
```

## ⚠️ Nota Importante

El alias `rggestion` puede tener **múltiples certificados asociados**. Solo los certificados que están:
1. ✅ Asociados al alias `rggestion`
2. ✅ En estado "VALIDO"
3. ✅ Con autorización para `wsfe`

Podrán ser usados para autenticarse con AFIP.

---

**Siguiente paso:** Haz clic en "Ver" en el alias `rggestion` y comparte qué certificados aparecen en "Certificados asociados".


