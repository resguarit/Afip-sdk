# 🔍 Verificar Certificado en ARCA

## 🎯 Problema

Tienes **2 certificados** en ARCA y **1 autorización** para `wsfe`, pero el SDK sigue dando el error:
```
ns1:cms.cert.notFound - No se ha encontrado certificado de firmador
```

**Esto significa:** El certificado que está usando el SDK **NO coincide** con el que tiene la autorización en ARCA.

## ✅ Solución: Verificar qué Certificado Usa el SDK

### Paso 1: Actualizar el SDK

```bash
cd "/Users/naimguarino/Documents/Resguar IT/POS/pos-system/apps/backend"

# Actualizar SDK
composer update resguar/afip-sdk:dev-main --no-interaction

# Limpiar cache
php artisan config:clear
php artisan cache:clear
```

### Paso 2: Ejecutar Diagnóstico

```bash
php artisan tinker
```

```php
use Resguar\AfipSdk\Facades\Afip;

$diagnosis = Afip::diagnoseAuthenticationIssue();
print_r($diagnosis);
```

**Busca esta línea en el resultado:**
```php
[certificate_serial] => 1bfe290685dac75c  // o 770c9971708cae1c
```

### Paso 3: Comparar con ARCA

1. Ve a ARCA: https://www.afip.gob.ar/arqa/
2. Ve a **"Certificados"** → Haz clic en **"Ver"** en cada certificado
3. Anota el **serial number** de cada uno:
   - Certificado 1: `1bfe290685dac75c`
   - Certificado 2: `770c9971708cae1c`
4. Ve a **"Autorizaciones"**
5. Verifica qué certificado tiene la autorización para `wsfe`

### Paso 4: Verificar Coincidencia

**Si el serial del SDK NO coincide con el que tiene la autorización:**

#### Opción A: Usar el Certificado Correcto

1. En ARCA, ve a **"Certificados"**
2. Haz clic en **"Ver"** en el certificado que **SÍ tiene la autorización**
3. Descarga el certificado (si no lo tienes)
4. Reemplaza `storage/certificates/certificado.crt` con el certificado correcto
5. Asegúrate de tener la clave privada correspondiente

#### Opción B: Crear Autorización para el Otro Certificado

1. En ARCA, ve a **"Crear autorización a servicio"**
2. Selecciona el certificado que está usando el SDK (el que NO tiene autorización)
3. Crea la autorización para `wsfe`

## 🔍 Verificar Serial del Certificado Manualmente

Si quieres verificar el serial del certificado que tienes en tu sistema:

```bash
cd "/Users/naimguarino/Documents/Resguar IT/POS/pos-system/apps/backend"

# Ver serial del certificado
openssl x509 -in storage/certificates/certificado.crt -serial -noout
```

**Resultado esperado:**
```
serial=1BFE290685DAC75C
```
o
```
serial=770C9971708CAE1C
```

## 📋 Checklist

- [ ] SDK actualizado a la versión más reciente
- [ ] Diagnóstico ejecutado y serial number obtenido
- [ ] Serial number comparado con los 2 certificados en ARCA
- [ ] Verificado qué certificado tiene la autorización `wsfe`
- [ ] Certificado del SDK coincide con el que tiene autorización
- [ ] O se creó nueva autorización para el certificado del SDK

## 🎯 Resultado Esperado

Después de verificar y corregir:

```bash
php artisan afip:test
```

Deberías ver:
```
✅ Token de autenticación obtenido exitosamente
✅ Último comprobante consultado: X
✅ Factura autorizada con CAE: XXXXXXXXXX
```

---

**¿Necesitas ayuda?** Comparte el resultado del diagnóstico y te ayudo a identificar el problema.

