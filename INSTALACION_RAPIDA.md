# Instalación Rápida - 5 Minutos

## 🚀 Pasos Rápidos

### 1. Instalar SDK en tu Proyecto Laravel

**Opción A: Desde GitHub (Recomendado)**

Edita `composer.json` de tu proyecto:

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

Luego:
```bash
composer require resguar/afip-sdk:dev-main
```

**Opción B: Desde Repositorio Local**

Si el SDK está en tu máquina:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../afip-sdk-resguar"
        }
    ],
    "require": {
        "resguar/afip-sdk": "@dev"
    }
}
```

```bash
composer require resguar/afip-sdk:@dev
```

### 2. Publicar Configuración

```bash
php artisan vendor:publish --tag=afip-config
```

### 3. Configurar `.env`

```env
AFIP_ENVIRONMENT=testing
AFIP_CUIT=20457809027
AFIP_CERTIFICATES_PATH=storage/certificates
AFIP_CERTIFICATE_KEY=clave_privada.key
AFIP_CERTIFICATE_CRT=certificado.crt
AFIP_CERTIFICATE_PASSWORD=
```

### 4. Colocar Certificados

```bash
mkdir -p storage/certificates
cp certificado.crt storage/certificates/
cp clave_privada.key storage/certificates/
chmod 600 storage/certificates/clave_privada.key
chmod 644 storage/certificates/certificado.crt
```

### 5. Limpiar Cache

```bash
php artisan config:clear
```

### 6. Probar

```bash
php artisan tinker
```

```php
use Resguar\AfipSdk\Facades\Afip;
Afip::isAuthenticated() // Debe retornar true/false
```

## ✅ Listo!

Ahora puedes usar el SDK en tu código:

```php
use Resguar\AfipSdk\Facades\Afip;

$result = Afip::authorizeInvoice($datosFactura);
```

## 📚 Más Información

- [Guía Completa de Uso](GUIA_USO_LARAVEL.md) - Instalación detallada y ejemplos
- [Checklist Pre-Producción](CHECKLIST_PRE_PRODUCCION.md) - Qué necesitas antes de probar

