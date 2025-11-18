# 📥 Usar Certificado Asociado al Alias en ARCA

## 🎯 Objetivo

Usar uno de los certificados que **SÍ están asociados** al alias `rggestion` en ARCA:
- `1bfe290685dac75c`
- `770c9971708cae1c`

## ⚠️ IMPORTANTE: Necesitas la Clave Privada

**Para usar un certificado, necesitas:**
1. ✅ El certificado (`.crt`) - Lo puedes descargar de ARCA
2. ❓ **La clave privada (`.key`)** - Esta NO se descarga, la generaste tú cuando creaste el certificado

**Si NO tienes la clave privada de estos certificados, NO podrás usarlos.**

## ✅ Paso 1: Verificar si Tienes la Clave Privada

Antes de descargar el certificado, verifica si tienes la clave privada correspondiente:

```bash
cd "/Users/naimguarino/Documents/Resguar IT/POS/pos-system/apps/backend"

# Buscar archivos .key en tu sistema
find . -name "*.key" -type f 2>/dev/null

# O buscar en la carpeta de certificados
ls -la storage/certificates/*.key
```

**Si encuentras archivos `.key`, verifica cuál corresponde a cada certificado:**

```bash
# Verificar si una clave privada corresponde a un certificado
openssl x509 -noout -modulus -in certificado.crt | openssl md5
openssl rsa -noout -modulus -in clave_privada.key | openssl md5
```

**Si los hashes coinciden** = La clave privada corresponde al certificado ✅

## ✅ Paso 2: Descargar el Certificado desde ARCA

1. En ARCA → **"Certificados"** → Haz clic en **"Ver"** en `rggestion`
2. En **"Certificados asociados"**, encuentra el certificado que quieres usar:
   - `1bfe290685dac75c` o
   - `770c9971708cae1c`
3. Haz clic en **"Ver"** o en el ícono de descarga (si está disponible)
4. Descarga el certificado (`.crt` o `.pem`)

## ✅ Paso 3: Reemplazar el Certificado en el SDK

```bash
cd "/Users/naimguarino/Documents/Resguar IT/POS/pos-system/apps/backend"

# Hacer backup del certificado actual (por si acaso)
cp storage/certificates/certificado.crt storage/certificates/certificado.crt.backup

# Copiar el nuevo certificado descargado
# (Ajusta la ruta según donde lo descargaste)
cp ~/Downloads/certificado_descargado.crt storage/certificates/certificado.crt

# Ajustar permisos
chmod 644 storage/certificates/certificado.crt
```

## ✅ Paso 4: Verificar que la Clave Privada Coincida

```bash
# Verificar que el certificado y la clave privada coincidan
openssl x509 -noout -modulus -in storage/certificates/certificado.crt | openssl md5
openssl rsa -noout -modulus -in storage/certificates/clave_privada.key | openssl md5
```

**Ambos comandos deben devolver el mismo hash.** Si no coinciden, necesitas la clave privada correcta.

## ✅ Paso 5: Verificar el Serial Number

```bash
# Ver el serial number del certificado
openssl x509 -in storage/certificates/certificado.crt -serial -noout
```

**Debe mostrar:**
- `serial=1BFE290685DAC75C` o
- `serial=770C9971708CAE1C`

## ✅ Paso 6: Limpiar Cache y Probar

```bash
# Limpiar cache
php artisan config:clear
php artisan cache:clear

# Probar
php artisan afip:test
```

## ⚠️ Si NO Tienes la Clave Privada

Si no tienes la clave privada de ninguno de estos certificados, tienes dos opciones:

### Opción A: Generar un Nuevo Certificado

1. Genera un nuevo CSR (Certificate Signing Request) con OpenSSL
2. Solicita el certificado en ARCA usando ese CSR
3. El nuevo certificado se asociará automáticamente al alias `rggestion`
4. Usa ese certificado con su clave privada

### Opción B: Agregar el Certificado Actual al Alias

1. En ARCA → **"Agregar certificado a alias"**
2. Agrega el certificado `348f6cb63d6dfe60` al alias `rggestion`
3. Así podrás seguir usando el certificado actual con su clave privada

## 🔍 Verificar Serial Number con el SDK

Después de reemplazar el certificado, verifica que el SDK lo detecte correctamente:

```bash
php artisan tinker
```

```php
use Resguar\AfipSdk\Facades\Afip;

$diagnosis = Afip::diagnoseAuthenticationIssue();
echo "Serial: " . ($diagnosis['details']['certificate_serial'] ?? 'No encontrado') . "\n";
```

**Debe mostrar:**
- `1bfe290685dac75c` o
- `770c9971708cae1c`

---

**¿Tienes la clave privada de alguno de estos certificados?** Si no, es mejor agregar el certificado actual al alias en ARCA.


