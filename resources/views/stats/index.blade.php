@extends('layouts.app')

@section('title', 'Statistik — TempMail')

@section('content')
@php
    $maksHari = max(1, max($perHari));
    $maksJam = max(1, max($perJam));
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart-line me-1" style="color: var(--tm-accent)"></i>Statistik</h4>
    <div class="btn-group btn-group-sm">
        @foreach([7 => '7 hari', 30 => '30 hari', 90 => '90 hari', 365 => '1 tahun'] as $h => $label)
            <a href="{{ route('stats.index', ['hari' => $h]) }}" class="btn {{ $hari === $h ? 'btn-tm' : 'btn-light border' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>

{{-- Ringkasan angka --}}
<div class="row g-2 mb-3">
    @foreach([
        ['Email masuk', number_format($total), 'bi-envelope', null],
        ['Rata-rata / hari', $rataPerHari, 'bi-graph-up', null],
        ['Kode OTP terdeteksi', number_format($totalOtp), 'bi-123', 'text-success'],
        ['Spam disaring', number_format($totalSpam), 'bi-exclamation-octagon', $totalSpam > 0 ? 'text-danger' : null],
        ['Total ukuran', $totalUkuranMb . ' MB', 'bi-hdd', null],
    ] as [$label, $nilai, $ikon, $warna])
        <div class="col-6 col-md">
            <div class="card h-100"><div class="card-body py-3 text-center">
                <div class="fs-4 fw-bold {{ $warna }}"><i class="bi {{ $ikon }} me-1 opacity-50"></i>{{ $nilai }}</div>
                <div class="small text-muted">{{ $label }}</div>
            </div></div>
        </div>
    @endforeach
</div>

{{-- Tren harian: bar chart CSS murni (tanpa library eksternal) --}}
<div class="card mb-3">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3">Email per hari</h6>
        @if($total === 0)
            <p class="text-muted small mb-0">Belum ada email dalam rentang ini.</p>
        @else
            <div class="d-flex align-items-end gap-1" style="height: 160px;">
                @foreach($perHari as $tgl => $jumlah)
                    <div class="flex-fill d-flex flex-column justify-content-end" style="min-width: 3px;"
                         title="{{ \Carbon\Carbon::parse($tgl)->translatedFormat('d M Y') }}: {{ $jumlah }} email">
                        <div style="height: {{ $jumlah > 0 ? max(3, round($jumlah / $maksHari * 100)) : 1 }}%;
                                    background: {{ $jumlah > 0 ? 'var(--tm-accent)' : 'var(--tm-border)' }};
                                    border-radius: 3px 3px 0 0;"></div>
                    </div>
                @endforeach
            </div>
            <div class="d-flex justify-content-between small text-muted mt-2">
                <span>{{ \Carbon\Carbon::parse(array_key_first($perHari))->translatedFormat('d M') }}</span>
                <span>puncak {{ $maksHari }} email/hari</span>
                <span>{{ \Carbon\Carbon::parse(array_key_last($perHari))->translatedFormat('d M') }}</span>
            </div>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Pengirim teratas</h6>
                @forelse($pengirimTeratas as $p)
                    <div class="d-flex justify-content-between align-items-center py-1 small">
                        <span class="text-truncate me-2">{{ $p->from_address }}</span>
                        <span class="badge-soft flex-shrink-0">{{ $p->total }}</span>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Belum ada data.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Alamat paling aktif</h6>
                @forelse($alamatTeraktif as $a)
                    <div class="d-flex justify-content-between align-items-center py-1 small">
                        <a href="{{ route('inbox.index', ['alias' => explode('@', $a->to_address)[0], 'domain' => explode('@', $a->to_address)[1] ?? null]) }}"
                           class="text-truncate me-2 text-decoration-none" style="color: inherit;">{{ $a->to_address }}</a>
                        <span class="badge-soft flex-shrink-0">{{ $a->total }}</span>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Belum ada data.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Per domain</h6>
                @forelse($perDomain as $d)
                    <div class="d-flex justify-content-between align-items-center py-1 small">
                        <span class="text-truncate me-2">{{ $d->domain }}</span>
                        <span class="badge-soft flex-shrink-0">{{ $d->total }}</span>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Belum ada data.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Jam tersibuk</h6>
                <div class="d-flex align-items-end gap-1" style="height: 90px;">
                    @foreach($perJam as $jam => $jumlah)
                        <div class="flex-fill d-flex flex-column justify-content-end" title="{{ sprintf('%02d:00', $jam) }} — {{ $jumlah }} email">
                            <div style="height: {{ $jumlah > 0 ? max(4, round($jumlah / $maksJam * 100)) : 1 }}%;
                                        background: {{ $jumlah > 0 ? 'var(--tm-accent)' : 'var(--tm-border)' }};
                                        border-radius: 3px 3px 0 0;"></div>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-between small text-muted mt-2">
                    <span>00:00</span><span>12:00</span><span>23:00</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
