# Payments Endpoints

Base prefix: `/api/payments`

Semua endpoint di grup ini **memerlukan autentikasi** (JWT Bearer Token).

---

## GET /api/payments/current

Mendapatkan informasi pembayaran saat ini dan instruksi transfer bank untuk pengguna yang login.

**Auth:** Diperlukan

**Response — Belum mendaftarkan pesantren:**
```json
{
  "accessDeniedReason": "Anda belum mendaftarkan pesantren."
}
```

**Response — Klaim sedang pending:**
```json
{
  "accessDeniedReason": "Menunggu verifikasi dari Admin Wilayah. Silakan tunggu proses validasi dokumen Anda."
}
```

**Response — Klaim ditolak:**
```json
{
  "accessDeniedReason": "Pengajuan Anda ditolak oleh Admin Wilayah. Silakan hubungi admin untuk informasi lebih lanjut."
}
```

**Response — Sudah disetujui (redirect ke dashboard):**
```json
{
  "redirectTo": "/user"
}
```

**Response — Pembayaran sedang diverifikasi:**
```json
{
  "redirectTo": "/payment-pending",
  "payment": {
    "id": "uuid",
    "status": "pending_verification",
    "rejectionReason": null
  }
}
```

**Response — Pembayaran sudah terverifikasi:**
```json
{
  "redirectTo": "/user",
  "payment": {
    "id": "uuid",
    "status": "verified",
    "rejectionReason": null
  }
}
```

**Response 200 — Menampilkan instruksi pembayaran:**
```json
{
  "payment": {
    "id": "uuid",
    "baseAmount": 150000,
    "uniqueCode": 542,
    "totalAmount": 150542,
    "status": "pending_payment",
    "rejectionReason": null
  },
  "claim": {
    "id": "uuid",
    "pesantren_name": "Pesantren Al-Ikhlas",
    "jenis_pengajuan": "pesantren_baru",
    "status": "regional_approved"
  },
  "bankInfo": {
    "bank": "Bank Syariah Indonesia (BSI)",
    "accountNumber": "7171234567890",
    "accountName": "MEDIA PONDOK JAWA TIMUR"
  }
}
```

> Status pembayaran: `pending_payment` | `pending_verification` | `verified` | `rejected`

---

## POST /api/payments/submit-proof

Mengunggah bukti transfer pembayaran.

**Auth:** Diperlukan
**Content-Type:** `multipart/form-data`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `paymentId` | string (uuid) | Ya | ID pembayaran dari endpoint `current` |
| `senderName` | string | Ya | Nama pengirim transfer |
| `file` | file | Ya | Bukti transfer (jpeg/png/webp/pdf, maksimal 350KB) |

**Response 200:**
```json
{
  "success": true
}
```

**Error Responses:**
- `404` — `{ "message": "Pembayaran tidak ditemukan" }` — Payment ID tidak ditemukan atau milik pengguna lain
- `422` — Validasi gagal (file terlalu besar, format tidak didukung, dll.)
