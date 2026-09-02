<?php

namespace App\Http\Middleware;

use App\Services\PulseAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePulseCapability
{
    public function __construct(private readonly PulseAccessService $access) {}

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $user = $request->user();

        if (! $user || ! $this->access->allows($user, $capability, false)) {
            $message = 'This Pulse feature is not included in your current plan.';

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => $message,
                    'code' => 'PULSE_PLAN_FEATURE_DISABLED',
                    'capability' => $capability,
                ], 403);
            }

            return redirect()->route('pulse.dashboard')->with('warning', $message);
        }

        return $next($request);
    }
}
