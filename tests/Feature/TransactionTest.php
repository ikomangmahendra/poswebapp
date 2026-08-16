<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_it_creates_a_transaction_and_computes_totals(): void
    {
        $productA = Product::factory()->create(['price' => 4.50, 'stock' => 10]);
        $productB = Product::factory()->create(['price' => 2.00, 'stock' => 10]);

        $response = $this->postJson('/api/transactions', [
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 2],
                ['product_id' => $productB->id, 'quantity' => 3],
            ],
        ]);

        $response->assertCreated();
        $this->assertEqualsWithDelta(15.00, (float) $response->json('data.total'), 0.001);
        $response->assertJsonCount(2, 'data.items');
        $response->assertJsonPath('data.items.0.product.id', $productA->id);
        $response->assertJsonPath('data.items.0.quantity', 2);
        $this->assertEqualsWithDelta(9.00, (float) $response->json('data.items.0.subtotal'), 0.001);
    }

    public function test_it_decrements_product_stock_when_creating_a_transaction(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $this->postJson('/api/transactions', [
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
        ])->assertCreated();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 6]);
    }

    public function test_it_snapshots_the_product_price_at_the_time_of_sale(): void
    {
        $product = Product::factory()->create(['price' => 10.00, 'stock' => 10]);

        $this->postJson('/api/transactions', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $product->update(['price' => 99.00]);

        $response = $this->getJson('/api/transactions');
        $response->assertJsonPath('data.0.items.0.unit_price', '10.00');
    }

    public function test_it_rejects_a_transaction_with_insufficient_stock(): void
    {
        $product = Product::factory()->create(['name' => 'Iced Latte', 'stock' => 2]);

        $response = $this->postJson('/api/transactions', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath(
            'message',
            'Insufficient stock for product "Iced Latte": requested 5, available 2.'
        );
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_it_requires_at_least_one_item(): void
    {
        $response = $this->postJson('/api/transactions', ['items' => []]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('items');
    }

    public function test_it_requires_a_valid_product_id_and_quantity(): void
    {
        $response = $this->postJson('/api/transactions', [
            'items' => [['product_id' => 999999, 'quantity' => 0]],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['items.0.product_id', 'items.0.quantity']);
    }

    public function test_it_shows_a_transaction_with_nested_items(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $transaction = Transaction::createFromItems([
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        $response = $this->getJson("/api/transactions/{$transaction->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $transaction->id);
        $response->assertJsonPath('data.items.0.product.id', $product->id);
    }

    public function test_it_lists_transactions_ordered_by_created_at_desc(): void
    {
        $product = Product::factory()->create(['stock' => 100]);

        $oldest = Transaction::createFromItems([['product_id' => $product->id, 'quantity' => 1]]);
        $oldest->forceFill(['created_at' => now()->subDays(2)])->save();

        $newest = Transaction::createFromItems([['product_id' => $product->id, 'quantity' => 1]]);
        $newest->forceFill(['created_at' => now()])->save();

        $response = $this->getJson('/api/transactions');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newest->id);
        $response->assertJsonPath('data.1.id', $oldest->id);
    }

    public function test_it_does_not_allow_updating_or_deleting_a_transaction(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $transaction = Transaction::createFromItems([
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->putJson("/api/transactions/{$transaction->id}", [])->assertStatus(405);
        $this->deleteJson("/api/transactions/{$transaction->id}")->assertStatus(405);
    }
}
