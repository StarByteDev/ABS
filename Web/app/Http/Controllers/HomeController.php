<?php

namespace App\Http\Controllers;

use App\Models\NewsArticle;
use App\Models\PulsePlan;
use App\Models\PulseStrategy;
use App\Services\LiveNewsService;
use App\Services\MarketDataService;

class HomeController extends Controller
{
    public function __invoke(MarketDataService $market, LiveNewsService $liveNews)
    {
        $editorial = NewsArticle::where('status', 'published')
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->take(8)
            ->get()
            ->map(fn (NewsArticle $article) => [
                'id' => 'editorial-'.$article->id,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'category' => $article->category,
                'author_name' => $article->author_name,
                'source_name' => $article->source_name ?: 'Alpha Block Solutions',
                'source_url' => $article->source_url,
                'url' => route('news.show', $article->slug),
                'image_url' => $article->image_url,
                'published_at' => $article->published_at,
                'is_external' => false,
                'is_featured' => (bool) $article->is_featured,
            ]);

        $featuredEditorial = $editorial->where('is_featured', true)->values();
        $remainingNews = $editorial
            ->where('is_featured', false)
            ->concat($liveNews->cached(10))
            ->unique(fn (array $item) => strtolower(($item['url'] ?? '').'|'.($item['title'] ?? '')))
            ->sortByDesc(fn (array $item) => $item['published_at']?->timestamp ?? 0);

        return view('home', [
            'news' => $featuredEditorial
                ->concat($remainingNews)
                ->unique(fn (array $item) => strtolower(($item['url'] ?? '').'|'.($item['title'] ?? '')))
                ->take(5)
                ->values(),
            'market' => $market->cachedOverview(),
            'movers' => $market->cachedMovers(5),
            'strategies' => PulseStrategy::where('is_enabled', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->take(4)
                ->get(['name', 'timeframe', 'minimum_score']),
            'plans' => PulsePlan::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'description', 'monthly_price', 'currency', 'access_days', 'badge', 'is_featured', 'is_trial', 'is_public', 'request_enabled']),
        ]);
    }
}
