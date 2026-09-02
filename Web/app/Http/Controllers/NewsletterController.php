<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Services\BrandedMailService;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function store(Request $request, BrandedMailService $mail)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        NewsletterSubscriber::updateOrCreate(
            ['email' => strtolower($data['email'])],
            ['status' => 'active', 'confirmed_at' => now(), 'unsubscribed_at' => null, 'preferences' => ['daily_market_brief' => false, 'product_updates' => true]]
        );
        $mail->newsletterSubscribed(strtolower($data['email']));
        return back()->with('success', 'Your subscription to ABS market updates is confirmed.');
    }
}
