{{-- Partial daftar domain — dimuat ulang via AJAX setelah tambah/hapus --}}
@php
    $domains = config('tempmail.domains');
    try { $kesehatan = app(\App\Services\DomainHealthService::class)->storedHealth(); }
    catch (\Throwable $e) { $kesehatan = []; }
@endphp

<ul class="list-group mb-3">
    @forelse($domains as $d)
        @php $sehat = $kesehatan[$d]['ok'] ?? null; @endphp
        <li class="list-group-item d-flex justify-content-between align-items-center py-2" style="border-radius: 10px; margin-bottom: .4rem; border: 1px solid var(--tm-border);">
            <span>
                {{-- Dot status dari cek DNS terjadwal: hijau sehat, merah rusak, abu belum dicek --}}
                <span class="d-inline-block rounded-circle me-2" style="width:9px;height:9px;background: {{ is_null($sehat) ? '#9aa0b5' : ($sehat ? '#0ea472' : '#dc2645') }}"
                      title="{{ is_null($sehat) ? 'Belum dicek scheduler' : ($sehat ? 'DNS sehat' : 'DNS rusak — cek halaman Setup') }}"></span>
                <strong>{{ $d }}</strong>
                <span class="text-muted small ms-1">semua alamat {{ '@' . $d }} dilayani</span>
            </span>
            <form method="POST" action="{{ route('settings.domain.remove') }}" data-ajax data-refresh="#domain-list"
                  data-confirm="Hapus {{ $d }} dari daftar? Email baru untuk domain ini akan ditolak; email lama tetap tersimpan.">
                @csrf @method('DELETE')
                <input type="hidden" name="domain" value="{{ $d }}">
                <button class="btn btn-sm btn-outline-danger" {{ count($domains) <= 1 ? 'disabled title=Minimal-satu-domain' : '' }}>
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        </li>
    @empty
        <li class="list-group-item text-muted small">Belum ada domain.</li>
    @endforelse
</ul>

<form method="POST" action="{{ route('settings.domain.add') }}" data-ajax data-reset data-refresh="#domain-list">
    @csrf
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-plus-lg"></i></span>
        <input type="text" name="domain" class="form-control" placeholder="mis. domainbaru.my.id" required>
        <button class="btn btn-tm" type="submit">Tambah Domain</button>
    </div>
    <div class="form-text">Setelah ditambah, jalankan langkah Setup (Cloudflare) untuk domain barunya agar emailnya benar-benar mengalir.</div>
</form>
