@extends('layouts.app')

@section('title', 'Setup — TempMail')

@section('content')
<style>
    .step-num {
        width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
        background: var(--tm-accent-soft); color: var(--tm-accent);
        display: flex; align-items: center; justify-content: center; font-weight: 800;
    }
    .copy-block {
        position: relative; background: #1e2235; color: #dfe3f5; border-radius: 10px;
        padding: 1rem; font-size: .8rem; overflow-x: auto; white-space: pre; font-family: ui-monospace, monospace;
    }
    .copy-block .btn-copy { position: absolute; top: .5rem; right: .5rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-rocket-takeoff me-1" style="color: var(--tm-accent)"></i>Setup Penerimaan Email</h4>
    @if($lastEmail)
        <span class="badge-soft"><i class="bi bi-check-circle me-1"></i>Email terakhir diterima {{ $lastEmail->received_at->diffForHumans() }}</span>
    @else
        <span class="badge text-bg-warning">Belum pernah menerima email</span>
    @endif
</div>

{{-- ===== Dashboard kesehatan: semua sinyal pipeline dalam satu layar ===== --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body py-3 text-center">
            <div class="fs-4 fw-bold">{{ number_format($health['total_emails']) }}</div>
            <div class="small text-muted">email tersimpan · {{ $health['total_size_mb'] }} MB{{ $health['max_storage_mb'] > 0 ? ' / ' . $health['max_storage_mb'] . ' MB' : '' }}</div>
            @if($health['max_storage_mb'] > 0)
                @php $persen = min(100, (int) round($health['total_size_mb'] / max(1, $health['max_storage_mb']) * 100)); @endphp
                <div class="progress mt-2" style="height: 5px;"><div class="progress-bar {{ $persen > 85 ? 'bg-danger' : '' }}" style="width: {{ $persen }}%; background-color: var(--tm-accent);"></div></div>
            @endif
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body py-3 text-center">
            <div class="fs-4"><i class="bi {{ $health['heartbeat_ok'] ? 'bi-heart-pulse text-success' : 'bi-heartbreak text-danger' }}"></i></div>
            <div class="small fw-semibold">Scheduler (cron)</div>
            <div class="small text-muted">{{ $health['heartbeat'] ? 'aktif ' . $health['heartbeat']->diffForHumans() : 'belum pernah jalan' }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body py-3 text-center">
            <div class="fs-4 fw-bold {{ $health['webhook_errors_24h'] > 0 ? 'text-danger' : '' }}">{{ $health['webhook_errors_24h'] }}</div>
            <div class="small fw-semibold">error webhook 24 jam</div>
            <div class="small text-muted">{{ $health['webhook_ok_24h'] }} sukses diterima</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body py-3 text-center">
            <div class="fs-4"><i class="bi {{ $health['backup'] ? 'bi-safe text-success' : 'bi-safe text-warning' }}"></i></div>
            <div class="small fw-semibold">Backup</div>
            <div class="small text-muted">{{ $health['backup'] ? 'terakhir ' . $health['backup']->diffForHumans() : 'belum pernah' }}</div>
        </div></div>
    </div>
</div>

{{-- Kesehatan per-domain: dengan 2+ domain, satu bisa rusak sendirian --}}
<div class="card mb-3">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-2"><i class="bi bi-activity me-1" style="color: var(--tm-accent)"></i>Kesehatan per Domain</h6>
        <p class="small text-muted mb-2">DNS tiap domain dicek otomatis tiap jam oleh scheduler. Saat ada domain rusak atau pulih, notifikasi Telegram/ntfy dikirim (atur di Pengaturan) dan banner peringatan muncul di aplikasi.</p>
        <div class="table-responsive">
            <table class="table table-sm small mb-0 align-middle">
                <thead><tr class="text-muted"><th>Domain</th><th>DNS (cek terjadwal)</th><th>Email terakhir</th><th>24 jam</th></tr></thead>
                <tbody>
                @foreach($health['per_domain'] as $domain => $d)
                    <tr>
                        <td class="fw-semibold">{{ $domain }}</td>
                        <td>
                            @if(is_null($d['dns']))
                                <span class="badge rounded-pill text-bg-secondary">belum dicek</span>
                                <span class="text-muted">(scheduler belum jalan)</span>
                            @elseif($d['dns']['ok'])
                                <span class="badge rounded-pill text-bg-success"><i class="bi bi-check-lg me-1"></i>sehat</span>
                            @else
                                <span class="badge rounded-pill text-bg-danger"><i class="bi bi-x-lg me-1"></i>rusak</span>
                                <span class="text-danger">
                                    {{ !$d['dns']['ns_ok'] ? 'NS bukan Cloudflare' : '' }}{{ !$d['dns']['ns_ok'] && !$d['dns']['mx_ok'] ? ' · ' : '' }}{{ !$d['dns']['mx_ok'] ? 'MX Email Routing hilang' : '' }}
                                </span>
                            @endif
                            @if(!is_null($d['dns']) && !empty($d['dns']['checked_at']))
                                <div class="text-muted" style="font-size:.72rem">dicek {{ \Carbon\Carbon::parse($d['dns']['checked_at'])->diffForHumans() }}@if(!empty($d['dns']['lookup_error'])) · lookup gagal, status lama dipertahankan @endif</div>
                            @endif
                        </td>
                        <td>{{ $d['last_email_at'] ? \Carbon\Carbon::parse($d['last_email_at'])->diffForHumans() : 'belum pernah' }}</td>
                        <td>
                            {{ $d['emails_24h'] }} email
                            @if($d['errors_24h'] > 0)
                                · <span class="text-danger fw-semibold">{{ $d['errors_24h'] }} error</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($health['recent_logs']->isNotEmpty())
<div class="card mb-3">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-2"><i class="bi bi-journal-text me-1" style="color: var(--tm-accent)"></i>Log Webhook Terakhir</h6>
        <p class="small text-muted mb-2">Termasuk request yang DITOLAK — di sinilah email "hilang" bisa didiagnosis (token salah setelah regenerate, domain belum terdaftar, email kebesaran).</p>
        <div class="table-responsive">
            <table class="table table-sm small mb-0">
                <thead><tr class="text-muted"><th>Waktu</th><th>Status</th><th>Kepada</th><th>Dari</th><th>Ukuran</th><th>Keterangan</th></tr></thead>
                <tbody>
                @foreach($health['recent_logs'] as $log)
                    <tr>
                        <td class="text-nowrap">{{ $log->created_at->format('d/m H:i:s') }}</td>
                        <td><span class="badge rounded-pill {{ $log->status < 400 ? 'text-bg-success' : 'text-bg-danger' }}">{{ $log->status }}</span></td>
                        <td class="text-truncate" style="max-width: 160px;">{{ $log->envelope_to ?: '—' }}</td>
                        <td class="text-truncate" style="max-width: 160px;">{{ $log->from_address ?: '—' }}</td>
                        <td class="text-nowrap">{{ number_format($log->size_bytes / 1024, 0) }} KB</td>
                        <td class="text-muted">{{ $log->reason ?: 'OK' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- LANGKAH 1: DNS --}}
<div class="card mb-3">
    <div class="card-body p-4">
        <div class="d-flex gap-3">
            <div class="step-num">1</div>
            <div class="flex-grow-1">
                <h6 class="fw-bold mb-1">Arahkan domain ke Cloudflare</h6>
                <p class="small text-muted mb-2">
                    Daftar gratis di <strong>dash.cloudflare.com</strong> → <em>Add a domain</em> → ikuti instruksi ganti
                    <em>nameserver</em> di registrar tempat domain dibeli. Lalu di menu <strong>Email → Email Routing</strong> klik <strong>Enable</strong>
                    (record MX dipasang otomatis). Lakukan untuk setiap domain.
                </p>

                <button type="button" id="btn-dns" class="btn btn-sm btn-tm mb-2">
                    <i class="bi bi-search me-1"></i>Periksa Status DNS Sekarang
                </button>

                <div id="dns-results">
                    @if(!is_null($dnsChecks))
                        @include('setup._dns')
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- LANGKAH 2: WORKER --}}
<div class="card mb-3">
    <div class="card-body p-4">
        <div class="d-flex gap-3">
            <div class="step-num">2</div>
            <div class="flex-grow-1" style="min-width: 0;">
                <h6 class="fw-bold mb-1">Buat Email Worker di Cloudflare</h6>
                <p class="small text-muted mb-2">
                    Menu <strong>Workers &amp; Pages → Create Worker</strong> (nama bebas, mis. <code>tempmail-forwarder</code>) →
                    tempel kode di bawah → <em>Deploy</em>. Lalu di <strong>Settings → Variables and Secrets</strong> worker itu, isi 2 variabel berikut:
                </p>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <div class="kv small">
                            <div class="text-muted">WEBHOOK_URL</div>
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <code class="text-truncate">{{ $webhookUrl }}</code>
                                <button class="btn btn-sm btn-light border flex-shrink-0" data-copy="{{ $webhookUrl }}" type="button"><i class="bi bi-clipboard"></i></button>
                            </div>
                            @if(str_contains($webhookUrl, 'localhost') || str_contains($webhookUrl, '127.0.0.1'))
                                <div class="text-warning-emphasis small mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Ini alamat lokal — Cloudflare tidak bisa menjangkaunya. Saat produksi, buka halaman ini dari domain VPS agar URL-nya benar, atau pakai tunnel <code>cloudflared</code> untuk uji coba.</div>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="kv small">
                            <div class="text-muted">WEBHOOK_TOKEN <span class="text-muted">(set sebagai Secret)</span></div>
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <code class="text-truncate">{{ substr($token, 0, 10) }}••••••••</code>
                                <div class="d-flex gap-1 flex-shrink-0">
                                    <button class="btn btn-sm btn-light border" data-copy="{{ $token }}" type="button" title="Salin token penuh"><i class="bi bi-clipboard"></i></button>
                                    <form method="POST" action="{{ route('setup.token') }}" data-ajax
                                          data-confirm="Buat token baru? Worker Cloudflare harus ikut diperbarui.">
                                        @csrf
                                        <button class="btn btn-sm btn-light border" title="Buat token baru"><i class="bi bi-arrow-repeat"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="copy-block">
                    <button class="btn btn-sm btn-light btn-copy" data-copy="{{ $workerScript }}" type="button"><i class="bi bi-clipboard me-1"></i>Salin kode</button>{{ $workerScript }}</div>
            </div>
        </div>
    </div>
</div>

{{-- LANGKAH 3: CATCH-ALL --}}
<div class="card mb-3">
    <div class="card-body p-4">
        <div class="d-flex gap-3">
            <div class="step-num">3</div>
            <div class="flex-grow-1">
                <h6 class="fw-bold mb-1">Arahkan catch-all ke worker</h6>
                <p class="small text-muted mb-0">
                    Di tiap domain: <strong>Email → Email Routing → Routing rules</strong> → bagian <strong>Catch-all address</strong> →
                    Action: <strong>Send to a Worker</strong> → pilih worker tadi → Save.
                    Sejak ini, email ke alamat <em>apa pun</em> di
                    @foreach($domains as $d)<code>{{ '@' . $d }}</code>@if(!$loop->last), @endif @endforeach
                    akan mampir ke aplikasi ini.
                </p>
            </div>
        </div>
    </div>
</div>

{{-- LANGKAH 4: UJI --}}
<div class="card mb-3">
    <div class="card-body p-4">
        <div class="d-flex gap-3 align-items-center">
            <div class="step-num">4</div>
            <div class="flex-grow-1">
                <h6 class="fw-bold mb-1">Uji coba</h6>
                <p class="small text-muted mb-0">
                    Tombol di samping membuat email contoh lewat jalur yang sama dengan webhook — membuktikan parser &amp; penyimpanan bekerja.
                    Untuk uji ujung-ke-ujung, kirim email sungguhan dari Gmail ke alamat apa pun di domain kamu.
                </p>
            </div>
            <form method="POST" action="{{ route('setup.test') }}" data-ajax>
                @csrf
                <button class="btn btn-tm"><i class="bi bi-send me-1"></i>Buat Email Uji</button>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Periksa DNS via AJAX — hasil dimuat ke #dns-results tanpa reload
    document.getElementById('btn-dns').addEventListener('click', async function () {
        const icon = this.querySelector('i');
        icon.className = 'bi bi-arrow-repeat me-1 spin';
        this.disabled = true;
        try {
            const res = await fetch('{{ route('setup.index') }}?check=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) {
                document.getElementById('dns-results').innerHTML = await res.text();
            } else {
                tmToast('error', 'Gagal memeriksa DNS.');
            }
        } catch (e) {
            tmToast('error', 'Tidak bisa menghubungi server.');
        } finally {
            icon.className = 'bi bi-search me-1';
            this.disabled = false;
        }
    });
</script>
@endsection
