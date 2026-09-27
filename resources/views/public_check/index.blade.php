@extends('layouts.app')

@section('title', ($alias ? "Kotak Masuk: {$alias} — " : '') . 'Cek Email Masuk Tanpa Login — TempMail')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">

        {{-- Hero Header --}}
        <div class="card border-0 text-white mb-4 shadow-sm" style="background: var(--tm-grad); border-radius: 16px; overflow: hidden;">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-white bg-opacity-25 text-white px-3 py-1 rounded-pill">
                        <i class="bi bi-shield-check me-1"></i>Akses Publik — Tanpa Perlu Login
                    </span>
                    <span class="badge bg-success bg-opacity-75 text-white px-2 py-1 rounded-pill">
                        <i class="bi bi-broadcast me-1"></i>Live Catch-All
                    </span>
                </div>
                <h2 class="fw-bold mb-2">🔍 Cek Email Masuk &amp; Kode OTP</h2>
                <p class="mb-0 text-white-50" style="max-width: 680px; font-size: 1.05rem;">
                    Ketik nama email Anda dan pilih domain untuk langsung membaca pesan masuk, isi HTML lengkap, dan mengambil kode OTP secara real-time.
                </p>
            </div>
        </div>

        {{-- Input Form & Domain Selector --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
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
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 px-3 py-1 cursor-pointer" 
                              id="active-email-badge" onclick="copyCurrentAddress()" title="Klik untuk salin alamat">
                            <span id="active-email-text">{{ $alias }}@ {{ $domain === 'all' ? '(Semua Domain)' : $domain }}</span>
                            <i class="bi bi-clipboard ms-1 small"></i>
                        </span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-light border" id="btn-refresh" onclick="refreshInbox(true)">
                            <i class="bi bi-arrow-repeat me-1" id="refresh-icon"></i>Refresh
                        </button>
                        <div class="form-check form-switch mb-0 small">
                            <input class="form-check-input" type="checkbox" id="auto-refresh-switch" checked>
                            <label class="form-check-label text-muted" for="auto-refresh-switch">Auto-Refresh (<span id="countdown">10</span>s)</label>
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
                    {{-- Diisi secara dinamis oleh JavaScript --}}
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="small text-muted mt-2">Memuat email masuk...</p>
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>

{{-- Modal Detail Email Lengkap --}}
<div class="modal fade" id="emailDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2 flex-grow-1 overflow-hidden">
                    <span class="badge bg-primary-subtle text-primary"><i class="bi bi-envelope me-1"></i>Isi Pesan</span>
                    <h6 class="modal-title fw-bold text-truncate" id="modal-email-subject">Detail Email</h6>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="modal-email-body">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
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
    .email-iframe-view {
        width: 100%;
        min-height: 480px;
        border: 1px solid var(--tm-border);
        border-radius: 10px;
        background: #fff;
    }
</style>

<script>
    let currentAlias = "{{ $alias }}";
    let currentDomain = "{{ $domain }}";
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

        if (currentAlias) {
            refreshInbox(false);
            startAutoRefresh();
        }

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

        // Update URL tanpa reload
        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set('alias', currentAlias);
        newUrl.searchParams.set('domain', currentDomain);
        window.history.pushState({}, '', newUrl);

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
            const url = `{{ route('public.check') }}?alias=${encodeURIComponent(currentAlias)}&domain=${encodeURIComponent(currentDomain)}&json=1`;
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
                        <div class="d-flex align-items-center gap-2">
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
                            <span class="small text-muted">Klik kartu ini untuk membaca pesan lengkap & format HTML...</span>
                        </div>
                        ${otpBadge}
                    </div>
                </div>
            `;
        });

        html += `</div>`;
        container.innerHTML = html;
    }

    async function openEmailDetail(emailId) {
        const modal = new bootstrap.Modal(document.getElementById('emailDetailModal'));
        const modalBody = document.getElementById('modal-email-body');
        const modalSubject = document.getElementById('modal-email-subject');

        modalSubject.innerText = 'Memuat email...';
        modalBody.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="small text-muted mt-2">Membuka pesan & mengurai HTML...</p>
            </div>
        `;
        modal.show();

        try {
            const res = await fetch(`{{ url('/cek/email') }}/${emailId}`);
            const data = await res.json();
            const em = data.email;

            modalSubject.innerText = em.subject;

            let otpBox = '';
            if (em.otp) {
                otpBox = `
                    <div class="alert alert-primary border-primary d-flex align-items-center justify-content-between p-3 mb-3" style="border-radius: 12px;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-shield-lock-fill fs-3 text-primary"></i>
                            <div>
                                <div class="fw-bold text-primary">KODE VERIFIKASI / OTP TERDETEKSI</div>
                                <div class="small text-muted">Klik tombol di samping untuk menyalin kode langsung ke clipboard.</div>
                            </div>
                        </div>
                        <button class="btn btn-primary px-3 py-2 fw-bold font-monospace fs-5" onclick="copyText('${em.otp}', event)">
                            ${em.otp} <i class="bi bi-clipboard ms-1"></i>
                        </button>
                    </div>
                `;
            }

            let attachHtml = '';
            if (em.attachments && em.attachments.length > 0) {
                attachHtml = `
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1"><i class="bi bi-paperclip me-1"></i>Lampiran (${em.attachments.length})</label>
                        <div class="d-flex flex-wrap gap-2">
                            ${em.attachments.map(a => `
                                <a href="${a.download_url}" class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1" target="_blank">
                                    <i class="bi bi-download"></i>
                                    <span>${escapeHtml(a.filename)}</span>
                                    <span class="badge text-bg-secondary">${a.size}</span>
                                </a>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            modalBody.innerHTML = `
                ${otpBox}

                {{-- Info Metadata Header --}}
                <div class="card bg-body-tertiary border-0 p-3 mb-3" style="border-radius: 12px;">
                    <div class="row g-2 small">
                        <div class="col-md-6">
                            <span class="text-muted d-block">Dari:</span>
                            <strong class="text-break">${escapeHtml(em.from_name)} &lt;${escapeHtml(em.from_address)}&gt;</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block">Kepada:</span>
                            <strong class="text-break">${escapeHtml(em.to)}</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block">Waktu:</span>
                            <span>${escapeHtml(em.received_time)} (${em.received_diff})</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block">Autentikasi Pengirim:</span>
                            <span class="badge ${em.spf === 'pass' ? 'text-bg-success' : 'text-bg-secondary'} me-1">SPF: ${em.spf || '-'}</span>
                            <span class="badge ${em.dkim === 'pass' ? 'text-bg-success' : 'text-bg-secondary'}">DKIM: ${em.dkim || '-'}</span>
                        </div>
                    </div>
                </div>

                ${attachHtml}

                {{-- Tabs Tampilan HTML vs Plaintext --}}
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
                        ${em.has_html ? `
                            <iframe class="email-iframe-view" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin" srcdoc="${escapeHtmlAttr(em.html_body)}"></iframe>
                        ` : `
                            <div class="p-3 bg-light rounded text-muted small">Email ini tidak memiliki konten HTML. Silakan lihat tab Teks Polos.</div>
                        `}
                    </div>
                    <div class="tab-pane fade" id="tab-body-text">
                        <pre class="p-3 bg-body-tertiary border rounded" style="white-space: pre-wrap; font-family: ui-monospace, monospace; font-size: .88rem;">${escapeHtml(em.text_body || '(Teks kosong)')}</pre>
                    </div>
                </div>
            `;
        } catch (err) {
            modalBody.innerHTML = `<div class="alert alert-danger">Gagal membuka email: ${err.message}</div>`;
        }
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

    function escapeHtmlAttr(str) {
        if (!str) return '';
        return String(str).replace(/"/g, '&quot;');
    }
</script>
@endsection
