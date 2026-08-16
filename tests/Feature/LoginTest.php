<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_the_login_form_to_a_guest(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Log in');
    }

    public function test_it_redirects_an_already_authenticated_user_away_from_login(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/login');

        $response->assertRedirect('/dashboard');
    }

    public function test_it_logs_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_it_rejects_an_unknown_email(): void
    {
        $response = $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_it_rejects_an_incorrect_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_it_logs_out_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_a_guest_is_redirected_to_login_from_a_protected_page(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_a_guest_gets_a_401_from_a_protected_api_endpoint(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertUnauthorized();
    }
}
