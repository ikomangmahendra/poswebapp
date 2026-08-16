<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $products = Product::query()->where('stock', '>', 5)->inRandomOrder()->limit(rand(1, 3))->get();

            if ($products->isEmpty()) {
                break;
            }

            $items = $products->map(fn (Product $product) => [
                'product_id' => $product->id,
                'quantity' => rand(1, min(3, $product->stock)),
            ])->all();

            Transaction::createFromItems($items);
        }
    }
}
