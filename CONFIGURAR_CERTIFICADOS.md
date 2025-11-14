# Configurar Certificados Descargados de AFIP

## 📥 Lo que Descargaste

Desde la página de "Administración de Certificados Digitales" descargaste:
- ✅ **Certificado público** (`.crt` o `.pem`) - Este es el certificado que se muestra en la tabla

## ⚠️ Importante: Necesitas DOS Archivos

Para que el SDK funcione, necesitas:

1. ✅ **Certificado público** (`.crt`) - Ya lo tienes (descargado de AFIP)
2. ❓ **Clave privada** (`.key`) - Esta NO se descarga, la generaste tú durante el proceso de creación del certificado

## 🔍 Verificar lo que Tienes

### Paso 1: Verificar el Certificado Descargado

```bash
# Ver información del certificado
openssl x509 -in certificado.crt -text -noout

# O si es .pem
openssl x509 -in certificado.pem -text -noout
```

Deberías ver:
- **Subject**: CN=rggestion, SERIALNUMBER=CUIT 20457809027
- **Issuer**: AFIP
- **Validity**: Fecha de emisión y vencimiento

### Paso 2: Buscar tu Clave Privada

La clave privada la generaste cuando:
- Creaste el certificado por primera vez
- O la descargaste en algún momento anterior

**Busca archivos como:**
- `clave_privada.key`
- `private_key.key`
- `clave.key`
- `*.key`
- `*.pem` (podría contener tanto certificado como clave)

**Ubicaciones comunes:**
- Carpeta de Descargas
- Documentos
- Alguna carpeta de proyecto anterior
- USB o backup

## 📋 Estructura Correcta

Necesitas tener estos dos archivos:

```
certificados/
├── certificado.crt      (o certificado.pem) - Descargado de AFIP
└── clave_privada.key    (o clave_privada.pem) - Generado por ti
```

## 🔧 Configuración en el SDK

### Opción 1: Si Tienes Ambos Archivos

1. **Crear directorio:**
```bash
mkdir -p storage/certificates
```

2. **Copiar archivos:**
```bash
# Copiar certificado
cp /ruta/descargado/certificado.crt storage/certificates/

# Copiar clave privada
cp /ruta/donde/esta/clave_privada.key storage/certificates/
```

3. **Permisos seguros:**
```bash
chmod 600 storage/certificates/clave_privada.key
chmod 644 storage/certificates/certificado.crt
```

4. **Configurar `.env`:**
```env
AFIP_CERTIFICATES_PATH=storage/certificates
AFIP_CERTIFICATE_KEY=clave_privada.key
AFIP_CERTIFICATE_CRT=certificado.crt
AFIP_CERTIFICATE_PASSWORD=tu_password_si_tiene
```

### Opción 2: Si el Certificado es .pem

Si descargaste un `.pem`, puede contener:
- Solo el certificado público
- O certificado + clave privada juntos

**Verificar contenido:**
```bash
# Ver qué contiene el archivo
cat certificado.pem
```

**Si contiene solo certificado:**
- Busca la clave privada por separado
- O renombra: `certificado.pem` → `certificado.crt`

**Si contiene ambos (certificado + clave):**
```bash
# Extraer certificado
openssl x509 -in certificado.pem -out certificado.crt

# Extraer clave privada
openssl rsa -in certificado.pem -out clave_privada.key
```

## 🧪 Verificar que Funcionen Juntos

```bash
# Verificar que la clave privada corresponde al certificado
openssl x509 -noout -modulus -in certificado.crt | openssl md5
openssl rsa -noout -modulus -in clave_privada.key | openssl md5
```

**Si los hashes coinciden** ✅ = Son un par válido
**Si no coinciden** ❌ = No son del mismo certificado

## 🔐 Si No Tienes la Clave Privada

**⚠️ PROBLEMA CRÍTICO:** Sin la clave privada, NO puedes:
- Firmar mensajes
- Autenticarte con WSAA
- Generar facturas

**Soluciones:**

1. **Buscar en backups:**
   - USB
   - Disco externo
   - Servicios de backup (Dropbox, Google Drive, etc.)
   - Email (si te la enviaste)

2. **Si realmente no la tienes:**
   - Tendrás que generar un NUEVO certificado en AFIP
   - Guardar la clave privada esta vez
   - Descargar el nuevo certificado

## 📝 Verificar Formato

### Certificado (.crt o .pem)
Debe empezar con:
```
-----BEGIN CERTIFICATE-----
...
-----END CERTIFICATE-----
```

### Clave Privada (.key o .pem)
Debe empezar con:
```
-----BEGIN PRIVATE KEY-----
```
o
```
-----BEGIN RSA PRIVATE KEY-----
```

## ✅ Checklist Final

Antes de probar, verifica:

- [ ] Certificado `.crt` o `.pem` descargado de AFIP
- [ ] Clave privada `.key` o `.pem` (generada por ti)
- [ ] Ambos archivos en `storage/certificates/`
- [ ] Permisos correctos (600 para .key, 644 para .crt)
- [ ] Variables de entorno configuradas en `.env`
- [ ] Los hashes MD5 coinciden (son un par válido)

## 🚀 Próximo Paso

Una vez que tengas ambos archivos configurados:

```bash
# Probar autenticación
php artisan tinker
```

```php
$wsaa = app(\Resguar\AfipSdk\Services\WsaaService::class);
$token = $wsaa->getToken('wsfe');
echo "Token: " . substr($token->token, 0, 20) . "...\n";
```

Si funciona, ¡estás listo para facturar! 🎉

