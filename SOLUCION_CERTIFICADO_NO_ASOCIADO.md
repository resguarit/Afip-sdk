# 🔧 Solución: Certificado No Asociado al Alias en ARCA

## 🎯 Problema Identificado

El certificado que está usando el SDK (`348f6cb63d6dfe60`) **NO está asociado** al alias `rggestion` en ARCA.

**En ARCA, el alias `rggestion` tiene estos certificados:**
- ✅ `1bfe290685dac75c`
- ✅ `770c9971708cae1c`
- ❌ `348f6cb63d6dfe60` ← **Este NO está asociado**

**Por eso el error:** `ns1:cms.cert.notFound` - AFIP no encuentra el certificado porque no está asociado al alias que tiene la autorización.

## ✅ Solución: Agregar Certificado al Alias

### Opción A: Agregar el Certificado Actual al Alias (Recomendado)

1. En ARCA, ve a **"Agregar certificado a alias"** (en el menú lateral)
2. Completa el formulario:
   - **Alias:** `rggestion`
   - **Certificado:** Selecciona o sube el certificado con serial `348f6cb63d6dfe60`
3. Haz clic en **"Agregar"** o **"Confirmar"**
4. Espera unos minutos para que se procese

### Opción B: Usar uno de los Certificados que SÍ Están Asociados

Si prefieres usar uno de los certificados que ya están asociados:

1. Descarga el certificado `1bfe290685dac75c` o `770c9971708cae1c` desde ARCA
2. Reemplaza `storage/certificates/certificado.crt` con el certificado descargado
3. Asegúrate de tener la clave privada correspondiente a ese certificado
4. Prueba de nuevo

## 🔍 Verificación

Después de agregar el certificado al alias:

1. En ARCA → **"Certificados"** → Haz clic en **"Ver"** en `rggestion`
2. En **"Certificados asociados"**, verifica que aparezca:
   - ✅ `348f6cb63d6dfe60` (el que usa el SDK)
   - ✅ Estado: **VALIDO**

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

**¿Por qué pasó esto?**

Probablemente:
- El certificado `348f6cb63d6dfe60` fue generado/descargado pero nunca se agregó al alias `rggestion` en ARCA
- O fue generado para otro alias y ahora quieres usarlo con `rggestion`

**Solución definitiva:** Agregar el certificado al alias en ARCA usando la opción **"Agregar certificado a alias"**.

---

**¿Necesitas ayuda con algún paso?** Comparte qué opción prefieres y te guío paso a paso.


