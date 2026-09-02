<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PulsePlan;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class AppController extends Controller
{
    public function bootstrap(Request $request)
    {
        $settings = SiteSetting::query()->whereIn('group', ['general', 'mobile', 'contact', 'legal'])->get()->mapWithKeys(function (SiteSetting $item) {
            return [$item->key => $this->castValue($item->value, $item->type)];
        });

        return response()->json(['data' => [
            'app' => [
                'name' => config('app.name', 'Alpha Block Solutions'),
                'environment' => config('app.env'),
                'api_version' => 'v1',
                'build' => '14.9.2',
                'release' => 'Mobile/Web Backend Parity & Admin Event Notifications',
                'mobile_api_ready' => true,
                'market_data_source' => 'ABS central database (Binance Futures upstream)',
                'market_refresh_seconds' => (int) config('pulse.market_data.target_price_refresh_seconds', 60),
                'minimum_mobile_version' => $settings->get('mobile_minimum_version'),
                'recommended_mobile_version' => $settings->get('mobile_recommended_version'),
                'maintenance_mode' => (bool) $settings->get('mobile_maintenance_mode', false),
                'maintenance_message' => $settings->get('mobile_maintenance_message'),
                'support_email' => config('brand.support_email'),
                'mobile_modules' => [
                    'authentication', 'account', 'dashboard', 'market', 'watchlist', 'news', 'research', 'learning',
                    'economic_calendar', 'pulse_packages', 'pulse_scanner', 'pulse_signals', 'pulse_strategies',
                    'binance_connections', 'execution_readiness', 'positions', 'orders', 'trades', 'reports',
                    'alerts', 'notifications', 'devices', 'private_member_portal', 'contact', 'newsletter',
                ],
            ],
            'settings' => $settings,
            'pulse_plans' => PulsePlan::query()->publiclyAvailable()->orderBy('sort_order')->get(),
            'links' => [
                'privacy' => route('legal.privacy'),
                'terms' => route('legal.terms'),
                'risk_disclosure' => route('legal.risk'),
                'market_disclaimer' => route('legal.disclaimer'),
            ],
        ]]);
    }

    private function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => json_decode((string) $value, true) ?: [],
            default => $value,
        };
    }
}
