<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_can_register_with_only_an_email_address(): void
    {
        $response = $this->post(route('register'), ['email' => '  Reader@Example.com ']);

        $response->assertRedirect(route('stories.index'));
        $user = User::sole();
        $this->assertSame('reader@example.com', $user->email);
        $this->assertNull($user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_email_address_can_only_be_registered_once(): void
    {
        User::factory()->create(['email' => 'reader@example.com']);

        $response = $this->post(route('register'), ['email' => 'READER@example.com']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(1, User::count());
    }

    public function test_registration_requires_a_valid_email_address(): void
    {
        $this->post(route('register'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
