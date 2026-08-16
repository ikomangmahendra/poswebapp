<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_it_returns_totals_inventory_value_and_low_stock_count(): void
    {
        $beverages = Category::factory()->create(['name' => 'Beverages']);
        $snacks = Category::factory()->create(['name' => 'Snacks']);

        Product::factory()->create(['category_id' => $beverages->id, 'price' => 4.50, 'stock' => 40]);
        Product::factory()->create(['category_id' => $beverages->id, 'price' => 2.00, 'stock' => 5]);
        Product::factory()->create(['category_id' => $beverages->id, 'price' => 1.00, 'stock' => 50]);
        Product::factory()->create(['category_id' => $snacks->id, 'price' => 3.00, 'stock' => 8]);

        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $response->assertJsonPath('total_products', 4);
        $response->assertJsonPath('total_categories', 2);
        $response->assertJsonPath('low_stock_threshold', 10);
        $response->assertJsonPath('low_stock_count', 2);
        $this->assertEqualsWithDelta(264.00, (float) $response->json('inventory_value'), 0.001);
    }

    public function test_it_orders_low_stock_products_by_stock_ascending(): void
    {
        $category = Category::factory()->create();

        Product::factory()->create(['category_id' => $category->id, 'name' => 'Water', 'stock' => 50]);
        $chips = Product::factory()->create(['category_id' => $category->id, 'name' => 'Chips', 'stock' => 8]);
        $soda = Product::factory()->create(['category_id' => $category->id, 'name' => 'Soda', 'stock' => 5]);

        $response = $this->getJson('/api/dashboard/low-stock');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $soda->id);
        $response->assertJsonPath('data.1.id', $chips->id);
    }

    public function test_it_paginates_low_stock_products_at_ten_per_page(): void
    {
        for ($i = 0; $i < 12; $i++) {
            Product::factory()->create(['stock' => $i % 10]);
        }

        $firstPage = $this->getJson('/api/dashboard/low-stock');
        $firstPage->assertOk();
        $firstPage->assertJsonCount(10, 'data');
        $firstPage->assertJsonPath('meta.total', 12);
        $firstPage->assertJsonPath('meta.last_page', 2);

        $secondPage = $this->getJson('/api/dashboard/low-stock?page=2');
        $secondPage->assertOk();
        $secondPage->assertJsonCount(2, 'data');
    }

    public function test_it_orders_products_per_category_breakdown_by_count_descending(): void
    {
        $beverages = Category::factory()->create(['name' => 'Beverages']);
        $snacks = Category::factory()->create(['name' => 'Snacks']);

        Product::factory()->count(3)->create(['category_id' => $beverages->id]);
        Product::factory()->count(1)->create(['category_id' => $snacks->id]);

        $response = $this->getJson('/api/dashboard/categories');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Beverages');
        $response->assertJsonPath('data.0.product_count', 3);
        $response->assertJsonPath('data.1.name', 'Snacks');
        $response->assertJsonPath('data.1.product_count', 1);
    }

    public function test_it_paginates_category_breakdown_at_ten_per_page(): void
    {
        Category::factory()->count(12)->create()->each(
            fn (Category $category) => Product::factory()->create(['category_id' => $category->id])
        );

        $firstPage = $this->getJson('/api/dashboard/categories');
        $firstPage->assertOk();
        $firstPage->assertJsonCount(10, 'data');
        $firstPage->assertJsonPath('meta.total', 12);
        $firstPage->assertJsonPath('meta.last_page', 2);

        $secondPage = $this->getJson('/api/dashboard/categories?page=2');
        $secondPage->assertOk();
        $secondPage->assertJsonCount(2, 'data');
    }

    public function test_it_orders_recently_updated_products_descending(): void
    {
        $oldest = Product::factory()->create(['updated_at' => now()->subDays(2)]);
        $newest = Product::factory()->create(['updated_at' => now()]);

        $response = $this->getJson('/api/dashboard/recent-products');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newest->id);
        $response->assertJsonPath('data.1.id', $oldest->id);
    }

    public function test_it_paginates_recently_updated_products_at_ten_per_page(): void
    {
        for ($i = 12; $i >= 1; $i--) {
            Product::factory()->create(['updated_at' => now()->subMinutes($i)]);
        }
        $newest = Product::factory()->create(['updated_at' => now()]);

        $firstPage = $this->getJson('/api/dashboard/recent-products');
        $firstPage->assertOk();
        $firstPage->assertJsonCount(10, 'data');
        $firstPage->assertJsonPath('meta.total', 13);
        $firstPage->assertJsonPath('meta.last_page', 2);
        $firstPage->assertJsonPath('data.0.id', $newest->id);

        $secondPage = $this->getJson('/api/dashboard/recent-products?page=2');
        $secondPage->assertOk();
        $secondPage->assertJsonCount(3, 'data');
    }
}
