{{-- Partial hasil pemeriksaan DNS — dimuat via AJAX dari tombol "Periksa Status DNS" --}}
<div class="row g-2 mt-1">
    @foreach($dnsChecks as $domain => $c)
        <div class="col-md-6">
            <div class="kv small">
                <div class="fw-bold mb-1">
                    {{ $domain }}
                    @if($c['ok'])
                        <span class="badge text-bg-success">Siap menerima email</span>
                    @else
                        <span class="badge text-bg-warning">Belum siap</span>
                    @endif
                </div>
                @if($c['error'])
                    <div class="text-danger">{{ $c['error'] }}</div>
                @else
                    <div>NS → Cloudflare: {!! ($c['ns_ok'] ?? false) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>' !!}
                        <span class="text-muted">{{ implode(', ', $c['ns']) ?: 'tidak ditemukan' }}</span></div>
                    <div>MX → Email Routing: {!! ($c['mx_ok'] ?? false) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>' !!}
                        <span class="text-muted">{{ implode(', ', $c['mx']) ?: 'tidak ditemukan' }}</span></div>
                @endif
            </div>
        </div>
    @endforeach
</div>
