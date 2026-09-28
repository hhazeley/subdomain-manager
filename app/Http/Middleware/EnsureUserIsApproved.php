<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isApproved()) {
            return redirect()->route('approval.notice');
        }

        return $next($request);
    }
}
