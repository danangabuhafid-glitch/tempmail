@extends('layouts.app')

@section('title', ($email->subject ?: '(tanpa subjek)') . ' — TempMail')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
    <a href="{{ route('inbox.index') }}" class="btn btn-sm btn-light border"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    <div class="d-flex gap-2 flex-wrap">
        {{-- Simpan permanen: kebal auto-hapus retensi --}}
        <form method="POST" action="{{ route('inbox.keep', $email) }}" data-ajax>
            @csrf @method('PATCH')
            <button class="btn btn-sm {{ is_null($email->expires_at) ? 'btn-tm' : 'btn-light border' }}" title="{{ is_null($email->expires_at) ? 'Kembalikan ke masa simpan otomatis' : 'Jangan pernah hapus otomatis email ini' }}">
                <i class="bi {{ is_null($email->expires_at) ? 'bi-bookmark-star-fill' : 'bi-bookmark-star' }} me-1"></i>{{ is_null($email->expires_at) ? 'Tersimpan permanen' : 'Simpan permanen' }}
            </button>
        </form>
        <form method="POST" action="{{ route('inbox.unread', $email) }}" data-ajax>
            @csrf @method('PATCH')
            <button class="btn btn-sm btn-light border" title="Kembalikan status belum dibaca"><i class="bi bi-envelope me-1"></i>Belum dibaca</button>
        </form>
        <a href="{{ route('inbox.eml', $email) }}" class="btn btn-sm btn-light border" title="Unduh sumber asli (.eml)"><i class="bi bi-download me-1"></i>.eml</a>
        @if(auth()->user()->isOwner() && $email->from_address)
            <form method="POST" action="{{ route('settings.block') }}" data-ajax
                  data-confirm="Blokir {{ $email->from_address }}? Email berikutnya dari pengirim ini langsung dibuang.">
                @csrf
                <input type="hidden" name="pattern" value="{{ $email->from_address }}">
                <button class="btn btn-sm btn-light border" title="Blokir pengirim ini"><i class="bi bi-slash-circle me-1"></i>Blokir pengirim</button>
            </form>
        @endif
        <form method="POST" action="{{ route('inbox.destroy', $email) }}" data-ajax data-confirm="Hapus email ini?">
            @csrf
            @method('DELETE')
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Hapus</button>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3">{{ $email->subject ?: '(tanpa subjek)' }}</h5>

        @if($email->otp_code)
            {{-- Kode verifikasi terdeteksi otomatis — klik untuk salin --}}
            <div class="mb-3">
                <span class="otp-chip fs-5 px-3 py-1" data-copy="{{ $email->otp_code }}" title="Salin kode verifikasi">
                    <i class="bi bi-123"></i>{{ $email->otp_code }}<i class="bi bi-clipboard small"></i>
                </span>
                <span class="small text-muted ms-2">kode verifikasi terdeteksi — klik untuk salin</span>
            </div>
        @endif
        <table class="table table-sm table-borderless small mb-0" style="width:auto">
            <tr>
                <td class="text-muted pe-3">Dari</td>
                <td>
                    <span class="fw-semibold">{{ $email->from_name ?: '' }}</span>
                    {{ $email->from_address ? '<' . $email->from_address . '>' : '-' }}
                    @if($email->spf_result)
                        <span class="badge rounded-pill {{ $email->spf_result === 'pass' ? 'text-bg-success' : 'text-bg-warning' }}">SPF {{ $email->spf_result }}</span>
                    @endif
                    @if($email->dkim_result)
                        <span class="badge rounded-pill {{ $email->dkim_result === 'pass' ? 'text-bg-success' : 'text-bg-warning' }}">DKIM {{ $email->dkim_result }}</span>
                    @endif
                    @if($email->dmarc_result)
                        <span class="badge rounded-pill {{ $email->dmarc_result === 'pass' ? 'text-bg-success' : 'text-bg-warning' }}">DMARC {{ $email->dmarc_result }}</span>
                    @endif
                    @if($email->is_spam)
                        <span class="badge rounded-pill text-bg-danger"><i class="bi bi-exclamation-octagon me-1"></i>SPAM — autentikasi pengirim gagal</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="text-muted pe-3">Kepada</td>
                <td>
                    <span class="alias-chip" data-copy="{{ $email->to_address }}"><i class="bi bi-clipboard me-1"></i>{{ $email->to_address }}</span>
                </td>
            </tr>
            <tr>
                <td class="text-muted pe-3">Diterima</td>
                <td>{{ $email->received_at->translatedFormat('d F Y H:i:s') }}
                    <span class="fw-semibold" style="color: var(--tm-accent)" data-reltime="{{ $email->received_at->valueOf() }}">({{ $email->received_at->diffForHumans() }})</span>
                </td>
            </tr>
            <tr>
                <td class="text-muted pe-3">Masa simpan</td>
                <td>
                    @if(is_null($email->expires_at))
                        <span class="badge rounded-pill text-bg-success"><i class="bi bi-bookmark-star me-1"></i>Permanen — tidak dihapus otomatis</span>
                    @else
                        @php $sisaHari = (int) now()->diffInDays($email->expires_at, false); @endphp
                        <span class="badge rounded-pill {{ $sisaHari < 3 ? 'text-bg-danger' : 'text-bg-light border text-muted' }}">
                            <i class="bi bi-hourglass-split me-1"></i>terhapus otomatis {{ $email->expires_at->diffForHumans() }}
                        </span>
                    @endif
                </td>
            </tr>
            @if($email->attachments->isNotEmpty())
            <tr>
                <td class="text-muted pe-3">Lampiran</td>
                <td>
                    <div class="d-flex flex-wrap gap-2">
                    @foreach($email->attachments as $att)
                        @php
                            $mime = (string) $att->mime_type;
                            $ikon = match (true) {
                                str_contains($mime, 'pdf') => 'bi-file-earmark-pdf text-danger',
                                str_contains($mime, 'zip') || str_contains($mime, 'compressed') || str_contains($mime, 'rar') => 'bi-file-earmark-zip',
                                str_contains($mime, 'word') || str_ends_with($att->filename, '.doc') || str_ends_with($att->filename, '.docx') => 'bi-file-earmark-word text-primary',
                                str_contains($mime, 'sheet') || str_contains($mime, 'excel') || str_ends_with($att->filename, '.csv') => 'bi-file-earmark-excel text-success',
                                str_starts_with($mime, 'audio/') => 'bi-file-earmark-music',
                                str_starts_with($mime, 'video/') => 'bi-file-earmark-play',
                                str_starts_with($mime, 'text/') => 'bi-file-earmark-text',
                                default => 'bi-file-earmark',
                            };
                        @endphp
                        <a href="{{ route('inbox.attachment', $att) }}" class="text-decoration-none border rounded-3 p-2 d-flex align-items-center gap-2 {{ $att->is_flagged ? 'att-flagged' : '' }}"
                           style="color: inherit; max-width: 240px; background: {{ $att->is_flagged ? 'rgba(220,38,69,.08)' : 'var(--tm-soft)' }}; border-color: {{ $att->is_flagged ? '#dc2645' : 'var(--tm-soft-border)' }} !important;"
                           title="{{ $att->is_flagged ? 'HATI-HATI: tipe file berisiko — jangan dibuka kecuali kamu yakin' : 'Unduh ' . $att->filename }}">
                            @if($att->is_flagged)
                                <i class="bi bi-exclamation-triangle-fill fs-3 text-danger"></i>
                            @elseif(str_starts_with($mime, 'image/'))
                                {{-- Thumbnail gambar via route inline ber-auth --}}
                                <img src="{{ $att->inlineUrl() }}" alt="" style="width: 44px; height: 44px; object-fit: cover; border-radius: 8px;" loading="lazy">
                            @else
                                <i class="bi {{ $ikon }} fs-3"></i>
                            @endif
                            <span class="text-truncate">
                                <span class="d-block text-truncate small fw-semibold">{{ $att->filename }}</span>
                                <span class="d-block {{ $att->is_flagged ? 'text-danger fw-semibold' : 'text-muted' }}" style="font-size: .72rem;">
                                    {{ $att->is_flagged ? 'berisiko · ' : '' }}{{ number_format($att->size_bytes / 1024, 0) }} KB
                                </span>
                            </span>
                        </a>
                    @endforeach
                    </div>
                </td>
            </tr>
            @endif
        </table>
    </div>
</div>

{{-- Catatan pribadi: pengingat kenapa alamat ini dipakai --}}
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="POST" action="{{ route('inbox.note', $email) }}" data-ajax>
            @csrf
            <label class="form-label small fw-semibold mb-1">
                <i class="bi bi-sticky me-1" style="color: #d97a06"></i>Catatan pribadi
            </label>
            <div class="input-group">
                <textarea name="note" class="form-control form-control-sm" rows="1" maxlength="2000"
                          placeholder="mis. akun trial premium, expire 3 Agustus">{{ $email->note }}</textarea>
                <button class="btn btn-sm btn-light border"><i class="bi bi-check-lg me-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-html" type="button"><i class="bi bi-window me-1"></i>Tampilan</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-text" type="button"><i class="bi bi-file-text me-1"></i>Teks Polos</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-header" type="button"><i class="bi bi-code-slash me-1"></i>Header</button></li>
</ul>

<div class="tab-content card p-3">
    <div class="tab-pane fade show active" id="tab-html">
        @if($htmlAman !== '')
            @unless($showImages)
                <div class="alert alert-light border py-2 small d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted"><i class="bi bi-shield-check me-1"></i>Gambar diblokir untuk mencegah pelacakan.</span>
                    <a href="{{ route('inbox.show', [$email, 'images' => 1]) }}" class="btn btn-sm btn-light border">Tampilkan gambar</a>
                </div>
            @endunless
            {{-- HTML sudah disanitasi server-side; iframe sandbox TANPA allow-scripts sebagai
                 lapisan kedua (allow-same-origin aman di sini karena skrip tetap tidak bisa
                 berjalan — dibutuhkan agar tinggi konten bisa diukur untuk auto-resize) --}}
            <iframe class="email-frame" id="email-frame" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin" srcdoc="{{ $htmlAman }}"></iframe>
        @else
            <pre class="tm-pre">{{ $email->text_body ?: '(email kosong)' }}</pre>
        @endif
    </div>
    <div class="tab-pane fade" id="tab-text">
        <pre class="tm-pre">{{ $email->text_body ?: '(tidak ada versi teks)' }}</pre>
    </div>
    <div class="tab-pane fade" id="tab-header">
        <pre class="tm-pre small text-muted">{{ $email->headers_raw }}</pre>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Iframe email menyesuaikan tinggi kontennya — tidak ada scrollbar ganda.
    // Bisa dibaca karena sandbox memakai allow-same-origin (tanpa allow-scripts).
    (function () {
        const frame = document.getElementById('email-frame');
        if (!frame) return;
        const pas = () => {
            try {
                const h = frame.contentDocument?.documentElement?.scrollHeight;
                if (h) frame.style.height = Math.max(h + 24, 220) + 'px';
            } catch (e) { /* biarkan tinggi default */ }
        };
        frame.addEventListener('load', () => {
            pas();
            // Gambar (inline/remote yang diizinkan) selesai dimuat belakangan
            setTimeout(pas, 700);
            setTimeout(pas, 2000);
        });
    })();

    // Lampiran berisiko: konfirmasi dulu sebelum diunduh
    document.addEventListener('click', async function (e) {
        const link = e.target.closest('a.att-flagged');
        if (!link) return;
        e.preventDefault();
        const c = await Swal.fire({
            title: 'Lampiran berisiko',
            html: 'File ini bertipe yang sering dipakai malware (executable/script/HTML).<br>Yakin mau mengunduhnya?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, unduh',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc2645',
        });
        if (c.isConfirmed) location.href = link.href;
    });
</script>
@endsection
