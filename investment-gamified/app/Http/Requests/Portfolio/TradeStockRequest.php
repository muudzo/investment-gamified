<?php

declare(strict_types=1);

namespace App\Http\Requests\Portfolio;

use Illuminate\Foundation\Http\FormRequest;

class TradeStockRequest extends FormRequest
{
    /**
     * Upper bound on a single trade's quantity. Rejects absurd values at the
     * boundary so they fail validation cleanly instead of overflowing the
     * decimal money/quantity columns downstream.
     */
    public const MAX_QUANTITY = 100000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stock_symbol' => 'required|exists:stocks,symbol',
            'quantity' => 'required|integer|min:1|max:'.self::MAX_QUANTITY,
        ];
    }
}
