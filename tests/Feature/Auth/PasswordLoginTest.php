<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_a_password_can_sign_in_with_it(): void
    {
        $user = User::factory()->create(['email' => 'reader@example.com', 'password' => 'secret-password']);

        $this->post(route('password-login.store'), ['email' => 'reader@example.com', 'password' => 'secret-password'])
            ->assertRedirect(route('stories.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'reader@example.com', 'password' => 'secret-password']);

        $this->post(route('password-login.store'), ['email' => 'reader@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_user_without_a_password_cannot_sign_in_with_a_password(): void
    {
        User::factory()->create(['email' => 'reader@example.com', 'password' => null]);

        $this->post(route('password-login.store'), ['email' => 'reader@example.com', 'password' => ''])
            ->assertSessionHasErrors('password');
        $this->post(route('password-login.store'), ['email' => 'reader@example.com', 'password' => 'anything'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
