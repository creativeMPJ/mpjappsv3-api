# Regional Admin Endpoints

Base prefix: `/api/regional`

Semua endpoint di grup ini **memerlukan autentikasi** (JWT Bearer Token) dan role **`admin_regional`** dengan `region_id` yang valid.

---

## GET /api/regional/master-data

Mendapatkan semua data pesantren dan kru dalam wilayah admin yang login.

**Auth:** Diperlukan | Role: `admin_regional`

**Response 200:**
```json
{
  "profiles": [
    {
      "id": "uuid",
      "nama_pesantren": "Pesantren Al-Ikhlas",
      "nama_pengasuh": "KH. Ahmad Fauzi",
      "status_account": "active",
      "status_payment": "paid",
      "profile_level": "silver",
      "no_wa_pendaftar": "6281234567890",
      "nip": "1234567890"
    }
  ],
  "crews": [
    {
      "id": "uuid",
      "nama": "Budi",
      "jabatan": "Koordinator",
      "niam": "KR123456789001",
      "xp_level": 5,
      "pesantren_name": "Pesantren Al-Ikhlas"
    }
  ]
}
```

---

## GET /api/regional/pending-claims

Mendapatkan daftar klaim pesantren yang menunggu persetujuan wilayah.

**Auth:** Diperlukan | Role: `admin_regional`

**Response 200:**
```json
{
  "claims": [
    {
      "id": "uuid",
      "user_id": "uuid",
      "pesantren_name": "Pesantren Baru",
      "status": "pending",
      "region_id": "uuid",
      "kecamatan": "Diwek",
      "nama_pengelola": "Ahmad",
      "email_pengelola": "ahmad@pesantren.com",
      "dokumen_bukti_url": "/uploads/registration-documents/uuid/doc.pdf",
      "notes": null,
      "claimed_at": "2026-03-01T08:00:00.000000Z",
      "created_at": "2026-03-01T08:00:00.000000Z",
      "jenis_pengajuan": "pesantren_baru",
      "nama_pengasuh": "KH. Fauzi",
      "alamat_singkat": "Jl. Pesantren No. 1",
      "no_wa_pendaftar": "6281234567890",
      "niam": null,
      "is_alumni": false,
      "alamat_lengkap": "Jl. Pesantren No. 1, Cukir, Diwek",
      "desa": "Cukir",
      "kode_pos": "61471",
      "maps_link": null,
      "ketua_media": "Budi",
      "tahun_berdiri": "2005",
      "jumlah_kru": 3,
      "logo_media_url": null,
      "foto_gedung_url": null,
      "social_links": null,
      "website": null,
      "instagram": null,
      "facebook": null,
      "youtube": null,
      "tiktok": null,
      "jenjang_pendidikan": null,
      "kecamatan_profile": "Diwek"
    }
  ]
}
```

---

## GET /api/regional/pricing-packages

Mendapatkan daftar paket harga yang aktif.

**Auth:** Diperlukan | Role: `admin_regional`

**Response 200:**
```json
{
  "packages": [
    {
      "id": "uuid",
      "name": "Paket Registrasi Reguler",
      "category": "registration",
      "harga_paket": 150000,
      "harga_diskon": 120000,
      "is_active": true,
      "created_at": "2026-01-01T00:00:00.000000Z"
    }
  ]
}
```

> Kategori: `registration` | `renewal` | `upgrade`

---

## POST /api/regional/claims/{id}/approve

Menyetujui klaim pesantren di tingkat wilayah.

**Auth:** Diperlukan | Role: `admin_regional`
**Content-Type:** `application/json`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID klaim pesantren |

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `pricingPackageId` | string (uuid) | Tidak | ID paket harga; jika kosong, paket `registration` default digunakan |

**Contoh Request:**
```json
{
  "pricingPackageId": "uuid-paket"
}
```

**Response 200:**
```json
{
  "success": true
}
```

> Untuk klaim `pesantren_baru`: status berubah ke `regional_approved` dan pembayaran dibuat otomatis.
> Untuk klaim `klaim`: status akun pesantren langsung diaktifkan.

**Error Responses:**
- `403` — Role bukan `admin_regional`
- `404` — `{ "message": "Claim tidak ditemukan" }`

---

## POST /api/regional/claims/{id}/reject

Menolak klaim pesantren di tingkat wilayah.

**Auth:** Diperlukan | Role: `admin_regional`
**Content-Type:** `application/json`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID klaim pesantren |

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `reason` | string | Ya | Alasan penolakan (minimal 1 karakter) |

**Contoh Request:**
```json
{
  "reason": "Dokumen tidak lengkap, mohon lengkapi SK Pesantren."
}
```

**Response 200:**
```json
{
  "success": true
}
```

**Error Responses:**
- `404` — `{ "message": "Claim tidak ditemukan" }`

---

## GET /api/regional/late-payments

Mendapatkan daftar pesantren yang sudah disetujui wilayah namun belum membayar lebih dari 7 hari.

**Auth:** Diperlukan | Role: `admin_regional`

**Response 200:**
```json
{
  "claims": [
    {
      "id": "uuid",
      "user_id": "uuid",
      "pesantren_name": "Pesantren Al-Ikhlas",
      "nama_pengelola": "Ahmad",
      "regional_approved_at": "2026-02-28T10:00:00.000000Z",
      "jenis_pengajuan": "pesantren_baru",
      "no_wa_pendaftar": "6281234567890",
      "days_overdue": 3
    }
  ]
}
```

---

## POST /api/regional/late-payments/{claimId}/follow-up

Mencatat tindak lanjut (follow-up via WhatsApp) untuk pesantren yang terlambat membayar.

**Auth:** Diperlukan | Role: `admin_regional`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `claimId` | string (uuid) | ID klaim pesantren |

**Response 200:**
```json
{
  "success": true
}
```

---

## GET /api/regional/performance

Mendapatkan statistik performa wilayah admin yang login.

**Auth:** Diperlukan | Role: `admin_regional`

**Response 200:**
```json
{
  "totalVerified": 45,
  "premiumConverted": 30,
  "conversionRate": 66.7,
  "pendingFollowUp": 5,
  "weeklyFollowUps": 12,
  "stuckOver14Days": 2
}
```

| Field | Keterangan |
|---|---|
| `totalVerified` | Total pesantren disetujui (approved/pusat_approved) |
| `premiumConverted` | Total pesantren yang sudah membayar |
| `conversionRate` | Persentase konversi (%) |
| `pendingFollowUp` | Pesantren yang belum bayar > 7 hari |
| `weeklyFollowUps` | Follow-up yang dilakukan dalam 7 hari terakhir |
| `stuckOver14Days` | Pesantren yang belum bayar > 14 hari |

---

## GET /api/regional/leaderboard

Mendapatkan peringkat semua wilayah berdasarkan konversi pembayaran.

**Auth:** Diperlukan | Role: `admin_regional`

**Response 200:**
```json
{
  "leaderboard": [
    {
      "region_id": "uuid",
      "region_name": "Wilayah Jombang",
      "total_verified": 45,
      "total_paid": 30,
      "conversion_rate": 66.7
    }
  ],
  "user_region_id": "uuid"
}
```

> Diurutkan berdasarkan `conversion_rate` lalu `total_paid` secara descending.
> `user_region_id` adalah ID wilayah admin yang sedang login (untuk highlight di frontend).
