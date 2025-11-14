<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Builders;

use Illuminate\Database\Eloquent\Model;

/**
 * Builder para construir comprobantes desde diferentes fuentes de datos
 *
 * Soporta construcción desde:
 * - Modelos Eloquent (con relaciones)
 * - Arrays simples
 * - Objetos genéricos
 */
class InvoiceBuilder
{
    /**
     * Datos del comprobante
     */
    protected array $invoice = [];

    /**
     * Create a new InvoiceBuilder instance.
     *
     * @param mixed $source Fuente de datos
     */
    protected function __construct(protected mixed $source)
    {
    }

    /**
     * Crea un nuevo builder desde una fuente de datos
     *
     * @param mixed $source Fuente de datos (Eloquent Model, array, objeto)
     * @return static
     */
    public static function from(mixed $source): static
    {
        return new static($source);
    }

    /**
     * Construye el array de datos del comprobante
     *
     * @return array
     */
    public function build(): array
    {
        if ($this->source instanceof Model) {
            return $this->buildFromModel($this->source);
        }

        if (is_array($this->source)) {
            return $this->buildFromArray($this->source);
        }

        if (is_object($this->source)) {
            return $this->buildFromObject($this->source);
        }

        throw new \InvalidArgumentException('Fuente de datos no soportada. Debe ser Model, array u objeto.');
    }

    /**
     * Construye desde un modelo Eloquent
     *
     * @param Model $model
     * @return array
     */
    protected function buildFromModel(Model $model): array
    {
        // TODO: Implementar construcción desde modelo Eloquent
        // - Extraer datos del modelo
        // - Procesar relaciones (customer, items, etc.)
        // - Mapear a formato AFIP
        // - Validar datos requeridos

        return $this->invoice;
    }

    /**
     * Construye desde un array
     *
     * @param array $data
     * @return array
     */
    protected function buildFromArray(array $data): array
    {
        // TODO: Implementar construcción desde array
        // - Validar estructura
        // - Mapear a formato AFIP
        // - Validar datos requeridos

        return $this->invoice;
    }

    /**
     * Construye desde un objeto genérico
     *
     * @param object $object
     * @return array
     */
    protected function buildFromObject(object $object): array
    {
        // TODO: Implementar construcción desde objeto
        // - Extraer propiedades públicas
        // - Procesar métodos getter si existen
        // - Mapear a formato AFIP
        // - Validar datos requeridos

        return $this->invoice;
    }

    /**
     * Establece el punto de venta
     *
     * @param int $pointOfSale
     * @return $this
     */
    public function pointOfSale(int $pointOfSale): static
    {
        $this->invoice['pointOfSale'] = $pointOfSale;
        return $this;
    }

    /**
     * Establece el tipo de comprobante
     *
     * @param int $invoiceType
     * @return $this
     */
    public function invoiceType(int $invoiceType): static
    {
        $this->invoice['invoiceType'] = $invoiceType;
        return $this;
    }

    /**
     * Establece el número de comprobante
     *
     * @param int $invoiceNumber
     * @return $this
     */
    public function invoiceNumber(int $invoiceNumber): static
    {
        $this->invoice['invoiceNumber'] = $invoiceNumber;
        return $this;
    }

    /**
     * Establece la fecha del comprobante
     *
     * @param string|\DateTime $date
     * @return $this
     */
    public function date(string|\DateTime $date): static
    {
        if ($date instanceof \DateTime) {
            $date = $date->format('Ymd');
        }

        $this->invoice['date'] = $date;
        return $this;
    }

    /**
     * Establece el CUIT del cliente
     *
     * @param string $cuit
     * @return $this
     */
    public function customerCuit(string $cuit): static
    {
        $this->invoice['customerCuit'] = $cuit;
        return $this;
    }

    /**
     * Establece el concepto
     *
     * @param int $concept
     * @return $this
     */
    public function concept(int $concept): static
    {
        $this->invoice['concept'] = $concept;
        return $this;
    }

    /**
     * Establece el tipo de documento del cliente
     *
     * @param int $documentType
     * @return $this
     */
    public function customerDocumentType(int $documentType): static
    {
        $this->invoice['customerDocumentType'] = $documentType;
        return $this;
    }

    /**
     * Establece el número de documento del cliente
     *
     * @param string $documentNumber
     * @return $this
     */
    public function customerDocumentNumber(string $documentNumber): static
    {
        $this->invoice['customerDocumentNumber'] = $documentNumber;
        return $this;
    }

    /**
     * Agrega un ítem al comprobante
     *
     * @param array $item
     * @return $this
     */
    public function addItem(array $item): static
    {
        if (!isset($this->invoice['items'])) {
            $this->invoice['items'] = [];
        }

        $this->invoice['items'][] = $item;
        return $this;
    }

    /**
     * Establece los impuestos
     *
     * @param array $taxes
     * @return $this
     */
    public function taxes(array $taxes): static
    {
        $this->invoice['taxes'] = $taxes;
        return $this;
    }

    /**
     * Establece el importe total
     *
     * @param float $total
     * @return $this
     */
    public function total(float $total): static
    {
        $this->invoice['total'] = $total;
        return $this;
    }
}

