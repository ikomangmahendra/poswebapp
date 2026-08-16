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

    public function test_it_deletes_a_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
