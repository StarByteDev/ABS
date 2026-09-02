<?php

namespace App\Http\Controllers;

use App\Models\NewsArticle;
use App\Services\LiveNewsService;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request, LiveNewsService $liveNews)
    {
        $query = NewsArticle::where('status', 'published')->orderByDesc('is_featured')->latest('published_at');
        if ($request->filled('category')) {
            $query->where('category', (string) $request->string('category'));
        }

        return view('news.index', [
            'articles' => $query->paginate(12)->withQueryString(),
            'liveHeadlines' => collect($liveNews->cached(12)),
        ]);
    }

    public function show(NewsArticle $article)
    {
        abort_unless($article->status === 'published', 404);
        return view('news.show', compact('article'));
    }
}
