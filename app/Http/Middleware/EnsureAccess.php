<?php

namespace App\Http\Middleware;

use App\Support\AccessControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccess
{
    public function handle(Request $request, Closure $next, string $key, string $action = 'view'): Response
    {
        $user = $request->user();

        if (!$user || !AccessControl::has($user, $key, $action)) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
