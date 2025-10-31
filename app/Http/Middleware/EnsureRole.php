<?php

namespace App\Http\Middleware;

use Closure;
use BackedEnum;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (empty($roles)) {
            return $next($request);
        }

        $userRole = $user->role instanceof BackedEnum ? $user->role->value : $user->role;

        $allowedRoles = array_map(
            static fn ($role) => $role instanceof BackedEnum ? $role->value : $role,
            $roles
        );

        if (! in_array($userRole, $allowedRoles, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Brak uprawnień do wyświetlenia tej strony.');
        }

        return $next($request);
    }
}
