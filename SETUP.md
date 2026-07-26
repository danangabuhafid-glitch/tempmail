# Setup — TempMail Pribadi

Panduan menghubungkan domain `danangabuhafid.my.id` dan `danang.biz.id`
ke aplikasi ini via Cloudflare Email Routing.

## 1. Jalankan aplikasi (lokal / VPS)

```bash
composer install
cp .env.example .env          # lalu isi (lihat bagian Env di bawah)
php artisan key:generate
php artisan migrate --seed    # seed membuat akun login dari ADMIN_EMAIL/ADMIN_PASSWORD
php artisan serve             # atau arahkan vhost/nginx ke folder public/
```

Env penting di `.env`:

| Key | Isi |
|---|---|
| `TEMPMAIL_DOMAINS` | `danangabuhafid.my.id,danang.biz.id` |
| `TEMPMAIL_RETENTION_DAYS` | umur email sebelum auto-hapus (default 30) |
| `INBOUND_WEBHOOK_TOKEN` | string acak panjang — samakan dengan secret di Worker |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | akun login pertama (password bisa diganti dari menu Pengaturan) |

> **Penting:** ganti `ADMIN_PASSWORD` sebelum deploy, atau segera ganti lewat
> menu **Pengaturan** setelah login pertama.

## 2. Pindahkan domain ke Cloudflare (sekali saja per domain)

1. Daftar/masuk https://dash.cloudflare.com → **Add a domain** → masukkan domain.
2. Pilih plan **Free** → Cloudflare menampilkan 2 nameserver (mis. `xxx.ns.cloudflare.com`).
3. Buka panel registrar tempat domain dibeli → ganti nameserver ke milik Cloudflare.
4. Tunggu aktif (biasanya < 1 jam untuk .my.id/.biz.id).

## 3. Aktifkan Email Routing + Worker

1. Di dashboard Cloudflare pilih domain → menu **Email** → **Email Routing** → **Enable**.
   Cloudflare otomatis memasang record MX & SPF yang dibutuhkan.
2. Menu **Workers & Pages** → **Create Worker** → beri nama `tempmail-forwarder`
   → tempel isi `cloudflare/email-worker.js` → **Deploy**.
3. Di worker → **Settings → Variables and Secrets**, tambahkan:
   - `WEBHOOK_URL` = `https://<domain-aplikasi>/inbound/email`
   - `WEBHOOK_TOKEN` = nilai `INBOUND_WEBHOOK_TOKEN` di `.env` (set sebagai **Secret**)
4. Kembali ke **Email → Email Routing → Routing rules**:
   - **Catch-all address** → Action: **Send to a Worker** → pilih `tempmail-forwarder` → Save.
5. Ulangi langkah 1 dan 4 untuk domain kedua (worker yang sama bisa dipakai bersama).

## 4. Tes

Kirim email dari Gmail/apa pun ke `tesapa aja@danangabuhafid.my.id`
(alamat bebas — catch-all) → refresh Inbox aplikasi → email muncul.

## 5. Development lokal (webhook perlu jalur masuk)

Webhook Cloudflare tidak bisa menjangkau `localhost`. Saat develop di XAMPP:

```bash
cloudflared tunnel --url http://localhost:8000
```

lalu set `WEBHOOK_URL` worker sementara ke URL tunnel yang diberikan.
Untuk produksi, arahkan ke domain VPS yang sudah ber-HTTPS.

## 6. Scheduler (auto-hapus email lama)

Tambahkan cron di server (script deploy di bawah sudah memasangnya otomatis):

```
* * * * * cd /path/ke/tempmail && php artisan schedule:run >> /dev/null 2>&1
```

Yang berjalan lewat scheduler: auto-hapus retensi (harian), backup DB + lampiran
(harian 03:00, rotasi 7 arsip), pemangkas kuota penyimpanan, heartbeat (jam-jaman —
kalau cron mati, banner merah muncul di aplikasi), pembersih log webhook, dan
**cek kesehatan per-domain** (jam-jaman): NS/MX tiap domain diperiksa — begitu ada
domain yang rusak atau pulih, notifikasi Telegram/ntfy terkirim dan banner + status
per-domain muncul di halaman Setup/Pengaturan.

## 7. Deploy ke VPS (Ubuntu 24.04 — nginx + MySQL sudah terpasang)

Satu kali jalan, semua beres (PHP 8.4, Composer, database, .env, nginx, HTTPS, cron):

```bash
# 1. Upload kode dari laptop ke VPS (dari folder project di laptop):
rsync -avz --exclude vendor --exclude node_modules --exclude .env \
    ./ user@ip-vps:/var/www/tempmail

# 2. SSH ke VPS, lalu:
cd /var/www/tempmail
sudo bash deploy/setup-vps.sh
```

Script akan bertanya: domain aplikasi (mis. `mail.danang.biz.id`), domain penerima
email, kredensial MySQL & akun pemilik (enter = digenerate acak) — lalu di akhir
mencetak **WEBHOOK_URL + WEBHOOK_TOKEN** yang tinggal disalin ke variabel worker
Cloudflare. Sebelum menjalankan certbot, pastikan DNS domain aplikasi (A record)
sudah mengarah ke IP VPS — di Cloudflare buat A record dengan proxy **DNS only**
dulu sampai sertifikat terbit.

Untuk update kode berikutnya:

```bash
rsync -avz --exclude vendor --exclude node_modules --exclude .env \
    --exclude storage ./ user@ip-vps:/var/www/tempmail
ssh user@ip-vps 'cd /var/www/tempmail && sudo bash deploy/update.sh'
```

## Catatan keamanan

- Endpoint `/inbound/email` hanya menerima request ber-token (`X-Webhook-Token`).
- HTML email disanitasi server-side + dirender dalam iframe sandbox; gambar remote
  diblokir default (anti tracking-pixel).
- Lampiran disimpan di disk privat dan hanya bisa diunduh setelah login.
- Aplikasi ini **menerima saja** — tidak bisa mengirim email.
