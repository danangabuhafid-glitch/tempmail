<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('inbox.index');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials, true)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Email atau password salah.'], 422);
            }
            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        // Akun ber-2FA: password saja belum cukup — tahan sesi, minta kode TOTP dulu
        $user = Auth::user();
        if ($user->hasTwoFactor()) {
            Auth::logout();
            $request->session()->regenerate();
            $request->session()->put('2fa.id', $user->id);

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'redirect' => route('login.2fa')]);
            }

            return redirect()->route('login.2fa');
        }

        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Selamat datang!',
                'redirect' => session()->pull('url.intended', route('inbox.index')),
            ]);
        }

        return redirect()->intended(route('inbox.index'));
    }

    public function showTwoFactor(Request $request)
    {
        abort_unless($request->session()->has('2fa.id'), 403);

        return view('auth.twofactor');
    }

    public function twoFactor(Request $request, TotpService $totp)
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        $userId = $request->session()->get('2fa.id');
        abort_unless($userId, 403);

        $user = User::findOrFail($userId);
        $code = trim($request->code);

        $recovery = collect(json_decode((string) $user->totp_recovery_codes, true) ?: []);
        $recoveryHit = $recovery->search(fn ($h) => hash_equals($h, hash('sha256', strtoupper($code))));

        if (!$totp->verify((string) $user->totp_secret, $code) && $recoveryHit === false) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Kode tidak valid. Coba lagi.'], 422);
            }
            return back()->withErrors(['code' => 'Kode tidak valid. Coba lagi.']);
        }

        // Kode recovery sekali pakai — hanguskan setelah dipakai
        if ($recoveryHit !== false) {
            $user->update(['totp_recovery_codes' => json_encode($recovery->forget($recoveryHit)->values())]);
        }

        $request->session()->forget('2fa.id');
        Auth::loginUsingId($user->id, true);
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Selamat datang!', 'redirect' => route('inbox.index')]);
        }

        return redirect()->route('inbox.index');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'redirect' => route('login')]);
        }

        return redirect()->route('login');
    }
}
