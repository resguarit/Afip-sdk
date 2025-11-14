<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\DTOs;

/**
 * DTO para la respuesta de autorización de factura
 */
readonly class InvoiceResponse
{
    /**
     * Create a new InvoiceResponse instance.
     *
     * @param string $cae Código de Autorización Electrónico
     * @param string $caeExpirationDate Fecha de vencimiento del CAE (formato Ymd)
     * @param int $invoiceNumber Número de comprobante
     * @param int $pointOfSale Punto de venta
     * @param int $invoiceType Tipo de comprobante
     * @param array $observations Observaciones (si las hay)
     * @param array $additionalData Datos adicionales de la respuesta
     */
    public function __construct(
        public string $cae,
        public string $caeExpirationDate,
        public int $invoiceNumber,
        public int $pointOfSale,
        public int $invoiceType,
        public array $observations = [],
        public array $additionalData = []
    ) {
    }

    /**
     * Crea una instancia desde un array de respuesta de AFIP
     *
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static(
            cae: (string) ($data['CAE'] ?? $data['cae'] ?? ''),
            caeExpirationDate: (string) ($data['CAEFchVto'] ?? $data['cae_expiration_date'] ?? ''),
            invoiceNumber: (int) ($data['CbteDesde'] ?? $data['invoice_number'] ?? 0),
            pointOfSale: (int) ($data['PtoVta'] ?? $data['point_of_sale'] ?? 0),
            invoiceType: (int) ($data['CbteTipo'] ?? $data['invoice_type'] ?? 0),
            observations: $data['Observaciones'] ?? $data['observations'] ?? [],
            additionalData: $data
        );
    }

    /**
     * Convierte el DTO a array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'cae' => $this->cae,
            'cae_expiration_date' => $this->caeExpirationDate,
            'invoice_number' => $this->invoiceNumber,
            'point_of_sale' => $this->pointOfSale,
            'invoice_type' => $this->invoiceType,
            'observations' => $this->observations,
            'additional_data' => $this->additionalData,
        ];
    }

    /**
     * Verifica si el CAE está vigente
     *
     * @return bool
     */
    public function isCaeValid(): bool
    {
        if (empty($this->caeExpirationDate)) {
            return false;
        }

        $expirationDate = \DateTime::createFromFormat('Ymd', $this->caeExpirationDate);
        if ($expirationDate === false) {
            return false;
        }

        return $expirationDate > new \DateTime();
    }
}

