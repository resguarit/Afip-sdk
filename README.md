    # AFIP SDK para Laravel - Guía Completa de Integración

    [![PHP Version](https://img.shields.io/badge/php-8.1%2B-blue.svg)](https://www.php.net/)
    [![Laravel Version](https://img.shields.io/badge/laravel-11%2B-red.svg)](https://laravel.com/)
    [![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

    SDK independiente y reutilizable para integración con AFIP (Administración Federal de Ingresos Públicos de Argentina) - Facturación Electrónica.

    ## 📋 Tabla de Contenidos

    - [Requisitos](#-requisitos)
    - [Instalación](#-instalación)
    - [Configuración](#-configuración)
    - [Uso Básico](#-uso-básico)
    - [Integración en Sistema POS](#-integración-en-sistema-pos)
    - [Troubleshooting](#-troubleshooting)
    - [Documentación Adicional](#-documentación-adicional)

    ---

    ## ✅ Requisitos

    Antes de comenzar, asegúrate de tener:

    - ✅ PHP 8.1 o superior
    - ✅ Laravel 11 o superior
    - ✅ Extensiones PHP: `openssl`, `soap`
    - ✅ Certificados digitales de AFIP (homologación o producción)
    - ✅ Configuración completada en ARCA/AFIP

    **Verificar extensiones:**
    ```bash
    php -m | grep -E "openssl|soap"
    ```

    ---

    ## 🚀 Instalación

    ### Paso 1: Agregar al `composer.json`

    Edita el archivo `composer.json` de tu proyecto Laravel y agrega el repositorio:

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

    ### Paso 2: Instalar el SDK

    ```bash
    composer require resguar/afip-sdk:dev-main
    ```

    ### Paso 3: Publicar Configuración

    ```bash
    php artisan vendor:publish --tag=afip-config
    ```

    Esto crea el archivo `config/afip.php` en tu proyecto.

    ---

    ## ⚙️ Configuración

    ### Paso 1: Configurar Variables de Entorno

    Edita tu archivo `.env` y agrega:

    ```env
    # ============================================
    # CONFIGURACIÓN AFIP
    # ============================================

    # Entorno: 'testing' para homologación, 'production' para producción
    AFIP_ENVIRONMENT=testing

    # CUIT del contribuyente (sin guiones, 11 dígitos)
    AFIP_CUIT=20457809027

    # Ruta donde están los certificados (relativa a la raíz del proyecto)
    AFIP_CERTIFICATES_PATH=storage/certificates

    # Nombres de los archivos de certificado
    AFIP_CERTIFICATE_KEY=clave_privada.key
    AFIP_CERTIFICATE_CRT=certificado.crt

    # Contraseña de la clave privada (dejar vacío si no tiene)
    AFIP_CERTIFICATE_PASSWORD=

    # Punto de venta por defecto
    AFIP_DEFAULT_POINT_OF_SALE=1

    # Cache (opcional, valores por defecto)
    AFIP_CACHE_ENABLED=true
    AFIP_CACHE_TTL=43200
    ```

    ### Paso 2: Colocar Certificados

    ```bash
    # Crear directorio para certificados
    mkdir -p storage/certificates

    # Copiar tus certificados (ajusta las rutas según tu caso)
    cp /ruta/a/certificado.crt storage/certificates/
    cp /ruta/a/clave_privada.key storage/certificates/

    # Ajustar permisos (IMPORTANTE para seguridad)
    chmod 600 storage/certificates/clave_privada.key
    chmod 644 storage/certificates/certificado.crt
    ```

    **⚠️ IMPORTANTE:**
    - **NUNCA** subas los certificados de usuario (`.key`, `.crt` privados) al repositorio Git
    - Asegúrate de que estén en `.gitignore`
    - Los certificados deben tener los nombres exactos especificados en `.env`

    #### Certificados de la Cadena de Certificación (Producción)

    En la carpeta `documentacion_afip/Cadena_de_certificacion_prod_2024_2035/` encontrarás los certificados raíz e intermedios de AFIP para producción:

    - **`AFIPRootCA2.cacert_2015-2035.crt`** - Certificado raíz de AFIP (válido 2015-2035)
    - **`Computadores.cacert_2024-2035.crt`** - Certificado intermedio de Computadores (válido 2024-2035)

    **¿Para qué sirven?**
    
    Estos certificados se usan para validar la cadena de certificación de AFIP en producción. Son certificados **públicos** de la Autoridad Certificadora (CA) de AFIP y **SÍ pueden** estar en el repositorio (a diferencia de tus certificados privados).

    **¿Cuándo se usan?**
    
    - En producción, para validar que los certificados emitidos por AFIP son confiables
    - Para configurar el cliente SOAP con la cadena de certificación completa
    - Para evitar errores de validación SSL/TLS al conectarse a los servicios de AFIP

    **Nota:** El SDK actualmente no requiere estos certificados explícitamente, pero pueden ser útiles para configuración avanzada o troubleshooting en producción.

    ### Paso 3: Limpiar Cache

    ```bash
    php artisan config:clear
    php artisan cache:clear
    ```

    ### Paso 4: Verificar Configuración

    ```bash
    php artisan tinker
    ```

    ```php
    // Verificar configuración
    config('afip.cuit');           // Debe mostrar tu CUIT
    config('afip.environment');    // Debe mostrar 'testing' o 'production'
    config('afip.certificates.path'); // Debe mostrar la ruta correcta

    // Probar autenticación
    use Resguar\AfipSdk\Facades\Afip;
    Afip::isAuthenticated(); // Debe retornar true/false
    ```

    ---

    ## 📖 Uso Básico

    ### Opción 1: Usando la Facade (Recomendado)

    ```php
    use Resguar\AfipSdk\Facades\Afip;
    use Resguar\AfipSdk\Exceptions\AfipException;

    try {
        // Preparar datos de la factura
        $invoiceData = [
            'pointOfSale' => 1,
            'invoiceType' => 1, // 1 = Factura A
            'invoiceNumber' => 0, // 0 = auto (se ajusta automáticamente)
            'date' => now()->format('Ymd'),
            'customerCuit' => '20123456789',
            'customerDocumentType' => 80, // 80 = CUIT
            'customerDocumentNumber' => '20123456789',
            'concept' => 1, // 1 = Productos
            'items' => [
                [
                    'code' => 'PROD001',
                    'description' => 'Producto de ejemplo',
                    'quantity' => 1,
                    'unitPrice' => 100.0,
                    'taxRate' => 21.0,
                ]
            ],
            'netAmount' => 100.0,
            'ivaTotal' => 21.0,
            'total' => 121.0,
            'ivaItems' => [
                [
                    'id' => 5, // 21%
                    'baseAmount' => 100.0,
                    'amount' => 21.0,
                ]
            ],
        ];

        // Autorizar factura (el SDK hace TODO automáticamente)
        $result = Afip::authorizeInvoice($invoiceData);

        // El resultado es un InvoiceResponse DTO
        echo "CAE: " . $result->cae . "\n";
        echo "Vencimiento: " . $result->caeExpirationDate . "\n";
        echo "Número: " . $result->invoiceNumber . "\n";

        // Verificar si el CAE está vigente
        if ($result->isCaeValid()) {
            echo "CAE válido\n";
        }

    } catch (AfipException $e) {
        echo "Error: " . $e->getMessage() . "\n";
        if ($e->getAfipCode()) {
            echo "Código AFIP: " . $e->getAfipCode() . "\n";
        }
    }
    ```

    ### Opción 2: Inyección de Dependencias

    ```php
    use Resguar\AfipSdk\Contracts\AfipServiceInterface;
    use Resguar\AfipSdk\DTOs\InvoiceResponse;

    class InvoiceController
    {
        public function __construct(
            private AfipServiceInterface $afipService
        ) {}

        public function authorize(array $invoiceData): InvoiceResponse
        {
            return $this->afipService->authorizeInvoice($invoiceData);
        }
    }
    ```

    ### Obtener Último Comprobante Autorizado

    ```php
    use Resguar\AfipSdk\Facades\Afip;

    // Consultar último comprobante autorizado
    $lastInvoice = Afip::getLastAuthorizedInvoice(
        pointOfSale: 1,
        invoiceType: 1
    );

    // Retorna:
    // [
    //     'CbteNro' => 105,
    //     'CbteFch' => '20240101',
    //     'PtoVta' => 1,
    //     'CbteTipo' => 1
    // ]
    ```

    **Nota:** El SDK **automáticamente** consulta el último comprobante antes de autorizar para asegurar correlatividad. No necesitas hacerlo manualmente.

    ### Verificar Factura Autorizada

    Después de autorizar una factura, puedes verificar que fue generada correctamente de varias formas:

    #### 1. Consultar Último Comprobante (SDK)

    ```php
    use Resguar\AfipSdk\Facades\Afip;

    // Consultar último comprobante autorizado
    $lastInvoice = Afip::getLastAuthorizedInvoice(
        pointOfSale: 1,
        invoiceType: 1
    );

    echo "Número: " . $lastInvoice['CbteNro'] . "\n";
    echo "Fecha: " . $lastInvoice['CbteFch'] . "\n";
    ```

    #### 2. Portal Web de AFIP

    **⚠️ IMPORTANTE - MODO TESTING (Homologación):**
    
    En el entorno de **testing/homologación**, las facturas **SÍ se registran** en los servidores de AFIP (verificado mediante WSFE), pero el **portal web puede NO mostrarlas** debido a limitaciones del portal en el ambiente de pruebas.
    
    **Forma confiable de verificar en testing:**
    - Usa el SDK para consultar: `Afip::getLastAuthorizedInvoice()`
    - Las facturas están registradas en AFIP (el SDK las consulta directamente)
    - El portal web es solo una interfaz y puede tener limitaciones en testing
    
    **Para Testing (Homologación):**
    - Portal: https://www.afip.gob.ar/fe/
    - ⚠️ **Nota:** Las facturas pueden no aparecer en el portal web de testing
    - ✅ **Verificación confiable:** Usa el SDK (`getLastAuthorizedInvoice()`)

    **Para Producción:**
    - Portal: https://www.afip.gob.ar/fe/
    - Ingresa con tu CUIT
    - Las facturas **SÍ aparecerán** en el portal web
    - También puedes verificar mediante SDK

    #### 3. En tu Base de Datos

    Si guardaste el CAE en tu base de datos:

    ```php
    // Ejemplo: Buscar venta por CAE
    $sale = SaleHeader::where('cae', '75467293120462')->first();
    
    if ($sale) {
        echo "Factura encontrada:\n";
        echo "CAE: " . $sale->cae . "\n";
        echo "Número: " . $sale->receipt_number . "\n";
        echo "Fecha vencimiento CAE: " . $sale->cae_expiration_date . "\n";
    }
    ```

    #### 4. En los Logs del Sistema

    ```bash
    # Ver logs de Laravel
    tail -f storage/logs/laravel.log | grep -i "cae\|factura\|afip"

    # Buscar por CAE específico
    grep "75467293120462" storage/logs/laravel.log
    ```

    ---

    ## 🎯 Integración en Sistema POS

    ### Paso 1: Agregar Método en SaleService

    Agrega este método a tu `SaleService`:

    ```php
    use Resguar\AfipSdk\Facades\Afip;
    use Resguar\AfipSdk\Exceptions\AfipException;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Log;
    use Carbon\Carbon;

    /**
    * Autoriza una venta con AFIP y obtiene el CAE
    *
    * @param SaleHeader $sale
    * @return array
    * @throws \Exception
    */
    public function authorizeWithAfip(SaleHeader $sale): array
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

            // Autorizar con AFIP (el SDK maneja todo automáticamente)
            $result = Afip::authorizeInvoice($invoiceData);

            // Actualizar la venta con el CAE
            DB::transaction(function () use ($sale, $result) {
                $sale->update([
                    'cae' => $result->cae,
                    'cae_expiration_date' => Carbon::createFromFormat('Ymd', $result->caeExpirationDate),
                    'receipt_number' => str_pad($result->invoiceNumber, 8, '0', STR_PAD_LEFT),
                ]);
            });

            Log::info('Venta autorizada con AFIP', [
                'sale_id' => $sale->id,
                'cae' => $result->cae,
                'invoice_number' => $result->invoiceNumber,
            ]);

            // Retornar array
            return $result->toArray();

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
    */
    private function prepareInvoiceDataForAfip(SaleHeader $sale): array
    {
        $customer = $sale->customer->person;
        $receiptType = $sale->receiptType;
        $branch = $sale->branch;

        // Mapear tipo de comprobante AFIP
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

        // Obtener punto de venta
        $pointOfSale = $branch->point_of_sale 
            ? (int) $branch->point_of_sale 
            : config('afip.default_point_of_sale', 1);

        return [
            'pointOfSale' => $pointOfSale,
            'invoiceType' => $invoiceType,
            'invoiceNumber' => (int) $sale->receipt_number, // Se ajustará automáticamente si es necesario
            'date' => $sale->date->format('Ymd'),
            'customerCuit' => $customer->cuit ?? '',
            'customerDocumentType' => $customerDocumentType,
            'customerDocumentNumber' => $customer->cuit ?? $sale->sale_document_number ?? '',
            'concept' => 1, // 1 = Productos, ajustar según tu lógica
            'items' => $items,
            'netAmount' => (float) $sale->subtotal,
            'ivaTotal' => (float) $sale->total_iva_amount,
            'total' => (float) $sale->total,
            'ivaItems' => $ivaItems,
            'nonTaxedTotal' => 0.0,
            'exemptAmount' => 0.0,
            'tributesTotal' => (float) (($sale->iibb ?? 0) + ($sale->internal_tax ?? 0)),
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
    ```

    ### Paso 2: Usar en Controlador

    ```php
    use App\Services\SaleService;
    use App\Models\SaleHeader;

    class SaleController extends Controller
    {
        public function __construct(
            private SaleService $saleService
        ) {}

        /**
        * Crear venta y autorizar con AFIP
        */
        public function store(Request $request)
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
                    'data' => $sale->fresh(),
                    'message' => 'Venta creada y autorizada con AFIP exitosamente'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ], 500);
            }
        }

        /**
        * Autorizar una venta existente con AFIP
        */
        public function authorizeWithAfip(int $id)
        {
            try {
                $sale = SaleHeader::findOrFail($id);

                if ($sale->cae) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La venta ya está autorizada con CAE: ' . $sale->cae
                    ], 400);
                }

                $result = $this->saleService->authorizeWithAfip($sale);

                return response()->json([
                    'success' => true,
                    'data' => $result,
                    'message' => 'Venta autorizada con AFIP exitosamente'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ], 500);
            }
        }
    }
    ```

    ### Paso 3: Agregar Ruta (Opcional)

    ```php
    // routes/api.php
    Route::post('/sales/{id}/authorize-afip', [SaleController::class, 'authorizeWithAfip'])
        ->middleware('auth:sanctum');
    ```

    ---

    ## 🔍 Características del SDK

    ### ✅ Automático

    El SDK maneja automáticamente:

    - **Autenticación con WSAA**: Obtiene token y firma (válidos 12 horas)
    - **Cache de tokens**: Evita llamadas innecesarias a AFIP
    - **Correlatividad**: Consulta último comprobante antes de autorizar
    - **Ajuste de números**: Ajusta automáticamente si el número ya existe
    - **Retry automático**: Reintenta en errores de conexión (hasta 3 intentos)
    - **Validación de datos**: Valida antes de enviar a AFIP
    - **Mapeo de datos**: Convierte tus datos al formato AFIP

    ### 📊 Respuesta del SDK

    El SDK siempre retorna un objeto `InvoiceResponse` (DTO):

    ```php
    InvoiceResponse {
        cae: "71000001234567"           // Código de Autorización Electrónico
        caeExpirationDate: "20240201"   // Fecha de vencimiento (formato Ymd)
        invoiceNumber: 106               // Número de comprobante autorizado
        pointOfSale: 1                   // Punto de venta
        invoiceType: 1                   // Tipo de comprobante
        observations: []                 // Observaciones de AFIP (si las hay)
    }
    ```

    **Acceso:**
    ```php
    $result->cae                    // string
    $result->caeExpirationDate      // string (Ymd)
    $result->invoiceNumber          // int
    $result->isCaeValid()           // bool - Verifica si está vigente
    $result->toArray()              // array - Convierte a array
    ```

    ---

    ## 🐛 Troubleshooting

    ### Errores de Configuración

    #### Error: "CUIT no configurado"

    **Solución:**
    ```bash
    # Verificar .env
    cat .env | grep AFIP_CUIT

    # Limpiar cache
    php artisan config:clear
    ```

    #### Error: "Error al cargar clave privada"

    **Causas posibles:**
    1. Archivo no existe o ruta incorrecta
    2. Permisos incorrectos
    3. Contraseña incorrecta

    **Solución:**
    ```bash
    # Verificar que el archivo existe
    ls -la storage/certificates/clave_privada.key

    # Verificar permisos (debe ser 600)
    chmod 600 storage/certificates/clave_privada.key

    # Verificar contraseña en .env
    AFIP_CERTIFICATE_PASSWORD=tu_password_correcto
    ```

    ### Errores de Certificados

    #### Error: "Certificado no incluido en CMS" (`ns1:cms.cert.notFound`)

    **Causa:** El certificado no se está incluyendo en el mensaje CMS.

    **Solución:** Verifica que el SDK esté actualizado. Este error fue corregido en versiones recientes.

    #### Error: "No se ha encontrado certificado de firmador"

    **Causas posibles:**
    1. Certificado no activado en ARCA
    2. Certificado no tiene autorización para WSFE
    3. Certificado no asociado al alias en ARCA
    4. Certificado corresponde a otro CUIT

    **Solución:**
    1. Ve a ARCA (https://www.afip.gob.ar/arqa/)
    2. Verifica que el certificado esté en estado **"VALIDO"**
    3. Verifica que exista autorización para el servicio **"wsfe"**
    4. Si el certificado no está asociado al alias, agrégalo desde ARCA → "Agregar certificado a alias"

    #### Error: "Certificado y clave privada no coinciden"

    **Solución:**
    ```bash
    # Verificar coincidencia
    openssl x509 -noout -modulus -in storage/certificates/certificado.crt | openssl md5
    openssl rsa -noout -modulus -in storage/certificates/clave_privada.key | openssl md5
    ```
    
    Si los hashes NO coinciden, el certificado y la clave no son del mismo par. Debes usar el par correcto.

    ### Errores de Autenticación

    #### Error 600: "ValidacionDeToken: Error al verificar hash"

    **Causa:** OpenSSL está modificando el XML antes de firmarlo.

    **Solución:** Verifica que el SDK esté actualizado. Este error fue corregido agregando el flag `-binary` a OpenSSL.

    #### Error: "El CEE ya posee un TA valido para el acceso al WSN solicitado"

    **Causa:** AFIP reporta que ya existe un token válido, pero el SDK no lo tiene en cache.

    **Solución:** El SDK maneja esto automáticamente. Si persiste, limpia el cache:
    ```bash
    php artisan cache:clear
    ```

    ### Errores de Autorización

    #### Error 10246: Campo `CondicionIVAReceptorId` faltante

    **Causa:** AFIP requiere obligatoriamente la condición frente al IVA del receptor (RG 5616).

    **Solución:** El SDK asigna automáticamente:
    - Factura A (tipo 1) → Responsable Inscripto (1)
    - Factura B (tipo 6) u otra → Consumidor Final (5)

    Puedes especificarlo manualmente:
    ```php
    $invoiceData['receiverConditionIVA'] = 1; // 1=RI, 4=Exento, 5=CF, 6=Monotributo
    ```

    #### Error 10243: Incompatibilidad tipo comprobante/IVA

    **Causa:** Se envió una condición IVA incompatible con el tipo de comprobante.

    **Solución:** El SDK maneja esto automáticamente. Factura A solo acepta Responsable Inscripto (1) o Exento (4).

    #### Error: "Código AFIP: 10015" (Comprobante ya existe)

    **Causa:** Intentaste autorizar un número que ya fue autorizado.

    **Solución:** El SDK automáticamente ajusta el número. Si persiste, verifica manualmente:
    ```php
    $lastInvoice = Afip::getLastAuthorizedInvoice(1, 1);
    echo "Último autorizado: " . $lastInvoice['CbteNro'];
    ```

    ### Errores de Conexión

    #### Error: "Error SOAP al llamar"

    **Causas posibles:**
    1. Problema de conexión a internet
    2. Servicios de AFIP caídos
    3. Certificado inválido o expirado

    **Solución:**
    - Verificar conexión a internet
    - Verificar que los certificados no hayan expirado
    - Revisar logs: `storage/logs/laravel.log`

    ### Ver Logs

    ```bash
    # Ver logs de Laravel
    tail -f storage/logs/laravel.log

    # Buscar errores de AFIP
    grep -i "afip" storage/logs/laravel.log

    # Ver solo errores
    grep -i "error.*afip" storage/logs/laravel.log
    ```

    ### Checklist de Verificación

    Antes de reportar un error, verifica:

    - [ ] Certificado (`.crt`) descargado de ARCA
    - [ ] Clave privada (`.key`) generada localmente (NO descargada)
    - [ ] Ambos archivos en la ruta configurada
    - [ ] Permisos correctos (600 para `.key`, 644 para `.crt`)
    - [ ] Certificado y clave privada coinciden
    - [ ] CUIT configurado correctamente en `.env`
    - [ ] Entorno configurado como `testing` (no `production`)
    - [ ] Certificado activado en ARCA (ambiente Testing para pruebas, Producción para producción)
    - [ ] Autorización creada para `wsfe` en ARCA
    - [ ] CUIT del certificado coincide con el configurado
    - [ ] SDK actualizado a la última versión
    - [ ] (Producción) Certificados de cadena de certificación disponibles si es necesario

    ---

## 📚 Referencias Técnicas

- [Documentación oficial de AFIP](https://www.afip.gob.ar/fe/)
- [Web Services de AFIP](https://www.afip.gob.ar/fe/documentos/)
- [ARCA - Administración de Certificados](https://www.afip.gob.ar/arqa/)

### Documentación Adicional Incluida

En la carpeta `documentacion_afip/` encontrarás:

- **Manuales de Desarrollo:**
  - `manual-desarrollador-ARCA-COMPG-v4-0-3.pdf` - Manual completo de ARCA v4.0.3
  - `manual-desarrollador-ARCA-COMPG-v4-0-2.pdf` - Manual ARCA v4.0.2
  - `WSAAmanualDev.pdf` - Manual de desarrollo WSAA

- **Especificaciones Técnicas:**
  - `Especificacion_Tecnica_WSAA_1.2.2.pdf` - Especificación técnica WSAA
  - `WSAA.ObtenerCertificado.pdf` - Guía para obtener certificados
  - `ADMINREL.DelegarWS.pdf` - Guía de delegación de Web Services

- **Guías de Adhesión:**
  - `WSASS_como_adherirse.pdf` - Cómo adherirse a Web Services
  - `WSASS_manual.pdf` - Manual de Web Services

- **Certificados de Producción:**
  - `Cadena_de_certificacion_prod_2024_2035/` - Certificados raíz e intermedios de AFIP para producción (2024-2035)

    ---

    ## ❓ Preguntas Frecuentes

    **P: ¿Puedo autorizar presupuestos?**
    R: No, los presupuestos (código 016) no se autorizan con AFIP.

    **P: ¿Qué pasa si el número de comprobante ya existe?**
    R: El SDK automáticamente consulta el último autorizado y ajusta al siguiente número disponible.

    **P: ¿Necesito manejar tokens manualmente?**
    R: No, el SDK cachea tokens automáticamente por 12 horas.

    **P: ¿Puedo usar el SDK en múltiples proyectos?**
    R: Sí, instálalo en cada proyecto siguiendo los pasos de instalación.

    **P: ¿Cómo sé si la autorización fue exitosa?**
    R: Si el método no lanza excepción y retorna un `InvoiceResponse`, fue exitosa. Verifica el campo `cae` en tu venta.

    **P: ¿Qué hacer si falla la autorización?**
    R: Revisa los logs (`storage/logs/laravel.log`) y el mensaje de error. Los errores de AFIP incluyen códigos específicos.

    ---

    ## 🔒 Seguridad

    ⚠️ **IMPORTANTE:**

    - **NUNCA** subas certificados digitales al repositorio Git
    - Asegúrate de que estén en `.gitignore`
    - Usa permisos restrictivos (600 para `.key`, 644 para `.crt`)
    - No compartas certificados por email o mensajería
    - Rota certificados periódicamente según políticas de seguridad

    ---

    ## 📝 Changelog

    Ver [CHANGELOG.md](CHANGELOG.md) para una lista de cambios.

    ## 🤝 Contribuir

    Las contribuciones son bienvenidas! Por favor lee [CONTRIBUTING.md](CONTRIBUTING.md) para detalles.

    ## 📄 Licencia

    Este proyecto está licenciado bajo la [MIT License](LICENSE).

    ## 👥 Autores

    **Resguar IT**
    - Email: info@resguar.com

    ## 🙏 Agradecimientos

    - AFIP por la documentación oficial
    - Comunidad de desarrolladores de Argentina
    - Todos los contribuidores

    ## 💬 Soporte

    Para soporte, por favor:
    - Abre un issue en el [repositorio](https://github.com/resguarit/Afip-sdk/issues)
    - Contacta a [info@resguar.com](mailto:info@resguar.com)

    ---

    **¿Necesitas ayuda?** Revisa la sección [Troubleshooting](#-troubleshooting) o consulta las [Guías Detalladas](#-documentación-adicional).
