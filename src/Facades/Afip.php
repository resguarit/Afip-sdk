<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Facades;

use Illuminate\Support\Facades\Facade;
use Resguar\AfipSdk\Contracts\AfipServiceInterface;

/**
 * Facade para el servicio de AFIP
 *
 * @method static \Resguar\AfipSdk\DTOs\InvoiceResponse authorizeInvoice(mixed $source, ?string $cuit = null)
 * @method static array getLastAuthorizedInvoice(int $pointOfSale, int $invoiceType, ?string $cuit = null)
 * @method static array getAvailableReceiptTypes(?string $cuit = null)
 * @method static array getAvailablePointsOfSale(?string $cuit = null)
 * @method static array getTaxpayerStatus(string $cuit)
 * @method static array getReceiptTypesForCuit(?string $cuit = null)
 * @method static bool isAuthenticated(?string $cuit = null)
 * @method static array diagnoseAuthenticationIssue(?string $cuit = null)
 * @method static void clearParamCache(?string $cuit = null)
 *
 * @see \Resguar\AfipSdk\Services\AfipService
 */
class Afip extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return AfipServiceInterface::class;
    }
}

