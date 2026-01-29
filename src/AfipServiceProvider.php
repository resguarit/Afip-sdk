<?php

declare(strict_types=1);

namespace Resguar\AfipSdk;

use Illuminate\Support\ServiceProvider;
use Resguar\AfipSdk\Contracts\AfipServiceInterface;
use Resguar\AfipSdk\Services\AfipService;
use Resguar\AfipSdk\Services\CertificateManager;
use Resguar\AfipSdk\Services\WsaaService;
use Resguar\AfipSdk\Services\ReceiptRenderer;
use Resguar\AfipSdk\Services\WsfeService;
use Resguar\AfipSdk\Services\WsPadronService;

/**
 * Service Provider para el SDK de AFIP
 *
 * Registra todos los servicios necesarios para la integración con AFIP
 */
class AfipServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/afip.php',
            'afip'
        );

        // Registrar CertificateManager como singleton
        $this->app->singleton(CertificateManager::class, function ($app) {
            return new CertificateManager(
                config('afip.certificates.path'),
                config('afip.certificates.key'),
                config('afip.certificates.crt')
            );
        });

        // Registrar WsaaService como singleton
        $this->app->singleton(WsaaService::class, function ($app) {
            $environment = config('afip.environment', 'testing');
            $wsaaUrls = config('afip.wsaa.url', []);
            $wsaaUrl = $wsaaUrls[$environment] ?? $wsaaUrls['testing'] ?? '';

            return new WsaaService(
                $app->make(CertificateManager::class),
                $environment,
                $wsaaUrl,
                $app->make('cache.store')
            );
        });

        // Registrar WsfeService como singleton
        $this->app->singleton(WsfeService::class, function ($app) {
            $environment = config('afip.environment', 'testing');
            $wsfeUrls = config('afip.wsfe.url', []);
            $wsfeUrl = $wsfeUrls[$environment] ?? $wsfeUrls['testing'] ?? '';

            return new WsfeService(
                $app->make(CertificateManager::class),
                $app->make(WsaaService::class),
                $environment,
                $wsfeUrl,
                $app->make('cache.store')
            );
        });

        // Registrar WsPadronService como singleton
        $this->app->singleton(WsPadronService::class, function ($app) {
            $environment = config('afip.environment', 'testing');
            $defaultCuit = config('afip.cuit', '');

            return new WsPadronService(
                $app->make(WsaaService::class),
                $environment,
                $defaultCuit
            );
        });

        // Registrar ReceiptRenderer (Ticket / Factura A4 con QR AFIP)
        $this->app->singleton(ReceiptRenderer::class, function ($app) {
            return new ReceiptRenderer();
        });

        // Registrar AfipService como singleton e implementación de la interfaz
        $this->app->singleton(AfipServiceInterface::class, AfipService::class);
        $this->app->singleton(AfipService::class, function ($app) {
            return new AfipService(
                $app->make(WsaaService::class),
                $app->make(WsfeService::class),
                $app->make(CertificateManager::class),
                $app->make(WsPadronService::class),
                $app->make(ReceiptRenderer::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publicar configuración
        $this->publishes([
            __DIR__ . '/../config/afip.php' => config_path('afip.php'),
        ], 'afip-config');

        // Publicar migraciones
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'afip-migrations');

        // Cargar migraciones automáticamente si están en el directorio del paquete
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}

