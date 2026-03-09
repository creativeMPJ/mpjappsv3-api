# Institution Endpoints

Base prefix: `/api/institution`

Semua endpoint di grup ini **memerlukan autentikasi** (JWT Bearer Token).

---

## GET /api/institution/ownership

Mendapatkan status kepemilikan/pendaftaran pesantren pengguna yang login.

**Auth:** Diperlukan

**Response 200 — Belum ada klaim:**
```json
{
  "claim": null
}
```

**Response 200 — Sudah ada klaim:**
```json
{
  "claim": {
    "id": "uuid",
    "status": "pending",
    "pesantren_name": "Pesantren Al-Ikhlas",
    "jenis_pengajuan": "pesantren_baru"
  }
}
```

> Status klaim: `pending` | `regional_approved` | `pusat_approved` | `approved` | `rejected`
> Jenis pengajuan: `pesantren_baru` | `klaim`

---

## POST /api/institution/upload-registration-document

Mengunggah dokumen bukti pendaftaran pesantren (SK Pesantren, dll.).

**Auth:** Diperlukan
**Content-Type:** `multipart/form-data`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `file` | file | Ya | Dokumen (pdf/jpeg/png/webp, maksimal 1MB) |

**Response 200:**
```json
{
  "path": "/uploads/registration-documents/{userId}/1709900000.pdf"
}
```

> Path ini digunakan sebagai nilai `dokumenBuktiUrl` pada endpoint `initial-data`.

**Error Responses:**
- `422` — File terlalu besar atau format tidak didukung

---

## POST /api/institution/initial-data

Menyimpan data awal pendaftaran institusi dan membuat/memperbarui klaim pesantren.

**Auth:** Diperlukan
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `namaPesantren` | string | Ya | Nama pesantren |
| `namaPengasuh` | string | Ya | Nama pengasuh |
| `alamatLengkap` | string | Ya | Alamat lengkap pesantren |
| `regencyId` | string (4 digit) | Ya | ID kabupaten/kota dari data wilayah |
| `kecamatan` | string | Ya | Nama kecamatan |
| `namaPengelola` | string | Ya | Nama pengelola/pendaftar |
| `emailPengelola` | string | Ya | Email pengelola |
| `noWhatsapp` | string | Ya | Nomor WhatsApp pengelola (min. 8 karakter) |
| `dokumenBuktiUrl` | string | Tidak | Path dokumen dari endpoint `upload-registration-document` |

**Contoh Request:**
```json
{
  "namaPesantren": "Pesantren Al-Ikhlas",
  "namaPengasuh": "KH. Ahmad Fauzi",
  "alamatLengkap": "Jl. Pesantren No. 1, Desa Cukir, Kec. Diwek",
  "regencyId": "3517",
  "kecamatan": "Diwek",
  "namaPengelola": "Ahmad",
  "emailPengelola": "ahmad@pesantren.com",
  "noWhatsapp": "6281234567890",
  "dokumenBuktiUrl": "/uploads/registration-documents/uuid/1709900000.pdf"
}
```

**Response 200:**
```json
{
  "success": true,
  "region": {
    "id": "uuid",
    "name": "Wilayah Jombang",
    "code": "01"
  }
}
```

> `region` adalah `null` jika kabupaten/kota belum dipetakan ke wilayah MPJ manapun.

**Error Responses:**
- `404` — `{ "message": "Regency not found" }` — `regencyId` tidak valid

---

## POST /api/institution/location

Menyimpan koordinat lokasi pesantren.

**Auth:** Diperlukan
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `latitude` | numeric | Tidak | Koordinat lintang |
| `longitude` | numeric | Tidak | Koordinat bujur |

**Contoh Request:**
```json
{
  "latitude": -7.5466,
  "longitude": 112.2384
}
```

**Response 200:**
```json
{
  "success": true
}
```

---

## GET /api/institution/pending-status

Mendapatkan status klaim pesantren yang sedang menunggu persetujuan.

**Auth:** Diperlukan

**Response 200 — Belum ada klaim:**
```json
{
  "claim": null,
  "region": null
}
```

**Response 200 — Ada klaim:**
```json
{
  "claim": {
    "pesantren_name": "Pesantren Al-Ikhlas",
    "nama_pengelola": "Ahmad",
    "region_id": "uuid",
    "status": "pending",
    "jenis_pengajuan": "pesantren_baru"
  },
  "region": {
    "name": "Wilayah Jombang",
    "admin_phone": "6281234567890"
  }
}
```
