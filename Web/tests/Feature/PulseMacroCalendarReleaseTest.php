<?php

namespace Tests\Feature;

use App\Models\EconomicEvent;
use App\Models\PulseMacroReleaseAlert;
use App\Services\EconomicCalendarService;
use App\Services\PulseMacroReleaseService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PulseMacroCalendarReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_provider_forecast_is_enriched_actual_is_not_emptied_and_push_is_deduplicated(): void
    {
        config()->set('services.fmp.api_key', 'fixture');
        config()->set('services.finance_calendar.enabled', true);
        config()->set('services.xoomar_calendar.enabled', false);
        config()->set('services.trading_economics.fallback_enabled', false);

        $date = CarbonImmutable::now('UTC')->subMinutes(4)->format('Y-m-d H:i:s');
        $fmp = [
            'id' => 'test-us-cpi', 'event' => 'US CPI MoM', 'country' => 'US',
            'currency' => 'USD', 'date' => $date, 'impact' => 'High', 'previous' => '0.2%',
            'estimate' => null, 'actual' => null,
        ];
        $fc = [
            'id' => 'test-fc-us-cpi', 'title' => 'US CPI MoM', 'country' => 'United States',
            'currency' => 'USD', 'time_utc' => $date, 'impact' => 'high',
            'consensus' => ['value' => '0.3', 'unit' => '%'], 'previous' => null, 'actual' => null,
        ];
        Http::fake([
            '*financialmodelingprep.com/*' => function () use (&$fmp) { return Http::response([$fmp]); },
            '*financecalendar.com/*' => function () use (&$fc) { return Http::response(['events' => [$fc]]); },
        ]);
        $calendar = app(EconomicCalendarService::class);
        $calendar->sync(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay());
        $this->assertSame(1, EconomicEvent::count());
        $event = EconomicEvent::firstOrFail();
        $this->assertSame('0.3%', $event->forecast_value);
        $this->assertNull($event->actual_value);
        $this->assertSame('0.2%', $event->previous_value);
        $this->assertSame(0, PulseMacroReleaseAlert::count());

        $fmp['actual'] = '0.1%';
        $calendar->sync(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay());
        $event->refresh();
        $this->assertSame(1, EconomicEvent::count());
        $this->assertSame('0.3%', $event->forecast_value);
        $this->assertSame('0.1%', $event->actual_value);
        $this->assertSame('supportive', $event->crypto_impact);
        $this->assertSame(1, PulseMacroReleaseAlert::count());
        $this->assertStringContainsString('vs forecast 0.3%', app(PulseMacroReleaseService::class)->notificationBody($event));

        $fmp['actual'] = null; // Regression: provider temporarily clears actual.
        $calendar->sync(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay());
        $this->assertSame('0.1%', $event->fresh()->actual_value);
        $this->assertSame(1, PulseMacroReleaseAlert::count());
    }

    public function test_historical_import_with_actual_does_not_spam_mobile_users(): void
    {
        $old = EconomicEvent::create([
            'title' => 'Old CPI', 'impact' => 'high', 'event_at' => now()->subDays(8),
            'provider_event_id' => 'fixture:old', 'forecast_value' => '1.0%',
        ]);
        $old->update(['actual_value' => '1.2%']);
        $this->assertSame(0, PulseMacroReleaseAlert::count());
    }
}
