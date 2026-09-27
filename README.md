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

### Developer API & Otomasi

Tersedia RESTful API lengkap untuk integrasi bot, script pendaftaran, testing, dan automasi.

**Domain yang Didukung (Semua Aktif):**
1. `@danang.biz.id`
2. `@danangabuhafid.my.id`
3. `@projectdanang.biz.id`

> **Domain Rotasi Otomatis:** Saat memanggil `/api/create` tanpa parameter `domain`, server akan **mengacak domain secara otomatis** dari ketiga domain di atas, sekaligus mendaftarkan alias tersebut di seluruh domain. Anda juga bisa memilih domain tertentu lewat parameter `?domain=danangabuhafid.my.id`.

**Autentikasi:**
Token API didapat dari menu **Pengaturan ➔ API & Bookmarklet** di web.
Kirim via salah satu metode berikut:
- Header: `Authorization: Bearer <TOKEN>`
- Header: `X-Api-Token: <TOKEN>`
- Query string: `?token=<TOKEN>` atau `?api_key=<TOKEN>`

#### Ringkasan Endpoint

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/api/docs` | Dokumentasi publik JSON & panduan endpoint |
| `GET` | `/api/domains` | Daftar semua domain aktif (`danang.biz.id`, `danangabuhafid.my.id`, `projectdanang.biz.id`) |
| `POST` / `GET` | `/api/create` | **Auto-Buat** alamat temp mail baru (acak semua domain / pilih domain + TTL) |
| `GET` | `/api/emails` | Daftar email masuk (filter: `?email=...`, `?alias=...`, `?unread=true`) |
| `GET` | `/api/emails/{id}` | Detail email lengkap (Teks, HTML, Attachment) |
| `GET` | `/api/otp` | Ambil kode OTP terbaru (bisa dari domain mana saja) |
| `GET` | `/api/wait-email` | **Long-polling**: Tunggu email baru masuk (timeout 5-60s) |
| `GET` | `/api/wait-otp` | **Long-polling**: Tunggu kode OTP masuk secara real-time |
| `DELETE` | `/api/emails/{id}` | Hapus email |
| `DELETE` | `/api/alias/{alias}` | Hapus alias temp mail |

#### Contoh cURL

```bash
# 1. Auto-buat email (domain otomatis diacak dari 3 domain aktif)
curl -X POST -H "Authorization: Bearer TOKEN" \
  "https://mail.danang.biz.id/api/create?prefix=bot_&ttl=24h"
# → {"success":true,"email":"bot_x8k2pq@danangabuhafid.my.id","all_domains":["bot_x8k2pq@danangabuhafid.my.id","bot_x8k2pq@danang.biz.id","bot_x8k2pq@projectdanang.biz.id"]}

# 1b. Atau pilih domain spesifik yang diinginkan:
curl -X POST -H "Authorization: Bearer TOKEN" \
  "https://mail.danang.biz.id/api/create?prefix=bot_&domain=projectdanang.biz.id&ttl=24h"

# 2. Ambil daftar email masuk (bisa pakai email lengkap atau alias saja)
curl -H "Authorization: Bearer TOKEN" \
  "https://mail.danang.biz.id/api/emails?email=bot_x8k2pq@projectdanang.biz.id"

# 3. Ambil kode OTP terbaru (otomatis mencari di semua domain jika hanya alias)
curl -H "Authorization: Bearer TOKEN" \
  "https://mail.danang.biz.id/api/otp?alias=bot_x8k2pq"
# → {"success":true,"otp":"126848","subject":"Kode OTP Verifikasi",...}

# 4. Long-polling tunggu OTP masuk (tanpa perlu loop retry manual)
curl -H "Authorization: Bearer TOKEN" \
  "https://mail.danang.biz.id/api/wait-otp?alias=bot_x8k2pq&timeout=45"
```

#### Integrasi Node.js (Puppeteer / Playwright / Axios)

```javascript
const API_URL = 'https://mail.danang.biz.id/api';
const TOKEN = 'YOUR_API_TOKEN';
const headers = { 'Authorization': `Bearer ${TOKEN}` };

// 1. Auto-buat email
const res = await fetch(`${API_URL}/create?prefix=reg_`, { method: 'POST', headers });
const { email } = await res.json();
console.log('Pakai email:', email);

// 2. Daftar di web target dengan email di atas...

// 3. Tunggu kode OTP masuk secara real-time (max 45 detik)
const otpRes = await fetch(`${API_URL}/wait-otp?email=${encodeURIComponent(email)}&timeout=45`, { headers });
const { otp } = await otpRes.json();
console.log('Kode OTP:', otp);
```

#### Integrasi Python (`requests`)

```python
import requests

API_URL = "https://mail.danang.biz.id/api"
headers = {"Authorization": "Bearer YOUR_API_TOKEN"}

# 1. Auto-buat email
res = requests.post(f"{API_URL}/create", params={"prefix": "pybot_"}, headers=headers).json()
email = res["email"]

# 2. Tunggu OTP masuk via Long-Polling
otp_res = requests.get(f"{API_URL}/wait-otp", params={"email": email, "timeout": 45}, headers=headers).json()
print("OTP:", otp_res.get("otp"))
```

- **Bookmarklet "Alias Cepat"** — satu klik saat mengisi form pendaftaran: alias bernama situsnya dibuat, tersimpan berlabel, dan langsung tersalin ke clipboard
- **Alias sekali pakai (TTL)** — 10 menit / 1 jam / 24 jam / 7 hari; setelah lewat, email ke alamat itu ditolak dengan bounce
- **Notifikasi Telegram / ntfy.sh** — ringkasan email baru + kode OTP langsung ke HP
- **Panduan lengkap & spesifikasi**: lihat file [API.md](API.md) atau buka `/api/docs`.

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
