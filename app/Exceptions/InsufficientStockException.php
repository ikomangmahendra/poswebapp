<?php

namespace App\Exceptions;

use App\Models\Product;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InsufficientStockException extends Exception
{
    public function __construct(private Product $product, private int $requested)
    {
        parent::__construct(
            "Insufficient stock for product \"{$product->name}\": requested {$requested}, available {$product->stock}."
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
