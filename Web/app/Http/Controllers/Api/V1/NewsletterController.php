<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Services\BrandedMailService;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function store(Request $request, BrandedMailService $mail)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'preferences' => ['nullable', 'array'],
            'preferences.daily_market_brief' => ['nullable', 'boolean'],
            'preferences.product_updates' => ['nullable', 'boolean'],
        ]);
        $email = strtolower(trim($data['email']));
        $preferences = array_merge([
            'daily_market_brief' => false,
            'product_updates' => true,
        ], (array) ($data['preferences'] ?? []));

        $item = NewsletterSubscriber::updateOrCreate(
            ['email' => $email],
            ['status' => 'active', 'confirmed_at' => now(), 'unsubscribed_at' => null, 'preferences' => $preferences],
        );
        $mail->newsletterSubscribed($email);

        return response()->json(['data' => $item], $item->wasRecentlyCreated ? 201 : 200);
    }
}
