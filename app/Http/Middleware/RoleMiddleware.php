<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use App\Policies\PagePolicy;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $policyMethod  De methode uit je PagePolicy (bijv. 'CanViewGridPage')
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $policyMethod): Response
    {
        // 1. Check of de gebruiker is ingelogd
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // 2. Check of de methode wel bestaat in je PagePolicy
        if (!method_exists(PagePolicy::class, $policyMethod)) {
            abort(500, "De policy methode '{$policyMethod}' bestaat niet in PagePolicy.");
        }

        // 3. Voer de check uit via de Gate
        if (!Gate::allows($policyMethod, PagePolicy::class)) {
            abort(403, 'Je hebt geen rechten om deze pagina te bekijken of deze actie uit te voeren.');
        }

        return $next($request);
    }
}