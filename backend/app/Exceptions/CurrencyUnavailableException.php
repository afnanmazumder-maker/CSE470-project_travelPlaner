<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * R8: no stored or fetchable rate exists for a currency. Rendered like a
 * validation error on the currency field.
 */
class CurrencyUnavailableException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['currency' => [$this->getMessage()]],
        ], 422);
    }
}
