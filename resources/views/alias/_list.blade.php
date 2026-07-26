{{-- Partial daftar alamat — dimuat ulang via AJAX setelah tambah/pin/hapus --}}
@forelse($aliases as $alias)
    @php $stat = $stats->get($alias->full_address); @endphp
    <div class="d-flex align-items-center gap-3 py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
        <div class="flex-grow-1 text-truncate">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if($alias->is_pinned)<i class="bi bi-pin-angle-fill" style="color: var(--tm-accent)"></i>@endif
                <span class="alias-chip" data-copy="{{ $alias->full_address }}">
                    <i class="bi bi-clipboard me-1"></i>{{ $alias->full_address }}
                </span>
                @if($alias->sudahKedaluwarsa())
                    <span class="badge rounded-pill text-bg-danger" title="Email baru ke alamat ini ditolak">kedaluwarsa</span>
                @elseif($alias->expires_at)
                    <span class="badge rounded-pill text-bg-warning" title="Alias sekali pakai">aktif {{ $alias->expires_at->diffForHumans() }}</span>
                @endif
            </div>
            <div class="small text-muted mt-1">
                {{ $alias->label ?: 'Tanpa label' }}
                <button type="button" class="btn btn-link btn-sm p-0 align-baseline btn-edit-label"
                        data-url="{{ route('alias.label', $alias) }}" data-label="{{ $alias->label }}"
                        title="Ubah label"><i class="bi bi-pencil small"></i></button>
                ·
                @if($stat)
                    {{ $stat->total }} email, terakhir {{ \Carbon\Carbon::parse($stat->last_received)->diffForHumans() }}
                @else
                    belum pernah menerima email
                @endif
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-light border btn-qr" data-address="{{ $alias->full_address }}" title="QR code alamat (scan dari HP)">
            <i class="bi bi-qr-code"></i>
        </button>
        <a href="{{ route('inbox.index', ['alias' => $alias->alias, 'domain' => $alias->domain]) }}" class="btn btn-sm btn-light border" title="Lihat email masuk">
            <i class="bi bi-inbox"></i>
        </a>
        <form method="POST" action="{{ route('alias.pin', $alias) }}" data-ajax data-refresh="#alias-list">
            @csrf @method('PATCH')
            <button class="btn btn-sm btn-light border" title="{{ $alias->is_pinned ? 'Lepas pin' : 'Pin' }}">
                <i class="bi {{ $alias->is_pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle' }}"></i>
            </button>
        </form>
        <form method="POST" action="{{ route('alias.destroy', $alias) }}" data-ajax data-refresh="#alias-list"
              data-confirm="Hapus dari daftar? Email yang sudah masuk tidak ikut terhapus.">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" title="Hapus dari daftar"><i class="bi bi-trash"></i></button>
        </form>
    </div>
@empty
    <p class="text-muted small mb-0">Belum ada alamat tersimpan. Buat lewat form di samping, atau langsung pakai alamat apa pun — semuanya otomatis aktif.</p>
@endforelse

@if($stats->isNotEmpty())
    <hr>
    <h6 class="fw-bold small text-muted text-uppercase">Alamat lain yang pernah menerima email</h6>
    <div class="d-flex flex-wrap gap-2 mt-2">
        @foreach($stats as $addr => $s)
            @if(!$aliases->contains(fn ($a) => $a->full_address === $addr))
                <a class="text-decoration-none small badge text-bg-light border"
                   href="{{ route('inbox.index', ['alias' => $s->alias, 'domain' => $s->domain]) }}">
                    {{ $addr }} <span class="text-muted">({{ $s->total }})</span>
                </a>
            @endif
        @endforeach
    </div>
@endif
