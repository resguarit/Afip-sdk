<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Resguar\AfipSdk\AfipServiceProvider;

/**
 * Clase base para tests
 */
abstract class TestCase extends OrchestraTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Configuración adicional para tests
    }

    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AfipServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Afip' => \Resguar\AfipSdk\Facades\Afip::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        // Configuración de entorno para tests
        $app['config']->set('afip.environment', 'testing');
        $app['config']->set('afip.certificates.path', __DIR__ . '/../storage/certificates');
    }
}

