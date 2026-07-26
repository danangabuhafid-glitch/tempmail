@extends('layouts.app')

@section('title', 'Verifikasi 2FA — TempMail')

@section('content')
<style>
    body { background: var(--tm-grad); }
    .login-card { max-width: 400px; margin: 10vh auto 0; }
    .login-icon {
        width: 64px; height: 64px; border-radius: 18px; margin: 0 auto 1rem;
        background: var(--tm-accent-soft); color: var(--tm-accent);
        display: flex; align-items: center; justify-content: center; font-size: 1.8rem;
    }
</style>

<div class="login-card">
    <div class="card">
        <div class="card-body p-4 p-md-5">
            <div class="login-icon"><i class="bi bi-phone-vibrate"></i></div>
            <h4 class="text-center fw-bold mb-1">Verifikasi Dua Faktor</h4>
            <p class="text-center text-muted small mb-4">Masukkan 6 digit kode dari aplikasi authenticator, atau salah satu kode recovery.</p>

            @error('code')
                <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('login.2fa') }}" data-ajax>
                @csrf
                <div class="mb-4">
                    <label class="form-label small fw-semibold">Kode</label>
                    <input type="text" name="code" class="form-control form-control-lg fs-5 text-center" inputmode="numeric"
                           autocomplete="one-time-code" placeholder="••••••" maxlength="20" required autofocus>
                </div>
                <button class="btn btn-tm w-100 py-2"><i class="bi bi-shield-check me-1"></i>Verifikasi</button>
            </form>
        </div>
    </div>
    <p class="text-center mt-3" style="color: rgba(255,255,255,.6); font-size:.8rem;">
        <a href="{{ route('login') }}" class="text-decoration-none" style="color: rgba(255,255,255,.8)">Kembali ke login</a>
    </p>
</div>
@endsection
