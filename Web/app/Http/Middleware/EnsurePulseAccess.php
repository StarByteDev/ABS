<?php

namespace App\Http\Middleware;

use App\Services\PulseAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePulseAccess
{
    public function __construct(private readonly PulseAccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isAdmin() && ! $user->hasPulseAccess())) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Pulse access is not active for this account.',
                    'code' => 'PULSE_ACCESS_REQUIRED',
                ], 403);
            }

            return redirect()->route('pulse.access')
                ->withErrors(['pulse' => 'Pulse access is not currently active for this account.']);
        }

        if ($request->is('api/*') && ! $this->access->allows($user, 'mobile_api', false)) {
            return response()->json([
                'message' => 'Pulse mobile API access is disabled for the current plan or account.',
                'code' => 'PULSE_MOBILE_API_DISABLED',
            ], 403);
        }

        return $next($request);
    }
}
