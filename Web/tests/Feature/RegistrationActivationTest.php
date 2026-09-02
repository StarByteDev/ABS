<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserServiceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegistrationActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_creates_abs_account_and_activates_it_before_sign_in(): void
    {
        $this->seed();
        Mail::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'New ABS Member',
            'email' => 'new.member@domain.test',
            'country_code' => '+971',
            'phone' => '50 123 4567',
            'country' => 'United Arab Emirates',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user = User::query()->where('email', 'new.member@domain.test')->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame('+971', $user->country_code);
        $this->assertSame('50 123 4567', $user->phone);
        $this->assertSame('United Arab Emirates', $user->country);
        $this->assertFalse(UserServiceAccess::query()->where('user_id', $user->id)->exists());

        $activationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'user' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);
        $this->get($activationUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('active', $user->status);
        $this->assertTrue(UserServiceAccess::query()->where('user_id', $user->id)->where('service', 'pulse')->exists());
    }

    public function test_activation_route_rejects_an_incorrect_email_hash(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'status' => 'pending',
        ]);

        $activationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'user' => $user->getKey(),
            'hash' => sha1('not-the-user-email'),
        ]);

        $this->get($activationUrl)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
