<?php

namespace App\Services;

use App\Models\PulseAlert;
use App\Models\PulseMembershipRequest;
use App\Models\SiteSetting;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PulseSupportService
{
    public const CATEGORIES = [
        'payment' => 'Payment & Activation',
        'package' => 'Package Access',
        'free_signal' => 'Free Signal',
        'scanner' => 'Scanner Help',
        'account' => 'Account Issue',
        'mobile' => 'Mobile App',
        'technical' => 'Technical Issue',
        'portfolio' => 'Investor Portfolio',
        'other' => 'Other',
    ];

    public function presence(): string
    {
        if (! Schema::hasTable('site_settings')) return 'away';
        $configured = strtolower((string) SiteSetting::query()->where('key', 'pulse_support_presence')->value('value'));
        $configured = in_array($configured, ['online', 'away', 'offline'], true) ? $configured : 'away';
        if ($configured !== 'online') return $configured;

        $lastSeen = (string) SiteSetting::query()->where('key', 'pulse_support_last_seen_at')->value('value');
        if ($lastSeen === '') return 'away';
        try {
            return now()->diffInSeconds(\Illuminate\Support\Carbon::parse($lastSeen)) <= 180 ? 'online' : 'away';
        } catch (\Throwable) {
            return 'away';
        }
    }

    public function presenceLabel(): string
    {
        return match ($this->presence()) {
            'online' => 'Live Support Available',
            'offline' => 'Support Offline · Pulse Assistant Available',
            default => 'Support Away · Pulse Assistant Available',
        };
    }

    public function setPresence(string $presence): void
    {
        $presence = in_array($presence, ['online', 'away', 'offline'], true) ? $presence : 'away';
        SiteSetting::query()->updateOrCreate(
            ['key' => 'pulse_support_presence'],
            ['value' => $presence, 'type' => 'string', 'group' => 'support']
        );
    }


    public function touchAdminPresence(): void
    {
        if (! Schema::hasTable('site_settings')) return;
        SiteSetting::query()->updateOrCreate(
            ['key' => 'pulse_support_last_seen_at'],
            ['value' => now()->toIso8601String(), 'type' => 'string', 'group' => 'support']
        );
    }

    public function activeConversation(User $user): ?SupportConversation
    {
        if (! Schema::hasTable('support_conversations')) return null;
        return SupportConversation::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->latest('last_message_at')
            ->latest('id')
            ->first();
    }

    public function createConversation(User $user, string $category = 'other', string $channel = 'web'): SupportConversation
    {
        $existing = $this->activeConversation($user);
        if ($existing) return $existing;

        $category = array_key_exists($category, self::CATEGORIES) ? $category : 'other';
        return SupportConversation::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'subject' => self::CATEGORIES[$category],
            'category' => $category,
            'channel' => in_array($channel, ['web', 'mobile'], true) ? $channel : 'web',
            'status' => 'waiting_support',
            'priority' => $category === 'technical' ? 'high' : 'normal',
            'last_message_at' => now(),
        ]);
    }

    public function customerMessage(User $user, string $body, string $category = 'other', string $channel = 'web', ?SupportConversation $conversation = null): SupportConversation
    {
        $conversation = $conversation ?: $this->createConversation($user, $category, $channel);
        $this->guardConversation($conversation, $user);

        $body = trim($body);
        SupportMessage::create([
            'support_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'sender_type' => 'customer',
            'body' => $body,
            'read_by_customer_at' => now(),
        ]);

        $conversation->update([
            'status' => 'waiting_support',
            'last_message_at' => now(),
            'last_customer_message_at' => now(),
            'closed_at' => null,
        ]);

        $handled = $this->assistantReply($conversation, $user, $body);
        if (! $handled) {
            $this->alertAdmins($conversation, $body);
        }

        return $conversation->fresh(['messages', 'user', 'assignedAdmin']);
    }

    public function adminMessage(User $admin, SupportConversation $conversation, string $body): SupportConversation
    {
        SupportMessage::create([
            'support_conversation_id' => $conversation->id,
            'user_id' => $admin->id,
            'sender_type' => 'admin',
            'body' => trim($body),
            'read_by_admin_at' => now(),
        ]);

        $conversation->update([
            'assigned_admin_id' => $conversation->assigned_admin_id ?: $admin->id,
            'status' => 'waiting_customer',
            'last_message_at' => now(),
            'last_admin_message_at' => now(),
        ]);

        if ($conversation->user) {
            PulseAlert::create([
                'user_id' => $conversation->user_id,
                'type' => 'support',
                'title' => 'Pulse Support replied',
                'message' => Str::limit(trim($body), 180),
                'severity' => 'info',
                'is_read' => false,
                'action_url' => route('pulse.support.index'),
                'data' => ['conversation_id' => $conversation->id],
            ]);
        }

        return $conversation->fresh(['messages', 'user', 'assignedAdmin']);
    }

    public function close(User $user, SupportConversation $conversation): void
    {
        $this->guardConversation($conversation, $user);
        $conversation->update(['status' => 'closed', 'closed_at' => now(), 'last_message_at' => now()]);
        SupportMessage::create([
            'support_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'sender_type' => 'system',
            'message_type' => 'status',
            'body' => 'Conversation closed.',
            'read_by_customer_at' => now(),
        ]);
    }

    public function markCustomerRead(SupportConversation $conversation): void
    {
        $conversation->messages()->whereIn('sender_type', ['admin', 'assistant', 'system'])->whereNull('read_by_customer_at')->update(['read_by_customer_at' => now()]);
    }

    public function markAdminRead(SupportConversation $conversation): void
    {
        $conversation->messages()->where('sender_type', 'customer')->whereNull('read_by_admin_at')->update(['read_by_admin_at' => now()]);
    }

    public function unreadForAdmin(): int
    {
        if (! Schema::hasTable('support_messages')) return 0;
        return SupportMessage::query()->where('sender_type', 'customer')->whereNull('read_by_admin_at')->count();
    }

    public function unreadForUser(User $user): int
    {
        if (! Schema::hasTable('support_messages') || ! Schema::hasTable('support_conversations')) return 0;
        return SupportMessage::query()
            ->whereHas('conversation', fn ($q) => $q->where('user_id', $user->id))
            ->whereIn('sender_type', ['admin', 'assistant'])
            ->whereNull('read_by_customer_at')
            ->count();
    }

    public function payload(SupportConversation $conversation): array
    {
        $conversation->loadMissing(['messages', 'assignedAdmin']);
        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'category' => $conversation->category,
            'category_label' => self::CATEGORIES[$conversation->category] ?? 'Support',
            'channel' => $conversation->channel,
            'status' => $conversation->status,
            'priority' => $conversation->priority,
            'assigned_to' => $conversation->assignedAdmin?->name,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'messages' => $conversation->messages->map(fn (SupportMessage $message) => [
                'id' => $message->id,
                'sender' => $message->sender_type,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
                'read' => $message->sender_type === 'customer' ? (bool) $message->read_by_admin_at : (bool) $message->read_by_customer_at,
            ])->values()->all(),
        ];
    }

    protected function assistantReply(SupportConversation $conversation, User $user, string $message): bool
    {
        [$reply, $confident, $category] = $this->assistantResponse($user, $message, $conversation->category);
        if ($category && $conversation->category === 'other') {
            $conversation->category = $category;
            $conversation->subject = self::CATEGORIES[$category] ?? $conversation->subject;
        }

        SupportMessage::create([
            'support_conversation_id' => $conversation->id,
            'sender_type' => 'assistant',
            'body' => $reply,
            'read_by_admin_at' => now(),
            'metadata' => ['assistant_mode' => 'pulse_support', 'confident' => $confident],
        ]);

        $updates = [
            'status' => $confident ? 'waiting_customer' : 'waiting_support',
            'last_message_at' => now(),
            'assistant_handled_at' => now(),
            'category' => $conversation->category,
            'subject' => $conversation->subject,
        ];
        if (! $confident) $updates['escalated_at'] = now();
        $conversation->update($updates);
        return $confident;
    }

    protected function assistantResponse(User $user, string $message, string $currentCategory): array
    {
        $text = Str::lower($message);
        $wantsHuman = Str::contains($text, ['human', 'agent', 'admin', 'person', 'someone', 'live support', 'representative']);
        if ($wantsHuman) {
            return ['I have kept this conversation open for the ABS Support team. You can add any extra details here and the team will continue from this same conversation.', false, $currentCategory];
        }

        if ($user->isPrivateInvestor() && Str::contains($text, ['portfolio', 'investment', 'invest more', 'add investment', 'withdraw', 'withdrawal', 'statement', 'monthly profit', 'profit statement', 'valuation'])) {
            $account = $user->portfolioAccount()->first();
            $openRequests = $user->portfolioRequests()->whereIn('status', ['submitted', 'under_review'])->count();
            if ($account) {
                $currency = strtoupper((string) $account->currency);
                $value = number_format((float) $account->current_value, 2);
                $profit = number_format((float) $account->total_profit, 2);
                $month = number_format((float) $account->monthly_profit, 2);
                $plan = $account->performancePlans()->with('accruals')->whereDate('plan_month', now()->startOfMonth()->toDateString())->first();
                $provisional = $plan ? (float) $plan->accruals->whereNotNull('posted_at')->sum(fn ($row) => (float) $row->posted_amount) : null;
                $planText = $plan ? ' Current-month provisional accrual is '.$currency.' '.number_format($provisional, 2).' against a '.$currency.' '.number_format((float)$plan->target_amount, 2).' monthly target.' : '';
                return ["Your Private Investor portfolio currently shows a reported value of {$currency} {$value}, published total P/L of {$currency} {$profit}, and latest published monthly result of {$currency} {$month}.{$planText} You have {$openRequests} open portfolio request(s). Use Investor Portfolio to view statements, requests and current-month progress.", true, 'portfolio'];
            }
            return ['Your Private Investor access is active, but a portfolio account has not been configured yet. I have kept this conversation open so the ABS team can complete the portfolio setup.', false, 'portfolio'];
        }

        if (Str::contains($text, ['payment', 'paid', 'txid', 'transaction', 'activate', 'activation', 'usdt'])) {
            $request = PulseMembershipRequest::query()->with('plan')->where('user_id', $user->id)->latest()->first();
            if ($request) {
                $status = match ($request->status) {
                    'submitted', 'under_review' => 'awaiting verification',
                    'approved' => 'approved',
                    'rejected' => 'needs attention',
                    'cancelled' => 'cancelled',
                    default => str_replace('_', ' ', $request->status),
                };
                $plan = $request->plan?->name ?? 'Pulse package';
                $extra = in_array($request->status, ['submitted', 'under_review'], true)
                    ? 'No further payment is required while verification is pending. The package activates after the transaction is confirmed.'
                    : 'You can review the latest status from Package & Payments.';
                return ["Your latest {$plan} request is {$status}. Request #{$request->id} was submitted on ".$request->created_at?->format('d M Y H:i').". {$extra}", true, 'payment'];
            }
            return ['I cannot see a recent Pulse payment request on your account. Open Package & Payments to review available plans or submit the transaction reference after sending USDT.', true, 'payment'];
        }

        if (Str::contains($text, ['package', 'plan', 'membership', 'access', 'expiry', 'expire', 'renew'])) {
            $access = $user->pulseAccess()->with('plan')->first();
            if ($access?->isActive()) {
                $until = $access->ends_at ? $access->ends_at->format('d M Y H:i') : 'no fixed expiry recorded';
                return ['Your current Pulse access is '.($access->plan?->name ?? 'active Pulse access')." and is active until {$until}. Package & Payments shows your benefits, payment history and available upgrade path.", true, 'package'];
            }
            return ['Your account does not currently show active Pulse package access. Open Package & Payments to review available plans or the status of a recent payment request.', true, 'package'];
        }

        if (Str::contains($text, ['free signal', 'free-signal', 'rewarded', 'ad unlock'])) {
            return ['Free Signal checks the current qualified Pulse signal pool first. If no qualified setup is available, ABS presents a BTC 4-Hour Outlook so you still have current market context while waiting for the next qualified entry.', true, 'free_signal'];
        }

        if (Str::contains($text, ['scanner', 'scan', 'best signal', 'find signal'])) {
            return ['Find Best Signal evaluates the markets available to your package across the configured 15M and 4H strategy engine, then ranks qualifying setups and presents the strongest eligible signal. If scanning appears delayed, check that the market feed is current and try again after the next price sync.', true, 'scanner'];
        }

        if (Str::contains($text, ['mobile', 'app', 'android', 'iphone', 'ios', 'notification'])) {
            return ['ABS Pulse mobile uses the same account, package, signal and support data as the web platform. Sign in with the same ABS account. If a screen is not updating, tell me the page name and what you expected to see so the support team can trace it.', true, 'mobile'];
        }

        if (Str::contains($text, ['login', 'password', 'email', 'account', 'profile'])) {
            return ['For account access, first confirm you are using the email registered with ABS. Password recovery is available from the sign-in page. If the account is active but you still cannot sign in, add the error message here and the support team can review it.', true, 'account'];
        }

        if (Str::contains($text, ['error', '500', 'not working', 'broken', 'problem', 'issue'])) {
            return ['I have marked this as a technical support request. Please add the page or feature name, the error shown, and what you were doing immediately before it appeared. The ABS Support team can continue from this conversation.', false, 'technical'];
        }

        return ['I could not match that question confidently to an account help answer, so I have passed this conversation to ABS Support. You can continue here and the team will reply in the same thread.', false, $currentCategory === 'other' ? 'other' : $currentCategory];
    }

    protected function alertAdmins(SupportConversation $conversation, string $body): void
    {
        $admins = User::query()->where('role', 'admin')->where('status', 'active')->get(['id']);
        foreach ($admins as $admin) {
            PulseAlert::create([
                'user_id' => $admin->id,
                'type' => 'support',
                'title' => 'New Pulse Support message',
                'message' => $conversation->name.': '.Str::limit($body, 160),
                'severity' => $conversation->priority === 'high' ? 'warning' : 'info',
                'is_read' => false,
                'action_url' => route('admin.support.show', $conversation),
                'data' => ['conversation_id' => $conversation->id],
            ]);
        }
    }

    protected function guardConversation(SupportConversation $conversation, User $user): void
    {
        abort_unless($conversation->user_id === $user->id, 404);
        abort_if(! $conversation->isOpen(), 422, 'This support conversation is closed. Start a new conversation to continue.');
    }
}
