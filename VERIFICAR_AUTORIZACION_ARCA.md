# 🔍 Verificar Autorización en ARCA

## 🎯 Problema

Aunque el certificado está correctamente asociado al alias `rggestion` y está `VALIDO`, el error `ns1:cms.cert.notFound` persiste.

**Esto puede significar:** La autorización para `wsfe` puede no estar correctamente vinculada al certificado o al alias.

## ✅ Verificación en ARCA

### Paso 1: Verificar Autorizaciones

1. En ARCA, ve a **"Autorizaciones"** (en el menú lateral)
2. Busca la fila con servicio `wsfe`
3. Verifica:
   - **Alias:** Debe ser `rggestion`
   - **Dador:** Debe ser `20457809027`
   - **Estado:** Debe ser `VIGENTE` o `ACTIVO`

### Paso 2: Verificar Detalles de la Autorización

Si puedes hacer clic en la autorización para ver detalles, verifica:
- Que el alias asociado sea `rggestion`
- Que el certificado asociado sea uno de los dos que están en "Certificados asociados"
- Que el estado sea `VIGENTE`

### Paso 3: Si la Autorización No Está Correctamente Vinculada

Si la autorización existe pero no está vinculada correctamente:

1. **Eliminar la autorización actual:**
   - Ve a **"Eliminar autorización a servicio"**
   - Elimina la autorización para `wsfe`

2. **Crear nueva autorización:**
   - Ve a **"Crear autorización a servicio"**
   - Completa:
     - **Nombre simbólico del DN:** `rggestion`
     - **CUIT del DN:** `20457809027`
     - **CUIT representada:** `20457809027`
     - **Nombre del servicio:** `wsfe - Facturacion Electronica`
     - **Entorno:** `Homologación` (testing)
   - Haz clic en **"Crear autorización"**

3. **Esperar propagación:**
   - Espera 10-15 minutos después de crear la autorización
   - Refresca la página para verificar que aparezca como `VIGENTE`

## 🔍 Verificación Completa

Después de verificar/corregir, asegúrate de tener:

1. ✅ **Certificado asociado al alias:**
   - ARCA → Certificados → Ver `rggestion`
   - El certificado `770c9971708cae1c` debe aparecer en "Certificados asociados"
   - Estado: `VALIDO`

2. ✅ **Autorización correcta:**
   - ARCA → Autorizaciones
   - Debe aparecer: Alias `rggestion`, Servicio `wsfe`, Estado `VIGENTE`

3. ✅ **Certificado en el SDK:**
   - El archivo `certificado.crt` debe ser el certificado `770c9971708cae1c`

## 🧪 Probar Después de Corregir

```bash
cd "/Users/naimguarino/Documents/Resguar IT/POS/pos-system/apps/backend"

# Limpiar cache
php artisan config:clear
php artisan cache:clear

# Probar
php artisan afip:test
```

## ⚠️ Tiempo de Propagación

**IMPORTANTE:** Después de crear o modificar una autorización en ARCA, puede tardar:
- **Mínimo:** 5-10 minutos
- **Máximo:** 24 horas (en casos extremos)

Si acabas de crear la autorización, espera al menos 10-15 minutos antes de probar de nuevo.

---

**¿Puedes verificar en ARCA → "Autorizaciones" qué aparece en la fila con servicio `wsfe`?**


