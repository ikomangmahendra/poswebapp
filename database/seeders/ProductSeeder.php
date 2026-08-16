<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            ['category' => 'Beverages', 'name' => 'Iced Latte', 'sku' => 'BEV-001', 'price' => 4.50, 'stock' => 40],
            ['category' => 'Snacks', 'name' => 'Potato Chips', 'sku' => 'SNK-001', 'price' => 2.25, 'stock' => 120],
            ['category' => 'Groceries', 'name' => 'Basmati Rice 5kg', 'sku' => 'GRO-001', 'price' => 12.99, 'stock' => 30],
            ['category' => 'Electronics', 'name' => 'USB-C Cable', 'sku' => 'ELC-001', 'price' => 9.99, 'stock' => 60],
            ['category' => 'Household', 'name' => 'Dish Soap', 'sku' => 'HHD-001', 'price' => 3.75, 'stock' => 80],
        ];

        foreach ($products as $product) {
            $category = Category::query()->where('name', $product['category'])->first();

            Product::query()->firstOrCreate(
                ['sku' => $product['sku']],
                [
                    'category_id' => $category->id,
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'stock' => $product['stock'],
                ],
            );
        }

        $categoryIds = Category::query()->pluck('id')->all();

        Product::factory()
            ->count(45)
            ->sequence(fn () => ['category_id' => fake()->randomElement($categoryIds)])
            ->create();
    }
}
