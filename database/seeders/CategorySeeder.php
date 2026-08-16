<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Beverages', 'description' => 'Soft drinks, juices, and bottled water'],
            ['name' => 'Snacks', 'description' => 'Chips, crackers, and other packaged snacks'],
            ['name' => 'Groceries', 'description' => 'Everyday staples like rice, oil, and canned goods'],
            ['name' => 'Electronics', 'description' => 'Small electronics and accessories'],
            ['name' => 'Household', 'description' => 'Cleaning supplies and home essentials'],
        ];

        foreach ($categories as $category) {
            Category::query()->firstOrCreate(['name' => $category['name']], $category);
        }
    }
}
