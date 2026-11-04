<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
/** Rubriques réservées à l'admin principal : le manager reçoit un refus, comme l'agent. */
class OwnerAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isOwner(), 403);
        return $next($request);
    }
}
