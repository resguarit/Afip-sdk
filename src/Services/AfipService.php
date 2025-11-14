<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Services;

use Resguar\AfipSdk\Builders\InvoiceBuilder;
use Resguar\AfipSdk\Contracts\AfipServiceInterface;
use Resguar\AfipSdk\DTOs\InvoiceResponse;
use Resguar\AfipSdk\Exceptions\AfipException;
use Resguar\AfipSdk\Helpers\ValidatorHelper;

/**
 * Servicio principal de AFIP
 *
 * Orquesta las operaciones con los diferentes Web Services de AFIP
 */
class AfipService implements AfipServiceInterface
{
    /**
     * Create a new AfipService instance.
     *
     * @param WsaaService $wsaaService
     * @param WsfeService $wsfeService
     * @param CertificateManager $certificateManager
     */
    public function __construct(
        private readonly WsaaService $wsaaService,
        private readonly WsfeService $wsfeService,
        private readonly CertificateManager $certificateManager
    ) {
    }

    /**
     * Autoriza una factura electrónica y obtiene el CAE
     *
     * @param mixed $source Fuente de datos (Eloquent Model, array, objeto)
     * @return InvoiceResponse Resultado con CAE y datos de la factura autorizada
     * @throws AfipException
     */
    public function authorizeInvoice(mixed $source): InvoiceResponse
    {
        // Construir el comprobante desde la fuente de datos
        $invoice = InvoiceBuilder::from($source)->build();

        // Validar datos del comprobante
        ValidatorHelper::validateInvoice($invoice);

        // Autorizar mediante WsfeService
        return $this->wsfeService->authorizeInvoice($invoice);
    }

    /**
     * Obtiene el último comprobante autorizado
     *
     * @param int $pointOfSale Punto de venta
     * @param int $invoiceType Tipo de comprobante
     * @return array Datos del último comprobante
     * @throws AfipException
     */
    public function getLastAuthorizedInvoice(int $pointOfSale, int $invoiceType): array
    {
        return $this->wsfeService->getLastAuthorizedInvoice($pointOfSale, $invoiceType);
    }

    /**
     * Obtiene los tipos de comprobantes disponibles
     *
     * @return array Lista de tipos de comprobantes
     * @throws AfipException
     */
    public function getInvoiceTypes(): array
    {
        return $this->wsfeService->getInvoiceTypes();
    }

    /**
     * Obtiene los puntos de venta habilitados
     *
     * @return array Lista de puntos de venta
     * @throws AfipException
     */
    public function getPointOfSales(): array
    {
        return $this->wsfeService->getPointOfSales();
    }

    /**
     * Obtiene el estado del contribuyente
     *
     * @param string $cuit CUIT del contribuyente
     * @return array Estado del contribuyente
     * @throws AfipException
     */
    public function getTaxpayerStatus(string $cuit): array
    {
        return $this->wsfeService->getTaxpayerStatus($cuit);
    }

    /**
     * Verifica si el servicio está autenticado
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->wsaaService->isAuthenticated('wsfe');
    }
}

