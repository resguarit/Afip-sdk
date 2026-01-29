# Paso a paso: Certificado AFIP para FE y copia a rgpos

Para que tu cliente pueda emitir Factura Electrónica (FE) en el servidor **rgpos**, seguí estos pasos en orden.

---

## Datos del cliente (RESGUAR IT)

| Dato | Valor |
|------|--------|
| CUIT | 30718708997 |
| Alias | resguarit |
| Razón social | RESGUAR IT CONSULTORIA EN INFORMATICA Y TECNOLOGIA S. R. L. |

---

## Parte 1: Generar clave privada y CSR (en tu computadora)

### Paso 1.1 — Abrir la Terminal

- En Mac: **Cmd + Espacio** → escribir **Terminal** → Enter.

### Paso 1.2 — Ir a la carpeta del proyecto

```bash
cd "/Users/naimguarino/Documents/Resguar IT/POS/afip-sdk-resguar"
```

*(Si no tenés carpeta `storage` en este proyecto, en el Paso 1.3 se crea.)*

### Paso 1.3 — Crear carpeta para certificados

```bash
mkdir -p storage/certificates/30718708997
chmod 700 storage/certificates/30718708997
```

### Paso 1.4 — Generar la clave privada

```bash
openssl genrsa -out storage/certificates/30718708997/private.key 2048
```

### Paso 1.5 — Generar el CSR (archivo para subir a AFIP)

```bash
openssl req -new \
  -key storage/certificates/30718708997/private.key \
  -out storage/certificates/30718708997/certificado.csr \
  -subj "/C=AR/O=RESGUAR IT CONSULTORIA EN INFORMATICA Y TECNOLOGIA S. R. L./CN=resguarit/serialNumber=CUIT 30718708997"
```

### Paso 1.6 — Proteger la clave privada

```bash
chmod 600 storage/certificates/30718708997/private.key
```

### Paso 1.7 — Verificar que existan los archivos

```bash
ls -la storage/certificates/30718708997/
```

Deberías ver:

- `private.key` — **no se sube a AFIP ni se copia por email**
- `certificado.csr` — **este sí se sube a AFIP**

---

## Parte 2: Subir el CSR a AFIP y obtener el certificado

### Paso 2.1 — Entrar a AFIP

1. Ir a: **https://www.afip.gob.ar/**
2. **Acceso con Clave Fiscal** → CUIT y clave del cliente (o quien actúe en nombre de RESGUAR IT).

### Paso 2.2 — Ir a Certificados digitales

1. Menú **Trámites y servicios**.
2. Buscar **ARCA** o **Certificados digitales**.
3. Entrar a **Administración de Certificados Digitales**.

### Paso 2.3 — Agregar certificado

1. Clic en **Agregar certificado** (o similar).
2. Completar:
   - **Alias:** `resguarit` (igual que en el CSR).
   - **Archivo CSR:** elegir el archivo  
     `storage/certificates/30718708997/certificado.csr`  
     desde tu Mac (o desde donde lo hayas generado).
3. Clic en **Agregar certificado** / **Crear** / **Guardar**.

### Paso 2.4 — Descargar el certificado

1. Cuando AFIP lo apruebe, en la lista de certificados vas a ver el nuevo.
2. Clic en **Descargar certificado** (o ícono de descarga).
3. Guardar el archivo en tu Mac.

### Paso 2.5 — Renombrar y ubicar el certificado

1. Renombrar el archivo descargado a: **`certificate.crt`**
2. Moverlo (o copiarlo) a la misma carpeta del CSR y la clave:
   - Ruta final:  
     `storage/certificates/30718708997/certificate.crt`

Ejemplo si lo descargaste en Escritorio:

```bash
mv ~/Desktop/archivo_descargado.crt "/Users/naimguarino/Documents/Resguar IT/POS/afip-sdk-resguar/storage/certificates/30718708997/certificate.crt"
```

*(Ajustá el nombre `archivo_descargado.crt` por el nombre real que tenga.)*

### Paso 2.6 — Autorizar servicios del certificado en ARCA

1. En ARCA / Certificados, buscar el certificado con alias **resguarit**.
2. Ir a **Autorizar servicios** o **Administrar relaciones**.
3. Agregar:
   - **wsfe** (Facturación Electrónica)
   - **ws_sr_padron_a13** (Padrón)
4. Guardar.

---

## Parte 3: Copiar certificado y clave al servidor rgpos

> **Importante:** Solo podés hacer esta parte **después** de tener el archivo `certificate.crt` descargado de AFIP (Parte 2). Si todavía no lo tenés, hacé primero los pasos 2.1 a 2.5.

En rgpos tienen que quedar solo estos dos archivos:

- `certificate.crt` (el que bajaste de AFIP)
- `private.key` (el que generaste en la Parte 1)

El archivo `.csr` no hace falta en el servidor.

### Datos del servidor rgpos (api)

| Dato | Valor |
|------|--------|
| Conexión SSH | `ssh -p 5614 root@200.58.127.86` |
| Ruta de la app | `/home/api.rgpos.com.ar/public_html/apps/backend` |
| Host | `200.58.127.86` |
| Puerto | `5614` |

**Nota:** `scp` usa **`-P`** (mayúscula) para el puerto; `ssh` usa **`-p`** (minúscula).

---

### Paso 3.1 — Crear la carpeta en el servidor (por SSH)

**En la Terminal de tu Mac** (si aún no creaste la carpeta):

```bash
ssh -p 5614 root@200.58.127.86 "mkdir -p /home/api.rgpos.com.ar/public_html/apps/backend/storage/certificates/30718708997"
```

### Paso 3.2 — Copiar la clave privada al servidor (desde tu Mac)

**En la Terminal de tu Mac**, desde la carpeta del proyecto:

```bash
cd "/Users/naimguarino/Documents/Resguar IT/POS/afip-sdk-resguar"

scp -P 5614 storage/certificates/30718708997/private.key root@200.58.127.86:/home/api.rgpos.com.ar/public_html/apps/backend/storage/certificates/30718708997/
```

### Paso 3.3 — Copiar el certificado al servidor (desde tu Mac)

Solo cuando ya tengas el archivo `certificate.crt` (descargado de AFIP y guardado en esa carpeta):

```bash
scp -P 5614 storage/certificates/30718708997/certificate.crt root@200.58.127.86:/home/api.rgpos.com.ar/public_html/apps/backend/storage/certificates/30718708997/
```

### Paso 3.4 — Ajustar permisos (en el servidor)

Entrá por SSH al servidor:

```bash
ssh -p 5614 root@200.58.127.86
```

Una vez dentro del servidor:

```bash
chmod 700 /home/api.rgpos.com.ar/public_html/apps/backend/storage/certificates/30718708997
chmod 600 /home/api.rgpos.com.ar/public_html/apps/backend/storage/certificates/30718708997/private.key
chmod 644 /home/api.rgpos.com.ar/public_html/apps/backend/storage/certificates/30718708997/certificate.crt
exit
```

### Paso 3.5 — Verificar en el servidor

Con SSH abierto en el servidor:

```bash
ls -la /home/api.rgpos.com.ar/public_html/apps/backend/storage/certificates/30718708997/
```

Deberías ver:

- `certificate.crt`
- `private.key`

---

## Parte 4: Configuración en rgpos

### Paso 4.1 — Variables de entorno

En el servidor rgpos, en el `.env` de la aplicación (`/home/api.rgpos.com.ar/public_html/apps/backend/.env`), debe estar algo como:

```env
AFIP_ENVIRONMENT=production
AFIP_CUIT=30718708997
AFIP_DEFAULT_POINT_OF_SALE=2
AFIP_CERTIFICATES_BASE_PATH=storage/certificates
```

El número de punto de venta (`AFIP_DEFAULT_POINT_OF_SALE`) tiene que ser el que tengan dado de alta en AFIP para ese CUIT (tipo WebService).

### Paso 4.2 — Limpiar caché (Laravel)

En el servidor (por SSH):

```bash
cd /home/api.rgpos.com.ar/public_html/apps/backend
php artisan config:clear
php artisan cache:clear
```

---

## Resumen rápido

| # | Dónde | Qué hacer |
|---|--------|-----------|
| 1 | Tu Mac (Terminal) | Generar `private.key` y `certificado.csr` en `storage/certificates/30718708997/` |
| 2 | AFIP (navegador) | Subir `certificado.csr`, alias `resguarit`, descargar certificado |
| 3 | Tu Mac | Guardar certificado como `certificate.crt` en la misma carpeta |
| 4 | AFIP (ARCA) | Autorizar servicios **wsfe** y **ws_sr_padron_a13** |
| 5 | Tu Mac (Terminal) | Copiar `private.key` y `certificate.crt` al servidor rgpos con `scp` |
| 6 | Servidor rgpos (SSH) | Permisos 700 en carpeta, 600 en `private.key` |
| 7 | Servidor rgpos | Revisar `.env` y ejecutar `config:clear` y `cache:clear` |

---

## Si algo falla

- **“Certificado y clave no coinciden”:** La `private.key` tiene que ser la misma con la que generaste el CSR. No reemplaces la clave sin generar un nuevo CSR y un nuevo certificado.
- **“Computador no autorizado”:** Revisar en ARCA que el certificado tenga autorizados **wsfe** y **ws_sr_padron_a13**.
- **Errores de conexión/SSL:** Ver en el manual `MANUAL-CONFIGURACION-AFIP.md` la sección de `openssl.cnf` y variables de entorno.

---

*Documento para uso interno — Resguar IT*
