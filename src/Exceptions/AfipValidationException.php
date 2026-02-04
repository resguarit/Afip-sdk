<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Exceptions;

/**
 * Excepción para errores de validación de reglas de facturación AFIP
 *
 * Se lanza cuando la combinación tipo de comprobante + condición IVA del receptor
 * no cumple con las reglas establecidas (RI: A a RI/monotributista, B a CF/exento;
 * Monotributista: C solo a consumidor final).
 */
class AfipValidationException extends AfipException
{
    //
}
