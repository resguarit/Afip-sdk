# 📋 Manual de Configuración AFIP - SDK Resguar

Este manual explica paso a paso cómo configurar AFIP para usar el SDK de facturación electrónica en cualquier sistema (POS, stock, ticketera, turnos, etc.).

---

## 📑 Índice

1. [Requisitos Previos](#1-requisitos-previos)
2. [Paso 1: Habilitar Facturación Electrónica en AFIP](#2-paso-1-habilitar-facturación-electrónica-en-afip)
3. [Paso 2: Crear Punto de Venta WebService](#3-paso-2-crear-punto-de-venta-webservice)
4. [Paso 3: Generar Certificado Digital](#4-paso-3-generar-certificado-digital)
5. [Paso 4: Subir CSR a ARCA y Obtener Certificado](#5-paso-4-subir-csr-a-arca-y-obtener-certificado)
6. [Paso 5: Autorizar Servicios en ARCA](#6-paso-5-autorizar-servicios-en-arca)
7. [Paso 6: Configurar el Sistema](#7-paso-6-configurar-el-sistema)
8. [Paso 7: Verificar Funcionamiento](#8-paso-7-verificar-funcionamiento)
9. [Solución de Problemas](#9-solución-de-problemas)
10. [Referencia de Métodos del SDK](#10-referencia-de-métodos-del-sdk)
11. [Configuración Multi-CUIT](#11-configuración-multi-cuit-múltiples-empresas)

---

## 1. Requisitos Previos

### Del Cliente (Contribuyente)

- ✅ CUIT activo
- ✅ Clave Fiscal nivel 3 o superior
- ✅ Estar inscripto en algún régimen (Responsable Inscripto, Monotributo, etc.)
- ✅ Tener habilitada la facturación electrónica

### Del Sistema

- ✅ PHP 8.1 o superior
- ✅ Laravel 10 o superior
- ✅ OpenSSL instalado
- ✅ Extensiones PHP: `soap`, `openssl`, `curl`

### Información Necesaria

| Dato | Descripción | Ejemplo |
|------|-------------|---------|
| CUIT | CUIT del contribuyente | 30718708997 |
| Razón Social | Nombre de la empresa | RESGUAR IT S.R.L. |
| Nombre del Alias | Identificador del certificado | mi-sistema |

---

## 2. Paso 1: Habilitar Facturación Electrónica en AFIP

### 2.1 Ingresar a AFIP

1. Ir a: **https://www.afip.gob.ar/**
2. Hacer clic en **"Acceso con Clave Fiscal"**
3. Ingresar CUIT y Clave Fiscal

### 2.2 Habilitar el Servicio de Facturación

1. En el menú principal, buscar **"Administrador de Relaciones de Clave Fiscal"**
2. Hacer clic en **"Adherir Servicio"**
3. Buscar: **"ARCA - Administración de Certificados"**
4. Seleccionarlo y confirmar

---

## 3. Paso 2: Crear Punto de Venta WebService

### 3.1 Ingresar a ABM de Puntos de Venta

1. Desde el menú de AFIP, buscar: **"ABM de Puntos de Venta"**
2. O ir directamente al servicio de facturación y buscar la opción

### 3.2 Crear Nuevo Punto de Venta

1. Hacer clic en **"Agregar Punto de Venta"**
2. Completar los datos:

| Campo | Valor |
|-------|-------|
| Número | El siguiente disponible (ej: 2, 3, etc.) |
| Nombre | Descripción del punto (ej: "Sistema Web") |
| Tipo | **RECE** ⚠️ MUY IMPORTANTE |
| Domicilio | Seleccionar el domicilio fiscal |

3. Hacer clic en **"Guardar"**

> ⚠️ **IMPORTANTE**: El tipo DEBE ser **"RECE"**.

### 3.3 Anotar el Número de Punto de Venta

Guardar el número asignado, lo necesitarás para la configuración:
```
Punto de Venta: ____
```

---

## 4. Paso 3: Generar Certificado Digital

### 4.1 Crear Carpeta para Certificados

En tu sistema, crear una carpeta segura:
```bash
mkdir -p storage/certificates
chmod 700 storage/certificates
```

### 4.2 Generar Clave Privada y CSR

Ejecutar en la terminal (reemplazar los valores):

```bash
# Variables (MODIFICAR)
CUIT="1234567890"
ALIAS="mi-sistema"
NOMBRE="NOMBRE DE LA EMPRESA S.R.L."

# Generar clave privada
openssl genrsa -out storage/certificates/clave_privada.key 2048

# Generar CSR
openssl req -new \
  -key storage/certificates/clave_privada.key \
  -out storage/certificates/certificado.csr \
  -subj "/C=AR/O=${NOMBRE}/CN=${ALIAS}/serialNumber=CUIT ${CUIT}"
```

### 4.3 Verificar el CSR

```bash
openssl req -text -noout -in storage/certificates/certificado.csr
```

Debe mostrar:
```
Subject: C = AR, O = NOMBRE DE LA EMPRESA S.R.L., CN = mi-sistema, serialNumber = CUIT 30718708997
```

### 4.4 Proteger la Clave Privada

```bash
chmod 600 storage/certificates/clave_privada.key
```

> ⚠️ **NUNCA** subir la clave privada a Git ni compartirla. Agregar a `.gitignore`:
> ```
> storage/certificates/*.key
> storage/certificates/*.crt
> ```

---

## 5. Paso 4: Subir CSR a ARCA y Obtener Certificado

### 5.1 Ingresar a ARCA

1. Ir a **"Certificados digitales"** → **"Agregar Certificado"**
2. Completar:

| Campo | Valor |
|-------|-------|
| Alias | El mismo que usaste en el CSR (ej: `mi-sistema`) |
| Archivo CSR | Subir el archivo `certificado.csr` |

3. Hacer clic en **"Crear"** o **"Guardar"**

### 5.3 Descargar el Certificado

1. Una vez creado, aparecerá en la lista
2. Hacer clic en **"Descargar Certificado"** o el ícono de descarga
3. Guardar el archivo como `certificado.crt` en `storage/certificates/`

### 5.4 Verificar el Certificado

```bash
openssl x509 -text -noout -in storage/certificates/certificado.crt | head -20
```

### 5.5 Verificar que Coincidan

```bash
# Obtener hash de la clave privada
openssl rsa -modulus -noout -in storage/certificates/clave_privada.key | openssl md5

# Obtener hash del certificado
openssl x509 -modulus -noout -in storage/certificates/certificado.crt | openssl md5
```

> ✅ Ambos hashes DEBEN ser iguales.

---

## 6. Paso 5: Autorizar Servicios en ARCA

### 6.1 Servicios Requeridos

El certificado necesita autorización para usar los siguientes servicios:

| Servicio | Nombre Técnico | Descripción |
|----------|----------------|-------------|
| Facturación Electrónica | `wsfe` | Emitir facturas, notas de crédito/débito |
| Padrón | `ws_sr_padron_a13` | Consultar datos de contribuyentes |

### 6.2 Autorizar Servicios

1. En ARCA, ir a **"Autorizar Servicios"** o **"Administrar Relaciones"**
2. Seleccionar el certificado/alias creado
3. Hacer clic en **"Agregar Servicio"**
4. Buscar y agregar:
   - `wsfe` o "Factura Electrónica"
   - `ws_sr_padron_a13` o "Padrón Alcance 13"
5. Guardar

### 6.3 Verificar Autorizaciones

En la lista de servicios del certificado deben aparecer:
- ✅ wsfe
- ✅ ws_sr_padron_a13

---

## 7. Paso 6: Configurar el Sistema

### 7.1 Instalar el SDK

```bash
composer require resguar/afip-sdk
```

### 7.2 Publicar Configuración

```bash
php artisan vendor:publish --provider="Resguar\AfipSdk\AfipServiceProvider" --tag="afip-config"
```

### 7.3 Configurar Variables de Entorno

Agregar al archivo `.env`:

```env
# ================================
# CONFIGURACIÓN AFIP
# ================================

# Entorno: testing o production
AFIP_ENVIRONMENT=production

# CUIT del contribuyente
AFIP_CUIT=30718708997

# Punto de venta (el que creaste en el paso 2)
AFIP_DEFAULT_POINT_OF_SALE=2

# Ruta a los certificados
AFIP_CERTIFICATES_PATH=storage/certificates

# Nombres de los archivos de certificado
AFIP_CERTIFICATE_KEY=clave_privada.key
AFIP_CERTIFICATE_CRT=certificado.crt

# Configuración SSL (para evitar errores de conexión)
OPENSSL_CONF=storage/certificates/openssl.cnf
```

### 7.4 Crear Archivo de Configuración SSL

Crear `storage/certificates/openssl.cnf`:

```ini
openssl_conf = openssl_init

[openssl_init]
ssl_conf = ssl_sect

[ssl_sect]
system_default = system_default_sect

[system_default_sect]
MinProtocol = TLSv1.2
CipherString = DEFAULT@SECLEVEL=1
```

### 7.5 Limpiar Cache

```bash
php artisan config:clear
php artisan cache:clear
```

---

## 8. Paso 7: Verificar Funcionamiento

### 8.1 Test Rápido

Crear un archivo `test-afip.php` en la raíz del proyecto:

```php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Resguar\AfipSdk\Facades\Afip;

echo "🧪 Verificando configuración AFIP...\n\n";

try {
    // 1. Verificar autenticación
    echo "1️⃣ Autenticación: ";
    $auth = Afip::isAuthenticated();
    echo $auth ? "✅ OK\n" : "❌ FALLO\n";

    // 2. Obtener puntos de venta
    echo "2️⃣ Puntos de venta: ";
    $pv = Afip::getAvailablePointsOfSale();
    echo "✅ " . count($pv) . " encontrados\n";

    // 3. Obtener tipos de comprobantes (filtrados por condición fiscal)
    echo "3️⃣ Tipos de comprobantes: ";
    $tipos = Afip::getReceiptTypesForCuit();
    echo "✅ " . count($tipos['receipt_types']) . " habilitados\n";
    echo "   Condición IVA: " . $tipos['condicion_iva']['description'] . "\n";

    // 4. Consultar último comprobante
    echo "4️⃣ Último comprobante (Factura A): ";
    $ultimo = Afip::getLastAuthorizedInvoice(
        config('afip.default_point_of_sale'),
        1 // Factura A
    );
    echo "✅ Nro. " . $ultimo['CbteNro'] . "\n";

    echo "\n✅ ¡Configuración correcta!\n";

} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}
```

### 8.2 Ejecutar Test

```bash
OPENSSL_CONF="storage/certificates/openssl.cnf" php test-afip.php
```

### 8.3 Resultado Esperado

```
🧪 Verificando configuración AFIP...

1️⃣ Autenticación: ✅ OK
2️⃣ Puntos de venta: ✅ 1 encontrados
3️⃣ Tipos de comprobantes: ✅ 18 habilitados
   Condición IVA: IVA Responsable Inscripto
4️⃣ Último comprobante (Factura A): ✅ Nro. 0

✅ ¡Configuración correcta!
```

---

## 9. Solución de Problemas

### Error: "Computador no autorizado a acceder al servicio"

**Causa**: El certificado no tiene autorizado el servicio.

**Solución**: 
1. Ir a ARCA
2. Buscar el certificado
3. Agregar el servicio faltante (wsfe o ws_sr_padron_a13)

---

### Error: "dh key too small" o "SSL routines"

**Causa**: Problema de compatibilidad SSL.

**Solución**: 
1. Crear el archivo `openssl.cnf` (ver paso 7.4)
2. Ejecutar con la variable de entorno:
```bash
OPENSSL_CONF="storage/certificates/openssl.cnf" php artisan serve
```

---

### Error: "El certificado y la clave privada no coinciden"

**Causa**: Se generó un nuevo CSR sin usar la clave privada original.

**Solución**: 
1. Generar nueva clave privada y CSR
2. Subir el nuevo CSR a ARCA
3. Descargar el nuevo certificado
4. Reemplazar ambos archivos

---

### Error: "No se pudo determinar la condición de IVA"

**Causa**: El servicio ws_sr_padron_a13 no está autorizado.

**Solución**:
1. Ir a ARCA
2. Autorizar el servicio `ws_sr_padron_a13` para el certificado

---

### Error: "Could not connect to host"

**Causa**: Problema de red o AFIP caído.

**Solución**:
1. Verificar conexión a internet
2. Probar más tarde (AFIP a veces tiene caídas)
3. Verificar que no haya firewall bloqueando

---

## 10. Referencia de Métodos del SDK

### Consultas

```php
use Resguar\AfipSdk\Facades\Afip;

// Verificar autenticación
$authenticated = Afip::isAuthenticated();

// Obtener puntos de venta
$puntosVenta = Afip::getAvailablePointsOfSale();

// Obtener tipos de comprobantes (TODOS los existentes)
$todosLosTipos = Afip::getAvailableReceiptTypes();

// Obtener tipos de comprobantes FILTRADOS por condición fiscal
$tiposHabilitados = Afip::getReceiptTypesForCuit();
// Respuesta:
// [
//     'cuit' => '30718708997',
//     'razon_social' => 'EMPRESA S.R.L.',
//     'condicion_iva' => ['id' => 1, 'description' => 'IVA Responsable Inscripto'],
//     'receipt_types' => [
//         ['id' => 1, 'description' => 'Factura A'],
//         ['id' => 6, 'description' => 'Factura B'],
//         ...
//     ]
// ]

// Obtener datos del contribuyente
$contribuyente = Afip::getTaxpayerStatus('20123456789');

// Obtener último comprobante autorizado
$ultimo = Afip::getLastAuthorizedInvoice(
    pointOfSale: 2,
    invoiceType: 1 // 1=Factura A, 6=Factura B, 11=Factura C
);

// Diagnóstico de problemas
$diagnostico = Afip::diagnoseAuthenticationIssue();
```

### Facturación

```php
// Autorizar factura
$response = Afip::authorizeInvoice([
    'CantReg' => 1,
    'PtoVta' => 2,
    'CbteTipo' => 1, // 1=Factura A
    'Concepto' => 1, // 1=Productos, 2=Servicios, 3=Ambos
    'DocTipo' => 80, // 80=CUIT, 96=DNI
    'DocNro' => '20123456789',
    'CbteDesde' => 1,
    'CbteHasta' => 1,
    'CbteFch' => date('Ymd'),
    'ImpTotal' => 121.00,
    'ImpTotConc' => 0,
    'ImpNeto' => 100.00,
    'ImpOpEx' => 0,
    'ImpIVA' => 21.00,
    'ImpTrib' => 0,
    'MonId' => 'PES',
    'MonCotiz' => 1,
    'Iva' => [
        [
            'Id' => 5, // 21%
            'BaseImp' => 100.00,
            'Importe' => 21.00,
        ]
    ],
]);

// Respuesta:
// $response->cae             // CAE asignado
// $response->caeExpirationDate  // Vencimiento del CAE
// $response->invoiceNumber   // Número de comprobante
```

---

## 11. Configuración Multi-CUIT (Múltiples Empresas)

Si tu sistema maneja facturación para **múltiples empresas/CUITs**, el SDK soporta certificados separados por CUIT.

### 11.1 Estructura de Carpetas

```
storage/certificates/
├── 20123456789/              # CUIT de Empresa 1
│   ├── certificate.crt
│   └── private.key
├── 30987654321/              # CUIT de Empresa 2
│   ├── certificate.crt
│   └── private.key
├── 27456789012/              # CUIT de Empresa 3
│   ├── certificate.crt
│   └── private.key
└── openssl.cnf               # Configuración SSL (compartida)
```

### 11.2 Configuración

```env
# .env
AFIP_CERTIFICATES_BASE_PATH=storage/certificates
```

### 11.3 Uso en el Código

```php
use Resguar\AfipSdk\Facades\Afip;

// El SDK detecta automáticamente el certificado según el CUIT
$tipos = Afip::getReceiptTypesForCuit('20123456789');

// Autorizar factura para un CUIT específico
$response = Afip::authorizeInvoice($invoiceData, '30987654321');

// Obtener puntos de venta de otro CUIT
$puntosVenta = Afip::getAvailablePointsOfSale('27456789012');
```

### 11.4 Cómo Funciona

1. Cuando se llama a un método con un CUIT, el SDK busca:
   - `{certificates_base_path}/{cuit}/certificate.crt`
   - `{certificates_base_path}/{cuit}/private.key`

2. Si existe la carpeta del CUIT, usa esos certificados
3. Si NO existe, usa los certificados por defecto de `certificates.path`

### 11.5 Agregar Nuevo Cliente

Para cada nuevo cliente:

1. Crear carpeta: `storage/certificates/{CUIT}/`
2. Generar clave privada y CSR (ver Paso 3)
3. Subir CSR a ARCA del cliente
4. Descargar certificado y guardarlo como `certificate.crt`
5. Copiar clave privada como `private.key`
6. Autorizar servicios en ARCA (wsfe, ws_sr_padron_a13)

```bash
# Crear estructura para nuevo cliente
CUIT="20123456789"
mkdir -p storage/certificates/$CUIT
chmod 700 storage/certificates/$CUIT

# Generar clave y CSR
openssl genrsa -out storage/certificates/$CUIT/private.key 2048
openssl req -new \
  -key storage/certificates/$CUIT/private.key \
  -out storage/certificates/$CUIT/certificado.csr \
  -subj "/C=AR/O=NOMBRE EMPRESA/CN=alias/serialNumber=CUIT $CUIT"

# Proteger clave
chmod 600 storage/certificates/$CUIT/private.key
```

---

## 📁 Estructura de Archivos

### Modo Simple (Un CUIT)

```
tu-proyecto/
├── storage/
│   └── certificates/
│       ├── clave_privada.key   # ⚠️ NO subir a Git
│       ├── certificado.crt     # ⚠️ NO subir a Git
│       └── openssl.cnf         # Configuración SSL
├── .env                        # Variables de entorno
├── .gitignore                  # Ignorar certificados
└── config/
    └── afip.php                # Configuración del SDK
```

### Modo Multi-CUIT

```
tu-proyecto/
├── storage/
│   └── certificates/
│       ├── 20123456789/
│       │   ├── certificate.crt
│       │   └── private.key
│       ├── 30987654321/
│       │   ├── certificate.crt
│       │   └── private.key
│       └── openssl.cnf
├── .env
├── .gitignore
└── config/
    └── afip.php
```

---

## ✅ Checklist Final

- [ ] CUIT con facturación electrónica habilitada
- [ ] Punto de venta tipo WebService creado
- [ ] Clave privada generada (`clave_privada.key`)
- [ ] CSR subido a ARCA
- [ ] Certificado descargado (`certificado.crt`)
- [ ] Servicio `wsfe` autorizado
- [ ] Servicio `ws_sr_padron_a13` autorizado
- [ ] Variables de entorno configuradas
- [ ] Archivo `openssl.cnf` creado
- [ ] Test de verificación pasado ✅

---

## 📞 Soporte

Si tenés problemas:

1. Ejecutar diagnóstico: `Afip::diagnoseAuthenticationIssue()`
2. Verificar logs: `storage/logs/laravel.log`
3. Consultar documentación AFIP: https://www.afip.gob.ar/ws/

---

**Desarrollado por Resguar IT** | https://resguar.it
