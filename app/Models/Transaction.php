<?php

namespace App\Models;

use App\Exceptions\InsufficientStockException;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'total',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    /**
     * Create a transaction from a list of {product_id, quantity} items,
     * snapshotting each product's current price and decrementing its stock.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     *
     * @throws InsufficientStockException
     */
    public static function createFromItems(array $items): self
    {
        return DB::transaction(function () use ($items) {
            $transaction = self::create(['total' => 0]);
            $total = 0;

            foreach ($items as $item) {
                $product = Product::query()->lockForUpdate()->findOrFail($item['product_id']);

                if ($item['quantity'] > $product->stock) {
                    throw new InsufficientStockException($product, $item['quantity']);
                }

                $unitPrice = $product->price;
                $subtotal = $unitPrice * $item['quantity'];

                $transaction->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $product->decrement('stock', $item['quantity']);

                $total += $subtotal;
            }

            $transaction->update(['total' => $total]);

            return $transaction->load('items.product');
        });
    }
}
