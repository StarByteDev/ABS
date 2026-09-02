<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\BrandedMailService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function store(Request $request, BrandedMailService $mail)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:180'],
            'category' => ['nullable', Rule::in(['general', 'account', 'membership', 'billing', 'technical', 'market-data', 'security', 'other'])],
            'message' => ['required', 'string', 'min:10', 'max:10000'],
        ]);
        $category = $data['category'] ?? 'general';
        $contact = ContactMessage::create([
            'user_id' => $request->user()?->id,
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'subject' => trim($data['subject']),
            'category' => $category,
            'message' => trim($data['message']),
            'status' => 'new',
            'priority' => $category === 'security' ? 'high' : 'normal',
        ]);
        $mail->contactAcknowledgement($contact);
        return response()->json(['message' => 'Your message has been received.', 'data' => ['reference' => $contact->id]], 201);
    }
}
