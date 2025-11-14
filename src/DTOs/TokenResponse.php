<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\DTOs;

/**
 * DTO para la respuesta de token de autenticación
 */
readonly class TokenResponse
{
    /**
     * Create a new TokenResponse instance.
     *
     * @param string $token Token de autenticación
     * @param string $signature Firma digital
     * @param \DateTime $expirationDate Fecha de expiración
     * @param string $generationTime Tiempo de generación
     */
    public function __construct(
        public string $token,
        public string $signature,
        public \DateTime $expirationDate,
        public string $generationTime
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
        $expirationDate = \DateTime::createFromFormat('YmdHis', $data['expiration'] ?? '');
        if ($expirationDate === false) {
            $expirationDate = new \DateTime('+24 hours');
        }

        return new static(
            token: (string) ($data['token'] ?? ''),
            signature: (string) ($data['sign'] ?? $data['signature'] ?? ''),
            expirationDate: $expirationDate,
            generationTime: (string) ($data['generationTime'] ?? date('YmdHis'))
        );
    }

    /**
     * Verifica si el token está vigente
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->expirationDate > new \DateTime();
    }

    /**
     * Obtiene los segundos restantes hasta la expiración
     *
     * @return int
     */
    public function getSecondsUntilExpiration(): int
    {
        $now = new \DateTime();
        $diff = $this->expirationDate->getTimestamp() - $now->getTimestamp();

        return max(0, $diff);
    }
}

