<?php

namespace App\Http\Controllers;

use App\Models\NewsArticle;
use App\Models\Product;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $results = collect();

        if (mb_strlen($q) >= 2) {
            $results = $results
                ->concat(NewsArticle::where('status', 'published')
                    ->where(fn ($query) => $query->where('title', 'like', "%$q%")->orWhere('excerpt', 'like', "%$q%"))
                    ->limit(12)
                    ->get()
                    ->map(fn ($item) => ['type' => 'Market News', 'title' => $item->title, 'excerpt' => $item->excerpt, 'url' => route('news.show', $item->slug)]))
                ->concat(Product::where('status', 'live')
                    ->whereIn('slug', ['pulse-trading-intelligence', 'private-member-portal'])
                    ->where(fn ($query) => $query->where('name', 'like', "%$q%")->orWhere('description', 'like', "%$q%"))
                    ->get()
                    ->map(fn ($item) => ['type' => 'ABS Service', 'title' => $item->name, 'excerpt' => $item->tagline, 'url' => route('products').'#'.$item->slug]))
                ->take(16);
        }

        return view('search', compact('q', 'results'));
    }
}
