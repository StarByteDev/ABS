<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LegalController;
use App\Models\EconomicEvent;
use App\Models\LearningArticle;
use App\Models\NewsArticle;
use App\Models\Product;
use App\Models\ResearchReport;
use App\Models\SiteSetting;
use App\Services\LiveNewsService;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function products(Request $request)
    {
        $query = Product::query()->where('status', 'live')->orderBy('sort_order')->orderBy('name');
        if ($request->filled('category')) $query->where('category', $request->string('category'));
        if ($request->boolean('featured')) $query->where('is_featured', true);
        return response()->json(['data' => $query->get()]);
    }

    public function product(Product $product)
    {
        abort_unless($product->status === 'live', 404);
        return response()->json(['data' => $product]);
    }

    public function news(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 50);
        return response()->json(NewsArticle::where('status', 'published')
            ->when($request->category, fn ($query, $category) => $query->where('category', $category))
            ->when($request->boolean('featured'), fn ($query) => $query->where('is_featured', true))
            ->orderByDesc('is_featured')->latest('published_at')->paginate($perPage));
    }

    public function liveNews(Request $request, LiveNewsService $liveNews)
    {
        return response()->json(['data' => $liveNews->latest((int) $request->integer('limit', 20), $request->boolean('refresh'))])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function newsShow(NewsArticle $article)
    {
        abort_unless($article->status === 'published', 404);
        return response()->json(['data' => $article]);
    }

    public function research(Request $request)
    {
        $query = ResearchReport::query()->where('status', 'published');
        if ($request->filled('category')) $query->where('category', $request->string('category'));
        if ($request->filled('asset_symbol')) $query->where('asset_symbol', strtoupper((string) $request->string('asset_symbol')));
        if ($request->boolean('featured')) $query->where('is_featured', true);
        return response()->json($query->orderByDesc('is_featured')->latest('published_at')->paginate($this->perPage($request)));
    }

    public function researchShow(ResearchReport $report)
    {
        abort_unless($report->status === 'published', 404);
        return response()->json(['data' => $report]);
    }

    public function learning(Request $request)
    {
        $query = LearningArticle::query()->where('status', 'published');
        if ($request->filled('category')) $query->where('category', $request->string('category'));
        if ($request->filled('level')) $query->where('level', $request->string('level'));
        if ($request->boolean('featured')) $query->where('is_featured', true);
        return response()->json($query->orderByDesc('is_featured')->latest('published_at')->paginate($this->perPage($request)));
    }

    public function learningShow(LearningArticle $lesson)
    {
        abort_unless($lesson->status === 'published', 404);
        return response()->json(['data' => $lesson]);
    }

    public function calendar(Request $request)
    {
        $from = $request->date('from') ?: now()->subDay()->startOfDay();
        $to = $request->date('to') ?: now()->addDays(30)->endOfDay();
        $query = EconomicEvent::query()->whereBetween('event_at', [$from, $to]);
        if ($request->filled('impact')) $query->where('impact', $request->string('impact'));
        if ($request->filled('currency')) $query->where('currency', strtoupper((string) $request->string('currency')));
        if ($request->boolean('crypto_relevant')) $query->where('is_crypto_relevant', true);

        return response()->json([
            'data' => $query->orderBy('event_at')->limit(500)->get(),
            'meta' => [
                'timezone' => (string) config('app.timezone'),
                'values' => ['previous' => 'Previous published reading', 'forecast' => 'Market/provider consensus estimate', 'actual' => 'Current released reading when available'],
                'crypto_impact_note' => 'Crypto impact notes are simplified market context, not predictions or financial advice. Actual market reaction can differ or reverse quickly.',
                'disclaimer_url' => route('legal.disclaimer'),
                'risk_url' => route('legal.risk'),
            ],
        ]);
    }

    public function settings(Request $request)
    {
        $allowed = ['general', 'mobile', 'contact', 'legal'];
        $group = $request->string('group')->toString();
        $query = SiteSetting::query()->whereIn('group', $allowed);
        if ($group !== '' && in_array($group, $allowed, true)) $query->where('group', $group);
        $data = $query->get()->groupBy('group')->map(fn ($items) => $items->mapWithKeys(fn (SiteSetting $item) => [$item->key => $this->castValue($item->value, $item->type)]));
        return response()->json(['data' => $data]);
    }

    public function legal(string $type)
    {
        $documents = LegalController::documents();
        abort_unless(isset($documents[$type]), 404);
        return response()->json(['data' => $documents[$type]]);
    }

    private function perPage(Request $request): int
    {
        return min(50, max(1, (int) $request->integer('per_page', 20)));
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
