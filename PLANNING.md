# Planning — TempMail Pribadi

Web app untuk menerima dan membaca email yang dikirim ke alamat apa pun (catch-all)
di dua domain milik sendiri:

- `*@danangabuhafid.my.id`
- `*@danang.biz.id`

Sifat: **privat, single-user** (hanya untuk pemilik domain), **receive-only**
(tidak mengirim email — hanya menerima dan menampilkan).

---

## 1. Keputusan arsitektur utama: bagaimana email masuk ke aplikasi

Ini keputusan paling penting sebelum koding. Ada 3 opsi:

### Opsi A — Cloudflare Email Routing + Email Worker → Webhook (REKOMENDASI)
Alur: `Pengirim → MX Cloudflare → Email Routing (catch-all) → Email Worker → POST raw email ke endpoint aplikasi → parse & simpan DB`

- **Syarat:** NS kedua domain diarahkan ke Cloudflare (gratis), aplikasi harus bisa
  diakses publik via HTTPS (hosting/VPS; saat development bisa pakai tunnel
  `cloudflared`/ngrok ke XAMPP lokal).
- **Plus:** gratis, tanpa mail server sendiri, catch-all unlimited alias, anti-spam
  dasar dari Cloudflare, real-time (push, bukan polling).
- **Minus:** butuh endpoint publik; terikat ekosistem Cloudflare.

### Opsi B — Catch-all mailbox di hosting + polling IMAP
Alur: `Pengirim → MX hosting (cPanel dsb.) → mailbox catch-all → aplikasi polling IMAP tiap menit → impor ke DB`

- **Syarat:** hosting email untuk kedua domain dengan fitur catch-all.
- **Plus:** aplikasi bisa jalan **lokal di XAMPP** (koneksi IMAP keluar, tidak butuh
  endpoint publik); paling sederhana kalau hosting email sudah ada.
- **Minus:** delay polling (± 1 menit), butuh scheduler jalan terus, kuota mailbox hosting.

### Opsi C — Mail server sendiri (VPS + Postfix/Haraka → pipe ke aplikasi)
- Kontrol penuh, tapi paling rumit: VPS dengan port 25 terbuka, PTR record, reputasi IP,
  perawatan anti-spam sendiri. **Overkill** untuk kebutuhan ini — tidak direkomendasikan.

> **Rekomendasi:** Opsi A jika bersedia memindahkan DNS ke Cloudflare dan aplikasi
> di-host publik. Opsi B jika ingin semuanya jalan dari XAMPP lokal dan sudah punya
> hosting email. Keduanya bisa dibangun di atas kode aplikasi yang sama — hanya beda
> "pintu masuk" (webhook vs polling), jadi keputusan ini tidak mengubah desain inti.

---

## 2. Tech stack

| Lapisan | Pilihan | Alasan |
|---|---|---|
| Framework | Laravel 12/13 | Konsisten dengan skill & proyek penggajian |
| DB | MySQL | Sudah tersedia di XAMPP |
| UI | Blade + Bootstrap 5 | Cepat, familiar |
| Parsing email | `zbateson/mail-mime-parser` | Parse MIME murni PHP (subject, body, attachment, header) |
| IMAP (jika Opsi B) | `webklex/laravel-imap` | Polling mailbox |
| Sanitasi HTML email | `symfony/html-sanitizer` | Wajib — email HTML adalah vektor XSS |

---

## 3. Skema database

**`emails`**
- id, `domain` (my.id / biz.id), `alias` (bagian sebelum @ dari penerima), `to_address`
- `from_address`, `from_name`, `subject`
- `text_body` (longtext), `html_body` (longtext, sudah disanitasi saat render)
- `headers_raw` (text), `message_id` (unik — tolak duplikat), `size_bytes`
- `spf_result`, `dkim_result`, `dmarc_result` (dari header autentikasi, untuk indikator "aman/mencurigakan")
- `is_read`, `is_favorite`, `received_at`, `expires_at` (untuk auto-hapus)
- Index: (domain, alias), received_at, message_id unique

**`attachments`**
- id, email_id (FK cascade), `filename`, `mime_type`, `size_bytes`, `path` (disk privat)

**`aliases`** (opsional, fase 3)
- id, `alias`, `domain`, `label` (mis. "buat daftar Netflix"), `is_pinned`, timestamps
- Catatan: karena catch-all, alias TIDAK perlu didaftarkan dulu — tabel ini hanya
  untuk memberi label/riwayat alias yang pernah dipakai.

**`users`** — single user (pemilik), login email+password.

**`settings`** — key/value: retensi hari (default 30), blokir pengirim, dsb.

---

## 4. Halaman web

1. **Login** — single user, throttle, tanpa registrasi.
2. **Inbox** — daftar email terbaru; filter: domain, alias, belum dibaca, pencarian
   (pengirim/subjek); badge jumlah belum dibaca; auto-refresh AJAX tiap 15–30 detik;
   tombol "salin alamat" untuk alias cepat (`acak123@danang.biz.id`).
3. **Detail email** — tab: Tampilan HTML (disanitasi, di dalam iframe sandbox,
   gambar remote diblok default dengan tombol "tampilkan gambar"), Teks polos,
   Header lengkap, Lampiran (download via route ber-auth, bukan URL publik).
4. **Alias** — daftar alias yang pernah menerima email + label + pin; generator alias acak.
5. **Pengaturan** — retensi auto-hapus, blokir pengirim/domain pengirim, ganti password.

---

## 5. Keamanan (wajib, bukan opsional)

- **Sanitasi HTML email** sebelum render + iframe `sandbox` + CSP — email adalah input
  tak tepercaya dari orang asing.
- **Blok remote image by default** (tracking pixel) — muat manual per email.
- **Webhook (Opsi A):** validasi shared secret/HMAC di header, tolak selain POST
  Cloudflare; batasi ukuran payload (mis. 10 MB).
- **Lampiran:** simpan di disk privat, download lewat controller ber-auth dengan
  `Content-Disposition: attachment`; jangan pernah eksekusi/inline HTML attachment.
- **Auth:** login wajib untuk semua halaman; rate limit login.
- Jangan tampilkan link di email sebagai link aktif tanpa konfirmasi (anti-phishing
  untuk diri sendiri) — minimal `rel="noopener noreferrer"` + target blank.

---

## 6. Setup DNS per domain (saat implementasi)

- **Opsi A:** NS → Cloudflare → aktifkan Email Routing → verifikasi → rule catch-all
  → tujuan: Email Worker (script ± 30 baris yang mem-forward raw MIME ke webhook).
- **Opsi B:** MX → mail server hosting; buat 1 mailbox (mis. `catchall@`) dan set
  catch-all/default address ke mailbox itu di panel hosting; aplikasi polling IMAP SSL.
- SPF/DKIM tidak wajib untuk *menerima* (itu urusan pengirim) — tapi kita **baca**
  hasil verifikasinya dari header untuk indikator keaslian.

---

## 7. Tahapan pengerjaan

**Fase 1 — Fondasi (inti bisa dipakai)**
- Scaffold Laravel + auth single user + migrasi tabel
- Pipeline inbound (webhook ATAU polling IMAP sesuai opsi terpilih)
- Parse MIME → simpan email + lampiran
- Inbox sederhana + detail teks polos

**Fase 2 — Viewer layak pakai**
- Render HTML disanitasi + iframe sandbox + blok gambar remote
- Lampiran (list + download aman)
- Tandai dibaca/belum, hapus, cari & filter

**Fase 3 — Kenyamanan**
- Manajemen alias + label + generator alias acak + tombol salin
- Auto-refresh inbox + badge
- Scheduler: auto-hapus email kedaluwarsa (retensi), blokir pengirim

**Fase 4 — Polish (opsional)**
- Notifikasi browser saat email baru, export .eml, indikator SPF/DKIM,
  dark mode, PWA agar enak dibuka dari HP

---

## 8. Keputusan final (26 Jul 2026)

1. **Jalur masuk: Opsi A — Cloudflare Email Routing + Worker → webhook.**
   Dipilih karena gratis, real-time, dan tanpa perawatan mail server. User punya
   shared hosting + VPS; NS kedua domain akan diarahkan ke Cloudflare (langkah
   setup ada di SETUP.md).
2. **Deploy: VPS publik milik user.** Development dilakukan lokal di XAMPP
   (`htdocs/tempmail`), webhook dites via tunnel `cloudflared`.
3. **Stack: Laravel + MySQL + Blade/Bootstrap.**
