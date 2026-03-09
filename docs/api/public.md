# Public Endpoints

Base prefix: `/api/public`

Semua endpoint di grup ini **tidak memerlukan autentikasi**.

---

## GET /api/public/regions

Mendapatkan daftar semua wilayah (region) MPJ.

**Auth:** Tidak diperlukan

**Response 200:**
```json
{
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

## GET /api/public/cities

Mendapatkan daftar semua kabupaten/kota dari data wilayah Indonesia.

**Auth:** Tidak diperlukan

> **Catatan DB:** Endpoint ini mengambil data dari tabel `regencies` (bukan `cities`). Tabel `cities` telah dihapus dan digantikan oleh `regencies` yang berisi data wilayah Indonesia dari [edwardsamuel/Wilayah-Administratif-Indonesia](https://github.com/edwardsamuel/Wilayah-Administratif-Indonesia). URL path tetap `/cities` untuk kompatibilitas.

**Response 200:**
```json
{
  "cities": [
    {
      "id": "3517",
      "name": "KABUPATEN JOMBANG",
      "province_id": "35"
    }
  ]
}
```

> `id` bertipe string 4 digit (bukan UUID), sesuai dengan ID kabupaten/kota dari data wilayah Indonesia.

---

## GET /api/public/cities/{id}/region

Mendapatkan informasi provinsi dari suatu kabupaten/kota.

**Auth:** Tidak diperlukan

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (4 digit) | ID kabupaten/kota |

**Response 200:**
```json
{
  "city": {
    "id": "3517",
    "name": "KABUPATEN JOMBANG"
  },
  "province": {
    "id": "35",
    "name": "JAWA TIMUR"
  }
}
```

**Error Responses:**
- `404` — `{ "message": "City not found" }`

---

## GET /api/public/directory-search

Pencarian pesantren dari tabel `pesantren_directory` (data direktori eksternal).

**Auth:** Tidak diperlukan

**Query Parameters:**

| Parameter | Type | Required | Keterangan |
|---|---|---|---|
| `search` | string | Tidak | Cari berdasarkan nama pesantren, kota/kabupaten, atau alamat |
| `regionId` | string (uuid) | Tidak | Filter berdasarkan wilayah MPJ; gunakan `all` untuk semua |

**Contoh Request:**
```
GET /api/public/directory-search?search=Al-Ikhlas&regionId=all
```

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "nama_pesantren": "Pesantren Al-Ikhlas",
      "nama_pengasuh": "KH. Ahmad Fauzi",
      "alamat": "Jl. Pesantren No. 1, Cukir",
      "kota_kabupaten": "Kabupaten Jombang",
      "regency_id": "3517",
      "region_id": "uuid",
      "no_wa_admin": "6281234567890",
      "email_admin": "admin@pesantren.com",
      "maps_link": "https://maps.google.com/?q=...",
      "kode_regional": "01",
      "is_claimed": false,
      "source_year": 2024,
      "region": {
        "id": "uuid",
        "name": "Wilayah Jombang",
        "code": "01"
      }
    }
  ]
}
```

> Maksimal 100 hasil. `is_claimed` menunjukkan apakah pesantren ini sudah diklaim oleh pengguna terdaftar.

---

## GET /api/public/directory

Pencarian direktori pesantren yang sudah aktif dan memiliki NIP.

**Auth:** Tidak diperlukan

**Query Parameters:**

| Parameter | Type | Required | Keterangan |
|---|---|---|---|
| `search` | string | Tidak | Cari berdasarkan nama pesantren atau NIP |
| `regionId` | string (uuid) | Tidak | Filter berdasarkan wilayah; gunakan `all` untuk semua |

**Contoh Request:**
```
GET /api/public/directory?search=Al-Ikhlas&regionId=all
```

**Response 200:**
```json
{
  "pesantren": [
    {
      "id": "uuid",
      "nama_pesantren": "Pesantren Al-Ikhlas",
      "logo_url": "/uploads/logos/logo.png",
      "nip": "1234567890",
      "profile_level": "silver",
      "region_id": "uuid",
      "region": {
        "name": "Wilayah Jombang",
        "code": "01"
      }
    }
  ]
}
```

> Maksimal 300 hasil dikembalikan.

---

## GET /api/public/pesantren

Pencarian pesantren berdasarkan nama untuk fitur klaim (hanya status `approved`/`pusat_approved`).

**Auth:** Tidak diperlukan

**Query Parameters:**

| Parameter | Type | Required | Keterangan |
|---|---|---|---|
| `search` | string | Ya | Kata kunci nama pesantren (minimal 1 karakter) |

**Response 200:**
```json
{
  "pesantren": [
    {
      "id": "uuid",
      "name": "Pesantren Al-Ikhlas",
      "region": "Wilayah Jombang",
      "alamat": "Jl. Pesantren No. 1, Jombang"
    }
  ]
}
```

> Maksimal 20 hasil. Mengembalikan array kosong jika `search` tidak diberikan.

---

## GET /api/public/pesantren/{nip}/profile

Mendapatkan profil publik pesantren berdasarkan NIP beserta daftar kru.

**Auth:** Tidak diperlukan

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `nip` | string | NIP pesantren (titik diabaikan secara otomatis) |

**Response 200:**
```json
{
  "pesantren": {
    "id": "uuid",
    "nama_pesantren": "Pesantren Al-Ikhlas",
    "nama_pengasuh": "KH. Ahmad Fauzi",
    "nama_media": "Media Al-Ikhlas",
    "logo_url": "/uploads/logos/logo.png",
    "nip": "1234567890",
    "profile_level": "silver",
    "region_id": "uuid",
    "status_account": "active",
    "social_links": {
      "instagram": "https://instagram.com/...",
      "youtube": "https://youtube.com/..."
    },
    "region": {
      "name": "Wilayah Jombang"
    }
  },
  "crews": [
    {
      "id": "uuid",
      "nama": "Ahmad",
      "niam": "KR123456789001",
      "jabatan": "Koordinator"
    }
  ]
}
```

**Error Responses:**
- `404` — `{ "message": "Pesantren tidak ditemukan" }`

---

## GET /api/public/pesantren/{nip}/crew/{niamSuffix}

Mendapatkan data kru tertentu dari suatu pesantren berdasarkan sufiks NIAM.

**Auth:** Tidak diperlukan

**Path Parameters:**

| Parameter | Type | Keterangan |
|---|---|---|
| `nip` | string | NIP pesantren |
| `niamSuffix` | string | Sufiks 2 digit NIAM kru (misalnya `01`, `02`) |

**Response 200:**
```json
{
  "crew": {
    "id": "uuid",
    "nama": "Ahmad",
    "niam": "KR123456789001",
    "jabatan": "Koordinator",
    "xp_level": 5,
    "profile": {
      "id": "uuid",
      "nama_pesantren": "Pesantren Al-Ikhlas",
      "nip": "1234567890",
      "logo_url": "/uploads/logos/logo.png"
    }
  }
}
```

**Error Responses:**
- `404` — `{ "message": "Pesantren tidak ditemukan" }` atau `{ "message": "Kru tidak ditemukan" }`
