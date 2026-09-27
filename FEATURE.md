# Daftar Ide Fitur — TempMail Pribadi

Dokumen hidup berisi kandidat fitur. Dipakai sebagai rujukan sebelum menambah
fitur baru, supaya tidak mengusulkan sesuatu yang sudah ada.

**Aturan pakai:**

- Status `⬜` = belum dibangun · `🔨` = sedang dikerjakan · `✅ SKIP` = sudah ada, **jangan diusulkan lagi**
- Begitu sebuah ide selesai dibangun, **ubah statusnya jadi `✅ SKIP`** di sini
- Ide baru ditambahkan ke bagian yang sesuai dengan nomor lanjutan
- Kolom **Usaha** relatif terhadap stack ini (Laravel + Blade, tanpa SPA)

Ringkasan: **50 kandidat** · 0 selesai · 50 belum.
Fitur yang **sudah ada** ada di [Lampiran A](#lampiran-a--sudah-ada-jangan-diusulkan-lagi) — semuanya berstatus SKIP.

---

## A. Inbox & Kenyamanan Harian

### 1. Real-time tanpa polling (SSE) ⬜
**Usaha:** sedang · **Nilai:** tinggi
Server-Sent Events menggantikan polling 15 detik: email muncul seketika begitu
webhook Cloudflare masuk. Langsung menyerang use-case utama — menunggu OTP saat
mendaftar. Bangun di atas endpoint `/inbox/poll` yang sudah ada.

### 2. Operator pencarian ⬜
**Usaha:** kecil · **Nilai:** tinggi
`from:netflix`, `has:attachment`, `is:unread`, `older_than:7d`, `alias:belanja`
diurai dari kolom cari yang sudah ada. Sisanya tetap masuk pencarian FULLTEXT.

### 3. Cuplikan isi email di daftar ⬜
**Usaha:** kecil · **Nilai:** tinggi
Tampilkan ~80 karakter pertama `text_body` di bawah subjek. Triase tanpa membuka
email — data sudah tersimpan, tinggal ditampilkan.

### 4. Pencarian tersimpan / folder pintar ⬜
**Usaha:** sedang · **Nilai:** sedang
Simpan kombinasi filter sebagai chip sendiri ("OTP minggu ini", "berlampiran").

### 5. Tampilan percakapan (threading) ⬜
**Usaha:** sedang · **Nilai:** sedang
Kolom `message_id` sudah tersimpan tapi belum dipakai. Kelompokkan balasan
berantai memakai `In-Reply-To`/`References`.

### 6. Tunda email (snooze) ⬜
**Usaha:** kecil · **Nilai:** sedang
Sembunyikan email sampai tanggal tertentu, lalu muncul lagi sebagai belum dibaca.

### 7. Label berwarna per email ⬜
**Usaha:** sedang · **Nilai:** sedang
Tag bebas ("penting", "trial", "belanja") dengan warna, bisa difilter.

### 8. Galeri lampiran ⬜
**Usaha:** sedang · **Nilai:** sedang
Satu halaman berisi semua lampiran dari seluruh email, bisa disaring per tipe —
cari invoice PDF lama tanpa mengingat emailnya.

### 9. Tampilan dua panel (split view) ⬜
**Usaha:** sedang · **Nilai:** sedang
Daftar di kiri, isi email di kanan. Baca berurutan tanpa bolak-balik halaman.

### 10. Batal aksi (undo) ⬜
**Usaha:** kecil · **Nilai:** sedang
Toast "Dihapus — Batalkan" selama 10 detik. Keranjang sampah sudah menutup
risikonya, ini soal kecepatan pemulihan.

### 11. Kontrol urutan & kepadatan daftar ⬜
**Usaha:** kecil · **Nilai:** rendah
Urut berdasarkan terbaru/terlama/ukuran/pengirim, plus mode padat vs longgar.

### 12. Filter rentang tanggal ⬜
**Usaha:** kecil · **Nilai:** sedang
Pelengkap operator pencarian: pemilih tanggal dari–sampai di form filter.

### 13. Gulir tak terbatas ⬜
**Usaha:** kecil · **Nilai:** rendah
Ganti pagination dengan muat-saat-digulir. Enak di HP.

### 14. Tampilan ramah cetak / simpan PDF ⬜
**Usaha:** kecil · **Nilai:** rendah
CSS `@media print` untuk halaman email — arsip bukti pendaftaran.

### 15. Terjemahan isi email ⬜
**Usaha:** sedang · **Nilai:** rendah
Tombol terjemahkan untuk email berbahasa asing. Butuh API eksternal — pertimbangkan
privasinya (isi email dikirim ke pihak ketiga).

---

## B. Otomasi & Integrasi

### 16. Mesin aturan (rules) ⬜
**Usaha:** sedang · **Nilai:** tinggi
Kondisi (pengirim / subjek mengandung / alias tujuan / hasil SPF) → aksi (tandai
dibaca, simpan permanen, hapus otomatis, retensi khusus, label, notifikasi khusus).
Perluasan alami dari blokir pengirim yang sudah ada; satu titik evaluasi di ingest.

### 17. Forward ke email pribadi (SMTP) ⬜
**Usaha:** besar · **Nilai:** sedang
Teruskan email alias penting ke Gmail dengan `.eml` asli sebagai lampiran.
Menutup sifat receive-only. Perhatikan deliverability — kirim dari domain
tervalidasi, jangan me-relay apa adanya.

### 18. Webhook keluar ⬜
**Usaha:** kecil · **Nilai:** sedang
POST ringkas ke URL bebas milik user setiap email masuk — sambungkan ke n8n,
Make, Zapier, atau skrip sendiri.

### 19. Ekstensi browser ⬜
**Usaha:** besar · **Nilai:** sedang
Lebih mulus dari bookmarklet: klik kanan di kolom email form pendaftaran → alias
dibuat & langsung terisi, plus popup menampilkan OTP yang masuk.

### 20. Resep iOS Shortcuts / Android Tasker ⬜
**Usaha:** kecil · **Nilai:** sedang
Dokumentasi + shortcut siap pakai yang memanggil `/api/otp` — ambil OTP dari
layar kunci HP.

### 21. CLI (`tempmail-cli`) ⬜
**Usaha:** sedang · **Nilai:** sedang
Perintah terminal: buat alias, tunggu OTP (`tempmail otp --wait`), daftar email.
Membungkus API yang sudah ada.

### 22. Notifikasi Slack / Discord ⬜
**Usaha:** kecil · **Nilai:** rendah
Sama polanya dengan Telegram/ntfy yang sudah ada — tinggal target baru.

### 23. Impor `.eml` / mbox ⬜
**Usaha:** sedang · **Nilai:** rendah
Masukkan arsip email lama dari tempat lain ke sistem ini.

### 24. Ekspor massal ZIP ⬜
**Usaha:** sedang · **Nilai:** sedang
Unduh semua email satu alias / rentang tanggal sebagai ZIP berisi `.eml`.
Berguna sebelum retensi menghapusnya.

### 25. Ringkasan harian (digest) ⬜
**Usaha:** kecil · **Nilai:** sedang
Sekali sehari kirim rekap ke Telegram: berapa email, dari siapa, alias mana yang
aktif. Menjaring email yang notifikasinya terlewat.

### 26. Halaman dokumentasi API (OpenAPI) ⬜
**Usaha:** sedang · **Nilai:** rendah
Spesifikasi + halaman coba-langsung untuk endpoint API yang sudah ada.

### 27. Beberapa token API dengan cakupan ⬜
**Usaha:** sedang · **Nilai:** sedang
Token per-skrip, bisa dicabut sendiri-sendiri, dibatasi ke alias tertentu.
Sekarang satu token global membuka semua inbox.

### 28. Alias umpan (honeypot) ⬜
**Usaha:** kecil · **Nilai:** tinggi
Karena tiap layanan dapat alias sendiri, saat spam masuk ke `netflix-x4k9@…`
ketahuan persis siapa yang membocorkan/menjual alamat Anda. Fitur ini
memvisualkannya: tandai alias yang mulai menerima email dari pengirim tak dikenal.

---

## C. Keamanan

### 29. Header keamanan global (CSP) ⬜
**Usaha:** kecil · **Nilai:** tinggi
Aplikasi belum punya Content-Security-Policy sama sekali (temuan review).
Sanitizer sudah kuat, tapi CSP adalah jaring pengaman terakhir kalau ada celah
yang lolos. Tambahkan juga `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`.

### 30. Log audit ⬜
**Usaha:** sedang · **Nilai:** tinggi
Catat login sukses/gagal + IP, regenerasi token, perubahan domain/retensi,
penghapusan massal. Panel terbuka di internet — tanpa ini, percobaan masuk
sama sekali tak terlihat.

### 31. Kelola sesi aktif ⬜
**Usaha:** sedang · **Nilai:** sedang
Daftar perangkat/sesi yang sedang login (IP, browser, terakhir aktif) + tombol
cabut. Pelengkap log audit.

### 32. Notifikasi login baru ⬜
**Usaha:** kecil · **Nilai:** tinggi
Kirim Telegram saat ada login berhasil dari IP baru. Deteksi dini paling murah.

### 33. Login passkey / WebAuthn ⬜
**Usaha:** sedang · **Nilai:** sedang
Sidik jari / Face ID menggantikan password + TOTP. Nilainya menurun karena 2FA
TOTP sudah ada.

### 34. Kunci akun setelah gagal berulang ⬜
**Usaha:** kecil · **Nilai:** sedang
Throttle login sudah ada (5/menit); ini menambah penguncian sementara +
notifikasi saat ada percobaan brute force.

### 35. Auto-logout saat menganggur ⬜
**Usaha:** kecil · **Nilai:** rendah
Keluar otomatis setelah sekian menit tanpa aktivitas — relevan kalau dibuka di
perangkat bersama.

### 36. Allowlist IP untuk panel admin ⬜
**Usaha:** kecil · **Nilai:** sedang
Batasi akses halaman pemilik ke IP rumah/kantor. Webhook tetap terbuka.
Hati-hati: bisa mengunci diri sendiri saat IP berubah.

### 37. Pindai virus lampiran (ClamAV) ⬜
**Usaha:** sedang · **Nilai:** sedang
Skrining berbasis ekstensi sudah ada; ini pemindaian isi sungguhan di VPS.

### 38. Enkripsi isi email at-rest ⬜
**Usaha:** besar · **Nilai:** rendah
Enkripsi `text_body`/`html_body` di database. Melindungi kalau dump DB bocor,
tapi mematikan pencarian FULLTEXT — pertimbangkan baik-baik.

### 39. 2FA untuk akun alias ⬜
**Usaha:** kecil · **Nilai:** rendah
TOTP sekarang khusus pemilik. Akun alias hanya melihat satu kotak masuk, jadi
nilainya kecil.

### 40. Catatan kredensial terenkripsi per alias ⬜
**Usaha:** sedang · **Nilai:** sedang
Simpan "layanan apa, username apa" per alias (terenkripsi). Catatan per email
sudah ada; ini versi per alamat yang lebih terstruktur.

---

## D. Operasional & Infrastruktur

### 41. Pemulihan dari backup ⬜
**Usaha:** sedang · **Nilai:** tinggi
`tempmail:restore` — backup sudah jalan, tapi belum pernah ada jalur pulih yang
teruji. Backup yang tidak pernah dites sama dengan tidak punya backup.

### 42. CI GitHub Actions ⬜
**Usaha:** kecil · **Nilai:** sedang
Jalankan 71 test otomatis tiap push. Repo sudah di GitHub.

### 43. Antrean untuk ingest berat ⬜
**Usaha:** sedang · **Nilai:** sedang
Parsing lampiran besar dipindah ke queue worker supaya webhook membalas cepat
dan Cloudflare Worker tidak timeout.

### 44. Ping monitor uptime eksternal ⬜
**Usaha:** kecil · **Nilai:** sedang
Endpoint `/up` sudah ada dari Laravel; sambungkan ke UptimeRobot/Healthchecks
agar tahu saat VPS mati — sekarang semua monitoring ada *di dalam* aplikasi yang
justru sedang mati.

### 45. Penampil log di aplikasi ⬜
**Usaha:** sedang · **Nilai:** sedang
Baca `laravel.log` dari halaman pemilik tanpa perlu SSH.

### 46. Perawatan database terjadwal ⬜
**Usaha:** kecil · **Nilai:** rendah
`OPTIMIZE TABLE` berkala setelah banyak penghapusan, plus laporan ukuran tabel.

### 47. Opsi deploy Docker Compose ⬜
**Usaha:** sedang · **Nilai:** rendah
Alternatif script VPS: app + MySQL + cron + Caddy dalam satu perintah.
Memudahkan pindah server.

### 48. Halaman status publik ⬜
**Usaha:** kecil · **Nilai:** rendah
Status ringkas tanpa login (pipeline sehat / tidak) untuk dicek dari HP saat
tidak bisa masuk panel.

### 49. Mode arsip dingin ⬜
**Usaha:** sedang · **Nilai:** rendah
Email lama dipindah ke penyimpanan terkompresi alih-alih dihapus saat retensi
lewat — hemat disk tanpa kehilangan data.

### 50. Metrik Prometheus ⬜
**Usaha:** sedang · **Nilai:** rendah
Endpoint metrik (email/menit, error webhook, ukuran disk) untuk Grafana.
Berlebihan untuk satu pengguna, tapi menyenangkan kalau sudah punya stack-nya.

---

## Lampiran A — Sudah ada (jangan diusulkan lagi)

Semua di bawah ini **✅ SKIP** — sudah terpasang dan teruji.

**Inti & inbox**
✅ Ingest catch-all via Cloudflare Worker → webhook ·
✅ Dedup per-penerima + tahan race ·
✅ Deteksi kode OTP + salin sekali klik ·
✅ Pencarian isi email (FULLTEXT) ·
✅ Aksi massal (hapus/dibaca/belum dibaca/pulihkan/hapus permanen) ·
✅ Keranjang sampah + pulihkan + auto-buang 7 hari ·
✅ Simpan permanen (kebal retensi) ·
✅ Tandai belum dibaca ·
✅ Catatan pribadi per email ·
✅ Unduh `.eml` (raw MIME tersimpan) ·
✅ Lampiran: thumbnail + ikon per tipe ·
✅ Gambar inline CID via signed URL ·
✅ Auto-refresh 15 detik + notifikasi browser ·
✅ Halaman statistik (tren harian, jam sibuk, pengirim/alamat teratas) ·
✅ Keyboard shortcuts ·
✅ Filter domain/alias/belum dibaca + chip kotak masuk

**Alamat (alias)**
✅ Buat manual / prefix / otomatis (massal s/d 50) ·
✅ Label + edit label ·
✅ Pin alamat ·
✅ QR code alamat ·
✅ Alias sekali pakai (TTL 1 jam / 24 jam / 7 hari) ·
✅ Bookmarklet alias cepat ·
✅ Akun login per alamat (password acak unik + wajib ganti)

**Keamanan**
✅ Sanitasi HTML + iframe sandbox ·
✅ Blokir gambar remote (termasuk srcset/poster/video/style) ·
✅ SVG tidak pernah dirender inline + CSP pada respons lampiran ·
✅ Proteksi tautan anti-phishing + pembersih parameter pelacak ·
✅ SPF/DKIM/DMARC tepercaya (anti-spoof authserv-id) ·
✅ Folder Spam otomatis ·
✅ Skrining lampiran berbahaya ·
✅ Blokir pengirim (alamat/domain) ·
✅ 2FA TOTP + kode recovery ·
✅ Webhook: token, rate limit, batas ukuran, log ·
✅ Isolasi akun alias (hanya kotaknya sendiri)

**Otomasi**
✅ API REST bertoken (`/api/emails`, `/api/emails/{id}`, `/api/otp`, `/api/alias/quick`) ·
✅ Notifikasi Telegram / ntfy.sh

**Operasional**
✅ Monitoring kesehatan per-domain (cek DNS terjadwal + notifikasi rusak/pulih) ·
✅ Dashboard kesehatan + log webhook ·
✅ Heartbeat scheduler (deteksi cron mati) ·
✅ Backup harian + rotasi ·
✅ Retensi otomatis + kuota penyimpanan ·
✅ Wizard setup Cloudflare di web ·
✅ Script deploy VPS + script update ·
✅ Dukungan banyak domain

**Tampilan**
✅ Dark mode + toggle ·
✅ PWA (install ke layar utama) ·
✅ Responsif + menu hamburger ·
✅ Toast + dialog konfirmasi

---

## Lampiran B — Sengaja tidak dikerjakan

Bukan "belum", tapi **diputuskan tidak** — dengan alasannya:

| Ide | Alasan |
|---|---|
| Multi-user / berbagi inbox | Bertentangan dengan sifat privat single-owner aplikasi ini |
| Layanan temp-mail publik | Butuh kontrol abuse yang jauh lebih ketat (kuota per IP, moderasi, biaya) |
| Dark mode untuk isi email | Email HTML membawa warnanya sendiri; pembalikan warna sering merusak tampilan |
| Balas email dari aplikasi | Cloudflare Email Routing tidak bisa mengirim; butuh SMTP + reputasi domain |
| Editor template email | Aplikasi ini receive-only |
