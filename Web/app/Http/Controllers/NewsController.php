<?php

namespace App\Http\Controllers;

use App\Models\EconomicEvent;
use App\Models\NewsArticle;
use App\Services\EconomicCalendarService;
use App\Services\LiveNewsService;
use App\Services\NewsMarketBriefService;
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

        // Bootstrap the current calendar window when the database is empty or only
        // contains stale events. This keeps Today / Upcoming / History populated
        // without requiring an Admin to notice that the old rows are out of range.
        $calendarFrom = CarbonImmutable::now(config('app.timezone'))->subDays(30)->startOfDay();
        $calendarTo = CarbonImmutable::now(config('app.timezone'))->addDays(45)->endOfDay();
        $hasCurrentWindow = EconomicEvent::query()
            ->whereBetween('event_at', [$calendarFrom, $calendarTo])
            ->where(fn ($q) => $q->where('is_crypto_relevant', true)->orWhereIn('impact', ['high', 'medium']))
            ->exists();
        $lastCalendarSync = $calendar->lastSyncAt();
        $calendarStale = true;
        if ($lastCalendarSync) {
            try { $calendarStale = CarbonImmutable::parse($lastCalendarSync)->lt(CarbonImmutable::now()->subHours(2)); }
            catch (\Throwable) { $calendarStale = true; }
        }
        if ($calendar->configured() && (! $hasCurrentWindow || $calendarStale)
            && Cache::add('abs:news:bootstrap-economic-calendar-v1561', '1', now()->addMinutes(15))) {
            try {
                $calendar->sync($calendarFrom, $calendarTo);
            } catch (\Throwable) {
                // Public ABS News stays available even if the calendar provider is temporarily unavailable.
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
            'all' => $countFor($now->copy()->subDays(30)->startOfDay(), $now->copy()->addDays(45)->endOfDay()),
        ];

        return view('news.index', [
            'articles' => $query->paginate(12)->withQueryString(),
            'liveHeadlines' => collect($liveNews->latest(12))->map(fn (array $item) => $this->withInternalHeadlineUrl($item)),
            'macroEvents' => $macroEvents,
            'macroTimezone' => config('app.timezone'),
            'macroMode' => $mode,
            'macroCounts' => $macroCounts,
            'calendarConfigured' => $calendar->configured(),
            'calendarLastSyncAt' => $calendar->lastSyncAt(),
            'calendarLastSyncStatus' => $calendar->lastSyncStatus(),
            'calendarProvider' => $calendar->providerName(),
        ]);
    }

    public function liveShow(string $id, LiveNewsService $liveNews, NewsMarketBriefService $briefs)
    {
        $headline = $liveNews->findById($id);
        abort_unless($headline, 404);

        $headline = $this->withInternalHeadlineUrl($headline);

        return view('news.live-show', [
            'headline' => $headline,
            'marketBrief' => $briefs->build($headline),
        ]);
    }

    public function show(NewsArticle $article)
    {
        abort_unless($article->status === 'published', 404);
        return view('news.show', compact('article'));
    }

    private function withInternalHeadlineUrl(array $item): array
    {
        $item['publisher_url'] = (string) ($item['url'] ?? '');
        $item['abs_url'] = route('news.live.show', ['id' => (string) ($item['id'] ?? '')]);
        return $item;
    }
}
