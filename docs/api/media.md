# Media (User Dashboard) Endpoints

Base prefix: `/api/media`

Semua endpoint di grup ini **memerlukan autentikasi** (JWT Bearer Token).

---

## GET /api/media/jabatan-codes

Mendapatkan daftar kode jabatan yang tersedia untuk anggota kru.

**Auth:** Diperlukan

**Response 200:**
```json
{
  "jabatan_codes": [
    {
      "id": "uuid",
      "name": "Koordinator",
      "code": "KR",
      "description": "Koordinator utama media pesantren"
    }
  ]
}
```

---

## GET /api/media/crew

Mendapatkan daftar kru milik pesantren pengguna yang login.

**Auth:** Diperlukan

**Response 200:**
```json
{
  "crews": [
    {
      "id": "uuid",
      "nama": "Ahmad",
      "jabatan": "Koordinator",
      "niam": "KR123456789001",
      "xp_level": 5,
      "jabatan_code_id": "uuid",
      "jabatan_code": {
        "id": "uuid",
        "name": "Koordinator",
        "code": "KR"
      }
    }
  ]
}
```

---

## POST /api/media/crew

Menambah anggota kru baru untuk pesantren pengguna yang login.

**Auth:** Diperlukan
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `nama` | string | Ya | Nama lengkap anggota kru |
| `jabatanCodeId` | string (uuid) | Tidak | ID kode jabatan (jika dipilih, akan menggantikan `jabatan`) |
| `jabatan` | string | Tidak | Jabatan manual (digunakan jika `jabatanCodeId` tidak diisi) |

**Contoh Request:**
```json
{
  "nama": "Budi Santoso",
  "jabatanCodeId": "uuid-jabatan-kode"
}
```

**Response 200:**
```json
{
  "crew": {
    "id": "uuid",
    "nama": "Budi Santoso",
    "jabatan": "Koordinator",
    "niam": null,
    "status": "pending",
    "xp_level": 0,
    "jabatan_code_id": "uuid",
    "jabatan_code": {
      "id": "uuid",
      "name": "Koordinator",
      "code": "KR"
    }
  },
  "invoice": {
    "id": "uuid",
    "invoice_number": "INV-20260801-0001",
    "status": "pending",
    "total_amount": 350000,
    "payment_type": "crew_activation"
  }
}
```

**Error Responses:**
- `403` — Slot kru penuh. Ajukan pembelian slot tambahan terlebih dahulu melalui `POST /api/media/slot-addons/request`.
- `404` — `{ "message": "Profile tidak ditemukan" }`
- `422` — `{ "message": "Institusi belum aktif penuh, kru belum bisa ditambahkan." }`

> Kru baru dibuat dengan status `pending`. NIAM diterbitkan backend setelah invoice aktivasi kru terverifikasi.
> Format NIAM: `{kodeJabatan}{NIP}{urutan2digit}` — contoh: `KR123456789001`

---

## POST /api/media/slot-addons/request

Membuat invoice pembelian slot kru tambahan memakai paket Finance aktif kategori `crew_addon`.

**Auth:** Diperlukan
**Content-Type:** `application/json`

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `quantity` | integer | Ya | Jumlah slot, 1 sampai 20 |

**Response 200:**
```json
{
  "success": true,
  "payment": {
    "id": "uuid",
    "invoiceNumber": "INV-SLA-20260801-1234",
    "status": "pending",
    "totalAmount": 100999,
    "paymentType": "slot_addon",
    "quantity": 2,
    "pricingPackageName": "Add-on Slot Kru",
    "pricingPackageCategory": "crew_addon"
  }
}
```

> Setelah Finance menyetujui pembayaran, `paid_slot_quantity` profil bertambah sesuai `quantity`.

---

## PUT /api/media/crew/{id}

Memperbarui data anggota kru.

**Auth:** Diperlukan
**Content-Type:** `application/json`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID kru |

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `nama` | string | Ya | Nama lengkap |
| `jabatan` | string | Tidak | Jabatan |

**Contoh Request:**
```json
{
  "nama": "Budi Santoso",
  "jabatan": "Editor"
}
```

**Response 200:**
```json
{
  "crew": {
    "id": "uuid",
    "nama": "Budi Santoso",
    "jabatan": "Editor",
    "niam": "KR123456789002",
    "xp_level": 0
  }
}
```

**Error Responses:**
- `404` — `{ "message": "Kru tidak ditemukan" }` — ID tidak ditemukan atau bukan milik pengguna

---

## DELETE /api/media/crew/{id}

Menghapus anggota kru.

**Auth:** Diperlukan

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

**Error Responses:**
- `404` — `{ "message": "Kru tidak ditemukan" }`

---

## GET /api/media/dashboard-context

Mendapatkan konteks dashboard pengguna (status persetujuan dan koordinator).

**Auth:** Diperlukan

**Response 200:**
```json
{
  "regionalApprovedAt": "2026-01-15T08:30:00.000000Z",
  "pusatApprovedAt": "2026-01-20T10:00:00.000000Z",
  "koordinator": {
    "nama": "Ahmad Fauzi",
    "niam": "KR123456789001",
    "jabatan": "Koordinator",
    "xp_level": 10
  }
}
```

> `koordinator` adalah `null` jika belum ada kru dengan jabatan `Koordinator`.

---

## GET /api/media/profile-settings

Mendapatkan pengaturan profil dasar pengguna.

**Auth:** Diperlukan

**Response 200:**
```json
{
  "namaPengelola": "Ahmad",
  "email": "pengelola@pesantren.com",
  "noWaPendaftar": "6281234567890",
  "namaPanggilan": "Ahmad",
  "alamatAsal": "Jombang",
  "prinsipHidup": "Bermanfaat untuk umat",
  "photoUrl": "/storage/crew-photos/uuid/123.jpg",
  "cvUrl": "/storage/crew-cv/uuid/123.pdf"
}
```

---

## POST /api/media/profile-settings/photo

Upload foto profil kru yang sedang login.

**Auth:** Diperlukan
**Content-Type:** `multipart/form-data`

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `file` | file | Ya | JPG, PNG, atau WEBP, maksimal 2MB |

**Response 200:**
```json
{
  "success": true,
  "photoUrl": "/storage/crew-photos/uuid/123.jpg"
}
```

---

## POST /api/media/profile-settings/cv

Upload CV atau portofolio kru yang sedang login.

**Auth:** Diperlukan
**Content-Type:** `multipart/form-data`

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `file` | file | Ya | PDF, DOC, DOCX, JPG, PNG, maksimal 2MB |

**Response 200:**
```json
{
  "success": true,
  "cvUrl": "/storage/crew-cv/uuid/123.pdf"
}
```
