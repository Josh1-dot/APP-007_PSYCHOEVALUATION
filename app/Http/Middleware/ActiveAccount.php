<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        $request->user()?->refresh();
        if ($request->user() && (! $request->user()->active || ! $request->user()->tenant_id || $request->session()->get('auth_version', $request->user()->auth_version) !== $request->user()->auth_version)) {
            auth()->logout();
            $request->session()->invalidate();
            abort(403, 'Compte inactif.');
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

        return $response;
    }
}
