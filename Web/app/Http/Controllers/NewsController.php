<?php

namespace App\Http\Controllers;

use App\Models\EconomicEvent;
use App\Models\NewsArticle;
use App\Services\EconomicCalendarService;
use App\Services\LiveNewsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NewsController extends Controller
{
    public function index(Request $request, LiveNewsService $liveNews, EconomicCalendarService $calendar)
    {
        $query = NewsArticle::where('status', 'published')->orderByDesc('is_featured')->latest('published_at');
        if ($request->filled('category')) {
            $query->where('category', (string) $request->string('category'));
        }

        // When the provider has been configured but the calendar is still empty,
        // bootstrap it once without making every page request wait on the provider.
        if ($calendar->configured() && ! EconomicEvent::query()->exists()
            && Cache::add('abs:news:bootstrap-economic-calendar', '1', now()->addMinutes(30))) {
            try {
                $calendar->sync(CarbonImmutable::now(config('app.timezone'))->subDays(30)->startOfDay(), CarbonImmutable::now(config('app.timezone'))->addDays(45)->endOfDay());
            } catch (\Throwable) {
                // Public ABS News stays available even if the external calendar is temporarily unavailable.
            }
        }

        $now = now();
        $relevant = static function ($q): void {
            $q->where(function ($inner): void {
                $inner->where('is_crypto_relevant', true)->orWhereIn('impact', ['high', 'medium']);
            });
        };

        $mode = (string) $request->query('calendar', 'upcoming');
        if (! in_array($mode, ['today', 'upcoming', 'history', 'all'], true)) $mode = 'upcoming';

        $eventQuery = EconomicEvent::query();
        $relevant($eventQuery);
        if ($mode === 'today') {
            $eventQuery->whereBetween('event_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])->orderBy('event_at');
        } elseif ($mode === 'history') {
            $eventQuery->whereBetween('event_at', [$now->copy()->subDays(30)->startOfDay(), $now])->orderByDesc('event_at');
        } elseif ($mode === 'all') {
            $eventQuery->whereBetween('event_at', [$now->copy()->subDays(30)->startOfDay(), $now->copy()->addDays(45)->endOfDay()])->orderBy('event_at');
        } else {
            $eventQuery->whereBetween('event_at', [$now, $now->copy()->addDays(45)->endOfDay()])->orderBy('event_at');
        }

        $macroEvents = $eventQuery->limit(240)->get();

        $countFor = function ($from, $to) use ($relevant): int {
            $q = EconomicEvent::query()->whereBetween('event_at', [$from, $to]);
            $relevant($q);
            return $q->count();
        };

        $macroCounts = [
            'today' => $countFor($now->copy()->startOfDay(), $now->copy()->endOfDay()),
            'upcoming' => $countFor($now, $now->copy()->addDays(45)->endOfDay()),
            'history' => $countFor($now->copy()->subDays(30)->startOfDay(), $now),
        ];

        return view('news.index', [
            'articles' => $query->paginate(12)->withQueryString(),
            'liveHeadlines' => collect($liveNews->cached(12)),
            'macroEvents' => $macroEvents,
            'macroTimezone' => config('app.timezone'),
            'macroMode' => $mode,
            'macroCounts' => $macroCounts,
            'calendarConfigured' => $calendar->configured(),
        ]);
    }

    public function show(NewsArticle $article)
    {
        abort_unless($article->status === 'published', 404);
        return view('news.show', compact('article'));
    }
}
