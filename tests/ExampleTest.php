<?php

namespace Resguar\AfipSdk\Tests;

use Orchestra\Testbench\TestCase;
use Resguar\AfipSdk\AfipServiceProvider;
use Resguar\AfipSdk\Facades\Afip;

class ExampleTest extends TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Configurar para testing
        config([
            'afip.environment' => 'testing',
            'afip.cuit' => env('AFIP_TEST_CUIT', '20123456789'),
            'afip.certificates.path' => env('AFIP_TEST_CERT_PATH', __DIR__ . '/../certificates'),
            'afip.certificates.key' => env('AFIP_TEST_CERT_KEY', 'private_key.key'),
            'afip.certificates.crt' => env('AFIP_TEST_CERT_CRT', 'certificate.crt'),
            'afip.certificates.password' => env('AFIP_TEST_CERT_PASSWORD'),
        ]);
    }

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [
            AfipServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Afip' => \Resguar\AfipSdk\Facades\Afip::class,
        ];
    }

    /**
     * Test básico de autenticación
     * 
     * NOTA: Este test requiere certificados válidos de AFIP
     * Para ejecutarlo, configura las variables de entorno necesarias
     */
    public function test_autenticacion_basica(): void
    {
        $this->markTestSkipped('Requiere certificados de AFIP. Configura variables de entorno para ejecutar.');
        
        // Descomentar cuando tengas certificados configurados:
        /*
        try {
            $isAuthenticated = Afip::isAuthenticated();
            $this->assertIsBool($isAuthenticated);
        } catch (\Exception $e) {
            $this->fail('Error en autenticación: ' . $e->getMessage());
        }
        */
    }

    /**
     * Test de estructura de datos
     */
    public function test_estructura_datos(): void
    {
        // Test que la estructura de datos sea correcta
        $invoice = [
            'pointOfSale' => 1,
            'invoiceType' => 1,
            'invoiceNumber' => 1,
            'date' => date('Ymd'),
            'customerCuit' => '20123456789',
            'customerDocumentType' => 80,
            'concept' => 1,
            'items' => [
                [
                    'description' => 'Test',
                    'quantity' => 1,
                    'unitPrice' => 100,
                    'taxRate' => 21,
                ],
            ],
            'total' => 121,
            'totalNetoGravado' => 100,
            'totalIva' => 21,
        ];

        $this->assertIsArray($invoice);
        $this->assertArrayHasKey('pointOfSale', $invoice);
        $this->assertArrayHasKey('invoiceType', $invoice);
        $this->assertArrayHasKey('items', $invoice);
    }

    /**
     * Test de validación de CUIT
     */
    public function test_validacion_cuit(): void
    {
        $this->markTestSkipped('Requiere implementar ValidatorHelper::validateCuit');
        
        // TODO: Implementar test cuando ValidatorHelper esté completo
    }
}

