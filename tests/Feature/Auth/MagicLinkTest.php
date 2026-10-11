<?php

namespace Tests\Feature\Auth;

use App\Models\MagicLink;
use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MagicLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_magic_link_emails_a_working_link_to_the_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reader@example.com']);

        $this->post(route('magic-link.store'), ['email' => 'Reader@example.com'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('magic_link_sent');

        Notification::assertSentTo($user, MagicLinkNotification::class, function (MagicLinkNotification $notification) use ($user): bool {
            $link = MagicLink::findUsable($notification->token);

            return $link?->user->is($user) ?? false;
        });
    }

    public function test_requesting_a_magic_link_for_an_unknown_email_does_not_reveal_that_it_is_unknown(): void
    {
        Notification::fake();

        $this->post(route('magic-link.store'), ['email' => 'nobody@example.com'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('magic_link_sent');

        Notification::assertNothingSent();
        $this->assertSame(0, MagicLink::count());
    }

    public function test_opening_a_magic_link_does_not_sign_the_user_in_until_it_is_confirmed(): void
    {
        $link = MagicLink::factory()->forToken('valid-token')->create();

        $this->get(route('magic-link.show', 'valid-token'))
            ->assertOk()
            ->assertSee(route('magic-link.consume', 'valid-token'));

        $this->assertGuest();
        $this->assertNull($link->fresh()->used_at);
    }

    public function test_a_magic_link_signs_the_user_in_and_verifies_their_email(): void
    {
        $user = User::factory()->unverified()->create();
        MagicLink::factory()->for($user)->forToken('valid-token')->create();

        $this->post(route('magic-link.consume', 'valid-token'))->assertRedirect(route('stories.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_a_magic_link_can_only_be_used_once(): void
    {
        MagicLink::factory()->forToken('valid-token')->create();

        $this->post(route('magic-link.consume', 'valid-token'));
        $this->post(route('logout'));

        $this->post(route('magic-link.consume', 'valid-token'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_an_expired_magic_link_cannot_be_used(): void
    {
        MagicLink::factory()->expired()->forToken('expired-token')->create();

        $this->get(route('magic-link.show', 'expired-token'))->assertSee('This link has expired');

        $this->post(route('magic-link.consume', 'expired-token'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
