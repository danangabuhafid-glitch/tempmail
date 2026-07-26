@extends('layouts.app')

@section('title', 'Konfirmasi Buka Tautan — TempMail')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <i class="bi bi-shield-exclamation" style="font-size: 2.6rem; color: var(--tm-accent)"></i>
                </div>
                <h5 class="fw-bold mb-2">Kamu akan meninggalkan TempMail</h5>
                <p class="text-muted small mb-3">
                    Tautan ini berasal dari email — pastikan alamat tujuannya memang yang kamu harapkan
                    sebelum memasukkan data apa pun di sana.
                </p>

                <div class="kv p-3 mb-3 text-start" style="word-break: break-all;">
                    <div class="small text-muted mb-1">Domain tujuan</div>
                    <div class="fw-bold mb-2">{{ $host }}</div>
                    <div class="small text-muted mb-1">URL lengkap{{ $urlBersih !== $url ? ' (parameter pelacak sudah dibuang)' : '' }}</div>
                    <code class="small">{{ $urlBersih }}</code>
                </div>

                @if($punycode)
                    <div class="alert alert-danger py-2 small text-start">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <strong>Hati-hati:</strong> domain ini memakai punycode (<code>xn--</code>) yang sering dipakai
                        meniru tampilan domain terkenal (phishing homograph).
                    </div>
                @endif
                @if($ipHost)
                    <div class="alert alert-warning py-2 small text-start">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Tujuannya alamat IP mentah, bukan nama domain — pola umum tautan phishing.
                    </div>
                @endif
                @if($insecure)
                    <div class="alert alert-warning py-2 small text-start">
                        <i class="bi bi-unlock me-1"></i>Koneksi tidak terenkripsi (HTTP, bukan HTTPS).
                    </div>
                @endif

                <div class="d-flex gap-2 justify-content-center">
                    {{-- Halaman ini biasanya dibuka di tab baru (target=_blank), jadi
                         history.back() tidak ada tujuannya — fallback ke inbox. --}}
                    <a href="{{ route('inbox.index') }}" class="btn btn-light border"
                       onclick="if (history.length > 1) { history.back(); return false; }">
                        <i class="bi bi-arrow-left me-1"></i>Kembali
                    </a>
                    <a href="{{ $urlBersih }}" rel="noopener noreferrer nofollow" class="btn btn-tm">
                        Lanjut ke {{ $host }} <i class="bi bi-box-arrow-up-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
