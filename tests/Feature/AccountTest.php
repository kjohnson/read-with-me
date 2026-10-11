<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_account_page(): void
    {
        $this->get(route('account.edit'))->assertRedirect(route('login'));
    }

    public function test_a_user_without_a_password_can_set_one(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->put(route('account.password.update'), ['password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_changing_an_existing_password_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->actingAs($user)
            ->put(route('account.password.update'), ['password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])
            ->assertSessionHasErrorsIn('password', 'current_password');

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'old-password-123',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_a_user_can_remove_their_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->actingAs($user)
            ->delete(route('account.password.destroy'), ['current_password' => 'old-password-123'])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->password);
    }

    public function test_changing_email_address_marks_it_unverified(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('account.update'), ['name' => 'Sam', 'email' => 'New@Example.com'])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertSame('Sam', $user->name);
        $this->assertNull($user->email_verified_at);
    }
}
