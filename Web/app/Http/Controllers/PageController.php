<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\MarketDataService;

class PageController extends Controller
{
    public function products()
    {
        return view('pages.products', ['products' => Product::where('status', 'live')->whereIn('slug', ['pulse-trading-intelligence', 'private-member-portal'])->orderBy('sort_order')->get()]);
    }

    public function markets(MarketDataService $market)
    {
        return view('pages.markets', ['market' => $market->cachedOverview(), 'movers' => $market->cachedMovers(10)]);
    }

    public function tools()
    {
        return view('pages.tools');
    }

    public function researchShowRedirect()
    {
        return redirect()->route('news.index', status: 301);
    }

    public function learningShowRedirect()
    {
        return redirect()->route('pulse.entry', status: 301);
    }

    public function about()
    {
        return view('pages.about');
    }
}
