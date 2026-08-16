<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_categories(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_it_lists_categories_ordered_by_updated_at_desc(): void
    {
        $oldest = Category::factory()->create(['updated_at' => now()->subDays(2)]);
        $newest = Category::factory()->create(['updated_at' => now()]);
        $middle = Category::factory()->create(['updated_at' => now()->subDay()]);

        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newest->id);
        $response->assertJsonPath('data.1.id', $middle->id);
        $response->assertJsonPath('data.2.id', $oldest->id);
    }

    public function test_it_searches_categories_by_name(): void
    {
        Category::factory()->create(['name' => 'Beverages']);
        Category::factory()->create(['name' => 'Snacks']);
        Category::factory()->create(['name' => 'Frozen Beverages']);

        $response = $this->getJson('/api/categories?search=bev');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing(
            ['Beverages', 'Frozen Beverages'],
            collect($response->json('data'))->pluck('name')->all(),
        );
    }

    public function test_it_returns_no_categories_for_a_search_with_no_matches(): void
    {
        Category::factory()->create(['name' => 'Beverages']);

        $response = $this->getJson('/api/categories?search=nonexistent');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_it_creates_a_category(): void
    {
        $response = $this->postJson('/api/categories', [
            'name' => 'Beverages',
            'description' => 'Drinks and refreshments',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Beverages');
        $this->assertDatabaseHas('categories', ['name' => 'Beverages']);
    }

    public function test_it_requires_a_name_to_create_a_category(): void
    {
        $response = $this->postJson('/api/categories', [
            'description' => 'Missing a name',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    public function test_it_rejects_a_duplicate_name_on_create(): void
    {
        Category::factory()->create(['name' => 'Beverages']);

        $response = $this->postJson('/api/categories', [
            'name' => 'Beverages',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    public function test_it_shows_a_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->getJson("/api/categories/{$category->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $category->id);
    }

    public function test_it_updates_a_category(): void
    {
        $category = Category::factory()->create(['name' => 'Old name']);

        $response = $this->putJson("/api/categories/{$category->id}", [
            'name' => 'New name',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New name');
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New name']);
    }

    public function test_it_allows_keeping_its_own_name_on_update(): void
    {
        $category = Category::factory()->create(['name' => 'Beverages']);

        $response = $this->putJson("/api/categories/{$category->id}", [
            'name' => 'Beverages',
            'description' => 'Updated description only',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Beverages');
    }

    public function test_it_rejects_a_duplicate_name_on_update(): void
    {
        Category::factory()->create(['name' => 'Beverages']);
        $category = Category::factory()->create(['name' => 'Snacks']);

        $response = $this->putJson("/api/categories/{$category->id}", [
            'name' => 'Beverages',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    public function test_it_deletes_a_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
