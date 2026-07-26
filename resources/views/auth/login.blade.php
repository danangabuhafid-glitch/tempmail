@extends('layouts.app')

@section('title', 'Masuk — TempMail')

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
            <div class="login-icon"><i class="bi bi-envelope-paper-fill"></i></div>
            <h4 class="text-center fw-bold mb-1">TempMail Pribadi</h4>
            <p class="text-center text-muted small mb-4">
                Inbox catch-all
                @forelse(config('tempmail.domains') as $d)<strong>{{ '@' . $d }}</strong>@if(!$loop->last) &amp; @endif @empty semua domainmu @endforelse
            </p>

            @error('email')
                <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('login') }}" data-ajax>
                @csrf
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg fs-6" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-semibold">Password</label>
                    <input type="password" name="password" class="form-control form-control-lg fs-6" required>
                </div>
                <button class="btn btn-tm w-100 py-2"><i class="bi bi-box-arrow-in-right me-1"></i>Masuk</button>
            </form>
        </div>
    </div>
    <p class="text-center mt-3" style="color: rgba(255,255,255,.6); font-size:.8rem;">Privat — hanya untuk pemilik domain.</p>
</div>
@endsection
