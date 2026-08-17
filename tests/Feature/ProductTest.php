<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_it_lists_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_it_includes_the_category_in_the_response(): void
    {
        $category = Category::factory()->create(['name' => 'Beverages']);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonPath('data.category.id', $category->id);
        $response->assertJsonPath('data.category.name', 'Beverages');
    }

    public function test_it_lists_products_ordered_by_updated_at_desc(): void
    {
        $oldest = Product::factory()->create(['updated_at' => now()->subDays(2)]);
        $newest = Product::factory()->create(['updated_at' => now()]);
        $middle = Product::factory()->create(['updated_at' => now()->subDay()]);

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newest->id);
        $response->assertJsonPath('data.1.id', $middle->id);
        $response->assertJsonPath('data.2.id', $oldest->id);
    }

    public function test_it_searches_products_by_name(): void
    {
        Product::factory()->create(['name' => 'Iced Latte']);
        Product::factory()->create(['name' => 'Potato Chips']);
        Product::factory()->create(['name' => 'Iced Tea']);

        $response = $this->getJson('/api/products?search=iced');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing(
            ['Iced Latte', 'Iced Tea'],
            collect($response->json('data'))->pluck('name')->all(),
        );
    }

    public function test_it_sorts_products_by_name_ascending(): void
    {
        Product::factory()->create(['name' => 'Snacks Combo']);
        Product::factory()->create(['name' => 'Beverages Combo']);
        Product::factory()->create(['name' => 'Groceries Combo']);

        $response = $this->getJson('/api/products?sort=name&direction=asc');

        $response->assertOk();
        $this->assertSame(
            ['Beverages Combo', 'Groceries Combo', 'Snacks Combo'],
            collect($response->json('data'))->pluck('name')->all(),
        );
    }

    public function test_it_sorts_products_by_name_descending(): void
    {
        Product::factory()->create(['name' => 'Snacks Combo']);
        Product::factory()->create(['name' => 'Beverages Combo']);
        Product::factory()->create(['name' => 'Groceries Combo']);

        $response = $this->getJson('/api/products?sort=name&direction=desc');

        $response->assertOk();
        $this->assertSame(
            ['Snacks Combo', 'Groceries Combo', 'Beverages Combo'],
            collect($response->json('data'))->pluck('name')->all(),
        );
    }

    public function test_it_ignores_an_unsupported_sort_column(): void
    {
        Product::factory()->create(['name' => 'Beverages', 'updated_at' => now()->subDay()]);
        $newest = Product::factory()->create(['name' => 'Snacks', 'updated_at' => now()]);

        $response = $this->getJson('/api/products?sort=price&direction=asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newest->id);
    }

    public function test_it_creates_a_product(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Iced Latte',
            'sku' => 'BEV-001',
            'price' => 4.50,
            'stock' => 40,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Iced Latte');
        $response->assertJsonPath('data.category.id', $category->id);
        $this->assertDatabaseHas('products', ['name' => 'Iced Latte', 'sku' => 'BEV-001']);
    }

    public function test_it_requires_a_name_a_category_and_a_price_to_create_a_product(): void
    {
        $response = $this->postJson('/api/products', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name', 'category_id', 'price']);
    }

    public function test_it_requires_an_existing_category_to_create_a_product(): void
    {
        $response = $this->postJson('/api/products', [
            'category_id' => 999999,
            'name' => 'Iced Latte',
            'price' => 4.50,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('category_id');
    }

    public function test_it_rejects_a_duplicate_sku_on_create(): void
    {
        Product::factory()->create(['sku' => 'BEV-001']);
        $category = Category::factory()->create();

        $response = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Another Product',
            'sku' => 'BEV-001',
            'price' => 1,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('sku');
    }

    public function test_it_allows_a_null_sku_on_create(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'No Sku Product',
            'price' => 1,
        ]);

        $response->assertCreated();
    }

    public function test_it_shows_a_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $product->id);
    }

    public function test_it_updates_a_product(): void
    {
        $product = Product::factory()->create(['name' => 'Old name']);

        $response = $this->putJson("/api/products/{$product->id}", [
            'name' => 'New name',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New name');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'New name']);
    }

    public function test_it_allows_keeping_its_own_sku_on_update(): void
    {
        $product = Product::factory()->create(['sku' => 'BEV-001']);

        $response = $this->putJson("/api/products/{$product->id}", [
            'sku' => 'BEV-001',
            'price' => 9.99,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.sku', 'BEV-001');
    }

    public function test_it_rejects_a_duplicate_sku_on_update(): void
    {
        Product::factory()->create(['sku' => 'BEV-001']);
        $product = Product::factory()->create(['sku' => 'SNK-001']);

        $response = $this->putJson("/api/products/{$product->id}", [
            'sku' => 'BEV-001',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('sku');
    }

    public function test_it_deletes_a_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_it_prevents_deleting_a_product_used_by_a_transaction(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $transaction = Transaction::createFromItems([
            ['product_id' => $product->id, 'quantity' => 1],
        ], User::factory()->create());

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(409);
        $response->assertJsonPath('message', "Product is referenced by transaction #{$transaction->id}.");
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }
}
