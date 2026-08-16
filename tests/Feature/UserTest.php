<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_users(): void
    {
        User::factory()->count(3)->create();

        $response = $this->getJson('/api/users');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_it_does_not_expose_the_password_in_the_response(): void
    {
        User::factory()->create();

        $response = $this->getJson('/api/users');

        $response->assertOk();
        $response->assertJsonMissingPath('data.0.password');
    }

    public function test_it_lists_users_ordered_by_updated_at_desc(): void
    {
        $oldest = User::factory()->create(['updated_at' => now()->subDays(2)]);
        $newest = User::factory()->create(['updated_at' => now()]);
        $middle = User::factory()->create(['updated_at' => now()->subDay()]);

        $response = $this->getJson('/api/users');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newest->id);
        $response->assertJsonPath('data.1.id', $middle->id);
        $response->assertJsonPath('data.2.id', $oldest->id);
    }

    public function test_it_searches_users_by_name(): void
    {
        User::factory()->create(['name' => 'Alice Nguyen']);
        User::factory()->create(['name' => 'Budi Santoso']);

        $response = $this->getJson('/api/users?search=ali');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Alice Nguyen');
    }

    public function test_it_searches_users_by_email(): void
    {
        User::factory()->create(['name' => 'Alice Nguyen', 'email' => 'alice@possystem.test']);
        User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@possystem.test']);

        $response = $this->getJson('/api/users?search=budi@');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Budi Santoso');
    }

    public function test_it_sorts_users_by_name_ascending(): void
    {
        User::factory()->create(['name' => 'Carla']);
        User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Budi']);

        $response = $this->getJson('/api/users?sort=name&direction=asc');

        $response->assertOk();
        $this->assertSame(
            ['Alice', 'Budi', 'Carla'],
            collect($response->json('data'))->pluck('name')->all(),
        );
    }

    public function test_it_ignores_an_unsupported_sort_column(): void
    {
        User::factory()->create(['name' => 'Alice', 'updated_at' => now()->subDay()]);
        $newest = User::factory()->create(['name' => 'Budi', 'updated_at' => now()]);

        $response = $this->getJson('/api/users?sort=email&direction=asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newest->id);
    }

    public function test_it_creates_a_user(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'Alice Nguyen',
            'email' => 'alice@possystem.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Alice Nguyen');
        $this->assertDatabaseHas('users', ['email' => 'alice@possystem.test']);

        $user = User::query()->where('email', 'alice@possystem.test')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_it_requires_a_name_email_and_password_to_create_a_user(): void
    {
        $response = $this->postJson('/api/users', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_it_rejects_a_duplicate_email_on_create(): void
    {
        User::factory()->create(['email' => 'alice@possystem.test']);

        $response = $this->postJson('/api/users', [
            'name' => 'Alice Nguyen',
            'email' => 'alice@possystem.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
    }

    public function test_it_rejects_a_password_confirmation_mismatch(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'Alice Nguyen',
            'email' => 'alice@possystem.test',
            'password' => 'password123',
            'password_confirmation' => 'not-the-same',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('password');
    }

    public function test_it_shows_a_user(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/users/{$user->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $user->id);
    }

    public function test_it_updates_a_user_without_changing_the_password(): void
    {
        $user = User::factory()->create(['name' => 'Old name']);
        $originalPassword = $user->password;

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'New name',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New name');
        $this->assertSame($originalPassword, $user->refresh()->password);
    }

    public function test_it_updates_a_users_password(): void
    {
        $user = User::factory()->create();

        $response = $this->putJson("/api/users/{$user->id}", [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('new-password123', $user->refresh()->password));
    }

    public function test_it_allows_keeping_its_own_email_on_update(): void
    {
        $user = User::factory()->create(['email' => 'alice@possystem.test']);

        $response = $this->putJson("/api/users/{$user->id}", [
            'email' => 'alice@possystem.test',
            'name' => 'Alice Updated',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.email', 'alice@possystem.test');
    }

    public function test_it_rejects_a_duplicate_email_on_update(): void
    {
        User::factory()->create(['email' => 'alice@possystem.test']);
        $user = User::factory()->create(['email' => 'budi@possystem.test']);

        $response = $this->putJson("/api/users/{$user->id}", [
            'email' => 'alice@possystem.test',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
    }

    public function test_it_deletes_a_user(): void
    {
        $user = User::factory()->create();

        $response = $this->deleteJson("/api/users/{$user->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
