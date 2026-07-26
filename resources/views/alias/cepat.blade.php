@extends('layouts.app')

@section('title', 'Alias Baru — TempMail')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card text-center">
            <div class="card-body p-4">
                <div class="mb-2"><i class="bi bi-magic" style="font-size: 2rem; color: var(--tm-accent)"></i></div>
                <h5 class="fw-bold mb-1">Alamat siap dipakai</h5>
                <p class="text-muted small mb-3">{{ $alias->label ? 'Untuk ' . $alias->label : 'Alias baru' }} — sudah tersimpan di daftar Alamat.</p>

                <div class="mb-3">
                    <span class="alias-chip fs-6" data-copy="{{ $alias->full_address }}">
                        <i class="bi bi-clipboard me-1"></i>{{ $alias->full_address }}
                    </span>
                </div>

                @if($alias->expires_at)
                    <p class="small text-warning-emphasis mb-3"><i class="bi bi-hourglass-split me-1"></i>Aktif {{ $alias->expires_at->diffForHumans() }}</p>
                @endif

                <button type="button" class="btn btn-sm btn-light border" onclick="window.close()">Tutup jendela</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Langsung salin ke clipboard begitu jendela terbuka — tinggal paste di form signup
    tmCopy(@json($alias->full_address))
        .then(() => tmToast('success', 'Alamat tersalin — tinggal paste!'))
        .catch(() => {});
</script>
@endsection
