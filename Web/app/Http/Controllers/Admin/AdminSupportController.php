<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\PulseSupportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminSupportController extends Controller
{
    public function index(Request $request, PulseSupportService $support)
    {
        $support->touchAdminPresence();
        $query = SupportConversation::query()->with('user')->latest('last_message_at');
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('category')) $query->where('category', $request->string('category'));
        if ($request->filled('q')) {
            $term = '%'.trim((string) $request->string('q')).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('subject', 'like', $term));
        }

        return view('admin.support.index', [
            'items' => $query->paginate(30)->withQueryString(),
            'presence' => $support->presence(),
            'categories' => PulseSupportService::CATEGORIES,
            'summary' => [
                'unread' => $support->unreadForAdmin(),
                'waiting_support' => SupportConversation::query()->where('status', 'waiting_support')->count(),
                'waiting_customer' => SupportConversation::query()->where('status', 'waiting_customer')->count(),
                'open' => SupportConversation::query()->whereNotIn('status', ['resolved', 'closed'])->count(),
            ],
        ]);
    }

    public function show(SupportConversation $conversation, PulseSupportService $support)
    {
        $support->touchAdminPresence();
        $support->markAdminRead($conversation);
        $conversation->load(['messages.user', 'user.pulseAccess.plan', 'assignedAdmin']);
        return view('admin.support.show', [
            'conversation' => $conversation,
            'presence' => $support->presence(),
            'categories' => PulseSupportService::CATEGORIES,
        ]);
    }

    public function start(Request $request, User $user, PulseSupportService $support)
    {
        abort_unless($user->status === 'active', 422, 'Support conversations can only be started for active accounts.');
        $conversation = $support->createConversation($user, $user->isPrivateInvestor() ? 'portfolio' : 'other', 'web');
        if (! $conversation->assigned_admin_id) {
            $conversation->update(['assigned_admin_id' => $request->user()->id]);
        }
        return redirect()->route('admin.support.show', $conversation);
    }

    public function message(Request $request, SupportConversation $conversation, PulseSupportService $support)
    {
        $support->touchAdminPresence();
        $data = $request->validate(['message' => ['required', 'string', 'min:1', 'max:5000']]);
        $support->adminMessage($request->user(), $conversation, $data['message']);
        return back()->with('success', 'Reply sent.');
    }

    public function update(Request $request, SupportConversation $conversation)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['waiting_support', 'waiting_customer', 'resolved', 'closed'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'category' => ['required', Rule::in(array_keys(PulseSupportService::CATEGORIES))],
        ]);
        $data['closed_at'] = in_array($data['status'], ['resolved', 'closed'], true) ? now() : null;
        $conversation->update($data);
        return back()->with('success', 'Conversation updated.');
    }

    public function presence(Request $request, PulseSupportService $support)
    {
        $data = $request->validate(['presence' => ['required', Rule::in(['online', 'away', 'offline'])]]);
        $support->setPresence($data['presence']);
        return back()->with('success', 'Support availability updated.');
    }

    public function heartbeat(PulseSupportService $support)
    {
        $support->touchAdminPresence();
        return response()->json(['presence' => $support->presence()]);
    }

    public function messages(SupportConversation $conversation, PulseSupportService $support)
    {
        $support->touchAdminPresence();
        $support->markAdminRead($conversation);
        return response()->json(['conversation' => $support->payload($conversation->fresh())]);
    }
}
