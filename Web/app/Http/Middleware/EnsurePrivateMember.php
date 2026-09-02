<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrivateMember
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isPrivateMember()) {
            abort(403, 'Private Member access is invitation-only and must be activated by Alpha Block Solutions.');
        }

        return $next($request);
    }
}
