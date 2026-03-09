# Admin Pusat Endpoints

Base prefix: `/api/admin`

Semua endpoint di grup ini **memerlukan autentikasi** (JWT Bearer Token).
Sebagian besar endpoint memerlukan role **`admin_pusat`**, beberapa juga bisa diakses oleh **`admin_finance`**.

---

## Dashboard

### GET /api/admin/home-summary

Mendapatkan ringkasan statistik utama untuk halaman beranda admin.

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "stats": {
    "totalPesantren": 120,
    "totalKru": 350,
    "totalWilayah": 8,
    "pendingPayments": 5,
    "totalIncome": 18000000
  },
  "levelStats": {
    "basic": 60,
    "silver": 40,
    "gold": 15,
    "platinum": 5
  },
  "recentUsers": [
    {
      "id": "uuid",
      "nama_pesantren": "Pesantren Baru",
      "nip": null,
      "region_name": "Wilayah Jombang",
      "status_account": "pending",
      "profile_level": "basic",
      "created_at": "2026-03-09T08:00:00.000000Z"
    }
  ]
}
```

---

## Clearing House

### GET /api/admin/clearing-house/pending

Mendapatkan daftar profil pesantren yang belum memiliki NIP (menunggu verifikasi awal).

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "profiles": [
    {
      "id": "uuid",
      "nama_pesantren": "Pesantren Al-Ikhlas",
      "nama_pengasuh": "KH. Ahmad Fauzi",
      "regency_id": "3517",
      "created_at": "2026-03-01T08:00:00.000000Z",
      "regency": {
        "name": "KABUPATEN JOMBANG"
      }
    }
  ]
}
```

---

### POST /api/admin/clearing-house/{id}/approve

Menyetujui profil pesantren dan meng-generate NIP.

**Auth:** Diperlukan | Role: `admin_pusat`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID profil pesantren |

**Response 200:**
```json
{
  "success": true,
  "nip": "1234567890"
}
```

---

### POST /api/admin/clearing-house/{id}/reject

Menolak profil pesantren.

**Auth:** Diperlukan | Role: `admin_pusat`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID profil pesantren |

**Response 200:**
```json
{
  "success": true
}
```

---

### GET /api/admin/pending-profiles

Mendapatkan daftar profil yang menunggu persetujuan.

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "pending_profiles": [
    {
      "id": "uuid",
      "nama_pesantren": "Pesantren Baru",
      "region_name": "Wilayah Jombang",
      "no_wa_pendaftar": "6281234567890",
      "created_at": "2026-03-01T08:00:00.000000Z",
      "status_account": "pending"
    }
  ]
}
```

---

## Admin Settings

### GET /api/admin/admin-settings/data

Mendapatkan daftar admin dan wilayah untuk pengaturan admin.

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "admins": [
    {
      "id": "uuid",
      "user_id": "uuid",
      "nama": "Ahmad",
      "niam": "KR123456789001",
      "jabatan": "Koordinator",
      "region_id": "uuid",
      "region_name": "Wilayah Jombang",
      "role": "admin_regional"
    }
  ],
  "regions": [
    {
      "id": "uuid",
      "name": "Wilayah Jombang"
    }
  ]
}
```

---

### GET /api/admin/admin-settings/search-crew

Mencari kru untuk dijadikan admin.

**Auth:** Diperlukan | Role: `admin_pusat`

**Query Parameters:**

| Parameter | Type | Required | Keterangan |
|---|---|---|---|
| `query` | string | Ya | Kata kunci (minimal 2 karakter) |

**Response 200:**
```json
{
  "crews": [
    {
      "id": "uuid",
      "profile_id": "uuid",
      "nama": "Budi",
      "niam": "KR123456789001",
      "jabatan": "Koordinator",
      "pesantren_name": "Pesantren Al-Ikhlas",
      "region_id": "uuid",
      "region_name": "Wilayah Jombang",
      "email": "budi@pesantren.com",
      "current_role": "user"
    }
  ]
}
```

---

### POST /api/admin/admin-settings/assign

Menetapkan role admin kepada pengguna.

**Auth:** Diperlukan | Role: `admin_pusat`
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `profileId` | string (uuid) | Ya | ID profil pengguna |
| `role` | string | Ya | Role yang ditetapkan (misal: `admin_regional`) |
| `regionId` | string (uuid) | Tidak | ID wilayah (diperlukan untuk `admin_regional`) |

**Contoh Request:**
```json
{
  "profileId": "uuid",
  "role": "admin_regional",
  "regionId": "uuid"
}
```

**Response 200:**
```json
{
  "success": true
}
```

---

### DELETE /api/admin/admin-settings/{userId}

Mencabut role admin dari pengguna (mengembalikan ke role `user`).

**Auth:** Diperlukan | Role: `admin_pusat`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `userId` | string (uuid) | ID pengguna |

**Response 200:**
```json
{
  "success": true
}
```

---

## Master Data

### GET /api/admin/master-data

Mendapatkan semua data pesantren, kru, dan wilayah.

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "profiles": [
    {
      "id": "uuid",
      "nama_pesantren": "Pesantren Al-Ikhlas",
      "nama_media": "Media Al-Ikhlas",
      "nip": "1234567890",
      "region_id": "uuid",
      "region_name": "Wilayah Jombang",
      "regency_name": "KABUPATEN JOMBANG",
      "status_account": "active",
      "profile_level": "silver",
      "alamat_singkat": "Jl. Pesantren No. 1",
      "nama_pengasuh": "KH. Ahmad Fauzi",
      "no_wa_pendaftar": "6281234567890"
    }
  ],
  "crews": [
    {
      "id": "uuid",
      "nama": "Budi",
      "niam": "KR123456789001",
      "jabatan": "Koordinator",
      "xp_level": 5,
      "profile_id": "uuid",
      "pesantren_name": "Pesantren Al-Ikhlas",
      "region_id": "uuid",
      "region_name": "Wilayah Jombang"
    }
  ],
  "regions": [
    {
      "id": "uuid",
      "name": "Wilayah Jombang",
      "code": "01"
    }
  ]
}
```

---

### PUT /api/admin/master-data/pesantren/{id}

Memperbarui data pesantren.

**Auth:** Diperlukan | Role: `admin_pusat`
**Content-Type:** `application/json`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID profil pesantren |

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `nama_pesantren` | string | Tidak | Nama pesantren |
| `nama_pengasuh` | string | Tidak | Nama pengasuh |
| `alamat_singkat` | string | Tidak | Alamat singkat |

**Response 200:**
```json
{
  "success": true
}
```

---

### PUT /api/admin/master-data/media/{id}

Memperbarui data media pesantren.

**Auth:** Diperlukan | Role: `admin_pusat`
**Content-Type:** `application/json`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID profil pesantren |

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `nama_pesantren` | string | Tidak | Nama pesantren |
| `nama_media` | string | Tidak | Nama media |
| `no_wa_pendaftar` | string | Tidak | Nomor WhatsApp |

**Response 200:**
```json
{
  "success": true
}
```

---

### PUT /api/admin/master-data/crew/{id}

Memperbarui data kru.

**Auth:** Diperlukan | Role: `admin_pusat`
**Content-Type:** `application/json`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID kru |

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `nama` | string | Tidak | Nama kru |
| `jabatan` | string | Tidak | Jabatan |

**Response 200:**
```json
{
  "success": true
}
```

---

### DELETE /api/admin/master-data/crew/{id}

Menghapus data kru.

**Auth:** Diperlukan | Role: `admin_pusat`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID kru |

**Response 200:**
```json
{
  "success": true
}
```

---

### POST /api/admin/master-data/import

Mengimpor data massal dari file Excel/CSV.

**Auth:** Diperlukan | Role: `admin_pusat`
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `type` | string | Ya | Tipe data: `pesantren` \| `media` \| `kru` |
| `rows` | array | Ya | Array baris data (minimal 1 baris) |

**Contoh Request (`type: pesantren`):**
```json
{
  "type": "pesantren",
  "rows": [
    {
      "nama_pesantren": "Pesantren Baru",
      "nama_pengasuh": "KH. Fauzi",
      "alamat_singkat": "Jombang"
    }
  ]
}
```

**Response 200:**
```json
{
  "success": true,
  "imported": 10,
  "skipped": 2,
  "errors": ["Baris 3: nama_pesantren kosong"]
}
```

---

## Jabatan Codes

### GET /api/admin/jabatan-codes

Mendapatkan semua kode jabatan kru.

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "jabatan_codes": [
    {
      "id": "uuid",
      "code": "KR",
      "name": "Koordinator",
      "description": "Koordinator utama media pesantren",
      "created_at": "2026-01-01T00:00:00.000000Z"
    }
  ]
}
```

---

### POST /api/admin/jabatan-codes

Membuat kode jabatan baru.

**Auth:** Diperlukan | Role: `admin_pusat`
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `code` | string | Ya | Kode 2-3 huruf kapital (regex: `^[A-Z]{2,3}$`) |
| `name` | string | Ya | Nama jabatan |
| `description` | string | Tidak | Deskripsi jabatan |

**Contoh Request:**
```json
{
  "code": "ED",
  "name": "Editor",
  "description": "Editor konten media"
}
```

**Response 200:**
```json
{
  "jabatan_code": {
    "id": "uuid",
    "code": "ED",
    "name": "Editor",
    "description": "Editor konten media",
    "created_at": "2026-03-09T10:00:00.000000Z"
  }
}
```

---

### PUT /api/admin/jabatan-codes/{id}

Memperbarui kode jabatan.

**Auth:** Diperlukan | Role: `admin_pusat`
**Content-Type:** `application/json`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID kode jabatan |

**Request Body:** (sama dengan POST jabatan-codes)

**Response 200:**
```json
{
  "jabatan_code": {
    "id": "uuid",
    "code": "ED",
    "name": "Editor",
    "description": "Editor konten media",
    "created_at": "2026-03-09T10:00:00.000000Z"
  }
}
```

---

### DELETE /api/admin/jabatan-codes/{id}

Menghapus kode jabatan.

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "success": true
}
```

---

## Search & Stats

### GET /api/admin/global-search

Pencarian global untuk pesantren dan kru.

**Auth:** Diperlukan | Role: `admin_pusat`

**Query Parameters:**

| Parameter | Type | Required | Keterangan |
|---|---|---|---|
| `query` | string | Tidak | Kata kunci pencarian |

**Response 200:**
```json
{
  "results": [
    {
      "type": "pesantren",
      "id": "uuid",
      "nomorId": "1234567890",
      "nama": "Pesantren Al-Ikhlas",
      "status": "active",
      "region": "Wilayah Jombang",
      "jabatan": null,
      "lembagaInduk": null
    },
    {
      "type": "crew",
      "id": "uuid",
      "nomorId": "KR123456789001",
      "nama": "Budi",
      "status": "active",
      "region": "Wilayah Jombang",
      "jabatan": "Koordinator",
      "lembagaInduk": "Pesantren Al-Ikhlas"
    }
  ]
}
```

---

### GET /api/admin/super-stats

Mendapatkan statistik super (ringkasan keseluruhan sistem).

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "total_users": 200,
  "total_pesantren": 120,
  "paid_users": 80,
  "estimated_revenue": 12000000
}
```

---

### GET /api/admin/late-payment-count

Mendapatkan jumlah pesantren yang terlambat membayar di seluruh wilayah.

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "count": 15
}
```

---

## Pusat Assistants

### GET /api/admin/pusat-assistants

Mendapatkan daftar asisten pusat dan kru yang tersedia untuk dijadikan asisten.

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "assistants": [
    {
      "id": "uuid",
      "crew_id": "uuid",
      "nama": "Budi",
      "email": "budi@pesantren.com",
      "niam": "KR123456789001",
      "pesantren_name": "Pesantren Al-Ikhlas",
      "region_name": "Wilayah Jombang",
      "appointed_at": "2026-01-01T00:00:00.000000Z",
      "appointed_by": "Admin Pusat"
    }
  ],
  "available_crews": [
    {
      "id": "uuid",
      "nama": "Citra",
      "niam": "ED123456789001",
      "pesantren_name": "Pesantren Al-Ikhlas",
      "region_name": "Wilayah Jombang",
      "email": "citra@pesantren.com",
      "profile_id": "uuid"
    }
  ]
}
```

---

### POST /api/admin/pusat-assistants

Menambah asisten pusat.

**Auth:** Diperlukan | Role: `admin_pusat`
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `crewId` | string (uuid) | Ya | ID kru yang dijadikan asisten |

**Response 200:**
```json
{
  "success": true
}
```

---

### DELETE /api/admin/pusat-assistants/{crewId}

Menghapus asisten pusat.

**Auth:** Diperlukan | Role: `admin_pusat`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `crewId` | string (uuid) | ID kru asisten |

**Response 200:**
```json
{
  "success": true
}
```

---

## Regional Management

### GET /api/admin/regional-management/data

Mendapatkan data manajemen wilayah (wilayah, kota, dan pengguna).

**Auth:** Diperlukan | Role: `admin_pusat`

> **Catatan DB:** Field `cities` dalam response berisi data dari tabel `regencies` (bukan `cities`). `id` bertipe string 4 digit sesuai data wilayah Indonesia.

**Response 200:**
```json
{
  "regions": [
    {
      "id": "uuid",
      "name": "Wilayah Jombang",
      "code": "01"
    }
  ],
  "cities": [
    {
      "id": "3517",
      "name": "KABUPATEN JOMBANG",
      "province_id": "35"
    }
  ],
  "users": [
    {
      "id": "uuid",
      "nama_pesantren": "Pesantren Al-Ikhlas",
      "nama_pengasuh": "KH. Ahmad Fauzi",
      "region_id": "uuid",
      "region_name": "Wilayah Jombang",
      "role": "user",
      "status_account": "active"
    }
  ]
}
```

---

### POST /api/admin/regional-management/regions

Membuat wilayah baru.

**Auth:** Diperlukan | Role: `admin_pusat`
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `name` | string | Ya | Nama wilayah |
| `code` | string | Ya | Kode 2 digit angka (regex: `^\d{2}$`) |

**Contoh Request:**
```json
{
  "name": "Wilayah Sidoarjo",
  "code": "09"
}
```

**Response 200:**
```json
{
  "region": {
    "id": "uuid",
    "name": "Wilayah Sidoarjo",
    "code": "09"
  }
}
```

---

### DELETE /api/admin/regional-management/regions/{id}

Menghapus wilayah.

**Auth:** Diperlukan | Role: `admin_pusat`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID wilayah |

**Response 200:**
```json
{
  "success": true
}
```

---

### POST /api/admin/regional-management/cities

> Data kabupaten/kota dikelola dari data wilayah Indonesia (read-only).

**Response 200:**
```json
{
  "message": "Data kabupaten/kota dikelola dari data wilayah Indonesia"
}
```

---

### DELETE /api/admin/regional-management/cities/{id}

> Data kabupaten/kota dikelola dari data wilayah Indonesia (read-only).

**Response 200:**
```json
{
  "message": "Data kabupaten/kota dikelola dari data wilayah Indonesia"
}
```

---

### POST /api/admin/regional-management/assign-admin

Menetapkan admin wilayah untuk suatu wilayah.

**Auth:** Diperlukan | Role: `admin_pusat`
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `userId` | string (uuid) | Ya | ID pengguna yang dijadikan admin wilayah |
| `regionId` | string (uuid) | Ya | ID wilayah |

**Response 200:**
```json
{
  "success": true,
  "region": {
    "id": "uuid",
    "name": "Wilayah Jombang"
  }
}
```

---

## Users Management

### GET /api/admin/users-management

Mendapatkan semua pengguna sistem.

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "users": [
    {
      "id": "uuid",
      "nama_pesantren": "Pesantren Al-Ikhlas",
      "nama_pengasuh": "KH. Ahmad Fauzi",
      "role": "user",
      "status_account": "active",
      "status_payment": "paid",
      "region_id": "uuid",
      "region_name": "Wilayah Jombang"
    }
  ]
}
```

---

### POST /api/admin/users/{id}

Memperbarui data pengguna.

**Auth:** Diperlukan | Role: `admin_pusat`
**Content-Type:** `application/json`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID pengguna |

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `role` | string | Ya | Role pengguna |
| `statusAccount` | string | Ya | `pending` \| `active` \| `rejected` |
| `statusPayment` | string | Ya | `paid` \| `unpaid` |

**Contoh Request:**
```json
{
  "role": "user",
  "statusAccount": "active",
  "statusPayment": "paid"
}
```

**Response 200:**
```json
{
  "success": true
}
```

---

## Settings

### GET /api/admin/bank-settings

Mendapatkan pengaturan rekening bank.

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "bankName": "Bank Syariah Indonesia (BSI)",
  "bankAccountNumber": "7171234567890",
  "bankAccountName": "MEDIA PONDOK JAWA TIMUR"
}
```

---

### POST /api/admin/bank-settings

Memperbarui pengaturan rekening bank.

**Auth:** Diperlukan | Role: `admin_pusat`
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `bankName` | string | Ya | Nama bank |
| `bankAccountNumber` | string | Ya | Nomor rekening |
| `bankAccountName` | string | Ya | Nama pemilik rekening |

**Response 200:**
```json
{
  "success": true
}
```

---

### GET /api/admin/price-settings

Mendapatkan pengaturan harga pendaftaran.

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "registrationPrice": 150000,
  "claimPrice": 100000
}
```

---

### POST /api/admin/price-settings

Memperbarui pengaturan harga.

**Auth:** Diperlukan | Role: `admin_pusat`
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `registrationPrice` | integer | Ya | Harga pendaftaran pesantren baru (min. 1) |
| `claimPrice` | integer | Ya | Harga klaim pesantren (min. 1) |

**Response 200:**
```json
{
  "success": true
}
```

---

## Regions Detail

### GET /api/admin/regions/{id}/detail

Mendapatkan detail suatu wilayah beserta statistik dan pesantren terbaru.

**Auth:** Diperlukan | Role: `admin_pusat`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID wilayah |

**Response 200:**
```json
{
  "region": {
    "id": "uuid",
    "name": "Wilayah Jombang",
    "code": "01",
    "cities": [
      { "name": "KABUPATEN JOMBANG" }
    ]
  },
  "stats": {
    "member_count": 150,
    "pesantren_count": 45,
    "admin_count": 2
  },
  "recent_pesantren": [
    {
      "id": "uuid",
      "nama_pesantren": "Pesantren Baru",
      "nama_pengasuh": "KH. Fauzi",
      "status_account": "pending",
      "created_at": "2026-03-01T08:00:00.000000Z"
    }
  ]
}
```

---

## Claims & Payments

### GET /api/admin/claims

Mendapatkan semua klaim pesantren.

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "claims": [
    {
      "id": "uuid",
      "user_id": "uuid",
      "pesantren_name": "Pesantren Al-Ikhlas",
      "nama_pengelola": "Ahmad",
      "jenis_pengajuan": "pesantren_baru",
      "status": "regional_approved",
      "created_at": "2026-03-01T08:00:00.000000Z",
      "region_id": "uuid",
      "mpj_id_number": null,
      "region_name": "Wilayah Jombang"
    }
  ]
}
```

---

### GET /api/admin/payments

Mendapatkan semua data pembayaran.

**Auth:** Diperlukan | Role: `admin_pusat` atau `admin_finance`

**Response 200:**
```json
{
  "payments": [
    {
      "id": "uuid",
      "user_id": "uuid",
      "pesantren_claim_id": "uuid",
      "base_amount": 150000,
      "unique_code": 542,
      "total_amount": 150542,
      "proof_file_url": "/uploads/payment-proofs/uuid/1709900000.jpg",
      "status": "pending_verification",
      "created_at": "2026-03-01T08:00:00.000000Z",
      "rejection_reason": null,
      "verified_by": null,
      "verified_at": null,
      "pesantren_claims": {
        "pesantren_name": "Pesantren Al-Ikhlas",
        "nama_pengelola": "Ahmad",
        "jenis_pengajuan": "pesantren_baru",
        "region_id": "uuid",
        "mpj_id_number": null
      },
      "profiles": {
        "no_wa_pendaftar": "6281234567890"
      }
    }
  ]
}
```

---

### POST /api/admin/payments/{id}/reject

Menolak bukti pembayaran.

**Auth:** Diperlukan | Role: `admin_pusat` atau `admin_finance`
**Content-Type:** `application/json`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID pembayaran |

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `reason` | string | Ya | Alasan penolakan (minimal 1 karakter) |

**Response 200:**
```json
{
  "success": true
}
```

---

### POST /api/admin/payments/{id}/approve

Menyetujui bukti pembayaran dan mengaktifkan pesantren.

**Auth:** Diperlukan | Role: `admin_pusat` atau `admin_finance`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID pembayaran |

**Response 200:**
```json
{
  "success": true,
  "nip": "1234567890",
  "phoneNumber": "6281234567890",
  "pesantrenName": "Pesantren Al-Ikhlas"
}
```

---

## Leveling

### GET /api/admin/leveling-profiles

Mendapatkan profil pesantren untuk penilaian level.

**Auth:** Diperlukan | Role: `admin_pusat`

**Response 200:**
```json
{
  "profiles": [
    {
      "id": "uuid",
      "nama_pesantren": "Pesantren Al-Ikhlas",
      "nip": "1234567890",
      "profile_level": "gold",
      "sejarah": "Didirikan tahun 2000...",
      "visi_misi": "Menjadi pesantren terbaik...",
      "logo_url": "/uploads/logos/logo.png",
      "foto_pengasuh_url": "/uploads/photos/pengasuh.jpg",
      "region_name": "Wilayah Jombang"
    }
  ]
}
```

---

### POST /api/admin/leveling/{id}/promote-platinum

Mempromosikan pesantren ke level platinum.

**Auth:** Diperlukan | Role: `admin_pusat`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID profil pesantren |

**Response 200:**
```json
{
  "success": true
}
```

---

## Pricing Packages

### GET /api/admin/pricing-packages

Mendapatkan semua paket harga.

**Auth:** Diperlukan | Role: `admin_pusat` atau `admin_finance`

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

### POST /api/admin/pricing-packages

Membuat paket harga baru.

**Auth:** Diperlukan | Role: `admin_pusat` atau `admin_finance`
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `name` | string | Ya | Nama paket |
| `category` | string | Ya | `registration` \| `renewal` \| `upgrade` |
| `hargaPaket` | integer | Ya | Harga normal (min. 1) |
| `hargaDiskon` | integer | Tidak | Harga diskon (min. 1) |
| `isActive` | boolean | Tidak | Status aktif; default: `true` |

**Contoh Request:**
```json
{
  "name": "Paket Registrasi Promo",
  "category": "registration",
  "hargaPaket": 150000,
  "hargaDiskon": 100000,
  "isActive": true
}
```

**Response 200:**
```json
{
  "success": true,
  "id": "uuid"
}
```

---

### PUT /api/admin/pricing-packages/{id}

Memperbarui paket harga.

**Auth:** Diperlukan | Role: `admin_pusat` atau `admin_finance`
**Content-Type:** `application/json`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID paket harga |

**Request Body:** (semua opsional)

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `name` | string | Tidak | Nama paket |
| `category` | string | Tidak | `registration` \| `renewal` \| `upgrade` |
| `hargaPaket` | integer | Tidak | Harga normal |
| `hargaDiskon` | integer | Tidak | Harga diskon |
| `isActive` | boolean | Tidak | Status aktif |

**Response 200:**
```json
{
  "success": true
}
```

---

### PATCH /api/admin/pricing-packages/{id}/toggle

Mengaktifkan/menonaktifkan paket harga.

**Auth:** Diperlukan | Role: `admin_pusat` atau `admin_finance`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID paket harga |

**Response 200:**
```json
{
  "success": true,
  "is_active": false
}
```
