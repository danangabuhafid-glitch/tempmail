{{-- Partial daftar email — dimuat ulang via AJAX (filter, pagination, auto-refresh) --}}
@php
    $avatarPalette = ['#6d5df6', '#e8618c', '#0ea472', '#d97a06', '#2f7ee3', '#9333ea'];
    $avatarColor = fn ($seed) => $avatarPalette[crc32((string) $seed) % count($avatarPalette)];

    // Mode filter satu alamat vs semua alamat
    $activeAlias = request('alias');
    $activeDomain = request('domain');
    $modeSatuAlamat = filled($activeAlias);
@endphp

<span id="latest-marker" data-latest="{{ $latestId }}" hidden></span>

@php
    $lihatSampah = request()->boolean('trash');
    $lihatSpam = !$lihatSampah && request()->boolean('spam');
@endphp

{{-- Folder Sampah: banner + tombol kosongkan --}}
@if($lihatSampah)
    <div class="alert alert-light border py-2 small d-flex justify-content-between align-items-center mb-3" style="border-radius: 10px;">
        <span><i class="bi bi-trash me-1" style="color: var(--tm-accent)"></i>
            Email di Sampah dihapus permanen otomatis setelah 7 hari.
        </span>
        @if(($trashCount ?? 0) > 0)
            <form method="POST" action="{{ route('inbox.trash.empty') }}" data-ajax data-refresh="#inbox-list"
                  data-confirm="Kosongkan Sampah? {{ $trashCount }} email dihapus permanen beserta lampirannya.">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3 me-1"></i>Kosongkan Sampah</button>
            </form>
        @endif
    </div>
@endif

@if(!empty($isAliasUser))
    {{-- Akun alias: satu kotak masuk — hanya pemisah Inbox / Spam / Sampah --}}
    @if(($spamCount ?? 0) > 0 || ($trashCount ?? 0) > 0 || $lihatSpam || $lihatSampah)
        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
            <a href="{{ route('inbox.index') }}" class="mbox-chip {{ !$lihatSpam && !$lihatSampah ? 'active' : '' }}">
                <i class="bi bi-inbox me-1"></i>Inbox
            </a>
            <a href="{{ route('inbox.index', ['spam' => 1]) }}" class="mbox-chip {{ $lihatSpam ? 'active' : '' }}" style="{{ $lihatSpam ? '' : 'color:#dc2645;border-color:#f0b6bf;' }}">
                <i class="bi bi-exclamation-octagon me-1"></i>Spam
                @if(($spamCount ?? 0) > 0)<span class="mbox-count">{{ $spamCount }}</span>@endif
            </a>
            <a href="{{ route('inbox.index', ['trash' => 1]) }}" class="mbox-chip {{ $lihatSampah ? 'active' : '' }}">
                <i class="bi bi-trash me-1"></i>Sampah
                @if(($trashCount ?? 0) > 0)<span class="mbox-count">{{ $trashCount }}</span>@endif
            </a>
        </div>
    @endif
@else
{{-- Chip pemilih kotak masuk: "Semua" + Spam + Sampah + tiap alamat --}}
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <a href="{{ route('inbox.index') }}" class="mbox-chip {{ !$modeSatuAlamat && !$lihatSpam && !$lihatSampah ? 'active' : '' }}">
        <i class="bi bi-collection me-1"></i>Semua
        @if($unreadCount > 0)<span class="mbox-count">{{ $unreadCount }}</span>@endif
    </a>
    @if(($spamCount ?? 0) > 0 || $lihatSpam)
        <a href="{{ route('inbox.index', ['spam' => 1]) }}" class="mbox-chip {{ $lihatSpam ? 'active' : '' }}" style="{{ $lihatSpam ? '' : 'color:#dc2645;border-color:#f0b6bf;' }}"
           title="Email yang gagal autentikasi pengirim (SPF/DKIM/DMARC)">
            <i class="bi bi-exclamation-octagon me-1"></i>Spam
            @if(($spamCount ?? 0) > 0)<span class="mbox-count">{{ $spamCount }}</span>@endif
        </a>
    @endif
    @if(($trashCount ?? 0) > 0 || $lihatSampah)
        <a href="{{ route('inbox.index', ['trash' => 1]) }}" class="mbox-chip {{ $lihatSampah ? 'active' : '' }}" title="Email terhapus — dipulihkan dalam 7 hari">
            <i class="bi bi-trash me-1"></i>Sampah
            @if(($trashCount ?? 0) > 0)<span class="mbox-count">{{ $trashCount }}</span>@endif
        </a>
    @endif
    @foreach($mailboxes as $addr => $m)
        @php $isActive = $modeSatuAlamat && $activeAlias === $m['alias'] && (blank($activeDomain) || $activeDomain === $m['domain']); @endphp
        <a href="{{ route('inbox.index', ['alias' => $m['alias'], 'domain' => $m['domain']]) }}"
           class="mbox-chip {{ $isActive ? 'active' : '' }}"
           title="{{ $m['label'] ?: $addr }}">
            @if($m['pinned'])<i class="bi bi-pin-angle-fill me-1"></i>@endif{{ $addr }}
            @if($m['unread'] > 0)<span class="mbox-count">{{ $m['unread'] }}</span>@endif
        </a>
    @endforeach
    @if($hiddenBoxes > 0)
        <button type="button" id="mbox-more" class="mbox-chip" title="Cari lewat dropdown Alamat di form filter">
            +{{ $hiddenBoxes }} lainnya…
        </button>
    @endif
</div>

{{-- Keterangan saat sedang melihat satu alamat saja --}}
@if($modeSatuAlamat)
    <div class="alert alert-light border py-2 small d-flex justify-content-between align-items-center mb-3" style="border-radius: 10px;">
        <span><i class="bi bi-funnel-fill me-1" style="color: var(--tm-accent)"></i>
            Hanya menampilkan email untuk <strong>{{ $activeAlias }}{{ $activeDomain ? '@' . $activeDomain : '' }}</strong>
        </span>
        <a href="{{ route('inbox.index') }}" class="btn btn-sm btn-light border mbox-chip-clear"><i class="bi bi-x-lg me-1"></i>Tampilkan semua</a>
    </div>
@endif
@endif

<div class="card overflow-hidden">
    @forelse($emails as $email)
        @php
            $seed = $email->from_address ?: 'x';
            $initial = strtoupper(substr($email->from_name ?: $email->from_address ?: '?', 0, 1));
            $boxAddr = $email->alias . '@' . $email->domain;
            $boxColor = $avatarColor($boxAddr);
        @endphp
        <div class="email-item {{ $email->is_read ? 'read' : 'unread' }}" data-email-id="{{ $email->id }}">
            <input type="checkbox" class="form-check-input bulk-check flex-shrink-0 m-0" value="{{ $email->id }}" title="Pilih untuk aksi massal">
            @if(!$email->is_read)<span class="unread-dot"></span>@else<span style="width:9px" class="flex-shrink-0"></span>@endif
            @if($lihatSampah)
                <button type="button" class="btn btn-sm btn-light border flex-shrink-0 btn-restore" data-id="{{ $email->id }}" title="Pulihkan dari Sampah">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            @endif
            <a href="{{ route('inbox.show', $email) }}" class="d-flex flex-grow-1 align-items-center gap-3 text-decoration-none" style="color: inherit; min-width: 0;">
                <div class="tm-avatar" style="background: {{ $avatarColor($seed) }}">{{ $initial }}</div>
                <div class="flex-grow-1 text-truncate">
                    <div class="text-truncate sender">
                        {{ $email->from_name ?: $email->from_address ?: '(tanpa pengirim)' }}
                        {{-- Mode "Semua": tampilkan keterangan alamat tujuan sebagai badge berwarna --}}
                        @unless($modeSatuAlamat)
                            <span class="mbox-badge ms-1" style="background: {{ $boxColor }}1c; color: {{ $boxColor }};">
                                <i class="bi bi-inbox-fill me-1"></i>{{ $boxAddr }}
                            </span>
                        @endunless
                    </div>
                    <div class="text-truncate subject small">
                        @if($email->otp_code)
                            <span class="otp-chip me-1" data-copy="{{ $email->otp_code }}" title="Salin kode verifikasi">
                                <i class="bi bi-123"></i>{{ $email->otp_code }}
                            </span>
                        @endif
                        {{ $email->subject ?: '(tanpa subjek)' }}
                    </div>
                </div>
                <div class="text-end flex-shrink-0">
                    {{-- Waktu relatif berdetak live (diperbarui tiap detik oleh JS di layout) --}}
                    <div class="small fw-semibold" style="color: var(--tm-accent)" data-reltime="{{ $email->received_at->valueOf() }}">{{ $email->received_at->diffForHumans() }}</div>
                    <div class="text-muted" style="font-size: .72rem;">{{ $email->received_at->format('d/m/Y · H:i:s') }}</div>
                    <span class="text-muted small">
                        @if($email->note)<i class="bi bi-sticky-fill" style="color: #d97a06" title="Ada catatan"></i>@endif
                        @if(is_null($email->expires_at))<i class="bi bi-bookmark-star-fill" style="color: var(--tm-accent)" title="Disimpan permanen"></i>@endif
                        @if($email->attachments_count > 0)<i class="bi bi-paperclip"></i>{{ $email->attachments_count }}@endif
                    </span>
                </div>
            </a>
        </div>
    @empty
        <div class="text-center text-muted py-5 px-3">
            <i class="bi bi-inbox" style="font-size: 3rem; color: #cfd2e4;"></i>
            @if($modeSatuAlamat)
                <p class="mt-2 mb-1 fw-semibold">Belum ada email untuk {{ $activeAlias }}{{ $activeDomain ? '@' . $activeDomain : '' }}</p>
                <p class="small mb-0">Alamat ini otomatis aktif — begitu ada yang mengirim, email muncul di sini.</p>
            @else
                <p class="mt-2 mb-1 fw-semibold">Belum ada email</p>
                <p class="small mb-0">Kirim apa saja ke <code>{{ 'apapun@' . (config('tempmail.domains')[0] ?? 'domainmu') }}</code> — semua alamat otomatis aktif.</p>
            @endif
        </div>
    @endforelse
</div>

<div class="mt-3">
    {{ $emails->links() }}
</div>
