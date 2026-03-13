# Profile Pesantren Endpoints

Semua endpoint di grup ini **memerlukan autentikasi** (JWT Bearer Token).

---

## GET /api/profile/pesantren

Mendapatkan semua data profil pesantren milik user yang login. Digunakan saat komponen halaman Identitas Pesantren mount.

**Auth:** Diperlukan

**Response 200:**
```json
{
  "profile": {
    "namaPesantren": "Pesantren Al-Ikhlas",
    "namaPengasuh": "KH. Ahmad Fauzi",
    "alamatSingkat": "Jl. Pesantren No. 1, Surabaya",
    "regionName": "Jawa Timur",
    "cityName": "Kota Surabaya",
    "profileLevel": "basic | silver | gold | platinum",

    "logoPesantrenUrl": "/uploads/pesantren/{userId}/logo_pesantren/...",
    "namaMedia": null,
    "instagram": null,
    "youtube": null,
    "tiktok": null,
    "website": null,
    "fotoPengasuhUrl": null,
    "dawuhPengasuh": null,
    "jumlahSantri": null,
    "tahunBerdiri": null,
    "latitude": null,
    "longitude": null,

    "visiMisi": null,
    "sejarahSingkat": null,
    "tipePesantren": null,
    "jenjangPendidikan": null,
    "programUnggulan": null,
    "fotoGedungUrl": null,
    "logoMediaUrl": null
  }
}
```

**Error Responses:**
- `404` — `{ "message": "Profile tidak ditemukan" }`

---

## PUT /api/profile/pesantren

Simpan/update data profil pesantren dan naikkan level jika syarat terpenuhi.

**Auth:** Diperlukan
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Step | Keterangan |
|---|---|---|---|
| `step` | integer | semua | `1`, `2`, atau `3` — menentukan field yang diproses |
| `namaPesantren` | string | 1 | Nama pesantren |
| `namaPengasuh` | string | 1 | Nama pengasuh |
| `alamatSingkat` | string | 1 | Alamat singkat |
| `namaMedia` | string | 2 | Nama media |
| `instagram` | string | 2 | Username/URL Instagram |
| `youtube` | string | 2 | URL YouTube |
| `tiktok` | string | 2 | Username/URL TikTok |
| `website` | string | 2 | URL website |
| `dawuhPengasuh` | string | 2 | Dawuh / pesan pengasuh |
| `jumlahSantri` | integer | 2 | Jumlah santri |
| `tahunBerdiri` | integer | 2 | Tahun berdiri |
| `latitude` | string | 2 | Koordinat latitude |
| `longitude` | string | 2 | Koordinat longitude |
| `visiMisi` | string | 3 | Visi dan misi pesantren |
| `sejarahSingkat` | string | 3 | Sejarah singkat |
| `tipePesantren` | string | 3 | Tipe pesantren |
| `jenjangPendidikan` | string | 3 | Jenjang pendidikan |
| `programUnggulan` | string | 3 | Program unggulan |

**Logika naik level:**

| Step | Syarat | Level |
|---|---|---|
| 1 | `namaPesantren` + `namaPengasuh` + `alamatSingkat` terisi | `silver` |
| 2 | Step 1 selesai + minimal 1 sosmed + `latitude` & `longitude` terisi | `gold` |
| 3 | Step 2 selesai + `visiMisi` + `sejarahSingkat` terisi | `platinum` |

**Response 200:**
```json
{
  "success": true,
  "profileLevel": "silver | gold | platinum"
}
```

**Error Responses:**
- `404` — `{ "message": "Profile tidak ditemukan" }`
- `422` — Validasi gagal

---

## POST /api/media/upload-pesantren

Upload file gambar untuk profil pesantren.

**Auth:** Diperlukan
**Content-Type:** `multipart/form-data`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `file` | file | Ya | Gambar (jpeg/png/jpg, maksimal 2MB) |
| `type` | string | Ya | `logo_pesantren` \| `foto_pengasuh` \| `foto_gedung` \| `logo_media` |

**Response 200:**
```json
{
  "url": "/uploads/pesantren/{userId}/{type}/{filename}"
}
```

**Mapping type ke field profil:**

| Type | Field yang diupdate |
|---|---|
| `logo_pesantren` | `logoPesantrenUrl` |
| `foto_pengasuh` | `fotoPengasuhUrl` |
| `foto_gedung` | `fotoGedungUrl` |
| `logo_media` | `logoMediaUrl` |

**Error Responses:**
- `422` — File terlalu besar, format tidak didukung, atau `type` tidak valid
