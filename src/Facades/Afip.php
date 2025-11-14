<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Facades;

use Illuminate\Support\Facades\Facade;
use Resguar\AfipSdk\Contracts\AfipServiceInterface;

/**
 * Facade para el servicio de AFIP
 *
 * @method static \Resguar\AfipSdk\DTOs\InvoiceResponse authorizeInvoice(mixed $source)
 * @method static array getLastAuthorizedInvoice(int $pointOfSale, int $invoiceType)
 * @method static array getInvoiceTypes()
 * @method static array getPointOfSales()
 * @method static array getTaxpayerStatus(string $cuit)
 * @method static bool isAuthenticated()
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

