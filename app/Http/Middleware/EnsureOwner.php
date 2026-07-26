<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi route khusus pemilik — akun alias hanya boleh melihat inbox-nya.
 */
class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->role !== 'owner') {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Fitur ini khusus pemilik.'], 403);
            }
            abort(403, 'Fitur ini khusus pemilik.');
        }

        return $next($request);
    }
}
