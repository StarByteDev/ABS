<?php

namespace Tests\Feature;

use App\Models\BinanceConnection;
use App\Models\PulseSignal;
use App\Models\PulseUserSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as FrameworkRequest;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PulsePremiumPagesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_pulse_pages_render_and_every_page_action_uses_a_registered_route(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();

        $pages = [
            '/pulse/dashboard' => ['Pulse Dashboard', 'Net P&amp;L', 'Risk &amp; Exposure'],
            '/pulse/scanner' => ['Market Scanner', 'Scanner Filters', 'Scan Quality'],
            '/pulse/signals' => ['Pulse Signals', 'Active Signal Queue', 'High-Conviction'],
            '/pulse/strategies' => ['Pulse Strategies', 'Strategy Catalog', 'Strategy Controls &amp; Health'],
        ];

        foreach ($pages as $url => $copy) {
            $response = $this->actingAs($admin)->get($url)->assertOk();
            foreach ($copy as $text) $response->assertSee($text, false);
            $response->assertSee('assets/css/pulse-premium.css', false)
                ->assertSee('assets/js/pulse-premium.js', false)
                ->assertSee('data-pulse-sidebar-toggle', false);

            $html = (string) $response->getContent();
            preg_match_all('/href="([^"]+)"/i', $html, $links);
            foreach ($links[1] as $link) {
                $this->assertLocalRouteMatches($link, 'GET', $url);
            }

            preg_match_all('/<form\b[^>]*>[\s\S]*?<\/form>/i', $html, $forms);
            foreach ($forms[0] as $form) {
                if (! preg_match('/\baction="([^"]+)"/i', $form, $action)) continue;
                preg_match('/\bmethod="([^"]+)"/i', $form, $method);
                preg_match('/name="_method"\s+value="([^"]+)"/i', $form, $override);
                $verb = strtoupper((string) ($override[1] ?? $method[1] ?? 'GET'));
                $this->assertLocalRouteMatches($action[1], $verb, $url);
            }
        }

        $this->actingAs($admin)->get('/pulse/execution')
            ->assertRedirect(route('pulse.signals.index'));

        foreach ([
            'home', 'markets', 'pulse.entry', 'about', 'profile', 'logout',
            'pulse.dashboard', 'pulse.scanner', 'pulse.scanner.run', 'pulse.signals.index',
            'pulse.signals.show', 'pulse.signals.dismiss', 'pulse.signals.execute',
            'pulse.strategies', 'pulse.execution', 'pulse.positions', 'pulse.orders',
            'pulse.trades.index', 'pulse.trades.sync', 'pulse.risk.index', 'pulse.alerts.index',
            'pulse.reports', 'pulse.binance.index', 'pulse.settings.edit', 'pulse.plans',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Missing named route: {$routeName}");
        }
    }

    public function test_mobile_api_exposes_screen_ready_payloads_and_owned_signal_dismissal(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $token = $admin->createToken('premium-page-api-test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];
        PulseUserSetting::query()->updateOrCreate(['user_id' => $admin->id], ['environment' => 'testnet']);
        BinanceConnection::query()->updateOrCreate(
            ['user_id' => $admin->id, 'environment' => 'testnet'],
            [
                'label' => 'API parity test',
                'api_key' => 'test-key-that-must-never-be-returned',
                'api_secret' => 'test-secret-that-must-never-be-returned',
                'is_active' => true,
                'permissions' => ['can_trade' => true, 'account_equity' => 24710, 'available_balance' => 24710],
                'last_tested_at' => now(),
                'last_error' => null,
            ],
        );

        $this->withHeaders($headers)->getJson('/api/v1/pulse/dashboard?period=30d')
            ->assertOk()->assertJsonStructure(['settings', 'access', 'summary' => ['net_pnl', 'signal_engagement', 'risk_utilization', 'open_positions']]);
        $this->withHeaders($headers)->getJson('/api/v1/pulse/scanner/overview')
            ->assertOk()->assertJsonStructure(['data' => ['pairs_monitored', 'setups_identified', 'results', 'scan_coverage']]);
        $this->withHeaders($headers)->getJson('/api/v1/pulse/signals/overview')
            ->assertOk()->assertJsonStructure(['data' => ['active_count', 'engagement', 'activity', 'signals']]);
        $this->withHeaders($headers)->getJson('/api/v1/pulse/strategies/overview?period=30d')
            ->assertOk()->assertJsonStructure(['data' => ['families', 'signals_generated', 'signals_actioned', 'realized_pnl']]);
        $this->withHeaders($headers)->getJson('/api/v1/pulse/execution/ticket')
            ->assertOk()
            ->assertJsonStructure(['data' => ['checks', 'ticket', 'calculation', 'account', 'execution_ready']])
            ->assertJsonPath('data.account.equity', 24710)
            ->assertJsonMissingPath('data.connection.api_key')
            ->assertJsonMissingPath('data.connection.api_secret');

        $signal = PulseSignal::create([
            'user_id' => $admin->id,
            'symbol' => 'BTCUSDT',
            'timeframe' => '4h',
            'direction' => 'LONG',
            'entry_price' => 68000,
            'stop_loss' => 66940,
            'take_profit' => 70350,
            'score' => 87,
            'confidence_label' => 'Strong',
            'status' => 'active',
            'strategy_breakdown' => [],
            'generated_at' => now(),
            'expires_at' => now()->addMinutes(90),
        ]);

        $this->withHeaders($headers)->patchJson("/api/v1/pulse/signals/{$signal->id}/dismiss")
            ->assertOk()->assertJsonPath('data.status', 'dismissed');
    }

    private function assertLocalRouteMatches(string $target, string $method, string $source): void
    {
        $target = html_entity_decode($target, ENT_QUOTES | ENT_HTML5);
        if ($target === '' || str_starts_with($target, '#') || str_starts_with($target, 'data:')) return;

        $host = parse_url($target, PHP_URL_HOST);
        if ($host !== null && $host !== '' && ! in_array($host, ['localhost', '127.0.0.1'], true)) return;

        $path = (string) (parse_url($target, PHP_URL_PATH) ?: '/');
        if (str_starts_with($path, '/assets/') || in_array($path, ['/favicon.svg', '/robots.txt'], true)) return;
        $query = (string) (parse_url($target, PHP_URL_QUERY) ?: '');
        $requestTarget = $path.($query !== '' ? '?'.$query : '');

        $matched = Route::getRoutes()->match(FrameworkRequest::create($requestTarget, $method));
        $this->assertNotNull($matched, "{$method} {$target} from {$source} did not match an application route.");
    }
}
