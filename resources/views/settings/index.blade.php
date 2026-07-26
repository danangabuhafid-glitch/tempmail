@extends('layouts.app')

@section('title', 'Pengaturan — TempMail')

@section('content')
<div class="row justify-content-center g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-shield-lock me-1" style="color: var(--tm-accent)"></i>Ganti Password Login</h5>
                <p class="text-muted small mb-4">Akun: <strong>{{ auth()->user()->email }}</strong></p>

                <form method="POST" action="{{ route('settings.password') }}" data-ajax data-reset>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password saat ini</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password baru</label>
                        <input type="password" name="password" class="form-control" minlength="8" required>
                        <div class="form-text">Minimal 8 karakter.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Ulangi password baru</label>
                        <input type="password" name="password_confirmation" class="form-control" minlength="8" required>
                    </div>
                    <button class="btn btn-tm w-100"><i class="bi bi-check-lg me-1"></i>Simpan Password Baru</button>
                </form>
            </div>
        </div>

        @if(auth()->user()->isOwner())
        {{-- ===== 2FA TOTP ===== --}}
        <div class="card mt-3">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-phone-vibrate me-1" style="color: var(--tm-accent)"></i>Autentikasi Dua Faktor (2FA)</h5>
                @if(auth()->user()->hasTwoFactor())
                    <p class="text-muted small mb-3">2FA <span class="badge text-bg-success">aktif</span> — login butuh kode dari aplikasi authenticator.</p>
                    <form method="POST" action="{{ route('settings.2fa.disable') }}" data-ajax data-confirm="Matikan 2FA? Login kembali hanya butuh password.">
                        @csrf
                        <div class="input-group">
                            <input type="password" name="current_password" class="form-control" placeholder="Password kamu" required>
                            <button class="btn btn-outline-danger">Matikan 2FA</button>
                        </div>
                    </form>
                @else
                    <p class="text-muted small mb-3">Panel ini terbuka dari internet — lapisi login dengan kode dari Google Authenticator / Aegis / 1Password.</p>
                    <button type="button" id="btn-2fa-setup" class="btn btn-tm"><i class="bi bi-qr-code me-1"></i>Aktifkan 2FA</button>

                    <div id="tfa-steps" class="d-none mt-3">
                        <ol class="small text-muted ps-3 mb-2">
                            <li>Scan QR ini dengan aplikasi authenticator</li>
                            <li>Masukkan 6 digit kode yang muncul</li>
                        </ol>
                        <div class="text-center mb-2"><div id="tfa-qr" class="d-inline-block p-2 bg-white rounded"></div></div>
                        <div class="small text-muted text-center mb-2">atau masukkan manual: <code id="tfa-secret"></code></div>
                        <form method="POST" action="{{ route('settings.2fa.confirm') }}" data-ajax>
                            @csrf
                            <div class="input-group">
                                <input type="text" name="code" class="form-control" placeholder="123456" inputmode="numeric" maxlength="6" required>
                                <button class="btn btn-tm">Verifikasi &amp; Aktifkan</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    @if(auth()->user()->isOwner())
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-globe2 me-1" style="color: var(--tm-accent)"></i>Domain yang Dilayani</h5>
                <p class="text-muted small mb-3">Kelola daftar domain penerima. Perubahan langsung aktif tanpa menyentuh file <code>.env</code>.</p>

                <div id="domain-list" data-refresh-url="{{ route('settings.index') }}">
                    @include('settings._domains')
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-hourglass-split me-1" style="color: var(--tm-accent)"></i>Masa Simpan &amp; Kuota</h5>
                <p class="text-muted small mb-3">Email lebih tua dari masa simpan dihapus otomatis. Bila kuota terlampaui, email tertua dipangkas duluan (email "simpan permanen" aman).</p>

                <form method="POST" action="{{ route('settings.general') }}" data-ajax>
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1">Masa simpan</label>
                        <div class="input-group">
                            <input type="number" name="retention_days" class="form-control" min="1" max="3650" value="{{ old('retention_days', config('tempmail.retention_days')) }}" required>
                            <span class="input-group-text">hari</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1">Kuota penyimpanan (0 = tanpa batas)</label>
                        <div class="input-group">
                            <input type="number" name="max_storage_mb" class="form-control" min="0" max="1000000" value="{{ old('max_storage_mb', config('tempmail.max_storage_mb', 0)) }}">
                            <span class="input-group-text">MB</span>
                        </div>
                    </div>
                    <button class="btn btn-tm w-100" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                </form>
            </div>
        </div>

        {{-- ===== Notifikasi push ===== --}}
        <div class="card mt-3">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-bell me-1" style="color: var(--tm-accent)"></i>Notifikasi Email Baru</h5>
                <p class="text-muted small mb-3">Kirim ringkasan (plus kode OTP bila terdeteksi) ke HP lewat bot Telegram dan/atau <a href="https://ntfy.sh" target="_blank" rel="noopener">ntfy.sh</a>. Kosongkan untuk mematikan.</p>

                <form method="POST" action="{{ route('settings.notify') }}" data-ajax>
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1">Telegram bot token</label>
                        <input type="text" name="telegram_token" class="form-control form-control-sm" value="{{ config('tempmail.notify_telegram_token') }}" placeholder="123456:ABC-DEF...">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1">Telegram chat ID</label>
                        <input type="text" name="telegram_chat_id" class="form-control form-control-sm" value="{{ config('tempmail.notify_telegram_chat_id') }}" placeholder="mis. 12345678">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1">URL topik ntfy</label>
                        <input type="url" name="ntfy_url" class="form-control form-control-sm" value="{{ config('tempmail.notify_ntfy_url') }}" placeholder="https://ntfy.sh/tempmail-rahasiaku">
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-tm flex-grow-1" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('settings.notify.test') }}" data-ajax class="mt-2">
                    @csrf
                    <button class="btn btn-light border w-100" type="submit"><i class="bi bi-send me-1"></i>Kirim Notifikasi Uji</button>
                </form>
            </div>
        </div>

        {{-- ===== API & Bookmarklet ===== --}}
        <div class="card mt-3">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-code-square me-1" style="color: var(--tm-accent)"></i>API &amp; Bookmarklet</h5>
                <p class="text-muted small mb-3">Untuk skrip/bot: baca email &amp; OTP lewat HTTP tanpa buka browser. Token = kunci penuh baca inbox — jaga seperti password.</p>

                @php $apiToken = (string) config('tempmail.api_token'); @endphp
                <div class="kv small mb-2">
                    <div class="text-muted">Token API (Bearer)</div>
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <code class="text-truncate">{{ $apiToken ? substr($apiToken, 0, 10) . '••••••••' : 'belum dibuat — API nonaktif' }}</code>
                        <div class="d-flex gap-1 flex-shrink-0">
                            @if($apiToken)
                                <button class="btn btn-sm btn-light border" data-copy="{{ $apiToken }}" type="button" title="Salin token penuh"><i class="bi bi-clipboard"></i></button>
                            @endif
                            <form method="POST" action="{{ route('settings.api.token') }}" data-ajax
                                  @if($apiToken) data-confirm="Buat token baru? Skrip lama yang memakai token sekarang akan berhenti bekerja." @endif>
                                @csrf
                                <button class="btn btn-sm btn-light border" title="{{ $apiToken ? 'Buat token baru' : 'Aktifkan API' }}">
                                    <i class="bi {{ $apiToken ? 'bi-arrow-repeat' : 'bi-power' }}"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="small text-muted mb-3">
                    Contoh: <code>curl -H "Authorization: Bearer TOKEN" {{ url('/api/otp') }}?alias=belanja</code><br>
                    Endpoint: <code>/api/emails</code> · <code>/api/emails/{id}</code> · <code>/api/otp</code> · <code>/api/alias/quick?site=…</code>
                </div>

                <hr>
                <h6 class="fw-bold small mb-1">Bookmarklet "Alias Cepat"</h6>
                <p class="text-muted small mb-2">Seret tombol ini ke bookmark bar. Saat mengisi form pendaftaran di situs mana pun, klik sekali — alias bernama situsnya langsung dibuat &amp; tersalin ke clipboard.</p>
                <a class="btn btn-sm btn-tm"
                   href="javascript:window.open('{{ url('/alamat/cepat') }}?site='+encodeURIComponent(location.hostname),'tmquick','width=460,height=420');"
                   onclick="tmToast('info', 'Jangan diklik di sini — seret ke bookmark bar browser.'); return false;">
                    <i class="bi bi-magic me-1"></i>Alias TempMail
                </a>
            </div>
        </div>

        {{-- ===== Blokir pengirim ===== --}}
        <div class="card mt-3">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-slash-circle me-1" style="color: var(--tm-accent)"></i>Pengirim Diblokir</h5>
                <p class="text-muted small mb-3">Email dari alamat/domain ini langsung dibuang saat diterima. Bisa juga lewat tombol "Blokir pengirim" di halaman email.</p>

                <div id="blocked-list" data-refresh-url="{{ route('settings.index', ['partial' => 'blocked']) }}">
                    @include('settings._blocked')
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@section('scripts')
@if(auth()->user()->isOwner() && !auth()->user()->hasTwoFactor())
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    // Mulai setup 2FA: minta secret ke server → tampilkan QR + form verifikasi
    document.getElementById('btn-2fa-setup')?.addEventListener('click', async function () {
        this.disabled = true;
        try {
            const res = await fetch('{{ route('settings.2fa.setup') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            const data = await res.json();
            if (!res.ok || !data.success) { tmToast('error', data.message || 'Gagal memulai setup 2FA.'); return; }

            document.getElementById('tfa-secret').textContent = data.secret;
            document.getElementById('tfa-qr').innerHTML = '';
            new QRCode(document.getElementById('tfa-qr'), { text: data.otpauth, width: 170, height: 170 });
            document.getElementById('tfa-steps').classList.remove('d-none');
            this.classList.add('d-none');
        } catch (e) {
            tmToast('error', 'Tidak bisa menghubungi server.');
        } finally {
            this.disabled = false;
        }
    });

    // Setelah konfirmasi sukses: tampilkan kode recovery SEKALI, lalu reload
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form[action="{{ route('settings.2fa.confirm') }}"]');
        if (!form) return;
        // Tunggu respons data-ajax global lalu cek recovery codes lewat fetch ulang?
        // Tidak — tangani sendiri di sini agar bisa menampilkan kode recovery.
        e.stopImmediatePropagation();
        e.preventDefault();
        (async () => {
            try {
                const res = await fetch(form.action, {
                    method: 'POST', body: new FormData(form),
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    const daftar = (data.recovery_codes || []).map(c => `<div><code>${c}</code></div>`).join('');
                    await Swal.fire({
                        title: '2FA aktif!', icon: 'success', confirmButtonColor: '#6d5df6',
                        html: `<p class="small">Simpan kode recovery ini — satu-satunya jalan masuk kalau HP hilang. Masing-masing sekali pakai.</p><div style="text-align:center">${daftar}</div>`,
                    });
                    location.reload();
                } else {
                    tmToast('error', data.message || 'Kode tidak cocok.');
                }
            } catch (err) {
                tmToast('error', 'Tidak bisa menghubungi server.');
            }
        })();
    }, true);
</script>
@endif
@endsection
