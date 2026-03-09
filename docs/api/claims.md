# Claims Endpoints

Base prefix: `/api/claims`

Semua endpoint di grup ini **memerlukan autentikasi** (JWT Bearer Token).

---

## GET /api/claims/pending-count

Mendapatkan jumlah klaim yang menunggu persetujuan di wilayah admin yang login.

**Auth:** Diperlukan | Role: `admin_regional`

**Response 200:**
```json
{
  "count": 5
}
```

**Error Responses:**
- `403` — `{ "message": "Forbidden" }` — Role bukan `admin_regional` atau tidak memiliki `region_id`

---

## GET /api/claims/search

Mencari klaim pesantren berdasarkan nama atau email pengelola.

**Auth:** Diperlukan

**Query Parameters:**

| Parameter | Type | Required | Keterangan |
|---|---|---|---|
| `query` | string | Ya | Kata kunci (nama pesantren atau email pengelola) |

**Response 200:**
```json
{
  "results": [
    {
      "id": "uuid",
      "pesantren_name": "Pesantren Al-Ikhlas",
      "kecamatan": "Diwek",
      "nama_pengelola": "Ahmad",
      "email_pengelola": "ahmad@pesantren.com",
      "region_id": "uuid",
      "user_id": "uuid",
      "status": "pending"
    }
  ]
}
```

> Mengembalikan array kosong jika `query` tidak diberikan. Maksimal 10 hasil. Tidak menampilkan klaim berstatus `approved` atau `pusat_approved`.

---

## POST /api/claims/send-otp

Mengirim kode OTP ke nomor WhatsApp pengelola pesantren untuk verifikasi klaim.

**Auth:** Diperlukan
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `claimId` | string (uuid) | Ya | ID klaim pesantren |

**Contoh Request:**
```json
{
  "claimId": "uuid-klaim"
}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Kode OTP telah dikirim ke nomor WhatsApp yang terdaftar",
  "otp_id": "uuid",
  "expires_at": "2026-03-09T10:15:00.000Z",
  "phone_masked": "***7890",
  "debug_otp": "123456"
}
```

> **Catatan:** Field `debug_otp` hanya untuk keperluan development.

**Error Responses:**
- `400` — `{ "message": "Nomor WhatsApp tidak tersedia untuk akun ini" }`
- `404` — `{ "message": "Claim tidak ditemukan" }`
- `429` — `{ "message": "Terlalu banyak permintaan OTP. Coba lagi dalam 1 jam." }` — Maksimal 3 permintaan per jam

---

## POST /api/claims/verify-otp

Memverifikasi kode OTP untuk mengkonfirmasi klaim pesantren.

**Auth:** Diperlukan
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `otpCode` | string (6 digit) | Ya | Kode OTP yang diterima |
| `otpId` | string (uuid) | Tidak | ID OTP dari respons `send-otp` |
| `claimId` | string (uuid) | Tidak | ID klaim (alternatif selain `otpId`) |

**Contoh Request:**
```json
{
  "otpCode": "123456",
  "otpId": "uuid-otp"
}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Verifikasi berhasil",
  "pesantren_claim_id": "uuid"
}
```

**Error Responses:**
- `400` (OTP kadaluarsa) — `{ "error": "Kode OTP tidak ditemukan atau sudah kadaluarsa", "expired": true }`
- `400` (Terlalu banyak percobaan) — `{ "error": "Terlalu banyak percobaan. Silakan minta kode OTP baru.", "max_attempts": true }`
- `400` (OTP salah) — `{ "error": "Kode OTP salah", "attempts_remaining": 4 }`

---

## GET /api/claims/contact/{claimId}

Mendapatkan informasi kontak admin wilayah untuk suatu klaim.

**Auth:** Diperlukan

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `claimId` | string (uuid) | ID klaim pesantren |

**Response 200:**
```json
{
  "claim": {
    "id": "uuid",
    "pesantren_name": "Pesantren Al-Ikhlas",
    "nama_pengaju": "Ahmad"
  },
  "region": {
    "admin_phone": "6281234567890"
  }
}
```

**Error Responses:**
- `404` — `{ "message": "Claim tidak ditemukan" }`
