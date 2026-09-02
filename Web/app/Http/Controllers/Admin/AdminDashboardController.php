<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use App\Models\EmailDeliveryLog;
use App\Models\ContactMessage;
use App\Models\NewsArticle;
use App\Models\PortfolioAccount;
use App\Models\PulsePlan;
use App\Models\PulseMembershipRequest;
use App\Models\PulseScannerRun;
use App\Models\PulseSignal;
use App\Models\PulseTrade;
use App\Models\User;
use App\Models\UserServiceAccess;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function __invoke()
    {
        $now = now();
        $in7 = $now->copy()->addDays(7);
        $in30 = $now->copy()->addDays(30);

        $pulseBase = UserServiceAccess::query()->where('service', 'pulse');
        $activePulse = (clone $pulseBase)->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));

        $expiring7 = (clone $activePulse)->whereNotNull('ends_at')->whereBetween('ends_at', [$now, $in7])->count();
        $expiring30 = (clone $activePulse)->whereNotNull('ends_at')->whereBetween('ends_at', [$now, $in30])->count();
        $expired = (clone $pulseBase)->whereNotNull('ends_at')->where('ends_at', '<=', $now)->count();
        $trialPlanIds = PulsePlan::query()->where('is_trial', true)->pluck('id');

        $planRows = PulsePlan::query()
            ->withCount([
                'accesses as assigned_count' => fn ($q) => $q->where('service', 'pulse'),
                'accesses as active_count' => fn ($q) => $q->where('service', 'pulse')->where('status', 'active')
                    ->where(fn ($x) => $x->whereNull('ends_at')->orWhere('ends_at', '>', $now)),
                'accesses as expiring_30_count' => fn ($q) => $q->where('service', 'pulse')->where('status', 'active')
                    ->whereNotNull('ends_at')->whereBetween('ends_at', [$now, $in30]),
            ])
            ->orderBy('sort_order')->orderBy('name')->get();

        $expiryQueue = UserServiceAccess::query()->with(['user', 'plan'])
            ->where('service', 'pulse')->where('status', 'active')->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$now, $in30])->orderBy('ends_at')->limit(12)->get();

        $recentUsers = User::query()->with(['pulseAccess.plan'])->latest()->limit(8)->get();

        return view('admin.dashboard', [
            'stats' => [
                'Total users' => User::count(),
                'Active accounts' => User::where('status', 'active')->count(),
                'Active Pulse users' => (clone $activePulse)->count(),
                'Trial users' => (clone $activePulse)->whereIn('pulse_plan_id', $trialPlanIds)->count(),
                'Expiring in 7 days' => $expiring7,
                'Expiring in 30 days' => $expiring30,
                'Expired Pulse access' => $expired,
                'Private members' => User::where('role', 'private_member')->whereNotNull('private_member_approved_at')->count(),
                'Membership requests' => PulseMembershipRequest::query()->whereIn('status',['submitted','under_review'])->count(),
            ],
            'planRows' => $planRows,
            'expiryQueue' => $expiryQueue,
            'recentUsers' => $recentUsers,
            'operations' => [
                'Scanner runs today' => PulseScannerRun::query()->whereDate('created_at', today())->count(),
                'Active signals' => PulseSignal::query()->where('status', 'active')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))->count(),
                'Open trades' => PulseTrade::query()->whereIn('status', ['submitting','pending','open','closing','protection_failed'])->count(),
                'Published headlines' => NewsArticle::where('status', 'published')->count(),
                'Member accounts' => PortfolioAccount::count(),
                'Active plans' => PulsePlan::where('is_active', true)->count(),
                'New support enquiries' => ContactMessage::query()->where('status', 'new')->count(),
                'Email failures 24h' => EmailDeliveryLog::query()->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
                'Active mobile devices' => MobileDevice::query()->where('is_active', true)->count(),
            ],
        ]);
    }
}
