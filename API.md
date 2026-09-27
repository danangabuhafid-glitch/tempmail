# Dokumentasi TempMail Developer API

TempMail menyediakan RESTful API untuk mempermudah developer dan bot dalam:
1. **Auto-Buat Email**: Membuat / men-generate alamat email sekali pakai (disposable mail) secara instan.
2. **Menerima Email**: Membaca kotak masuk, pesan teks, HTML, dan file lampiran.
3. **Mengambil Kode OTP**: Mengambil kode verifikasi angka (OTP / PIN) secara langsung tanpa perlu parsing manual.
4. **Auto-Wait / Long-Polling**: Menunggu email atau OTP tiba secara real-time langsung dalam 1 pemanggilan API (sangat cocok untuk bot registrasi Puppeteer/Playwright/Python).

---

## 🔑 Autentikasi

Semua endpoint `/api/*` (kecuali `/api/docs`) membutuhkan **API Token**.
Token dapat diperoleh di Dashboard Web: **Pengaturan ➔ API & Bookmarklet**.

Token dapat dikirim melalui salah satu dari 3 cara berikut:

1. **Header Authorization (Rekomendasi)**:
   ```http
   Authorization: Bearer <TOKEN>
   ```
2. **Header Custom**:
   ```http
   X-Api-Token: <TOKEN>
   ```
3. **Query Parameter URL**:
   ```http
   ?token=<TOKEN>  atau  ?api_key=<TOKEN>
   ```

---

## 🌐 Base URL
```
https://mail.danang.biz.id
```

Domain email yang aktif:
- `@danang.biz.id` (Domain Utama)
- `@danangabuhafid.my.id`
- `@projectdanang.biz.id`

---

## 📌 Ringkasan Endpoint

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/api/docs` | Dokumentasi API publik (tanpa token) |
| `GET` | `/api/domains` | Daftar domain aktif & domain default |
| `POST` / `GET` | `/api/create` | **Auto-Buat** alamat temp mail baru (acak atau custom) |
| `GET` | `/api/emails` | Ambil daftar email masuk (bisa filter per email) |
| `GET` | `/api/emails/{id}` | Ambil isi email lengkap (text, HTML, lampiran) |
| `GET` | `/api/otp` | Ambil kode OTP terbaru untuk email tertentu |
| `GET` | `/api/wait-email` | **Long-polling**: Tunggu email baru masuk (timeout 5-60s) |
| `GET` | `/api/wait-otp` | **Long-polling**: Tunggu kode OTP masuk (timeout 5-60s) |
| `DELETE` | `/api/emails/{id}` | Hapus email dari database |
| `DELETE` | `/api/alias/{alias}` | Hapus/tutup alias temp mail |

---

## 🚀 Detail Endpoint & Contoh Request

### 1. Auto-Buat Alamat Email Baru
Generate email baru secara otomatis. Jika dipanggil tanpa parameter, bot akan membuat alamat acak berawalan `dev_xxxxxx@danang.biz.id`.

- **URL**: `POST /api/create` atau `GET /api/create`
- **Query / Body Parameter** (Semua Opsional):
  - `name`: Nama username spesifik (mis: `user_tester`). Jika ada benturan, otomatis ditambahkan suffix acak.
  - `prefix`: Awalan email acak (mis: `bot_`, `reg_`, `test_`). Default: `dev_`.
  - `domain`: Pilihan domain (mis: `danang.biz.id`, `danangabuhafid.my.id`). Default: domain utama.
  - `ttl`: Masa aktif alias (`10m`, `30m`, `1h`, `24h`, `7d`, `30d`). Default: permanen / mengikuti retensi global.
  - `label`: Catatan keperluan email (mis: `Testing Registrasi Toko`).

**Contoh Request cURL:**
```bash
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
  "https://mail.danang.biz.id/api/create?prefix=bot_&ttl=24h"
```

**Contoh Response:**
```json
{
  "success": true,
  "email": "bot_9x4k1a@danang.biz.id",
  "alias": "bot_9x4k1a",
  "domain": "danang.biz.id",
  "label": "Dev API",
  "expires_at": "2026-09-28T13:50:00+07:00",
  "created_at": "2026-09-27T13:50:00+07:00"
}
```

> **💡 Catatan Catch-All:**
> Berkat fitur Catch-All Cloudflare di domain Anda, developer juga bisa langsung menggunakan alamat email apa pun (misal: `apapun@danang.biz.id`) tanpa memanggil `/api/create` terlebih dahulu. Semua email yang dikirim ke domain Anda akan tetap masuk dan bisa dibaca lewat API.

---

### 2. Ambil Daftar Email Masuk
Melihat daftar email yang diterima.

- **URL**: `GET /api/emails`
- **Query Parameter**:
  - `email`: Alamat email lengkap tujuan (mis: `bot_9x4k1a@danang.biz.id`).
  - `alias`: Alias tujuan saja (mis: `bot_9x4k1a`).
  - `unread`: `true` untuk hanya menampilkan yang belum dibaca.
  - `limit`: Jumlah maksimal email (default: 20, max: 100).
  - `after_id`: Hanya ambil email baru yang memiliki ID lebih besar dari ID ini.

**Contoh Request cURL:**
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://mail.danang.biz.id/api/emails?email=bot_9x4k1a@danang.biz.id"
```

**Contoh Response:**
```json
{
  "success": true,
  "total": 1,
  "emails": [
    {
      "id": 280,
      "to": "bot_9x4k1a@danang.biz.id",
      "from": "noreply@layanan.com",
      "from_name": "Layanan",
      "subject": "Kode Verifikasi Anda: 839201",
      "otp": "839201",
      "is_read": false,
      "received_at": "2026-09-27T13:52:10+07:00"
    }
  ]
}
```

---

### 3. Ambil Isi Email Lengkap
Membaca seluruh isi teks, format HTML, hasil SPF/DKIM, dan daftar lampiran.

- **URL**: `GET /api/emails/{id}`

**Contoh Request cURL:**
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://mail.danang.biz.id/api/emails/280"
```

---

### 4. Ambil Kode OTP Langsung
Mengambil angka kode verifikasi (OTP / PIN) terbaru dari email masuk tanpa harus mengurai teks sendiri.

- **URL**: `GET /api/otp`
- **Query Parameter**:
  - `email`: Alamat email lengkap (mis: `bot_9x4k1a@danang.biz.id`).
  - `minutes`: Batas rentang menit ke belakang (default: 15 menit).

**Contoh Request cURL:**
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://mail.danang.biz.id/api/otp?email=bot_9x4k1a@danang.biz.id"
```

**Contoh Response:**
```json
{
  "success": true,
  "otp": "839201",
  "to": "bot_9x4k1a@danang.biz.id",
  "from": "noreply@layanan.com",
  "from_name": "Layanan",
  "subject": "Kode Verifikasi Anda",
  "email_id": 280,
  "received_at": "2026-09-27T13:52:10+07:00"
}
```

---

### 5. Auto-Wait OTP (Long Polling untuk Bot Otomasi)
Sangat berguna untuk skrip bot (Puppeteer, Playwright, Selenium, Python). Script tidak perlu membuat perulangan `while sleep(2)` sendiri. Server akan menahan koneksi dan **langsung mengembalikan OTP** begitu email tiba!

- **URL**: `GET /api/wait-otp`
- **Query Parameter**:
  - `email`: Email tujuan (Wajib).
  - `timeout`: Waktu tunggu maksimal dalam detik (default: 30, max: 60).
  - `after_id`: Hanya cari email dengan ID lebih besar dari ini (opsional).

**Contoh Request cURL:**
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://mail.danang.biz.id/api/wait-otp?email=bot_9x4k1a@danang.biz.id&timeout=45"
```

---

## 💻 Contoh Kode Integrasi Developer

### 🟢 Node.js / JavaScript (Fetch / Axios)
```javascript
const API_URL = 'https://mail.danang.biz.id/api';
const API_TOKEN = 'YOUR_TOKEN_HERE';

async function tempMailDemo() {
  const headers = { 'Authorization': `Bearer ${API_TOKEN}` };

  // 1. Auto-buat email baru
  const createRes = await fetch(`${API_URL}/create?prefix=bot_`, { method: 'POST', headers });
  const { email } = await createRes.json();
  console.log(`[+] Email dibuat: ${email}`);

  // 2. Gunakan email di website target (misal daftar akun)
  console.log(`[+] Mendaftarkan akun dengan email ${email}...`);

  // 3. Tunggu kode OTP masuk secara real-time
  console.log(`[+] Menunggu OTP tiba...`);
  const otpRes = await fetch(`${API_URL}/wait-otp?email=${encodeURIComponent(email)}&timeout=45`, { headers });
  const otpData = await otpRes.json();

  if (otpData.success) {
    console.log(`[✅] OTP Berhasil Diterima: ${otpData.otp} (Dari: ${otpData.from})`);
  } else {
    console.log(`[❌] Gagal / Timeout: ${otpData.message}`);
  }
}

tempMailDemo();
```

---

### 🐍 Python (Requests)
```python
import requests
import time

API_URL = "https://mail.danang.biz.id/api"
API_TOKEN = "YOUR_TOKEN_HERE"

headers = {
    "Authorization": f"Bearer {API_TOKEN}"
}

# 1. Auto-buat email baru
resp = requests.post(f"{API_URL}/create", params={"prefix": "pybot_"}, headers=headers)
data = resp.json()
email = data["email"]
print(f"[+] Email Baru: {email}")

# 2. Trigger pendaftaran / kirim OTP ke email di atas...
print("[+] Menunggu kode OTP...")

# 3. Tunggu OTP masuk via Long-Polling
otp_resp = requests.get(f"{API_URL}/wait-otp", params={"email": email, "timeout": 45}, headers=headers)
otp_data = otp_resp.json()

if otp_data.get("success"):
    print(f"[✅] KODE OTP: {otp_data['otp']}")
else:
    print(f"[❌] Error: {otp_data.get('message')}")
```
