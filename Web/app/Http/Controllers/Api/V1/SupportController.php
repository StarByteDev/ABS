<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Services\PulseSupportService;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function overview(Request $request, PulseSupportService $support)
    {
        $conversation = $support->activeConversation($request->user());
        if ($conversation) $support->markCustomerRead($conversation);

        return response()->json([
            'data' => [
                'presence' => $support->presence(),
                'presence_label' => $support->presenceLabel(),
                'categories' => collect(PulseSupportService::CATEGORIES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
                'unread_count' => $support->unreadForUser($request->user()),
                'conversation' => $conversation ? $support->payload($conversation) : null,
            ],
        ]);
    }

    public function message(Request $request, PulseSupportService $support)
    {
        $data = $request->validate([
            'conversation_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $conversation = null;
        if (! empty($data['conversation_id'])) {
            $conversation = SupportConversation::query()->findOrFail((int) $data['conversation_id']);
            abort_unless($conversation->user_id === $request->user()->id, 404);
        }

        $conversation = $support->customerMessage(
            $request->user(),
            $data['message'],
            $data['category'] ?? 'other',
            'mobile',
            $conversation
        );

        return response()->json(['message' => 'Message sent.', 'data' => $support->payload($conversation)], 201);
    }

    public function show(Request $request, SupportConversation $conversation, PulseSupportService $support)
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
        $support->markCustomerRead($conversation);
        return response()->json(['data' => $support->payload($conversation->fresh())]);
    }

    public function close(Request $request, SupportConversation $conversation, PulseSupportService $support)
    {
        $support->close($request->user(), $conversation);
        return response()->json(['message' => 'Conversation closed.']);
    }
}
