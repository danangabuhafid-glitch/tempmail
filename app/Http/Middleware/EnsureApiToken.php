<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi API via token statis (settings api_token) — untuk skrip/bot,
 * tanpa sesi browser. Kirim sebagai "Authorization: Bearer <token>"
 * atau header "X-Api-Token".
 */
class EnsureApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('tempmail.api_token');
        $given = (string) ($request->bearerToken() ?: $request->header('X-Api-Token', ''));

        if ($token === '' || $given === '' || !hash_equals($token, $given)) {
            return response()->json(['message' => 'Token API tidak valid. Buat/salin token di halaman Pengaturan.'], 401);
        }

        return $next($request);
    }
}
