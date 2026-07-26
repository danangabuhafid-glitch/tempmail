<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        // Terapkan tema SEBELUM CSS dirender — mencegah kedipan putih di mode gelap
        (function () {
            const t = localStorage.getItem('tm-theme')
                || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TempMail')</title>
    {{-- PWA: bisa di-install ke layar utama HP --}}
    <link rel="manifest" href="{{ url('manifest.json') }}">
    <meta name="theme-color" content="#241f57">
    <link rel="apple-touch-icon" href="{{ url('icons/icon-192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --tm-bg: #f4f5fb;
            --tm-ink: #1e2235;
            --tm-muted: #7a8099;
            --tm-accent: #6d5df6;
            --tm-accent-soft: #edeafe;
            --tm-grad: linear-gradient(120deg, #241f57 0%, #4c2a91 55%, #6d5df6 120%);
            --tm-radius: 14px;
            --tm-shadow: 0 6px 24px rgba(30, 34, 53, .07);
            --tm-card: #ffffff;
            --tm-border: #e2e4f0;
            --tm-hover: #f8f7ff;
            --tm-soft: #f6f5ff;
            --tm-soft-border: #e4e0fb;
        }
        [data-bs-theme="dark"] {
            --tm-bg: #14162a;
            --tm-ink: #e6e8f5;
            --tm-muted: #9aa0b8;
            --tm-accent-soft: #2a2653;
            --tm-shadow: 0 6px 24px rgba(0, 0, 0, .35);
            --tm-card: #1d2036;
            --tm-border: #2c3048;
            --tm-hover: #232741;
            --tm-soft: #232741;
            --tm-soft-border: #34385a;
        }
        body {
            background: var(--tm-bg);
            color: var(--tm-ink);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }
        .tm-nav {
            background: var(--tm-grad);
            padding: .9rem 0;
        }
        .tm-brand {
            color: #fff; font-weight: 800; letter-spacing: -.02em;
            text-decoration: none; font-size: 1.15rem;
        }
        .tm-brand .bi { color: #c9c1ff; }
        .tm-navlink {
            color: rgba(255,255,255,.75); text-decoration: none; font-weight: 500;
            padding: .45rem .95rem; border-radius: 999px; font-size: .95rem;
            transition: all .15s ease;
        }
        .tm-navlink:hover { color: #fff; background: rgba(255,255,255,.12); }
        .tm-navlink.active { color: #fff; background: rgba(255,255,255,.18); }
        .tm-userchip {
            background: rgba(255,255,255,.14); color: #fff; border-radius: 999px;
            padding: .35rem .5rem .35rem .9rem; font-size: .85rem;
        }
        .card { border: 0; border-radius: var(--tm-radius); box-shadow: var(--tm-shadow); background: var(--tm-card); }
        .btn { border-radius: 10px; font-weight: 600; }
        .btn-tm {
            background: var(--tm-accent); color: #fff; border: 0;
        }
        .btn-tm:hover { background: #5a49e8; color: #fff; }
        .form-control, .form-select { border-radius: 10px; }
        .form-control:focus, .form-select:focus {
            border-color: var(--tm-accent); box-shadow: 0 0 0 .2rem rgba(109, 93, 246, .15);
        }
        .tm-avatar {
            width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: #fff; font-size: 1.05rem;
        }
        .email-item {
            display: flex; gap: .9rem; align-items: center;
            padding: .9rem 1.1rem; text-decoration: none; color: inherit;
            border-bottom: 1px solid var(--tm-border); transition: background .12s ease;
        }
        .email-item:last-child { border-bottom: 0; }
        .email-item:hover { background: var(--tm-hover); }
        .email-item .subject { color: var(--tm-ink); }
        .email-item.unread .subject, .email-item.unread .sender { font-weight: 700; }
        .email-item.read { color: var(--tm-muted); }
        .unread-dot {
            width: 9px; height: 9px; border-radius: 50%; background: var(--tm-accent);
            flex-shrink: 0;
        }
        .badge-soft {
            background: var(--tm-accent-soft); color: var(--tm-accent);
            font-weight: 600; border-radius: 999px; padding: .35em .8em;
        }
        .alias-chip {
            background: var(--tm-card); border: 1px dashed #c9c4ee; color: var(--tm-accent);
            border-radius: 999px; padding: .4rem .95rem; font-size: .85rem; font-weight: 600;
            cursor: pointer; transition: all .15s ease;
        }
        .alias-chip:hover { background: var(--tm-accent-soft); border-style: solid; }
        .mbox-chip {
            display: inline-flex; align-items: center; text-decoration: none;
            background: var(--tm-card); border: 1px solid var(--tm-border); color: var(--tm-muted);
            border-radius: 999px; padding: .35rem .9rem; font-size: .83rem; font-weight: 600;
            transition: all .15s ease; box-shadow: 0 1px 4px rgba(30,34,53,.05);
        }
        .mbox-chip:hover { border-color: var(--tm-accent); color: var(--tm-accent); }
        .mbox-chip.active { background: var(--tm-accent); border-color: var(--tm-accent); color: #fff; }
        .mbox-count {
            background: #ef4444; color: #fff; border-radius: 999px; font-size: .68rem;
            padding: .1rem .45rem; margin-left: .45rem; font-weight: 700;
        }
        .mbox-chip.active .mbox-count { background: rgba(255,255,255,.28); }
        .mbox-badge {
            display: inline-block; border-radius: 999px; padding: .12rem .6rem;
            font-size: .72rem; font-weight: 700; vertical-align: middle;
        }
        .tm-seg { border: 1px solid var(--tm-border); color: var(--tm-muted); font-weight: 600; }
        .btn-check:checked + .tm-seg {
            background: var(--tm-accent); border-color: var(--tm-accent); color: #fff;
        }

        /* ===== Toast premium ===== */
        #tm-toasts {
            position: fixed; top: 1.1rem; right: 1.1rem; z-index: 10800;
            display: flex; flex-direction: column; gap: .65rem; pointer-events: none;
        }
        .tm-toast {
            pointer-events: auto; position: relative; overflow: hidden;
            display: flex; align-items: center; gap: .85rem;
            min-width: 310px; max-width: 400px; padding: .8rem 2.4rem .8rem .8rem;
            border-radius: 16px;
            background: rgba(255, 255, 255, .82);
            backdrop-filter: blur(16px) saturate(1.7);
            -webkit-backdrop-filter: blur(16px) saturate(1.7);
            border: 1px solid rgba(255, 255, 255, .75);
            box-shadow: 0 14px 40px rgba(30, 34, 53, .18), 0 2px 10px rgba(30, 34, 53, .08);
            transform: translateX(120%) scale(.96); opacity: 0;
            transition: transform .55s cubic-bezier(.18, 1.35, .3, 1), opacity .35s ease;
        }
        .tm-toast.show { transform: none; opacity: 1; }
        .tm-toast.hide {
            transform: translateX(40%) scale(.92); opacity: 0;
            transition: transform .3s ease-in, opacity .25s ease-in;
        }
        .tm-toast-icon {
            width: 40px; height: 40px; flex-shrink: 0; border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.15rem;
        }
        .tm-toast-success .tm-toast-icon { background: linear-gradient(135deg, #34d399, #0b9e6d); box-shadow: 0 6px 16px rgba(14, 164, 114, .45); }
        .tm-toast-error   .tm-toast-icon { background: linear-gradient(135deg, #fb7185, #dc2645); box-shadow: 0 6px 16px rgba(220, 38, 69, .45); }
        .tm-toast-warning .tm-toast-icon { background: linear-gradient(135deg, #fbbf24, #d97a06); box-shadow: 0 6px 16px rgba(217, 122, 6, .45); }
        .tm-toast-info    .tm-toast-icon { background: linear-gradient(135deg, #8b7cf8, #5a49e8); box-shadow: 0 6px 16px rgba(109, 93, 246, .45); }
        .tm-toast-body { font-size: .875rem; font-weight: 600; color: var(--tm-ink); line-height: 1.35; }
        .tm-toast-close {
            position: absolute; top: .45rem; right: .45rem; border: 0; background: transparent;
            color: #9aa0b5; font-size: .95rem; line-height: 1; padding: .2rem; border-radius: 8px;
        }
        .tm-toast-close:hover { color: var(--tm-ink); background: rgba(30, 34, 53, .06); }
        .tm-toast-progress {
            position: absolute; left: 0; bottom: 0; height: 3px; width: 100%;
            transform-origin: left; animation: tm-toast-run linear forwards;
            border-radius: 0 999px 999px 0;
        }
        .tm-toast-success .tm-toast-progress { background: linear-gradient(90deg, #34d399, #0b9e6d); }
        .tm-toast-error   .tm-toast-progress { background: linear-gradient(90deg, #fb7185, #dc2645); }
        .tm-toast-warning .tm-toast-progress { background: linear-gradient(90deg, #fbbf24, #d97a06); }
        .tm-toast-info    .tm-toast-progress { background: linear-gradient(90deg, #8b7cf8, #5a49e8); }
        .tm-toast:hover .tm-toast-progress { animation-play-state: paused; }
        @keyframes tm-toast-run { from { transform: scaleX(1); } to { transform: scaleX(0); } }

        /* SweetAlert (dialog konfirmasi & popup massal) diselaraskan dengan tema */
        .swal2-popup { border-radius: 18px !important; font-family: 'Inter', system-ui, sans-serif !important; box-shadow: 0 18px 60px rgba(30,34,53,.22) !important; }
        .swal2-styled { border-radius: 10px !important; font-weight: 600 !important; }
        /* Tinggi menyesuaikan konten via JS; min-height hanya fallback awal */
        .email-frame { width: 100%; min-height: 220px; border: 0; border-radius: 10px; background: #fff; }
        .nav-tabs { border-bottom: 0; gap: .3rem; }
        .nav-tabs .nav-link {
            border: 0; border-radius: 10px 10px 0 0; color: var(--tm-muted); font-weight: 600;
        }
        .nav-tabs .nav-link.active { color: var(--tm-accent); background: var(--tm-card); }
        .tab-content { border-radius: 0 var(--tm-radius) var(--tm-radius) var(--tm-radius); }
        .table td { vertical-align: middle; }
        .pagination { --bs-pagination-active-bg: var(--tm-accent); --bs-pagination-active-border-color: var(--tm-accent); --bs-pagination-color: var(--tm-accent); }
        pre.tm-pre { white-space: pre-wrap; word-break: break-word; margin: 0; font-size: .9rem; }
        .spin { animation: tm-spin .7s linear infinite; display: inline-block; }
        @keyframes tm-spin { to { transform: rotate(360deg); } }
        /* Chip kode OTP — bisa diklik untuk salin */
        .otp-chip {
            display: inline-flex; align-items: center; gap: .35rem;
            background: #eefbf4; border: 1px dashed #34d399; color: #0b9e6d;
            border-radius: 8px; padding: .1rem .55rem; font-weight: 800;
            font-family: ui-monospace, SFMono-Regular, monospace; cursor: pointer;
            letter-spacing: .06em;
        }
        .otp-chip:hover { background: #ddf7ea; }
        [data-bs-theme="dark"] .otp-chip { background: #10321f; border-color: #1f7a4d; color: #4ade80; }
        [data-bs-theme="dark"] .otp-chip:hover { background: #154029; }

        /* Kotak info lembut (dipakai halaman Setup & konfirmasi tautan) */
        .kv { background: var(--tm-soft); border: 1px solid var(--tm-soft-border); border-radius: 10px; padding: .6rem .9rem; }

        /* Toast di mode gelap: kaca gelap, bukan putih */
        [data-bs-theme="dark"] .tm-toast {
            background: rgba(29, 32, 54, .88);
            border-color: rgba(255, 255, 255, .08);
            box-shadow: 0 14px 40px rgba(0, 0, 0, .5), 0 2px 10px rgba(0, 0, 0, .3);
        }

        /* Menu mobile (hamburger) */
        .tm-menu-mobile .tm-navlink { display: block; border-radius: 10px; margin-top: .25rem; }
    </style>
</head>
<body>
@auth
<nav class="tm-nav mb-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <a class="tm-brand d-flex align-items-center gap-2" href="{{ route('inbox.index') }}">
                <i class="bi bi-envelope-paper-fill fs-4"></i> TempMail
            </a>

            {{-- Menu desktop --}}
            <div class="d-none d-md-flex align-items-center gap-1">
                <a href="{{ route('inbox.index') }}" class="tm-navlink {{ request()->routeIs('inbox.*') ? 'active' : '' }}">
                    <i class="bi bi-inbox me-1"></i>Inbox
                </a>
                @if(auth()->user()->isOwner())
                    <a href="{{ route('alias.index') }}" class="tm-navlink {{ request()->routeIs('alias.*') ? 'active' : '' }}">
                        <i class="bi bi-at me-1"></i>Alamat
                    </a>
                    <a href="{{ route('stats.index') }}" class="tm-navlink {{ request()->routeIs('stats.*') ? 'active' : '' }}">
                        <i class="bi bi-bar-chart-line me-1"></i>Statistik
                    </a>
                    <a href="{{ route('setup.index') }}" class="tm-navlink {{ request()->routeIs('setup.*') ? 'active' : '' }}">
                        <i class="bi bi-rocket-takeoff me-1"></i>Setup
                    </a>
                @endif
                <a href="{{ route('settings.index') }}" class="tm-navlink {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <i class="bi bi-gear me-1"></i>Pengaturan
                </a>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" id="theme-toggle" class="btn btn-sm btn-light rounded-pill py-0 px-2" title="Ganti mode terang/gelap">
                    <i class="bi bi-moon-stars"></i>
                </button>
                <div class="tm-userchip d-none d-md-flex align-items-center gap-2">
                    <span>{{ auth()->user()->email }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="d-inline" data-ajax>
                        @csrf
                        <button class="btn btn-sm btn-light rounded-pill py-0 px-2" title="Keluar">
                            <i class="bi bi-box-arrow-right"></i>
                        </button>
                    </form>
                </div>
                {{-- Hamburger (mobile) --}}
                <button class="btn btn-sm text-white d-md-none p-1" type="button" data-bs-toggle="collapse" data-bs-target="#tm-menu" aria-label="Menu">
                    <i class="bi bi-list fs-3"></i>
                </button>
            </div>
        </div>

        {{-- Menu mobile --}}
        <div class="collapse d-md-none tm-menu-mobile pb-2" id="tm-menu">
            <a href="{{ route('inbox.index') }}" class="tm-navlink {{ request()->routeIs('inbox.*') ? 'active' : '' }}">
                <i class="bi bi-inbox me-1"></i>Inbox
            </a>
            @if(auth()->user()->isOwner())
                <a href="{{ route('alias.index') }}" class="tm-navlink {{ request()->routeIs('alias.*') ? 'active' : '' }}">
                    <i class="bi bi-at me-1"></i>Alamat
                </a>
                <a href="{{ route('stats.index') }}" class="tm-navlink {{ request()->routeIs('stats.*') ? 'active' : '' }}">
                    <i class="bi bi-bar-chart-line me-1"></i>Statistik
                </a>
                <a href="{{ route('setup.index') }}" class="tm-navlink {{ request()->routeIs('setup.*') ? 'active' : '' }}">
                    <i class="bi bi-rocket-takeoff me-1"></i>Setup
                </a>
            @endif
            <a href="{{ route('settings.index') }}" class="tm-navlink {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <i class="bi bi-gear me-1"></i>Pengaturan
            </a>
            <div class="d-flex align-items-center justify-content-between mt-2 px-2">
                <span class="small" style="color: rgba(255,255,255,.75)">{{ auth()->user()->email }}</span>
                <form method="POST" action="{{ route('logout') }}" data-ajax>
                    @csrf
                    <button class="btn btn-sm btn-light rounded-pill py-0 px-2" title="Keluar">
                        <i class="bi bi-box-arrow-right me-1"></i>Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
@endauth

<div class="container pb-5">
    @auth
        {{-- Akun alias dengan password bawaan: paksa ganti dulu --}}
        @if(auth()->user()->must_change_password)
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" style="border-radius: 10px;">
                <i class="bi bi-shield-exclamation fs-5"></i>
                <div class="small">Demi keamanan, <strong>ganti password bawaan</strong> dulu lewat form di bawah — fitur lain terkunci sampai password diganti.</div>
            </div>
        @endif

        {{-- Peringatan cron mati: auto-hapus retensi & backup diam-diam tidak jalan --}}
        @if(auth()->user()->isOwner())
            @php
                try {
                    $hbRaw = \App\Models\Setting::getValue('scheduler_last_run');
                    $hbStale = !$hbRaw || \Carbon\Carbon::parse($hbRaw)->lt(now()->subHours(25));
                } catch (\Throwable $e) { $hbStale = false; }

                // Domain yang rusak menurut cek DNS terjadwal terakhir
                try {
                    $domainRusak = collect(app(\App\Services\DomainHealthService::class)->storedHealth())
                        ->filter(fn ($h, $d) => ($h['ok'] ?? true) === false && in_array($d, config('tempmail.domains'), true))
                        ->keys();
                } catch (\Throwable $e) { $domainRusak = collect(); }
            @endphp
            @if($hbStale)
                <div class="alert alert-danger alert-dismissible d-flex align-items-center gap-2 mb-3" style="border-radius: 10px;">
                    <i class="bi bi-alarm fs-5"></i>
                    <div class="small">
                        <strong>Scheduler tidak jalan.</strong> Auto-hapus retensi, backup, dan pemangkas kuota mati.
                        Pasang cron di server: <code>* * * * * php {{ base_path('artisan') }} schedule:run</code>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if($domainRusak->isNotEmpty())
                <div class="alert alert-danger alert-dismissible d-flex align-items-center gap-2 mb-3" style="border-radius: 10px;">
                    <i class="bi bi-globe2 fs-5"></i>
                    <div class="small">
                        <strong>Domain bermasalah:</strong>
                        @foreach($domainRusak as $d)<code>{{ $d }}</code>@if(!$loop->last), @endif @endforeach
                        — DNS tidak lagi mengarah ke Cloudflare, email ke domain itu kemungkinan tidak masuk.
                        <a href="{{ route('setup.index') }}" class="alert-link">Lihat detail di Setup</a>.
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
        @endif
    @endauth

    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // ===== Helper AJAX global =====

    // Toast premium: glass blur + ikon gradien + progress bar (pause saat hover)
    const tmToastIcons = {
        success: 'bi-check-lg',
        error: 'bi-x-lg',
        warning: 'bi-exclamation-triangle-fill',
        info: 'bi-info-lg',
    };

    function tmToast(type, title, duration = 3400) {
        type = tmToastIcons[type] ? type : 'info';

        let wrap = document.getElementById('tm-toasts');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'tm-toasts';
            document.body.appendChild(wrap);
        }

        const t = document.createElement('div');
        t.className = 'tm-toast tm-toast-' + type;
        t.innerHTML = `
            <div class="tm-toast-icon"><i class="bi ${tmToastIcons[type]}"></i></div>
            <div class="tm-toast-body"></div>
            <button type="button" class="tm-toast-close" aria-label="Tutup"><i class="bi bi-x"></i></button>
            <div class="tm-toast-progress" style="animation-duration:${duration}ms"></div>`;
        t.querySelector('.tm-toast-body').textContent = title; // textContent: aman dari HTML injection
        wrap.appendChild(t);

        // Dua frame agar transisi masuk selalu ter-trigger
        requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('show')));

        const tutup = () => {
            if (t.classList.contains('hide')) return;
            t.classList.add('hide');
            setTimeout(() => t.remove(), 320);
        };
        t.querySelector('.tm-toast-close').addEventListener('click', tutup);
        t.querySelector('.tm-toast-progress').addEventListener('animationend', tutup);

        return t;
    }

    // Muat ulang sebagian halaman (partial) tanpa full reload
    async function tmRefresh(selector, url = null) {
        const el = document.querySelector(selector);
        if (!el) return;
        const target = url || el.dataset.refreshUrl || window.location.href;
        const res = await fetch(target, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        // Sesi habis → server me-redirect ke halaman login; jangan suntikkan
        // halaman login ke dalam daftar — pindah halaman beneran.
        if (res.redirected) { location.href = res.url; return; }
        if (res.ok) el.innerHTML = await res.text();
    }

    // Salin ke clipboard dengan fallback utk origin non-HTTPS (mis. akses via IP lokal)
    function tmCopy(text) {
        if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
        return new Promise((resolve, reject) => {
            const ta = document.createElement('textarea');
            ta.value = text; ta.style.cssText = 'position:fixed;opacity:0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy') ? resolve() : reject(new Error('execCommand gagal')); }
            catch (err) { reject(err); }
            finally { ta.remove(); }
        });
    }

    // Semua form ber-atribut data-ajax dikirim via fetch + respon JSON
    document.addEventListener('submit', async function (e) {
        const form = e.target.closest('form[data-ajax]');
        if (!form) return;
        e.preventDefault();

        if (form.dataset.confirm) {
            const c = await Swal.fire({
                title: form.dataset.confirm, icon: 'question',
                showCancelButton: true, confirmButtonText: 'Ya', cancelButtonText: 'Batal',
                confirmButtonColor: '#6d5df6',
            });
            if (!c.isConfirmed) return;
        }

        const btn = form.querySelector('[type="submit"], button:not([type])');
        if (btn) btn.disabled = true;

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok && data.success !== false) {
                // Hasil massal → popup daftar; selain itu cukup toast
                if (data.popup_html) {
                    Swal.fire({
                        title: data.message, html: data.popup_html, icon: 'success',
                        confirmButtonColor: '#6d5df6', confirmButtonText: 'Selesai',
                    });
                } else if (data.message) {
                    tmToast('success', data.message);
                }
                if ('reset' in form.dataset) form.reset();
                // Form di dalam modal → tutup modalnya setelah sukses
                const modalEl = form.closest('.modal');
                if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                if (data.redirect) { setTimeout(() => location.href = data.redirect, 500); return; }
                if (data.reload) { setTimeout(() => location.reload(), 500); return; }
                if (form.dataset.refresh) tmRefresh(form.dataset.refresh);
            } else {
                const msg = data.message || (data.errors ? Object.values(data.errors)[0][0] : 'Terjadi kesalahan.');
                tmToast('error', msg);
            }
        } catch (err) {
            tmToast('error', 'Tidak bisa menghubungi server.');
        } finally {
            if (btn) btn.disabled = false;
        }
    });

    // Waktu relatif berdetak: semua elemen [data-reltime] diperbarui tiap detik
    function tmRelTime(ms) {
        const s = Math.floor((Date.now() - ms) / 1000);
        if (s < 5) return 'baru saja';
        if (s < 60) return s + ' detik lalu';
        const m = Math.floor(s / 60);
        if (m < 60) return m + ' menit ' + (s % 60) + ' dtk lalu';
        const h = Math.floor(m / 60);
        if (h < 24) return h + ' jam ' + (m % 60) + ' mnt lalu';
        const d = Math.floor(h / 24);
        if (d < 30) return d + ' hari lalu';
        const mo = Math.floor(d / 30);
        if (mo < 12) return mo + ' bulan lalu';
        return Math.floor(mo / 12) + ' tahun lalu';
    }
    setInterval(() => {
        document.querySelectorAll('[data-reltime]').forEach(el => {
            el.textContent = tmRelTime(parseInt(el.dataset.reltime));
        });
    }, 1000);

    // Tombol "salin" — dipakai chip alias & chip OTP di beberapa halaman
    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-copy]');
        if (!el) return;
        e.preventDefault(); // chip bisa berada di dalam link — jangan ikut navigasi
        tmCopy(el.dataset.copy.replace(/&#10;/g, '\n')).then(() => {
            const old = el.innerHTML;
            el.innerHTML = '<i class="bi bi-check2"></i> Tersalin!';
            setTimeout(() => el.innerHTML = old, 1300);
        }).catch(() => tmToast('error', 'Tidak bisa menyalin di browser ini.'));
    });

    // Form alias: ganti mode Manual / Prefix / Otomatis → toggle field yang relevan
    document.addEventListener('change', function (e) {
        if (!e.target.matches('.alias-form [name="mode"]')) return;
        const form = e.target.closest('form');
        const mode = e.target.value;

        form.querySelector('.f-manual').classList.toggle('d-none', mode !== 'manual');
        form.querySelector('.f-prefix').classList.toggle('d-none', mode !== 'prefix');
        form.querySelector('.f-qty').classList.toggle('d-none', mode === 'manual');

        // Kosongkan field yang tidak relevan agar tidak ikut tervalidasi
        if (mode !== 'manual') form.querySelector('[name="alias"]').value = '';
        if (mode !== 'prefix') form.querySelector('[name="prefix"]').value = '';

        const qty = form.querySelector('[name="quantity"]');
        if (mode === 'manual') qty.value = '';
        else if (!qty.value) qty.value = 5;

        form.querySelector('.submit-text').textContent = mode === 'manual' ? 'Simpan Alamat' : 'Buat Alamat';
    });

    // Tombol shuffle di form alias (mode manual) → isi alias acak
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.alias-form .btn-shuffle');
        if (!btn) return;
        const input = btn.closest('form').querySelector('[name="alias"]');
        input.value = Math.random().toString(36).slice(2, 10);
        input.focus();
    });

    @if(session('success'))
        tmToast('success', @json(session('success')));
    @endif
    @if(session('warning'))
        tmToast('warning', @json(session('warning')), 6000);
    @endif

    // PWA: daftarkan service worker (aman di-skip di browser lama)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('{{ url('sw.js') }}').catch(() => {});
    }

    // Toggle mode terang/gelap — pilihan diingat di localStorage
    (function () {
        const btn = document.getElementById('theme-toggle');
        if (!btn) return;
        const icon = () => btn.querySelector('i').className =
            'bi ' + (document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'bi-sun' : 'bi-moon-stars');
        icon();
        btn.addEventListener('click', () => {
            const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', next);
            localStorage.setItem('tm-theme', next);
            icon();
        });
    })();
</script>
@yield('scripts')
</body>
</html>
