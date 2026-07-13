<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneIsSet
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user || $user->super_admin) {
            return $next($request);
        }

        if ($request->routeIs('dashboard.profile.complete-phone', 'dashboard.profile.complete-phone.store', 'logout')) {
            return $next($request);
        }

        $person = $user->person;

        if ($person && empty($person->phone) && empty($user->phone)) {
            return redirect()->route('dashboard.profile.complete-phone');
        }

        return $next($request);
    }
}
