@extends('layouts.app')

@section('title', 'Inbox — TempMail')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h4 class="mb-0 fw-bold">
        Inbox
        @if($isAliasUser)
            <span class="badge-soft ms-1">{{ auth()->user()->email }}</span>
        @endif
        @if($unreadCount > 0)
            <span class="badge-soft ms-1">{{ $unreadCount }} baru</span>
        @endif
    </h4>
    <div class="d-flex align-items-center gap-2">
        <span class="small text-muted d-none d-md-inline" title="Email baru dicek otomatis tiap 15 detik">
            <i class="bi bi-arrow-repeat me-1"></i>auto-cek tiap 15 dtk
        </span>
        <button type="button" id="btn-notif" class="btn btn-sm btn-light border d-none" title="Aktifkan notifikasi browser saat email baru masuk">
            <i class="bi bi-bell"></i>
        </button>
        <button type="button" id="btn-refresh" class="btn btn-sm btn-light border" title="Muat ulang sekarang (r)">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <span class="small text-muted d-none d-lg-inline" title="Tekan ? untuk daftar pintasan keyboard">
            <kbd>?</kbd> pintasan
        </span>
        @unless($isAliasUser)
            <button type="button" class="btn btn-sm btn-tm" data-bs-toggle="modal" data-bs-target="#modal-alias">
                <i class="bi bi-plus-lg me-1"></i>Buat Alamat Baru
            </button>
        @endunless
    </div>
</div>

@unless($isAliasUser)
{{-- Modal buat alamat baru — tanpa meninggalkan inbox --}}
<div class="modal fade" id="modal-alias" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--tm-radius); border: 0;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-1" style="color: var(--tm-accent)"></i>Buat Alamat Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Pilih mode: ketik sendiri (Manual), pakai awalan + akhiran acak (Prefix), atau acak penuh (Otomatis). Mode Prefix &amp; Otomatis bisa membuat sampai 50 alamat sekaligus.</p>
                @include('alias._form', ['refreshTarget' => '#inbox-list', 'uid' => 'md'])
                <div class="text-center mt-3">
                    <a href="{{ route('alias.index') }}" class="small text-decoration-none" style="color: var(--tm-accent)">Kelola semua alamat <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>
@endunless

<form method="GET" action="{{ route('inbox.index') }}" id="filter-form" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="{{ $isAliasUser ? 'col-md-9' : 'col-md-4' }}">
            <label class="form-label small mb-1 text-muted">Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Subjek / isi email / pengirim{{ $isAliasUser ? '' : ' / alias' }}...">
        </div>
        @unless($isAliasUser)
        <div class="col-md-3">
            <label class="form-label small mb-1 text-muted">Domain</label>
            <select name="domain" class="form-select form-select-sm">
                <option value="">Semua domain</option>
                @foreach($domains as $d)
                    <option value="{{ $d }}" {{ request('domain') === $d ? 'selected' : '' }}>{{ $d }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1 text-muted">Alamat</label>
            <select name="alias" class="form-select form-select-sm">
                <option value="">Semua alamat</option>
                @foreach($aliases as $a)
                    <option value="{{ $a->alias }}" {{ request('alias') === $a->alias ? 'selected' : '' }}>{{ $a->alias . '@' . $a->domain }}</option>
                @endforeach
            </select>
        </div>
        @endunless
        <div class="{{ $isAliasUser ? 'col-md-3' : 'col-md-2' }} d-flex align-items-center gap-2">
            <div class="form-check mb-0">
                <input type="checkbox" name="unread" value="1" class="form-check-input" id="unread" {{ request('unread') ? 'checked' : '' }}>
                <label for="unread" class="form-check-label small">Belum dibaca</label>
            </div>
            <button class="btn btn-sm btn-tm ms-auto" title="Terapkan filter"><i class="bi bi-funnel"></i></button>
        </div>
    </div>
</form>

{{-- Bar aksi massal: pilih beberapa email → hapus / tandai sekaligus --}}
<div class="card card-body py-2 mb-3">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="form-check mb-0">
            <input type="checkbox" id="bulk-all" class="form-check-input">
            <label for="bulk-all" class="form-check-label small">Pilih semua</label>
        </div>
        <span class="small text-muted"><span id="bulk-count">0</span> dipilih</span>
        <div class="ms-auto d-flex gap-2 flex-wrap">
            @if(request()->boolean('trash'))
                <button type="button" id="bulk-restore" class="btn btn-sm btn-light border" disabled><i class="bi bi-arrow-counterclockwise me-1"></i>Pulihkan</button>
                <button type="button" id="bulk-purge" class="btn btn-sm btn-outline-danger" disabled><i class="bi bi-trash3 me-1"></i>Hapus permanen</button>
            @else
                <button type="button" id="bulk-read" class="btn btn-sm btn-light border" disabled><i class="bi bi-envelope-open me-1"></i>Tandai dibaca</button>
                <button type="button" id="bulk-unread" class="btn btn-sm btn-light border" disabled><i class="bi bi-envelope me-1"></i>Belum dibaca</button>
                <button type="button" id="bulk-delete" class="btn btn-sm btn-outline-danger" disabled><i class="bi bi-trash me-1"></i>Hapus</button>
            @endif
        </div>
    </div>
</div>

<div id="inbox-list">
    @include('inbox._list')
</div>
@endsection

@section('scripts')
<script>
    // Semua interaksi inbox via AJAX: filter, pagination, refresh, auto-cek
    async function loadInbox(url, push = true) {
        const icon = document.querySelector('#btn-refresh i');
        icon.classList.add('spin');
        try {
            await tmRefresh('#inbox-list', url);
            if (push) history.pushState({}, '', url);
            updateBulkUI();
        } finally {
            icon.classList.remove('spin');
        }
    }

    // Filter form → AJAX (URL ikut berubah agar bisa di-bookmark)
    const filterForm = document.getElementById('filter-form');
    filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const params = new URLSearchParams(new FormData(this));
        loadInbox(this.action + '?' + params.toString());
    });

    // Klik pagination / chip kotak-masuk / tombol "tampilkan semua" → AJAX
    document.getElementById('inbox-list').addEventListener('click', function (e) {
        // Chip "+N lainnya" → arahkan ke dropdown Alamat di form filter
        if (e.target.closest('#mbox-more')) {
            const select = document.querySelector('#filter-form [name="alias"]');
            select.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => select.focus(), 300);
            return;
        }

        const link = e.target.closest('.pagination a, a.mbox-chip, a.mbox-chip-clear');
        if (!link) return;
        e.preventDefault();
        loadInbox(link.href);
    });

    // Tombol refresh manual → muat ulang daftar saja
    document.getElementById('btn-refresh').addEventListener('click', () => loadInbox(location.href, false));

    // Navigasi back/forward: muat ulang daftar DAN sinkronkan isi form filter
    function syncFilterForm() {
        const p = new URLSearchParams(location.search);
        filterForm.elements.q.value = p.get('q') || '';
        if (filterForm.elements.domain) filterForm.elements.domain.value = p.get('domain') || '';
        if (filterForm.elements.alias) filterForm.elements.alias.value = p.get('alias') || '';
        filterForm.elements.unread.checked = p.get('unread') === '1';
    }
    window.addEventListener('popstate', () => { syncFilterForm(); loadInbox(location.href, false); });

    // ===== Aksi massal =====
    const bulkButtons = ['bulk-read', 'bulk-unread', 'bulk-delete', 'bulk-restore', 'bulk-purge'];

    function bulkIds() {
        return [...document.querySelectorAll('.bulk-check:checked')].map(c => c.value);
    }

    function updateBulkUI() {
        const n = bulkIds().length;
        document.getElementById('bulk-count').textContent = n;
        bulkButtons.forEach(id => { const b = document.getElementById(id); if (b) b.disabled = n === 0; });
        const all = document.querySelectorAll('.bulk-check');
        document.getElementById('bulk-all').checked = all.length > 0 && n === all.length;
    }

    document.getElementById('inbox-list').addEventListener('change', e => {
        if (e.target.matches('.bulk-check')) updateBulkUI();
    });

    // Tombol pulihkan per baris (folder Sampah)
    document.getElementById('inbox-list').addEventListener('click', async function (e) {
        const btn = e.target.closest('.btn-restore');
        if (!btn) return;
        e.preventDefault();
        try {
            const res = await fetch('{{ url('/email') }}/' + btn.dataset.id + '/pulihkan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ _method: 'PATCH' }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                tmToast('success', data.message);
                loadInbox(location.href, false);
            } else {
                tmToast('error', data.message || 'Gagal memulihkan.');
            }
        } catch (err) { tmToast('error', 'Tidak bisa menghubungi server.'); }
    });
    document.getElementById('bulk-all').addEventListener('change', function () {
        document.querySelectorAll('.bulk-check').forEach(c => c.checked = this.checked);
        updateBulkUI();
    });

    async function bulkAction(action) {
        const ids = bulkIds();
        if (!ids.length) return;

        if (action === 'purge') {
            const c = await Swal.fire({
                title: `Hapus permanen ${ids.length} email?`,
                text: 'Tidak bisa dipulihkan lagi.',
                icon: 'warning',
                showCancelButton: true, confirmButtonText: 'Ya, hapus permanen', cancelButtonText: 'Batal',
                confirmButtonColor: '#dc2645',
            });
            if (!c.isConfirmed) return;
        }

        try {
            const res = await fetch('{{ route('inbox.bulk') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ action, ids }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                tmToast('success', data.message || 'Selesai.');
                // Daftar menyusut setelah hapus/tandai — buang ?page= supaya tidak
                // mendarat di halaman kosong yang di luar jangkauan
                const url = new URL(location.href);
                url.searchParams.delete('page');
                history.replaceState({}, '', url);
                loadInbox(url.toString(), false);
            } else {
                tmToast('error', data.message || 'Terjadi kesalahan.');
            }
        } catch (err) {
            tmToast('error', 'Tidak bisa menghubungi server.');
        }
    }
    // Tombol yang tampil tergantung folder (Sampah vs Inbox) — pasang yang ada saja
    Object.entries({
        'bulk-read': 'read', 'bulk-unread': 'unread', 'bulk-delete': 'delete',
        'bulk-restore': 'restore', 'bulk-purge': 'purge',
    }).forEach(([id, action]) => {
        document.getElementById(id)?.addEventListener('click', () => bulkAction(action));
    });

    // ===== Notifikasi browser =====
    const btnNotif = document.getElementById('btn-notif');
    if ('Notification' in window && Notification.permission === 'default') {
        btnNotif.classList.remove('d-none');
        btnNotif.addEventListener('click', async () => {
            const izin = await Notification.requestPermission();
            btnNotif.classList.add('d-none');
            tmToast(izin === 'granted' ? 'success' : 'info',
                izin === 'granted' ? 'Notifikasi aktif — email baru muncul di layar.' : 'Notifikasi tidak diizinkan.');
        });
    }

    function notifikasiEmailBaru(latest) {
        if (!('Notification' in window) || Notification.permission !== 'granted' || !latest) return;
        const body = (latest.from ? 'Dari ' + latest.from : '') + (latest.otp ? ' — Kode: ' + latest.otp : '');
        const judul = latest.subject || 'Email baru';
        const url = '{{ url('/email') }}/' + latest.id;

        try {
            // Android Chrome melempar TypeError di sini — harus lewat service worker
            const n = new Notification(judul, { body, tag: 'tempmail-baru' });
            n.onclick = () => { window.focus(); location.href = url; };
        } catch (e) {
            navigator.serviceWorker?.ready
                .then(reg => reg.showNotification(judul, { body, tag: 'tempmail-baru', data: { url } }))
                .catch(() => {});
        }
    }

    // Auto-cek email baru tiap 15 detik; daftar dimuat ulang hanya jika ada yang baru.
    // Saat tab disembunyikan polling di-skip; begitu tab dibuka lagi langsung cek.
    let pollBusy = false;
    async function pollBaru() {
        if (pollBusy || document.hidden) return;
        pollBusy = true;
        try {
            const res = await fetch('{{ route('inbox.poll') }}', { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            document.title = (data.unread > 0 ? `(${data.unread}) ` : '') + 'Inbox — TempMail';
            const marker = document.getElementById('latest-marker');
            if (marker && data.latest_id > parseInt(marker.dataset.latest)) {
                // Muat daftar dulu: kegagalan notifikasi tidak boleh membatalkan refresh
                await loadInbox(location.href, false);
                notifikasiEmailBaru(data.latest);
            }
        } catch (e) { /* server sedang tidak terjangkau — coba lagi di siklus berikutnya */ }
        finally { pollBusy = false; }
    }
    setInterval(pollBaru, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) pollBaru(); });

    // ===== Keyboard shortcuts (gaya Gmail) =====
    let kursor = -1;

    function barisEmail() {
        return [...document.querySelectorAll('#inbox-list .email-item')];
    }

    function sorotBaris(i) {
        const rows = barisEmail();
        if (!rows.length) return;
        kursor = Math.max(0, Math.min(i, rows.length - 1));
        rows.forEach(r => r.style.outline = '');
        const el = rows[kursor];
        el.style.outline = '2px solid var(--tm-accent)';
        el.style.outlineOffset = '-2px';
        el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    document.addEventListener('keydown', function (e) {
        // Jangan bajak tombol saat sedang mengetik atau memakai modifier
        const t = e.target;
        if (t.matches('input, textarea, select') || t.isContentEditable) return;
        if (e.ctrlKey || e.metaKey || e.altKey) return;

        const rows = barisEmail();

        switch (e.key) {
            case 'j': e.preventDefault(); sorotBaris(kursor + 1); break;
            case 'k': e.preventDefault(); sorotBaris(kursor - 1); break;
            case '/': e.preventDefault(); filterForm.elements.q.focus(); filterForm.elements.q.select(); break;
            case 'r': e.preventDefault(); loadInbox(location.href, false); break;
            case 'g': e.preventDefault(); location.href = '{{ route('inbox.index') }}'; break;
            case 'Enter': {
                if (kursor < 0 || !rows[kursor]) return;
                e.preventDefault();
                rows[kursor].querySelector('a[href*="/email/"]')?.click();
                break;
            }
            case 'x': {
                if (kursor < 0 || !rows[kursor]) return;
                e.preventDefault();
                const cb = rows[kursor].querySelector('.bulk-check');
                if (cb) { cb.checked = !cb.checked; updateBulkUI(); }
                break;
            }
            case '#': {
                if (!bulkIds().length) return;
                e.preventDefault();
                bulkAction(@json(request()->boolean('trash')) ? 'purge' : 'delete');
                break;
            }
            case '?':
                e.preventDefault();
                Swal.fire({
                    title: 'Pintasan keyboard',
                    confirmButtonColor: '#6d5df6',
                    html: `<div style="text-align:left;font-size:.9rem;line-height:1.9">
                        <kbd>j</kbd> / <kbd>k</kbd> — turun / naik<br>
                        <kbd>Enter</kbd> — buka email tersorot<br>
                        <kbd>x</kbd> — centang email tersorot<br>
                        <kbd>#</kbd> — hapus yang tercentang<br>
                        <kbd>/</kbd> — fokus ke kolom cari<br>
                        <kbd>r</kbd> — muat ulang daftar<br>
                        <kbd>g</kbd> — kembali ke Inbox<br>
                        <kbd>?</kbd> — bantuan ini</div>`,
                });
                break;
        }
    });
</script>
@endsection
