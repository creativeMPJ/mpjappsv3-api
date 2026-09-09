# API Integrasi MPJApps — untuk MPJ Fest

Jawaban atas dokumen *Kebutuhan API MPJApps untuk MPJ Fest* (7 September 2026).
Seluruh endpoint yang diminta sudah tersedia dan aktif di server testing.

**Base URL:** `https://mpj-api.demotesting.fun`

Contoh payload di dokumen ini diambil langsung dari server, bukan karangan. Nama,
email, dan nomor pada contoh adalah data uji.

---

## 1. Ringkasan pemenuhan

| Permintaan | Endpoint | Status |
|---|---|---|
| 3.1 Daftar lembaga | `GET /api/external/institutions` | Tersedia |
| 3.1 Detail lembaga | `GET /api/external/institutions/{id}` | Tersedia |
| 3.2 Anggota per lembaga | `GET /api/external/institutions/{id}/members` | Tersedia |
| 3.3 Validasi NIAM | `GET /api-event/v1/event/niam/validate/{niam}` | Sudah ada, diperkaya |
| 3.4 Pencarian pondok | `GET /api/public/pesantren?search=` | Sudah ada, diperkaya |
| 3.5 Lembaga milik anggota (P2) | `GET /api/external/members/{id}/institutions` | Tersedia |

P2 ikut dikerjakan sekalian karena datanya sudah tersedia.

Prefix `external` dipakai supaya kontrak integrasi ini terpisah dari endpoint internal
MPJApps. Penamaan fieldnya memakai istilah domain integrasi (`institution`, `member`),
bukan nama kolom database, sehingga perubahan skema internal MPJApps tidak otomatis
menjadi perubahan kontrak di sisi MPJ Fest.

---

## 2. Pemetaan istilah

| Istilah di dokumen Anda | Di MPJApps |
|---|---|
| Lembaga (`institution`) | Profil pesantren yang terdaftar |
| Anggota (`member`) | Kru media pesantren |
| Relasi anggota ↔ lembaga | Kru selalu terikat pada satu profil pesantren |
| Status keanggotaan | `aktif` bila kru sudah diaktivasi dan ber-NIAM, selain itu `nonaktif` |
| Admin lembaga | Kru yang ditandai sebagai PIC pesantren |

Yang dikembalikan sebagai lembaga adalah **profil pesantren yang sudah terdaftar**,
bukan baris direktori hasil impor. Hanya profil yang punya anggota, status keanggotaan,
dan NIP.

Satu kru saat ini terikat pada tepat satu lembaga, jadi endpoint 3.5 mengembalikan
paling banyak satu baris. Bentuknya tetap berupa daftar agar MPJ Fest tidak perlu
berubah kalau nanti satu anggota bisa berada di lebih dari satu lembaga.

---

## 3. Autentikasi

> **Status saat ini: autentikasi belum diaktifkan.**
> Endpoint `/api/external` untuk sementara bisa dipanggil **tanpa token** supaya MPJ Fest
> bisa langsung mulai integrasi. Responsnya membawa header `X-Api-Auth: disabled`.
>
> Begitu token dipasang di server, pemeriksaan token **langsung berlaku** dan permintaan
> tanpa token akan dibalas `401`.
>
> **Mohon siapkan pengiriman header token sejak sekarang**, supaya tidak ada yang putus
> saat autentikasi dinyalakan. Header yang dikirim saat autentikasi belum aktif akan
> diabaikan tanpa efek samping.

Dua bentuk header diterima, pilih salah satu:

```http
X-Api-Key: <token>
```

```http
Authorization: Bearer <token>
```

Token bersifat khusus server-to-server: terpisah dari token pengguna dan tidak membawa
hak akses peran mana pun. MPJ Fest akan mendapat token sendiri yang bisa dicabut tanpa
mengganggu konsumen lain.

**Rate limit:** 120 permintaan per menit per IP. Melebihi batas dibalas `429`.

**Catatan keamanan:** endpoint ini membawa data pribadi anggota (nama, email, nomor
WhatsApp). Selama autentikasi belum aktif, mohon panggil hanya dari server MPJ Fest —
jangan dari browser — dan beri tahu kami IP server Anda bila ingin kami batasi lewat
allowlist.

---

## 4. Endpoint

### 4.1 Daftar lembaga

```http
GET /api/external/institutions
```

| Parameter | Wajib | Keterangan |
|---|---|---|
| `search` | tidak | Cari berdasarkan nama lembaga |
| `region` | tidak | Menerima id wilayah maupun namanya, mis. `MALANG` atau `MALANG RAYA` |
| `is_active` | tidak | `true` / `false` |
| `page` | tidak | Halaman, mulai dari 1 |
| `per_page` | tidak | Default 25, maksimal 100 |

**Contoh:** `GET /api/external/institutions?search=SYADZILI&per_page=1`

```json
{
    "data": [
        {
            "id": "1581e4b4-be9c-4fde-808e-32fbb34d979a",
            "nama": "PPSQ ASY SYADZILI 1",
            "jenis": null,
            "region": "MALANG RAYA",
            "region_id": "3791c85c-4022-4698-9ea1-f610dfd9434c",
            "kota": "KABUPATEN MALANG",
            "kecamatan": null,
            "alamat": "Jl. Sumber Pasir.99A, Sumberpasir, Kec. Pakis, Kabupaten Malang, Jawa Timur 65154",
            "nip": "2601008",
            "is_active": true
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 2,
        "per_page": 1,
        "total": 2
    }
}
```

**Field lembaga**

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | string (UUID) | ID lembaga di MPJApps — **simpan sebagai `institution.mpjapps_id`** |
| `nama` | string | Nama lembaga |
| `jenis` | string \| null | Jenis lembaga. Sebagian besar belum terisi |
| `region` | string \| null | Nama wilayah MPJ |
| `region_id` | string (UUID) \| null | ID wilayah, untuk filter |
| `kota` | string \| null | Kota/Kabupaten |
| `kecamatan` | string \| null | Kecamatan |
| `alamat` | string \| null | Alamat lengkap, jatuh ke alamat singkat bila belum diisi |
| `nip` | string \| null | Nomor Induk Pesantren |
| `is_active` | boolean | Status aktif lembaga |

---

### 4.2 Detail lembaga

```http
GET /api/external/institutions/{id}
```

Objeknya sama dengan 4.1, dibungkus `data` tanpa `meta`.

```json
{
    "data": {
        "id": "1581e4b4-be9c-4fde-808e-32fbb34d979a",
        "nama": "PPSQ ASY SYADZILI 1",
        "jenis": null,
        "region": "MALANG RAYA",
        "region_id": "3791c85c-4022-4698-9ea1-f610dfd9434c",
        "kota": "KABUPATEN MALANG",
        "kecamatan": null,
        "alamat": "Jl. Sumber Pasir.99A, Sumberpasir, Kec. Pakis, Kabupaten Malang, Jawa Timur 65154",
        "nip": "2601008",
        "is_active": true
    }
}
```

---

### 4.3 Anggota per lembaga

```http
GET /api/external/institutions/{id}/members
```

| Parameter | Wajib | Keterangan |
|---|---|---|
| `status` | tidak | Default `aktif`. Isi `all` untuk seluruhnya, termasuk yang belum diaktivasi |
| `page` | tidak | Halaman, mulai dari 1 |
| `per_page` | tidak | Default 25, maksimal 100 |

**Contoh:** `GET /api/external/institutions/1581e4b4-.../members?per_page=2`

```json
{
    "data": [
        {
            "id": "9eb87626-2e5f-4ae8-bf7d-e4b21130035a",
            "member_id": "9eb87626-2e5f-4ae8-bf7d-e4b21130035a",
            "niam": "260100801",
            "nama": "Ilham Islamuddin",
            "email": "ilham@contoh.test",
            "whatsapp": "0812xxxxxxx",
            "jenis_kelamin": null,
            "foto": null,
            "institution_id": "1581e4b4-be9c-4fde-808e-32fbb34d979a",
            "status": "aktif",
            "jabatan": "Koordinator",
            "is_admin_lembaga": true
        },
        {
            "id": "f6dc21de-24ab-4f5e-9eee-657544af361c",
            "member_id": "f6dc21de-24ab-4f5e-9eee-657544af361c",
            "niam": "260100802",
            "nama": "Hadi",
            "email": "hadi@contoh.test",
            "whatsapp": "0812xxxxxxx",
            "jenis_kelamin": null,
            "foto": null,
            "institution_id": "1581e4b4-be9c-4fde-808e-32fbb34d979a",
            "status": "aktif",
            "jabatan": "ketua bidang",
            "is_admin_lembaga": false
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 2,
        "per_page": 2,
        "total": 3
    }
}
```

**Field anggota**

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | string (UUID) | ID anggota — **simpan sebagai `users.mpjapps_id`** |
| `member_id` | string (UUID) | Sama dengan `id`, disediakan agar cocok dengan penamaan di dokumen Anda |
| `niam` | string \| null | Nomor Induk Anggota. `null` bila belum diaktivasi |
| `nama` | string | Nama lengkap |
| `email` | string \| null | |
| `whatsapp` | string \| null | Nomor WhatsApp |
| `jenis_kelamin` | null | Belum ada kolomnya di MPJApps. Dikirim tetap agar bentuk responsnya tidak berubah saat kolomnya ditambahkan |
| `foto` | string (URL) \| null | URL absolut bila ada |
| `institution_id` | string (UUID) | ID lembaga tempat anggota terdaftar |
| `status` | `aktif` \| `nonaktif` | Status keanggotaan |
| `jabatan` | string \| null | mis. `Koordinator`, `ketua bidang` |
| `is_admin_lembaga` | boolean | `true` bila anggota adalah PIC/pengelola lembaga |

Anggota yang masih menunggu verifikasi berstatus `nonaktif` karena NIAM-nya belum
terbit. Untuk keperluan pendaftaran event, gunakan default (`aktif`) saja.

---

### 4.4 Lembaga milik anggota

```http
GET /api/external/members/{id}/institutions
```

Objek lembaga yang sama, ditambah tiga field relasi: `status`, `jabatan`, dan
`is_admin_lembaga`. Tidak dipaginasi.

```json
{
    "data": [
        {
            "id": "1581e4b4-be9c-4fde-808e-32fbb34d979a",
            "nama": "PPSQ ASY SYADZILI 1",
            "jenis": null,
            "region": "MALANG RAYA",
            "region_id": "3791c85c-4022-4698-9ea1-f610dfd9434c",
            "kota": "KABUPATEN MALANG",
            "kecamatan": null,
            "alamat": "Jl. Sumber Pasir.99A, Sumberpasir, Kec. Pakis, Kabupaten Malang, Jawa Timur 65154",
            "nip": "2601008",
            "is_active": true,
            "status": "aktif",
            "jabatan": "Koordinator",
            "is_admin_lembaga": true
        }
    ]
}
```

---

### 4.5 Validasi NIAM — sudah dipakai, kini diperkaya

```http
GET /api-event/v1/event/niam/validate/{niam}
```

Tanpa autentikasi, seperti sebelumnya. **Bentuk lamanya tidak berubah** — hanya bertambah
field, jadi implementasi MPJ Fest yang sudah jalan tidak perlu disesuaikan.

```json
{
    "valid": true,
    "crew": {
        "id": "f6dc21de-24ab-4f5e-9eee-657544af361c",
        "niam": "260100802",
        "full_name": "Hadi",
        "unit": "PPSQ ASY SYADZILI 1",
        "photo_path": null,
        "institution_id": "1581e4b4-be9c-4fde-808e-32fbb34d979a",
        "institution_name": "PPSQ ASY SYADZILI 1",
        "institution_nip": "2601008",
        "jabatan": "ketua bidang",
        "is_admin_lembaga": false,
        "whatsapp": "0812xxxxxxx",
        "email": "hadi@contoh.test",
        "membership_status": "aktif"
    }
}
```

Yang baru: `institution_id`, `institution_name`, `institution_nip`, `jabatan`,
`is_admin_lembaga`, `whatsapp`, `email`, `membership_status`. `photo_path` kini terisi
bila fotonya ada.

NIAM tidak ditemukan — status `404`:

```json
{
    "success": false,
    "message": "NIAM tidak ditemukan.",
    "valid": false
}
```

---

### 4.6 Pencarian pondok — sudah dipakai, kini diperkaya

```http
GET /api/public/pesantren?search=
```

Tanpa autentikasi. Maksimal 20 hasil, tidak dipaginasi.

```json
{
    "pesantren": [
        {
            "id": "3c004071-a6ca-4899-9194-7702965fa6a6",
            "institution_id": "1581e4b4-be9c-4fde-808e-32fbb34d979a",
            "name": "PPSQ ASY SYADZILI 1",
            "region": "MALANG RAYA",
            "region_id": "3791c85c-4022-4698-9ea1-f610dfd9434c",
            "kota": "KABUPATEN MALANG",
            "alamat": "Jl. Sumber Pasir.99A, Sumberpasir, Kec. Pakis, Kabupaten Malang, Jawa Timur 65154",
            "nip": "2601008",
            "status": "aktif",
            "is_active": true
        }
    ]
}
```

> **Penting.** Field `id` di sini adalah **id pengajuan**, bukan id lembaga. Nilainya
> dipertahankan apa adanya karena sudah dipakai MPJ Fest.
>
> Referensi lembaga yang stabil ada di **`institution_id`** — itu yang harus disimpan
> sebagai `institution.mpjapps_id`, dan nilainya sama dengan `id` pada endpoint 4.1.
> Satu lembaga bisa punya lebih dari satu pengajuan, jadi `id` bukan referensi yang tepat.

---

## 5. Format error

```json
{
    "status": "error",
    "message": "Lembaga tidak ditemukan.",
    "errors": {}
}
```

| Kode | Arti |
|---|---|
| `401` | Token tidak disertakan atau tidak dikenali (berlaku setelah autentikasi dinyalakan) |
| `404` | Lembaga atau anggota tidak ditemukan |
| `429` | Melebihi rate limit 120/menit |

Endpoint 4.5 memakai format lamanya sendiri (`success`, `message`, `valid`) karena sudah
dipakai dan tidak diubah.

---

## 6. Catatan integrasi

**Pagination.** Semua daftar di `/api/external` memakai `page` dan `per_page`, dengan
`meta` berisi `current_page`, `last_page`, `per_page`, dan `total`. `per_page` di atas 100
otomatis diturunkan ke 100, bukan ditolak.

**Penambahan field bisa terjadi kapan saja** tanpa pemberitahuan — mohon jangan memakai
parser yang menolak field tak dikenal. **Perubahan atau penghapusan field** akan
diinformasikan minimal 7 hari sebelumnya.

**Webhook belum tersedia.** Sinkronisasi untuk sekarang lewat pull terjadwal seperti
rencana di dokumen Anda. Bila nanti dibutuhkan, silakan ajukan kembali.

**Data yang berubah di MPJApps langsung terlihat** di endpoint ini — tidak ada cache di
sisi kami.

---

## 7. Kontak

Untuk permintaan token, allowlist IP, atau pertanyaan teknis, hubungi tim MPJApps.
