<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Exceptions;

use Exception;

/**
 * Excepción base para errores de AFIP
 */
class AfipException extends Exception
{
    /**
     * Código de error de AFIP
     */
    protected ?string $afipCode = null;

    /**
     * Mensaje de error de AFIP
     */
    protected ?string $afipMessage = null;

    /**
     * Create a new exception instance.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     * @param string|null $afipCode
     * @param string|null $afipMessage
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $afipCode = null,
        ?string $afipMessage = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->afipCode = $afipCode;
        $this->afipMessage = $afipMessage;
    }

    /**
     * Get the AFIP error code
     */
    public function getAfipCode(): ?string
    {
        return $this->afipCode;
    }

    /**
     * Get the AFIP error message
     */
    public function getAfipMessage(): ?string
    {
        return $this->afipMessage;
    }

    /**
     * Get the full error message including AFIP details
     */
    public function getFullMessage(): string
    {
        $message = $this->getMessage();

        if ($this->afipCode !== null) {
            $message .= " [AFIP Code: {$this->afipCode}]";
        }

        if ($this->afipMessage !== null) {
            $message .= " [AFIP Message: {$this->afipMessage}]";
        }

        return $message;
    }
}

