{{-- Partial daftar pengirim diblokir — dimuat ulang via AJAX setelah tambah/hapus --}}
@php $blocked = \App\Models\BlockedSender::orderBy('pattern')->get(); @endphp

<ul class="list-group mb-3">
    @forelse($blocked as $b)
        <li class="list-group-item d-flex justify-content-between align-items-center py-2" style="border-radius: 10px; margin-bottom: .4rem; border: 1px solid #e2e4f0;">
            <span>
                <i class="bi bi-slash-circle me-2 text-danger"></i>
                <strong>{{ $b->pattern }}</strong>
                <span class="text-muted small ms-1">{{ str_contains($b->pattern, '@') ? 'alamat' : 'seluruh domain' }}</span>
            </span>
            <form method="POST" action="{{ route('settings.unblock') }}" data-ajax data-refresh="#blocked-list">
                @csrf @method('DELETE')
                <input type="hidden" name="pattern" value="{{ $b->pattern }}">
                <button class="btn btn-sm btn-light border" title="Cabut blokir"><i class="bi bi-x-lg"></i></button>
            </form>
        </li>
    @empty
        <li class="list-group-item text-muted small">Belum ada pengirim yang diblokir.</li>
    @endforelse
</ul>

<form method="POST" action="{{ route('settings.block') }}" data-ajax data-reset data-refresh="#blocked-list">
    @csrf
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-plus-lg"></i></span>
        <input type="text" name="pattern" class="form-control" placeholder="spam@contoh.com atau contoh.com" required>
        <button class="btn btn-tm" type="submit">Blokir</button>
    </div>
</form>
