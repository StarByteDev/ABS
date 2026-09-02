<?php

namespace Tests\Feature;

use App\Models\PulsePair;
use App\Models\PulsePlan;
use App\Models\PulseSignal;
use App\Models\PulseStrategy;
use App\Models\PulseTrade;
use App\Models\User;
use App\Models\UserServiceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PulseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pulse_schema_and_default_full_trial_plan_are_seeded_without_fake_trades_or_signals(): void
    {
        $this->seed();

        foreach (['pulse_plans', 'pulse_plan_strategies', 'user_service_access', 'pulse_strategies', 'pulse_pairs', 'pulse_user_settings', 'binance_connections', 'pulse_scanner_runs', 'pulse_signals', 'pulse_trades', 'pulse_alerts', 'pulse_system_settings', 'pulse_audit_logs', 'pulse_automation_runs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing Pulse table: {$table}");
        }

        $trial = PulsePlan::where('slug', 'pulse-trial')->firstOrFail();
        $this->assertTrue($trial->is_active);
        foreach (PulsePlan::CAPABILITIES as $key => $label) {
            $this->assertTrue($trial->allows($key, false), "Pulse Trial should include {$label}.");
        }

        $this->assertGreaterThanOrEqual(1, PulsePlan::count());
        $this->assertSame(15, PulseStrategy::count());
        $this->assertGreaterThanOrEqual(5, PulsePair::count());
        $this->assertSame(0, PulseSignal::count());
        $this->assertSame(0, PulseTrade::count());
    }

    public function test_explore_pulse_uses_the_shared_abs_account_and_current_pulse_access(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->get('/pulse')
            ->assertOk()
            ->assertSee('Trade smarter with')
            ->assertSee('Your complete trading-intelligence workflow')
            ->assertSee('Explore Pulse with a complimentary Trial');

        $this->actingAs($admin)
            ->get('/pulse')
            ->assertRedirect(route('pulse.dashboard'));

        $this->actingAs($admin)
            ->get('/pulse/dashboard')
            ->assertOk()
            ->assertSee('Pulse Dashboard')
            ->assertSee('Track your trading activity, signal engagement and risk position');
    }

    public function test_complete_pulse_workspace_pages_use_real_account_routes(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();

        $pages = [
            '/pulse/scanner' => 'Market Scanner',
            '/pulse/signals' => 'Pulse Signals',
            '/pulse/strategies' => 'Pulse Strategies',
            '/pulse/positions' => 'Open Positions',
            '/pulse/trades' => 'Trade History',
            '/pulse/risk-controls' => 'Risk Controls',
            '/pulse/alerts' => 'Alerts &amp; Watchlists',
            '/pulse/reports' => 'Reports &amp; P&amp;L',
            '/pulse/binance' => 'Binance Connection',
            '/pulse/settings' => 'Pulse Settings',
        ];

        foreach ($pages as $url => $text) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee($text, false);
        }

        $this->actingAs($admin)->get('/pulse/execution')
            ->assertRedirect(route('pulse.signals.index'));
    }

    public function test_public_pulse_gateway_and_login_use_the_premium_flow_layout(): void
    {
        $this->seed();
        $response = $this->get('/pulse');
        $response->assertOk()
            ->assertSee('pulse-capability-flow', false)
            ->assertSee('pulse-access-timeline', false)
            ->assertSee('pulse-membership-grid', false);

        $login = $this->get('/login?service=pulse');
        $login->assertOk()
            ->assertSee('Intelligence that')
            ->assertSee('keeps you ahead.')
            ->assertSee('Verified Intelligence')
            ->assertSee('MEMBER PORTAL')
            ->assertSee('Welcome back')
            ->assertSee('Forgot password?')
            ->assertSee('premium-login-form', false)
            ->assertSee('assets/brand/abs-logo-512.png', false)
            ->assertSee('assets/css/abs-auth.css', false)
            ->assertSee('assets/js/abs-auth.js', false);
    }

    public function test_password_recovery_pages_use_the_secure_abs_authentication_experience(): void
    {
        Mail::fake();
        Notification::fake();

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Recover your account')
            ->assertSee('Password')
            ->assertSee('Username / Email')
            ->assertSee('Email Address or Username')
            ->assertSee('Send Recovery Link')
            ->assertSee('Your recovery request is protected and encrypted.')
            ->assertSee('assets/brand/abs-logo-512.png', false);

        $this->post('/forgot-password', ['recovery_identifier' => 'not-an-account@example.test'])
            ->assertSessionHas('status', 'If an ABS account matches those details, secure recovery instructions have been sent.');

        User::factory()->create([
            'name' => 'Recovery Member',
            'email' => 'recovery.member@example.test',
            'country_code' => '+971',
            'phone' => '50 123 4567',
        ]);

        $this->post('/forgot-password', ['recovery_identifier' => 'recovery.member'])
            ->assertSessionHas('status', 'If an ABS account matches those details, secure recovery instructions have been sent.');

        $this->post('/forgot-username-or-email', [
            'country_code' => '+971',
            'phone' => '501234567',
        ])->assertSessionHas('status', 'If an ABS account matches those details, account recovery instructions have been sent.');

        $this->get('/reset-password/review-token?email=member@example.test')
            ->assertOk()
            ->assertSee('Choose a new password')
            ->assertSee('member@example.test');
    }

    public function test_plan_capabilities_control_visible_routes_for_a_standard_user(): void
    {
        $this->seed();
        $trial = PulsePlan::where('slug', 'pulse-trial')->firstOrFail();
        $caps = $trial->capabilities;
        $caps['reports'] = false;
        $trial->update(['capabilities' => $caps]);

        $user = User::create([
            'name' => 'Capability Review User',
            'email' => 'capability@example.test',
            'password' => bcrypt('Review@12345'),
            'role' => 'user',
            'status' => 'active',
        ]);
        UserServiceAccess::create([
            'user_id' => $user->id,
            'service' => 'pulse',
            'status' => 'active',
            'pulse_plan_id' => $trial->id,
            'starts_at' => now(),
            'permissions' => [],
        ]);

        $this->actingAs($user)->get('/pulse/dashboard')
            ->assertOk()
            ->assertSee('Reports &amp; P&amp;L', false)
            ->assertSee('LOCKED');
        $this->actingAs($user)->get('/pulse/reports')->assertRedirect(route('pulse.dashboard'));
    }

    public function test_standard_login_and_dashboard_never_fall_back_to_the_legacy_member_screen(): void
    {
        $this->seed();
        $trial = PulsePlan::where('slug', 'pulse-trial')->firstOrFail();

        $user = User::factory()->create([
            'name' => 'Final Workspace User',
            'email' => 'final.workspace@example.test',
            'password' => bcrypt('Workspace@12345'),
            'role' => 'user',
            'status' => 'active',
        ]);
        UserServiceAccess::create([
            'user_id' => $user->id,
            'service' => 'pulse',
            'status' => 'active',
            'pulse_plan_id' => $trial->id,
            'starts_at' => now(),
            'permissions' => [],
        ]);

        $this->withSession(['url.intended' => route('profile')])->post('/login', [
            'email' => 'final.workspace@example.test',
            'password' => 'Workspace@12345',
        ])->assertRedirect(route('pulse.dashboard'));

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('pulse.dashboard'));
    }

    public function test_user_without_active_pulse_access_stays_in_the_finalized_pulse_shell(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('pulse.access'));
        $this->actingAs($user)->get('/pulse/access')
            ->assertOk()
            ->assertSee('Market Scanner')
            ->assertSee('Signals')
            ->assertSee('Trade Execution')
            ->assertSee('Reports &amp; P&amp;L', false)
            ->assertSee('LOCKED')
            ->assertSee('data-pulse-sidebar-toggle', false);
    }

    public function test_authenticated_membership_pages_keep_the_finalized_pulse_shell(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($user)->get('/pulse/membership')
            ->assertOk()
            ->assertSee('data-pulse-sidebar-toggle', false)
            ->assertSee('Market Scanner')
            ->assertSee('Membership &amp; Billing');
    }

    public function test_pulse_mobile_api_is_protected_and_returns_effective_capabilities(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;

        $this->getJson('/api/v1/pulse/access')->assertUnauthorized();

        $this->withToken($token)
            ->getJson('/api/v1/pulse/dashboard')
            ->assertOk()
            ->assertJsonStructure(['settings', 'access', 'active_signals', 'recent_trades', 'effective_capabilities']);
    }
}
