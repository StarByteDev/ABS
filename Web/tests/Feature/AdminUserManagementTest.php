<?php

namespace Tests\Feature;

use App\Models\PulsePlan;
use App\Models\User;
use App\Models\UserServiceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_standard_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Account User',
            'email' => 'account.user@domain.test',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role' => 'user',
            'status' => 'active',
            'email_verified' => '1',
        ]);

        $user = User::query()->where('email', 'account.user@domain.test')->firstOrFail();
        $response->assertRedirect(route('admin.users.show', $user));
        $this->assertSame('user', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('SecurePass123', $user->password));
    }

    public function test_admin_can_create_private_member_ready_for_private_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Private Account',
            'email' => 'private.account@domain.test',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role' => 'private_member',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $member = User::query()->where('email', 'private.account@domain.test')->firstOrFail();
        $this->assertNotNull($member->private_member_approved_at);
        $this->assertTrue($member->isPrivateMember());

        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'email' => ' PRIVATE.ACCOUNT@DOMAIN.TEST ',
            'password' => 'SecurePass123',
            'service' => 'private',
        ])->assertRedirect(route('private.index'));
    }

    public function test_admin_can_assign_pulse_plan_and_exact_dates_during_user_creation(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $plan = PulsePlan::create([
            'name' => 'Managed Plan',
            'slug' => 'managed-plan',
            'description' => 'Administrator-managed plan.',
            'monthly_price' => 25,
            'currency' => 'USDT',
            'scanner_runs_per_day' => 20,
            'signals_per_day' => 20,
            'manual_trades_per_day' => 0,
            'auto_trades_per_day' => 0,
            'max_open_trades' => 2,
            'max_selected_pairs' => 5,
            'allow_testnet_trading' => false,
            'allow_manual_trading' => false,
            'allow_live_trading' => false,
            'allow_auto_trading' => false,
            'allow_mobile_api' => true,
            'capabilities' => ['scanner' => true, 'signals' => true],
            'is_active' => true,
            'sort_order' => 50,
            'is_trial' => false,
            'is_public' => false,
            'request_enabled' => false,
            'requires_payment' => false,
            'access_days' => 30,
            'is_featured' => false,
        ]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Pulse Account',
            'email' => 'pulse.account@domain.test',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role' => 'user',
            'status' => 'active',
            'assign_pulse' => '1',
            'pulse_plan_id' => $plan->id,
            'pulse_status' => 'active',
            'starts_at' => '2026-08-10 09:00:00',
            'ends_at' => '2026-09-10 09:00:00',
            'restriction_live_trading' => '1',
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'pulse.account@domain.test')->firstOrFail();
        $access = UserServiceAccess::query()->where('user_id', $user->id)->where('service', 'pulse')->firstOrFail();

        $this->assertSame($plan->id, $access->pulse_plan_id);
        $this->assertSame('active', $access->status);
        $this->assertSame('2026-08-10 09:00:00', $access->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-10 09:00:00', $access->ends_at?->format('Y-m-d H:i:s'));
        $this->assertFalse((bool) ($access->permissions['live_trading'] ?? true));
    }

    public function test_last_active_admin_cannot_demote_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'user',
            'status' => 'active',
            'email_verified' => '1',
        ])->assertSessionHasErrors('role');

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_suspended_user_is_blocked_even_with_an_existing_web_session(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'suspended']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
