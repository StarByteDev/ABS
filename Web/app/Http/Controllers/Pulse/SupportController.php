<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Services\PulseSupportService;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index(Request $request, PulseSupportService $support)
    {
        $conversation = $support->activeConversation($request->user());
        if ($conversation) {
            $conversation->load(['messages', 'assignedAdmin']);
            $support->markCustomerRead($conversation);
        }

        $history = SupportConversation::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['resolved', 'closed'])
            ->latest('last_message_at')
            ->limit(8)
            ->get();

        return view('pulse.support.index', [
            'conversation' => $conversation,
            'history' => $history,
            'presence' => $support->presence(),
            'presenceLabel' => $support->presenceLabel(),
            'categories' => PulseSupportService::CATEGORIES,
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
        }

        $support->customerMessage(
            $request->user(),
            $data['message'],
            $data['category'] ?? 'other',
            'web',
            $conversation
        );

        return redirect()->route('pulse.support.index')->with('success', 'Message sent.');
    }

    public function messages(Request $request, SupportConversation $conversation, PulseSupportService $support)
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
        $support->markCustomerRead($conversation);
        return response()->json([
            'presence' => $support->presence(),
            'presence_label' => $support->presenceLabel(),
            'conversation' => $support->payload($conversation->fresh()),
        ]);
    }

    public function close(Request $request, SupportConversation $conversation, PulseSupportService $support)
    {
        $support->close($request->user(), $conversation);
        return redirect()->route('pulse.support.index')->with('success', 'Support conversation closed.');
    }
}
