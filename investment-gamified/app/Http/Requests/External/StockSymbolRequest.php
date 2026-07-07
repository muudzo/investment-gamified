<?php

declare(strict_types=1);

namespace App\Http\Requests\External;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a `{symbol}` route parameter shared by the external stock
 * endpoints (quote/history/profile). Normalizes to uppercase/trim and
 * enforces the tradable-symbol format before any provider is called.
 */
class StockSymbolRequest extends FormRequest
{
    public const SYMBOL_PATTERN = '/^[A-Z0-9.\-]{1,8}$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'symbol' => ['required', 'string', 'regex:'.self::SYMBOL_PATTERN],
        ];
    }

    public function messages(): array
    {
        return [
            'symbol.required' => 'Invalid stock symbol format',
            'symbol.string' => 'Invalid stock symbol format',
            'symbol.regex' => 'Invalid stock symbol format',
        ];
    }

    /**
     * Normalized (uppercase, trimmed) symbol read from the route parameter.
     */
    public function symbol(): string
    {
        return $this->normalizedSymbol();
    }

    protected function prepareForValidation(): void
    {
        // The `symbol` rule validates against the route parameter, not the
        // query/body payload, so merge the normalized value into the
        // request's input bag before validation runs.
        $this->merge(['symbol' => $this->normalizedSymbol()]);
    }

    private function normalizedSymbol(): string
    {
        return strtoupper(trim((string) $this->route('symbol')));
    }
}
