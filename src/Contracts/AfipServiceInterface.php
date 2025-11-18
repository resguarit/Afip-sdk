<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Contracts;

/**
 * Interfaz para el servicio principal de AFIP
 */
interface AfipServiceInterface
{
    /**
     * Autoriza una factura electrónica y obtiene el CAE
     *
     * @param mixed $source Fuente de datos (Eloquent Model, array, objeto)
     * @param string|null $cuit CUIT del contribuyente (opcional, usa config si no se proporciona)
     * @return \Resguar\AfipSdk\DTOs\InvoiceResponse Resultado con CAE y datos de la factura autorizada
     * @throws \Resguar\AfipSdk\Exceptions\AfipException
     */
    public function authorizeInvoice(mixed $source, ?string $cuit = null): \Resguar\AfipSdk\DTOs\InvoiceResponse;

    /**
     * Obtiene el último comprobante autorizado
     *
     * @param int $pointOfSale Punto de venta
     * @param int $invoiceType Tipo de comprobante
     * @param string|null $cuit CUIT del contribuyente (opcional, usa config si no se proporciona)
     * @return array Datos del último comprobante
     * @throws \Resguar\AfipSdk\Exceptions\AfipException
     */
    public function getLastAuthorizedInvoice(int $pointOfSale, int $invoiceType, ?string $cuit = null): array;

    /**
     * Obtiene los tipos de comprobantes disponibles
     *
     * @return array Lista de tipos de comprobantes
     * @throws \Resguar\AfipSdk\Exceptions\AfipException
     */
    public function getInvoiceTypes(): array;

    /**
     * Obtiene los puntos de venta habilitados
     *
     * @return array Lista de puntos de venta
     * @throws \Resguar\AfipSdk\Exceptions\AfipServiceException
     */
    public function getPointOfSales(): array;

    /**
     * Obtiene el estado del contribuyente
     *
     * @param string $cuit CUIT del contribuyente
     * @return array Estado del contribuyente
     * @throws \Resguar\AfipSdk\Exceptions\AfipException
     */
    public function getTaxpayerStatus(string $cuit): array;

    /**
     * Verifica si el servicio está autenticado
     *
     * @param string|null $cuit CUIT del contribuyente (opcional, usa config si no se proporciona)
     * @return bool
     */
    public function isAuthenticated(?string $cuit = null): bool;

    /**
     * Diagnostica problemas de autenticación y configuración
     *
     * @param string|null $cuit CUIT del contribuyente (opcional)
     * @return array Diagnóstico completo con problemas y sugerencias
     */
    public function diagnoseAuthenticationIssue(?string $cuit = null): array;
}

