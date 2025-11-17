<?php

declare(strict_types=1);

namespace Resguar\AfipSdk\Helpers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Helper para validación de datos
 */
class ValidatorHelper
{
    /**
     * Valida los datos de un comprobante
     *
     * @param array $data
     * @return array Datos validados
     * @throws ValidationException
     */
    public static function validateInvoice(array $data): array
    {
        $rules = [
            'pointOfSale' => 'required|integer|min:1|max:99999',
            'invoiceType' => 'required|integer|min:1',
            'invoiceNumber' => 'required|integer|min:1',
            'date' => 'required|date_format:Ymd',
            'customerCuit' => 'required|string|size:11|regex:/^\d{11}$/',
            'customerDocumentType' => 'required|integer|min:80|max:99',
            'customerDocumentNumber' => 'required|string',
            'concept' => 'required|integer|in:1,2,3',
            'items' => 'required|array|min:1',
            'items.*.code' => 'nullable|string|max:50',
            'items.*.description' => 'required|string|max:250',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unitPrice' => 'required|numeric|min:0',
            'items.*.taxRate' => 'nullable|numeric|min:0|max:100',
            'total' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Valida un CUIT
     *
     * @param string $cuit
     * @return bool
     */
    public static function validateCuit(string $cuit): bool
    {
        // Remover guiones si los tiene
        $cuit = str_replace('-', '', $cuit);

        // Debe tener 11 dígitos
        if (!preg_match('/^\d{11}$/', $cuit)) {
            return false;
        }

        // Validar dígito verificador
        $multipliers = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $sum += (int) $cuit[$i] * $multipliers[$i];
        }

        $remainder = $sum % 11;
        $checkDigit = $remainder < 2 ? $remainder : 11 - $remainder;

        return (int) $cuit[10] === $checkDigit;
    }

    /**
     * Formatea un CUIT con guiones
     *
     * @param string $cuit
     * @return string
     */
    public static function formatCuit(string $cuit): string
    {
        $cuit = str_replace('-', '', $cuit);

        if (strlen($cuit) !== 11) {
            return $cuit;
        }

        return substr($cuit, 0, 2) . '-' . substr($cuit, 2, 8) . '-' . substr($cuit, 10, 1);
    }

    /**
     * Limpia un CUIT removiendo guiones, espacios y caracteres no numéricos
     *
     * @param string $cuit CUIT a limpiar
     * @return string CUIT limpio (solo dígitos)
     */
    public static function cleanCuit(string $cuit): string
    {
        return preg_replace('/[^0-9]/', '', $cuit);
    }

    /**
     * Valida y limpia un CUIT
     *
     * @param string $cuit CUIT a validar y limpiar
     * @return string CUIT limpio y validado
     * @throws \InvalidArgumentException Si el CUIT no es válido
     */
    public static function validateAndCleanCuit(string $cuit): string
    {
        $cleaned = self::cleanCuit($cuit);

        if (strlen($cleaned) !== 11) {
            throw new \InvalidArgumentException("El CUIT debe tener 11 dígitos. Recibido: {$cuit}");
        }

        if (!self::validateCuit($cleaned)) {
            throw new \InvalidArgumentException("CUIT inválido: {$cuit}");
        }

        return $cleaned;
    }
}

