# Guía de Integración: SDK AFIP en Sistema POS

Esta guía te muestra cómo integrar el SDK de AFIP en tu sistema POS específico.

## 📋 Requisitos Previos

- ✅ Sistema POS con Laravel 11
- ✅ Modelos: `SaleHeader`, `SaleItem`, `SaleIva`, `Customer`, `Person`
- ✅ Certificados digitales de AFIP (homologación o producción)
- ✅ Configuración en ARCA/AFIP completada

## 🚀 Paso 1: Instalar el SDK

### Opción A: Desde GitHub (Recomendado)

Edita `apps/backend/composer.json`:

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

Luego ejecuta:

```bash
cd apps/backend
composer require resguar/afip-sdk:dev-main
```

### Opción B: Desde Repositorio Local (Desarrollo)

Si el SDK está en tu máquina local:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../../afip-sdk-resguar"
        }
    ],
    "require": {
        "resguar/afip-sdk": "@dev"
    }
}
```

```bash
cd apps/backend
composer require resguar/afip-sdk:@dev
```

## ⚙️ Paso 2: Configurar el SDK

### 2.1. Publicar Configuración

```bash
cd apps/backend
php artisan vendor:publish --tag=afip-config
```

Esto crea `config/afip.php` en tu proyecto.

### 2.2. Configurar Variables de Entorno

Edita `apps/backend/.env`:

```env
# ============================================
# CONFIGURACIÓN AFIP
# ============================================

# Entorno: 'testing' para homologación, 'production' para producción
AFIP_ENVIRONMENT=testing

# CUIT del contribuyente (sin guiones)
AFIP_CUIT=20457809027

# Ruta base donde están los certificados
AFIP_CERTIFICATES_PATH=storage/certificates

# Nombres de los archivos de certificado
AFIP_CERTIFICATE_KEY=clave_privada.key
AFIP_CERTIFICATE_CRT=certificado.crt

# Contraseña de la clave privada (si tiene)
AFIP_CERTIFICATE_PASSWORD=

# Punto de venta por defecto
AFIP_DEFAULT_POINT_OF_SALE=1

# Cache (opcional)
AFIP_CACHE_ENABLED=true
AFIP_CACHE_TTL=43200
```

### 2.3. Colocar Certificados

```bash
cd apps/backend
mkdir -p storage/certificates

# Copiar tus certificados
cp /ruta/a/certificado.crt storage/certificates/
cp /ruta/a/clave_privada.key storage/certificates/

# Ajustar permisos (importante para seguridad)
chmod 600 storage/certificates/clave_privada.key
chmod 644 storage/certificates/certificado.crt
```

### 2.4. Limpiar Cache

```bash
php artisan config:clear
php artisan cache:clear
```

## 🔧 Paso 3: Integrar en SaleService

Agrega un método en `SaleService` para autorizar facturas con AFIP:

```php
<?php
namespace App\Services;

use App\Interfaces\SaleServiceInterface;
use App\Models\SaleHeader;
use Resguar\AfipSdk\Facades\Afip;
use Resguar\AfipSdk\DTOs\InvoiceResponse;
use Resguar\AfipSdk\Exceptions\AfipException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SaleService implements SaleServiceInterface
{
    // ... métodos existentes ...

    /**
     * Autoriza una venta con AFIP y obtiene el CAE
     *
     * @param SaleHeader $sale
     * @return InvoiceResponse
     * @throws \Exception
     */
    public function authorizeWithAfip(SaleHeader $sale): InvoiceResponse
    {
        try {
            // Cargar relaciones necesarias
            $sale->load([
                'receiptType',
                'customer.person',
                'items.product.iva',
                'saleIvas.iva',
                'branch'
            ]);

            // Validar que la venta sea facturable (no presupuesto)
            if ($sale->receiptType && $sale->receiptType->afip_code === '016') {
                throw new \Exception('Los presupuestos no se pueden autorizar con AFIP');
            }

            // Validar que tenga cliente
            if (!$sale->customer || !$sale->customer->person) {
                throw new \Exception('La venta debe tener un cliente asociado');
            }

            // Preparar datos para AFIP
            $invoiceData = $this->prepareInvoiceDataForAfip($sale);

            // Autorizar con AFIP (el SDK maneja todo: autenticación, correlatividad, etc.)
            $result = Afip::authorizeInvoice($invoiceData);

            // Actualizar la venta con el CAE
            DB::transaction(function () use ($sale, $result) {
                $sale->update([
                    'cae' => $result->cae,
                    'cae_expiration_date' => \Carbon\Carbon::createFromFormat('Ymd', $result->caeExpirationDate),
                    'receipt_number' => str_pad($result->invoiceNumber, 8, '0', STR_PAD_LEFT),
                ]);
            });

            Log::info('Venta autorizada con AFIP', [
                'sale_id' => $sale->id,
                'cae' => $result->cae,
                'invoice_number' => $result->invoiceNumber,
            ]);

            return $result;
        } catch (AfipException $e) {
            Log::error('Error de AFIP al autorizar venta', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
                'afip_code' => $e->getAfipCode(),
            ]);
            throw new \Exception("Error al autorizar con AFIP: {$e->getMessage()}", 0, $e);
        } catch (\Exception $e) {
            Log::error('Error inesperado al autorizar venta con AFIP', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Prepara los datos de la venta en formato requerido por AFIP
     *
     * @param SaleHeader $sale
     * @return array
     */
    private function prepareInvoiceDataForAfip(SaleHeader $sale): array
    {
        $customer = $sale->customer->person;
        $receiptType = $sale->receiptType;
        $branch = $sale->branch;

        // Mapear tipo de comprobante AFIP
        // ReceiptType debe tener un campo afip_code o similar
        $invoiceType = $this->mapReceiptTypeToAfipType($receiptType);

        // Mapear tipo de documento del cliente
        $customerDocumentType = $this->mapDocumentTypeToAfipType($sale->saleDocumentType);

        // Preparar items
        $items = [];
        foreach ($sale->items as $item) {
            $items[] = [
                'code' => $item->product->code ?? null,
                'description' => $item->product->description ?? 'Producto sin descripción',
                'quantity' => (float) $item->quantity,
                'unitPrice' => (float) $item->unit_price,
                'taxRate' => (float) $item->iva_rate,
            ];
        }

        // Preparar IVA por tasa
        $ivaItems = [];
        foreach ($sale->saleIvas as $saleIva) {
            $ivaItems[] = [
                'id' => $this->mapIvaRateToAfipId((float) $saleIva->iva->rate),
                'baseAmount' => (float) $saleIva->base_amount,
                'amount' => (float) $saleIva->iva_amount,
            ];
        }

        // Calcular totales
        $netAmount = (float) $sale->subtotal; // Base neta gravada
        $ivaTotal = (float) $sale->total_iva_amount;
        $total = (float) $sale->total;

        // Determinar concepto (1=Productos, 2=Servicios, 3=Productos y Servicios)
        $concept = $this->determineConcept($sale);

        return [
            'pointOfSale' => $branch->afip_point_of_sale ?? config('afip.default_point_of_sale', 1),
            'invoiceType' => $invoiceType,
            'invoiceNumber' => (int) $sale->receipt_number, // Se ajustará automáticamente si es necesario
            'date' => $sale->date->format('Ymd'),
            'customerCuit' => $customer->cuit ?? '',
            'customerDocumentType' => $customerDocumentType,
            'customerDocumentNumber' => $customer->cuit ?? $sale->sale_document_number ?? '',
            'concept' => $concept,
            'items' => $items,
            'netAmount' => $netAmount,
            'ivaTotal' => $ivaTotal,
            'total' => $total,
            'ivaItems' => $ivaItems,
            'nonTaxedTotal' => 0.0, // Ajustar si tienes montos no gravados
            'exemptAmount' => 0.0, // Ajustar si tienes montos exentos
            'tributesTotal' => (float) ($sale->iibb ?? 0) + (float) ($sale->internal_tax ?? 0),
            'serviceStartDate' => $sale->service_from_date ? $sale->service_from_date->format('Ymd') : null,
            'serviceEndDate' => $sale->service_to_date ? $sale->service_to_date->format('Ymd') : null,
            'paymentDueDate' => $sale->service_due_date ? $sale->service_due_date->format('Ymd') : null,
        ];
    }

    /**
     * Mapea el tipo de comprobante del sistema al código AFIP
     */
    private function mapReceiptTypeToAfipType($receiptType): int
    {
        if (!$receiptType || !$receiptType->afip_code) {
            return 1; // Factura A por defecto
        }

        // Mapeo común de códigos AFIP
        $mapping = [
            '001' => 1,  // Factura A
            '006' => 6,  // Factura B
            '011' => 11, // Factura C
            '012' => 12, // Nota de Débito A
            '013' => 13, // Nota de Débito B
            '008' => 8,  // Nota de Crédito A
            '003' => 3,  // Nota de Crédito B
        ];

        return $mapping[$receiptType->afip_code] ?? 1;
    }

    /**
     * Mapea el tipo de documento del cliente al código AFIP
     */
    private function mapDocumentTypeToAfipType($documentType): int
    {
        if (!$documentType) {
            return 99; // Consumidor Final
        }

        // Mapeo común
        $mapping = [
            'CUIT' => 80,
            'CUIL' => 86,
            'CDI' => 87,
            'LE' => 89,
            'LC' => 90,
            'DNI' => 96,
            'Consumidor Final' => 99,
        ];

        $name = strtoupper($documentType->name ?? '');
        return $mapping[$name] ?? 99;
    }

    /**
     * Mapea la tasa de IVA al ID de AFIP
     */
    private function mapIvaRateToAfipId(float $rate): int
    {
        $mapping = [
            0.0 => 3,   // 0% (Exento)
            10.5 => 4,  // 10.5%
            21.0 => 5,  // 21%
            27.0 => 6,  // 27%
        ];

        return $mapping[$rate] ?? 5; // 21% por defecto
    }

    /**
     * Determina el concepto según los items de la venta
     */
    private function determineConcept(SaleHeader $sale): int
    {
        // Por defecto, asumimos productos
        // Ajustar según tu lógica de negocio
        return 1; // 1 = Productos, 2 = Servicios, 3 = Productos y Servicios
    }
}
```

## 🎯 Paso 4: Usar en Controladores

### Ejemplo 1: Autorizar al Crear Venta

Modifica tu controlador de ventas para autorizar automáticamente:

```php
<?php
namespace App\Http\Controllers;

use App\Services\SaleService;
use App\Models\SaleHeader;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    protected SaleServiceInterface $saleService;

    public function __construct(SaleServiceInterface $saleService)
    {
        $this->saleService = $saleService;
    }

    /**
     * Crear venta y autorizar con AFIP
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Crear la venta
            $sale = $this->saleService->createSale($request->all());

            // Autorizar con AFIP (solo si no es presupuesto)
            if ($sale->receiptType && $sale->receiptType->afip_code !== '016') {
                $this->saleService->authorizeWithAfip($sale);
            }

            return response()->json([
                'success' => true,
                'data' => $sale->fresh(['items', 'saleIvas']),
                'message' => 'Venta creada y autorizada con AFIP exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear venta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Autorizar una venta existente con AFIP
     */
    public function authorizeWithAfip(int $id): JsonResponse
    {
        try {
            $sale = SaleHeader::findOrFail($id);

            // Verificar que no esté ya autorizada
            if ($sale->cae) {
                return response()->json([
                    'success' => false,
                    'message' => 'La venta ya está autorizada con CAE: ' . $sale->cae
                ], 400);
            }

            $result = $this->saleService->authorizeWithAfip($sale);

            return response()->json([
                'success' => true,
                'data' => [
                    'cae' => $result->cae,
                    'cae_expiration_date' => $result->caeExpirationDate,
                    'invoice_number' => $result->invoiceNumber,
                ],
                'message' => 'Venta autorizada con AFIP exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al autorizar con AFIP: ' . $e->getMessage()
            ], 500);
        }
    }
}
```

### Ejemplo 2: Ruta API

Agrega la ruta en `routes/api.php`:

```php
Route::post('/sales/{id}/authorize-afip', [SaleController::class, 'authorizeWithAfip'])
    ->middleware('auth:sanctum');
```

## 🧪 Paso 5: Probar la Integración

### 5.1. Verificar Configuración

```bash
cd apps/backend
php artisan tinker
```

```php
use Resguar\AfipSdk\Facades\Afip;

// Verificar configuración
config('afip.cuit');
config('afip.environment');

// Probar autenticación
Afip::isAuthenticated(); // Debe retornar true/false
```

### 5.2. Probar Autorización

```php
// En tinker o en un test
$sale = \App\Models\SaleHeader::with(['customer.person', 'items', 'saleIvas'])->first();

$saleService = app(\App\Services\SaleService::class);
$result = $saleService->authorizeWithAfip($sale);

echo "CAE: " . $result->cae . "\n";
echo "Número: " . $result->invoiceNumber . "\n";
echo "Vencimiento: " . $result->caeExpirationDate . "\n";
```

## 📝 Paso 6: Consideraciones Importantes

### 6.1. Mapeo de Tipos de Comprobante

Asegúrate de que tu modelo `ReceiptType` tenga el campo `afip_code` con los códigos correctos de AFIP:

```php
// Ejemplo de migración si no existe
Schema::table('receipt_types', function (Blueprint $table) {
    $table->string('afip_code', 3)->nullable()->after('code');
});
```

### 6.2. Punto de Venta por Sucursal

Si cada sucursal tiene su propio punto de venta AFIP, agrega el campo en `Branch`:

```php
Schema::table('branches', function (Blueprint $table) {
    $table->integer('afip_point_of_sale')->nullable()->after('description');
});
```

### 6.3. Manejo de Errores

El SDK lanza excepciones específicas:

- `AfipAuthenticationException`: Error de autenticación
- `AfipAuthorizationException`: Error al autorizar comprobante
- `AfipException`: Error general

Maneja estos errores apropiadamente en tu código.

### 6.4. Correlatividad

El SDK **automáticamente** consulta el último comprobante autorizado y ajusta el número si es necesario. No necesitas hacer nada adicional.

### 6.5. Cache de Tokens

Los tokens de autenticación se cachean automáticamente por 12 horas. No necesitas manejar esto manualmente.

## 🔄 Flujo Completo

1. **Usuario crea venta** → `SaleService::createSale()`
2. **Sistema autoriza con AFIP** → `SaleService::authorizeWithAfip()`
3. **SDK consulta último comprobante** (automático)
4. **SDK ajusta número si es necesario** (automático)
5. **SDK autoriza con AFIP** → Obtiene CAE
6. **Sistema guarda CAE en venta** → `SaleHeader::update(['cae' => ...])`
7. **Sistema genera PDF** (con el CAE incluido)

## 📚 Recursos Adicionales

- [Guía de Uso Completa](GUIA_USO_LARAVEL.md)
- [Checklist Pre-Producción](CHECKLIST_PRE_PRODUCCION.md)
- [Configurar Certificados](CONFIGURAR_CERTIFICADOS.md)
- [Guía de Pruebas](GUIA_PRUEBAS.md)

## ❓ Preguntas Frecuentes

**P: ¿Puedo autorizar presupuestos?**
R: No, los presupuestos (código 016) no se autorizan con AFIP.

**P: ¿Qué pasa si el número de comprobante ya existe?**
R: El SDK automáticamente consulta el último autorizado y ajusta el número.

**P: ¿Cómo sé si la autorización fue exitosa?**
R: Si el método no lanza excepción y retorna un `InvoiceResponse`, fue exitosa. Verifica el campo `cae` en la venta.

**P: ¿Puedo autorizar ventas en lote?**
R: El SDK autoriza una factura a la vez. Para lote, itera sobre las ventas y autoriza cada una.

**P: ¿Qué hacer si falla la autorización?**
R: Revisa los logs (`storage/logs/laravel.log`) y el mensaje de error. Los errores de AFIP incluyen códigos específicos.

