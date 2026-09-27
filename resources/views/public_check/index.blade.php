@extends('layouts.app')

@section('title', ($alias ? "Kotak Masuk: {$alias} — " : '') . 'Cek Email Masuk Tanpa Login — TempMail')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">

        {{-- ===== TAMPILAN 1: KOTAK MASUK & DAFTAR EMAIL ===== --}}
        <div id="view-inbox">
            {{-- Hero Header --}}
            <div class="card border-0 text-white mb-4 shadow-sm" style="background: var(--tm-grad); border-radius: 16px; overflow: hidden;">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                        <span class="badge bg-white bg-opacity-25 text-white px-3 py-1 rounded-pill">
                            <i class="bi bi-shield-check me-1"></i>Akses Publik — Tanpa Perlu Login
                        </span>
                        <span class="badge bg-success bg-opacity-75 text-white px-2 py-1 rounded-pill">
                            <i class="bi bi-broadcast me-1"></i>Live Catch-All
                        </span>
                    </div>
                    <h2 class="fw-bold mb-2 fs-3 fs-md-2">🔍 Cek Email Masuk &amp; Kode OTP</h2>
                    <p class="mb-0 text-white-50" style="max-width: 680px; font-size: 1rem;">
                        Ketik nama email Anda dan pilih domain untuk langsung membaca pesan masuk, isi HTML lengkap, dan mengambil kode OTP secara real-time.
                    </p>
                </div>
            </div>

            {{-- Input Form & Domain Selector --}}
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                <div class="card-body p-3 p-md-4">
                    <form id="check-form" onsubmit="event.preventDefault(); submitCheck();">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold text-muted mb-1">Username / Alamat Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-body-tertiary border-end-0"><i class="bi bi-envelope-at text-muted"></i></span>
                                    <input type="text" id="input-alias" class="form-control border-start-0" 
                                           placeholder="mis. bot_tester atau ketik email lengkap" 
                                           value="{{ $alias }}" autofocus autocomplete="off" spellcheck="false">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted mb-1">Pilih Domain</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-body-tertiary"><i class="bi bi-globe2 text-muted"></i></span>
                                    <select id="select-domain" class="form-select">
                                        <option value="all" {{ $domain === 'all' || empty($domain) ? 'selected' : '' }}>Semua Domain (Cari di 3 Domain)</option>
                                        @foreach($domains as $d)
                                            <option value="{{ $d }}" {{ $domain === $d ? 'selected' : '' }}>@ {{ $d }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 mt-md-4">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-tm flex-grow-1" id="btn-submit">
                                        <i class="bi bi-search me-1"></i>Periksa
                                    </button>
                                    <button type="button" class="btn btn-light border" id="btn-random" title="Generate Alamat Acak" onclick="generateRandomAlias()">
                                        <i class="bi bi-shuffle"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    {{-- Status Bar saat email aktif --}}
                    <div id="active-box-bar" class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-3 border-top gap-2 {{ empty($alias) ? 'd-none' : '' }}">
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-muted">Kotak Masuk:</span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 px-3 py-1 cursor-pointer text-break" 
                                  id="active-email-badge" onclick="copyCurrentAddress()" title="Klik untuk salin alamat">
                                <span id="active-email-text">{{ $alias }}@ {{ $domain === 'all' ? '(Semua Domain)' : $domain }}</span>
                                <i class="bi bi-clipboard ms-1 small"></i>
                            </span>
                        </div>

                        <div class="d-flex align-items-center gap-2 ms-auto">
                            <button type="button" class="btn btn-sm btn-light border" id="btn-refresh" onclick="refreshInbox(true)">
                                <i class="bi bi-arrow-repeat me-1" id="refresh-icon"></i>Refresh
                            </button>
                            <div class="form-check form-switch mb-0 small">
                                <input class="form-check-input" type="checkbox" id="auto-refresh-switch" checked>
                                <label class="form-check-label text-muted" for="auto-refresh-switch">Auto (<span id="countdown">10</span>s)</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Container Hasil Inbox --}}
            <div id="inbox-container">
                @if(empty($alias))
                    {{-- State Belum Ada Input --}}
                    <div class="card border-0 shadow-sm text-center py-5" style="border-radius: 16px;">
                        <div class="card-body">
                            <div class="display-5 text-muted opacity-50 mb-3"><i class="bi bi-mailbox2"></i></div>
                            <h5 class="fw-bold mb-2">Masukkan Nama Alamat Email Anda</h5>
                            <p class="text-muted small mx-auto" style="max-width: 480px;">
                                Masukkan username atau klik tombol acak <i class="bi bi-shuffle text-primary"></i> di atas untuk memeriksa pesan masuk dan kode OTP secara instan tanpa perlu mendaftar.
                            </p>
                        </div>
                    </div>
                @else
                    <div id="emails-list-wrapper">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="small text-muted mt-2">Memuat email masuk...</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ===== TAMPILAN 2: BACA EMAIL LENGKAP (IN-PAGE / BUKAN MODAL) ===== --}}
        <div id="view-detail" class="d-none">
            {{-- Tombol Navigasi Kembali --}}
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <button type="button" class="btn btn-light border shadow-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2" onclick="backToInbox()">
                    <i class="bi bi-arrow-left fs-5"></i>
                    <span>Kembali ke Kotak Masuk</span>
                </button>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 small d-none d-sm-inline-flex align-items-center gap-1">
                        <i class="bi bi-envelope"></i>
                        <span id="detail-recipient"></span>
                    </span>
                    <button type="button" class="btn btn-light border shadow-sm" onclick="refreshCurrentEmail()" title="Muat ulang email ini">
                        <i class="bi bi-arrow-clockwise" id="detail-refresh-icon"></i>
                    </button>
                </div>
            </div>

            {{-- Kartu Utama Detail Email --}}
            <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                <div class="card-body p-3 p-md-4">
                    {{-- Spinner Loading Pesan --}}
                    <div id="detail-loader" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="small text-muted mt-2">Membuka pesan email &amp; mengurai HTML...</p>
                    </div>

                    {{-- Isi Pesan Email --}}
                    <div id="detail-content" class="d-none">
                        {{-- Judul Subjek --}}
                        <h4 class="fw-bold mb-3 text-break" id="detail-subject" style="color: var(--tm-ink);"></h4>

                        {{-- Banner OTP / Kode Verifikasi Terdeteksi --}}
                        <div id="detail-otp-box" class="alert alert-primary border-primary d-none flex-wrap align-items-center justify-content-between p-3 p-md-4 mb-4 gap-3" style="border-radius: 14px;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-primary bg-opacity-10 p-2 p-md-3 text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                                    <i class="bi bi-shield-lock-fill fs-3"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-primary fs-6">KODE VERIFIKASI / OTP TERDETEKSI</div>
                                    <div class="small text-muted">Klik tombol di samping untuk menyalin kode langsung ke clipboard.</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary px-4 py-2 fw-bold font-monospace fs-4 shadow-sm d-flex align-items-center gap-2" id="detail-otp-btn" onclick="copyDetailOtp()">
                                <span id="detail-otp-code"></span>
                                <i class="bi bi-clipboard fs-5"></i>
                            </button>
                        </div>

                        {{-- Metadata Box: Pengirim, Penerima, Waktu, Autentikasi --}}
                        <div class="card bg-body-tertiary border-0 p-3 mb-4" style="border-radius: 12px;">
                            <div class="row g-2 small">
                                <div class="col-12 col-md-6">
                                    <span class="text-muted d-block">Dari:</span>
                                    <strong class="text-break" id="detail-from"></strong>
                                </div>
                                <div class="col-12 col-md-6">
                                    <span class="text-muted d-block">Kepada:</span>
                                    <strong class="text-break" id="detail-to"></strong>
                                </div>
                                <div class="col-12 col-md-6">
                                    <span class="text-muted d-block">Waktu Diterima:</span>
                                    <span id="detail-time"></span>
                                </div>
                                <div class="col-12 col-md-6">
                                    <span class="text-muted d-block">Autentikasi Pengirim:</span>
                                    <span class="badge me-1" id="detail-spf"></span>
                                    <span class="badge" id="detail-dkim"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Daftar Lampiran --}}
                        <div id="detail-attachments-box" class="mb-4 d-none">
                            <label class="form-label small fw-semibold text-muted mb-2"><i class="bi bi-paperclip me-1"></i>Lampiran File</label>
                            <div class="d-flex flex-wrap gap-2" id="detail-attachments-list"></div>
                        </div>

                        {{-- Nav Tabs: HTML Asli vs Teks Polos --}}
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-body-html" type="button">
                                    <i class="bi bi-window me-1"></i>Tampilan Asli (HTML)
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-body-text" type="button">
                                    <i class="bi bi-file-text me-1"></i>Teks Polos
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="tab-body-html">
                                <div class="email-iframe-wrapper" style="width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid var(--tm-border); border-radius: 12px; background: #ffffff;">
                                    <iframe id="detail-iframe" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin" style="width: 100%; border: 0; min-height: 480px; display: block; background: #ffffff;"></iframe>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tab-body-text">
                                <pre id="detail-text" class="p-3 bg-body-tertiary border rounded" style="white-space: pre-wrap; word-break: break-word; font-family: ui-monospace, monospace; font-size: .88rem; max-height: 650px; overflow-y: auto;"></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    .cursor-pointer { cursor: pointer; }
    .email-card {
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        border-radius: 14px;
    }
    .email-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,.08);
        border-color: var(--tm-accent) !important;
    }
    .otp-chip-large {
        background: #edeafe;
        color: #5b21b6;
        border: 1px dashed #7c3aed;
        border-radius: 10px;
        font-family: ui-monospace, SFMono-Regular, monospace;
        letter-spacing: 2px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    [data-bs-theme="dark"] .otp-chip-large {
        background: #2e1065;
        color: #ddd6fe;
        border-color: #8b5cf6;
    }
    .email-iframe-wrapper {
        box-shadow: inset 0 1px 3px rgba(0,0,0,.03);
    }
    @media (max-width: 576px) {
        .card-body {
            padding: 1rem !important;
        }
        #detail-otp-box {
            padding: 1rem !important;
        }
        #detail-otp-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<script>
    let currentAlias = "{{ $alias }}";
    let currentDomain = "{{ $domain }}";
    let currentEmailId = null;
    let autoRefreshTimer = null;
    let countdownSec = 10;
    let countdownInterval = null;

    document.addEventListener('DOMContentLoaded', () => {
        // Deteksi jika user paste email utuh di input alias
        const inputAlias = document.getElementById('input-alias');
        inputAlias.addEventListener('input', () => {
            const val = inputAlias.value.trim();
            if (val.includes('@')) {
                const parts = val.split('@');
                inputAlias.value = parts[0];
                const domainSelect = document.getElementById('select-domain');
                for (let opt of domainSelect.options) {
                    if (opt.value === parts[1]) {
                        domainSelect.value = parts[1];
                        break;
                    }
                }
            }
        });

        // Trigger check saat ganti domain
        document.getElementById('select-domain').addEventListener('change', () => {
            const aliasVal = document.getElementById('input-alias').value.trim();
            if (aliasVal) submitCheck();
        });

        // Toggle auto refresh
        document.getElementById('auto-refresh-switch').addEventListener('change', (e) => {
            if (e.target.checked) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });

        // Periksa apakah URL memuat parameter email_id langsung
        const urlParams = new URLSearchParams(window.location.search);
        const urlEmailId = urlParams.get('email_id');

        if (urlEmailId) {
            openEmailDetail(urlEmailId, false);
        } else if (currentAlias) {
            refreshInbox(false);
            startAutoRefresh();
        }

        // Tangani navigasi tombol Back/Forward browser / HP
        window.addEventListener('popstate', (e) => {
            const params = new URLSearchParams(window.location.search);
            const eid = params.get('email_id');
            if (eid) {
                openEmailDetail(eid, false);
            } else {
                showInboxView(false);
            }
        });

        // Auto adjust iframe saat resize layar
        window.addEventListener('resize', adjustIframeHeight);
    });

    function generateRandomAlias() {
        const rand = 'user_' + Math.random().toString(36).substring(2, 8);
        document.getElementById('input-alias').value = rand;
        submitCheck();
    }

    function submitCheck() {
        const aliasInput = document.getElementById('input-alias').value.trim();
        const domainSelect = document.getElementById('select-domain').value;

        if (!aliasInput) {
            if (typeof tmToast === 'function') {
                tmToast('warning', 'Masukkan nama username email terlebih dahulu.');
            } else {
                alert('Masukkan nama username email terlebih dahulu.');
            }
            return;
        }

        currentAlias = aliasInput.toLowerCase().replace(/[^a-z0-9._-]+/g, '');
        currentDomain = domainSelect;

        // Pastikan tampilan kembali ke list inbox jika sedang di dalam email
        showInboxView(false);

        // Update URL tanpa reload
        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set('alias', currentAlias);
        newUrl.searchParams.set('domain', currentDomain);
        newUrl.searchParams.delete('email_id');
        window.history.pushState({ view: 'inbox' }, '', newUrl);

        // Update bar status
        document.getElementById('active-box-bar').classList.remove('d-none');
        document.getElementById('active-email-text').innerText = `${currentAlias}@${currentDomain === 'all' ? '(Semua Domain)' : currentDomain}`;

        refreshInbox(true);
        startAutoRefresh();
    }

    async function refreshInbox(showSpinner = false) {
        if (!currentAlias) return;

        const icon = document.getElementById('refresh-icon');
        if (icon) icon.classList.add('spin-anim');

        const wrapper = document.getElementById('emails-list-wrapper') || document.getElementById('inbox-container');
        if (showSpinner && wrapper) {
            wrapper.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="small text-muted mt-2">Mengecek email masuk...</p>
                </div>
            `;
        }

        try {
            const url = `{{ route('public.check') }}?alias=${encodeURIComponent(currentAlias)}&domain=${encodeURIComponent(currentDomain)}&json=1&v=2`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            renderEmails(data.emails || []);
        } catch (err) {
            console.error(err);
            if (wrapper) {
                wrapper.innerHTML = `<div class="alert alert-danger">Gagal mengambil email: ${err.message}</div>`;
            }
        } finally {
            if (icon) icon.classList.remove('spin-anim');
            countdownSec = 10;
        }
    }

    function renderEmails(emails) {
        const container = document.getElementById('inbox-container');
        if (!container) return;

        if (emails.length === 0) {
            container.innerHTML = `
                <div class="card border-0 shadow-sm text-center py-5" style="border-radius: 16px;">
                    <div class="card-body">
                        <div class="display-6 text-muted opacity-50 mb-3"><i class="bi bi-inbox"></i></div>
                        <h5 class="fw-bold mb-1">Belum Ada Email Masuk</h5>
                        <p class="text-muted small mx-auto mb-3" style="max-width: 480px;">
                            Kotak masuk untuk <code>${escapeHtml(currentAlias)}@${escapeHtml(currentDomain)}</code> masih kosong. Kirim email atau request OTP sekarang, lalu tunggu auto-refresh.
                        </p>
                        <button class="btn btn-sm btn-light border" onclick="refreshInbox(true)">
                            <i class="bi bi-arrow-repeat me-1"></i>Periksa Sekarang
                        </button>
                    </div>
                </div>
            `;
            return;
        }

        let html = `<div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0 text-muted"><i class="bi bi-inboxes me-1"></i>Daftar Email Masuk (${emails.length})</h6>
            <span class="badge text-bg-secondary font-monospace">Terakhir diperbarui: baru saja</span>
        </div>`;

        html += `<div class="d-flex flex-column gap-3">`;

        emails.forEach(e => {
            const otpBadge = e.otp ? `
                <div class="mt-2 mt-md-0 d-flex align-items-center gap-2">
                    <span class="otp-chip-large px-3 py-1 fs-6" onclick="copyText('${e.otp}', event)">
                        <i class="bi bi-key-fill text-warning"></i>
                        <span>${e.otp}</span>
                        <i class="bi bi-clipboard small ms-1"></i>
                    </span>
                    <span class="small text-muted d-none d-md-inline">Kode OTP</span>
                </div>
            ` : '';

            const attachBadge = e.has_attachments ? `<span class="badge bg-light text-muted border me-1"><i class="bi bi-paperclip"></i> Lampiran</span>` : '';

            html += `
                <div class="card border shadow-sm email-card cursor-pointer p-3 p-md-4" onclick="openEmailDetail(${e.id})">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"><i class="bi bi-envelope-open me-1"></i>${escapeHtml(e.to)}</span>
                            <span class="fw-bold fs-6 text-truncate" style="max-width: 280px;">${escapeHtml(e.from_name)}</span>
                            <span class="text-muted small d-none d-md-inline">&lt;${escapeHtml(e.from)}&gt;</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            ${attachBadge}
                            <span class="text-muted small" title="${escapeHtml(e.received_time)}"><i class="bi bi-clock me-1"></i>${e.received_diff}</span>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1" style="color: var(--tm-ink);">${escapeHtml(e.subject)}</h6>
                            <span class="small text-muted"><i class="bi bi-box-arrow-in-right me-1"></i>Buka pesan lengkap &amp; format HTML...</span>
                        </div>
                        ${otpBadge}
                    </div>
                </div>
            `;
        });

        html += `</div>`;
        container.innerHTML = html;
    }

    // ===== BUKA DETAIL EMAIL (IN-PAGE BUKAN MODAL) =====
    async function openEmailDetail(emailId, updateHistory = true) {
        currentEmailId = emailId;
        stopAutoRefresh();

        // Update URL browser
        if (updateHistory) {
            const newUrl = new URL(window.location.href);
            newUrl.searchParams.set('email_id', emailId);
            window.history.pushState({ view: 'detail', emailId: emailId }, '', newUrl);
        }

        // Tampilkan view detail, sembunyikan view inbox
        document.getElementById('view-inbox').classList.add('d-none');
        document.getElementById('view-detail').classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });

        // Tampilkan loader
        document.getElementById('detail-loader').classList.remove('d-none');
        document.getElementById('detail-content').classList.add('d-none');

        try {
            const res = await fetch(`{{ url('/cek/email') }}/${emailId}?v=2`);
            if (!res.ok) throw new Error('Gagal memuat email (HTTP ' + res.status + ')');
            const data = await res.json();
            const em = data.email;

            // Render Subjek & Header
            document.getElementById('detail-subject').innerText = em.subject;
            document.getElementById('detail-recipient').innerText = em.to;
            document.getElementById('detail-from').innerHTML = `${escapeHtml(em.from_name)} &lt;${escapeHtml(em.from_address)}&gt;`;
            document.getElementById('detail-to').innerText = em.to;
            document.getElementById('detail-time').innerText = `${em.received_time} (${em.received_diff})`;

            // SPF & DKIM
            const spfEl = document.getElementById('detail-spf');
            spfEl.className = 'badge ' + (em.spf === 'pass' ? 'text-bg-success' : 'text-bg-secondary');
            spfEl.innerText = 'SPF: ' + (em.spf || '-');

            const dkimEl = document.getElementById('detail-dkim');
            dkimEl.className = 'badge ' + (em.dkim === 'pass' ? 'text-bg-success' : 'text-bg-secondary');
            dkimEl.innerText = 'DKIM: ' + (em.dkim || '-');

            // Banner OTP
            const otpBox = document.getElementById('detail-otp-box');
            if (em.otp) {
                document.getElementById('detail-otp-code').innerText = em.otp;
                otpBox.classList.remove('d-none');
                otpBox.classList.add('d-flex');
            } else {
                otpBox.classList.add('d-none');
                otpBox.classList.remove('d-flex');
            }

            // Lampiran
            const attachBox = document.getElementById('detail-attachments-box');
            const attachList = document.getElementById('detail-attachments-list');
            if (em.attachments && em.attachments.length > 0) {
                attachList.innerHTML = em.attachments.map(a => `
                    <a href="${a.download_url}" class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1" target="_blank">
                        <i class="bi bi-download"></i>
                        <span>${escapeHtml(a.filename)}</span>
                        <span class="badge text-bg-secondary">${a.size}</span>
                    </a>
                `).join('');
                attachBox.classList.remove('d-none');
            } else {
                attachBox.classList.add('d-none');
                attachList.innerHTML = '';
            }

            // Teks Polos
            document.getElementById('detail-text').innerText = em.text_body || '(Tidak ada konten teks polos)';

            // HTML Iframe
            const iframe = document.getElementById('detail-iframe');
            if (em.has_html) {
                iframe.srcdoc = em.html_body;
                iframe.onload = () => {
                    setTimeout(adjustIframeHeight, 150);
                    setTimeout(adjustIframeHeight, 600);
                    setTimeout(adjustIframeHeight, 1500);
                };
            } else {
                iframe.srcdoc = '<p style="font-family:sans-serif;color:#666;padding:16px;">Email ini tidak memiliki konten HTML. Silakan buka tab Teks Polos.</p>';
            }

            // Sembunyikan loader, munculkan konten
            document.getElementById('detail-loader').classList.add('d-none');
            document.getElementById('detail-content').classList.remove('d-none');

            // Trigger auto adjust height
            setTimeout(adjustIframeHeight, 250);

        } catch (err) {
            document.getElementById('detail-loader').innerHTML = `
                <div class="alert alert-danger py-4">
                    <i class="bi bi-exclamation-triangle fs-3 d-block mb-2"></i>
                    <strong>Gagal membuka email:</strong> ${escapeHtml(err.message)}
                    <div class="mt-3">
                        <button class="btn btn-sm btn-outline-danger" onclick="openEmailDetail(${emailId}, false)">
                            <i class="bi bi-arrow-clockwise me-1"></i>Coba Lagi
                        </button>
                    </div>
                </div>
            `;
        }
    }

    function refreshCurrentEmail() {
        if (!currentEmailId) return;
        const icon = document.getElementById('detail-refresh-icon');
        if (icon) icon.classList.add('spin-anim');
        openEmailDetail(currentEmailId, false).finally(() => {
            if (icon) icon.classList.remove('spin-anim');
        });
    }

    function backToInbox() {
        showInboxView(true);
    }

    function showInboxView(updateHistory = true) {
        currentEmailId = null;
        document.getElementById('view-detail').classList.add('d-none');
        document.getElementById('view-inbox').classList.remove('d-none');

        if (updateHistory) {
            const newUrl = new URL(window.location.href);
            newUrl.searchParams.delete('email_id');
            window.history.pushState({ view: 'inbox' }, '', newUrl);
        }

        // Jalankan auto refresh kembali jika diaktifkan
        const sw = document.getElementById('auto-refresh-switch');
        if (sw && sw.checked) {
            startAutoRefresh();
        }
    }

    function adjustIframeHeight() {
        const iframe = document.getElementById('detail-iframe');
        if (!iframe) return;
        try {
            const doc = iframe.contentWindow.document;
            if (doc) {
                const body = doc.body;
                const html = doc.documentElement;
                const h = Math.max(
                    body ? body.scrollHeight : 0,
                    html ? html.scrollHeight : 0,
                    body ? body.offsetHeight : 0
                );
                if (h > 120) {
                    iframe.style.height = (h + 30) + 'px';
                }
            }
        } catch (e) {
            // cross-origin safety
        }
    }

    function copyDetailOtp() {
        const code = document.getElementById('detail-otp-code').innerText.trim();
        if (code) copyText(code);
    }

    function startAutoRefresh() {
        stopAutoRefresh();
        countdownSec = 10;
        updateCountdownUI();

        countdownInterval = setInterval(() => {
            countdownSec--;
            if (countdownSec <= 0) {
                refreshInbox(false);
                countdownSec = 10;
            }
            updateCountdownUI();
        }, 1000);
    }

    function stopAutoRefresh() {
        if (countdownInterval) clearInterval(countdownInterval);
        countdownInterval = null;
    }

    function updateCountdownUI() {
        const el = document.getElementById('countdown');
        if (el) el.innerText = countdownSec;
    }

    function copyCurrentAddress() {
        const fullAddr = `${currentAlias}@${currentDomain === 'all' ? '{{ $domains[0] }}' : currentDomain}`;
        copyText(fullAddr);
    }

    function copyText(text, event) {
        if (event) event.stopPropagation();
        navigator.clipboard.writeText(text).then(() => {
            if (typeof tmToast === 'function') {
                tmToast('success', 'Disalin: ' + text);
            } else {
                alert('Disalin: ' + text);
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
</script>
@endsection
