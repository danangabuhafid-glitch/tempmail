# TempMail Pribadi

Inbox **catch-all** untuk domain sendiri: kirim email ke alamat _apa pun_
(`apapun@domainmu.com`) dan langsung baca di web. Dibangun untuk keperluan
pribadi — daftar layanan, ambil kode OTP, buang alamatnya.

Sifat aplikasi: **receive-only** (hanya menerima, tidak mengirim), **self-hosted**,
satu pemilik + akun terbatas per alamat.

```
Pengirim → MX Cloudflare → Email Routing (catch-all)
        → Email Worker → POST /inbound/email → parse & simpan → web
```

Tanpa mail server sendiri, tanpa polling IMAP, real-time, dan gratis di sisi Cloudflare.

---

## Fitur

### Inbox & membaca email

| Fitur | Keterangan |
|---|---|
| **Deteksi kode OTP** | Kode verifikasi 4–8 digit dideteksi otomatis dari subjek/isi, tampil sebagai chip — klik sekali untuk salin, tanpa perlu membuka emailnya |
| **Catch-all tanpa daftar** | Semua alamat di domainmu otomatis aktif; alamat cukup didaftarkan kalau mau diberi label |
| **Pencarian isi email** | FULLTEXT index (MySQL) atas subjek + isi, plus pengirim & alias |
| **Aksi massal** | Centang beberapa email → hapus / tandai dibaca / belum dibaca sekaligus |
| **Keranjang sampah** | Email terhapus bisa dipulihkan dalam 7 hari sebelum dibuang permanen |
| **Simpan permanen** | Tandai email agar kebal dari auto-hapus retensi |
| **Catatan pribadi** | Tempel pengingat di email ("akun trial, expire 3 Agustus") |
| **Unduh .eml** | Sumber MIME asli tersimpan — bisa diarsipkan atau dibuka di Thunderbird |
| **Lampiran** | Thumbnail gambar, ikon per tipe file, unduh lewat rute ber-auth |
| **Gambar inline (CID)** | Email HTML dengan gambar tertanam tampil utuh lewat signed URL |
| **Auto-refresh** | Cek email baru tiap 15 detik (berhenti saat tab disembunyikan) + notifikasi browser |
| **Statistik** | Grafik email per hari, jam tersibuk, pengirim teratas, alamat teraktif |
| **Keyboard shortcuts** | `j`/`k` navigasi · `Enter` buka · `x` centang · `#` hapus · `/` cari · `?` bantuan |

### Keamanan

- **Sanitasi HTML** server-side (Symfony HtmlSanitizer) + render dalam `iframe sandbox` tanpa `allow-scripts`
- **Blokir gambar remote** default (anti tracking-pixel) — termasuk `srcset`, `poster`, `<video>`, dan `style`, bukan hanya `<img src>`
- **SVG tidak pernah dirender inline** — hanya raster (PNG/JPEG/GIF/WebP/AVIF); sisanya dipaksa unduh dengan tipe dinetralkan + header CSP `sandbox`
- **Proteksi tautan anti-phishing** — semua link email lewat halaman perantara: domain tujuan ditampilkan, ada peringatan punycode / IP mentah / HTTP, dan parameter pelacak (`utm_*`, `fbclid`, dll.) dibuang
- **SPF/DKIM/DMARC tepercaya** — hanya dibaca dari header `Authentication-Results` milik Cloudflare, jadi header palsu sisipan pengirim tidak bisa memalsukan status "pass"
- **Folder Spam otomatis** untuk email yang gagal autentikasi pengirim
- **Skrining lampiran berbahaya** (`.exe`, `.js`, `.html`, ekstensi ganda) — badge merah + konfirmasi sebelum unduh
- **Blokir pengirim** per alamat atau seluruh domain; email berikutnya langsung dibuang
- **2FA TOTP** (Google Authenticator / Aegis / 1Password) + kode recovery sekali pakai
- **Webhook**: shared secret `hash_equals`, token diperiksa sebelum rate limiter, batas ukuran payload, rate limit per menit, dan log setiap request
- **Akun alias**: password acak unik per akun, wajib diganti saat login pertama, hanya bisa melihat kotak masuknya sendiri

### Otomasi

**API REST bertoken** (aktifkan di Pengaturan → API & Bookmarklet):

```bash
# Daftar email
curl -H "Authorization: Bearer TOKEN" "https://mail.domainmu.com/api/emails?alias=belanja"

# Kode OTP terbaru — untuk skrip yang menunggu verifikasi
curl -H "Authorization: Bearer TOKEN" "https://mail.domainmu.com/api/otp?alias=belanja"
# → {"otp":"482913","from":"info@netflix.com","received_at":"..."}

# Buat alias baru dari skrip
curl -H "Authorization: Bearer TOKEN" "https://mail.domainmu.com/api/alias/quick?site=netflix.com&ttl=24h"
```

- **Bookmarklet "Alias Cepat"** — satu klik saat mengisi form pendaftaran: alias bernama situsnya dibuat, tersimpan berlabel, dan langsung tersalin ke clipboard
- **Alias sekali pakai (TTL)** — 1 jam / 24 jam / 7 hari; setelah lewat, email ke alamat itu ditolak dengan bounce
- **Notifikasi Telegram / ntfy.sh** — ringkasan email baru + kode OTP langsung ke HP

### Operasional

- **Monitoring kesehatan per-domain** — NS/MX tiap domain dicek tiap jam; saat satu domain rusak (nameserver pindah, domain expired, catch-all dimatikan) atau pulih, notifikasi terkirim dan banner muncul di aplikasi
- **Dashboard kesehatan** — email terakhir diterima, pemakaian disk, status cron, error webhook 24 jam, waktu backup terakhir, cek DNS on-demand
- **Log webhook** — semua request tercatat termasuk yang ditolak, jadi email "hilang" bisa didiagnosis
- **Heartbeat scheduler** — kalau cron mati, banner merah muncul (tanpa ini, auto-hapus & backup berhenti diam-diam)
- **Backup otomatis** — `tempmail:backup` membuat ZIP berisi dump DB + lampiran + raw .eml, rotasi 7 arsip
- **Retensi & kuota** — auto-hapus email lama; kuota penyimpanan memangkas email tertua bila disk penuh
- **Wizard setup Cloudflare** di web: kode worker siap salin, URL + token webhook, cek DNS, dan tombol email uji

### Tampilan

Dark mode (ikut tema OS, bisa di-toggle) · PWA (bisa di-install ke layar utama HP) ·
responsif dengan menu hamburger · toast & dialog konfirmasi.

---

## Stack

Laravel 12 · PHP 8.4 · MySQL · Blade + Bootstrap 5 · `zbateson/mail-mime-parser` ·
`symfony/html-sanitizer` · Cloudflare Email Routing + Worker.

TOTP diimplementasikan sendiri (RFC 6238, tanpa dependensi tambahan).

---

## Instalasi lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed     # akun pemilik dibuat dari ADMIN_EMAIL / ADMIN_PASSWORD
php artisan serve
```

Env penting:

| Key | Isi |
|---|---|
| `TEMPMAIL_DOMAINS` | daftar domain penerima, dipisah koma |
| `TEMPMAIL_RETENTION_DAYS` | umur email sebelum auto-hapus (default 30) |
| `INBOUND_WEBHOOK_TOKEN` | shared secret dengan Cloudflare Worker |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | akun login pertama |

Sebagian besar pengaturan (domain, retensi, kuota, notifikasi, token API) bisa
diubah lewat halaman **Pengaturan** tanpa menyentuh `.env`.

## Deploy ke VPS

Ubuntu 24.04 yang sudah punya nginx + MySQL — satu perintah:

```bash
# dari laptop
rsync -avz --exclude vendor --exclude node_modules --exclude .env \
    ./ user@ip-vps:/var/www/tempmail

# di VPS
cd /var/www/tempmail && sudo bash deploy/setup-vps.sh
```

Script memasang PHP 8.4 + Composer, membuat database, menulis `.env` produksi,
mengonfigurasi nginx + HTTPS (certbot), memasang cron scheduler, lalu mencetak
`WEBHOOK_URL` + `WEBHOOK_TOKEN` untuk disalin ke Cloudflare Worker.

Update berikutnya: `sudo bash deploy/update.sh`.

Langkah menghubungkan domain ke Cloudflare ada di **[SETUP.md](SETUP.md)**, dan
wizard-nya juga tersedia di halaman `/setup` aplikasi.

## Test

```bash
php artisan test
```

71 test — mencakup pipeline ingest, otorisasi akun alias, sanitasi HTML & XSS,
anti-spoof autentikasi pengirim, keranjang sampah, API, 2FA, dan monitoring domain.

---

Aplikasi privat untuk satu pemilik. Jangan dijadikan layanan temp-mail publik
tanpa menambah kontrol abuse yang jauh lebih ketat.
