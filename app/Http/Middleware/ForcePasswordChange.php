<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun alias yang dibuat massal wajib mengganti password bawaannya dulu
 * sebelum bisa memakai fitur lain.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password
            && !$request->routeIs('settings.*', 'logout')) {
            if ($request->expectsJson() && !$request->ajax()) {
                return response()->json(['message' => 'Wajib ganti password dulu.'], 423);
            }

            return redirect()->route('settings.index')
                ->with('warning', 'Demi keamanan, ganti password bawaan dulu sebelum memakai inbox.');
        }

        return $next($request);
    }
}
