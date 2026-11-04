<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class StaffAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()?->active || !array_key_exists((string) $request->user()->role, \App\Models\User::ROLES)) {
            Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
            return redirect()->route('login');
        }
        if ($request->user()->must_change_password && !$request->routeIs('staff.password*', 'staff.logout')) return redirect()->route('staff.password');
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        return $response;
    }
}
