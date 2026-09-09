# API Integrasi Eksternal (`/api/external`)

Untuk aplikasi lain yang memakai MPJApps sebagai master data anggota dan lembaga —
konsumen pertamanya MPJ Fest.

Prefix `external` dipilih supaya kontrak integrasi terpisah dari endpoint internal.
Penamaan fieldnya memakai istilah domain integrasi (`institution`, `member`), bukan
nama kolom database, sehingga perubahan skema di MPJApps tidak otomatis menjadi
perubahan kontrak di sisi konsumen.

## Pemetaan istilah

| Istilah integrasi | Di MPJApps |
|---|---|
| Lembaga (`institution`) | `pesantren_profiles` — pesantren yang sudah punya profil |
| Anggota (`member`) | `crews` — kru media pesantren |
| Relasi anggota ↔ lembaga | `crews.profile_id` |
| Status keanggotaan | `crews.status` (`active` → `aktif`, selain itu `nonaktif`) |
| Admin lembaga | `crews.is_pic` |

Yang dianggap lembaga adalah **profil pesantren**, bukan baris direktori hasil impor.
Hanya profil yang punya anggota, status keanggotaan, dan NIP.

Satu kru terikat pada tepat satu profil, jadi `GET /members/{id}/institutions`
mengembalikan paling banyak satu baris. Bentuknya tetap daftar supaya konsumen tidak
perlu berubah kalau nanti satu anggota bisa berada di banyak lembaga.

## Autentikasi

Token layanan, bukan sesi pengguna. Pemanggilnya tidak pernah membawa hak akses peran
mana pun.

```
X-Api-Key: <token>
```

atau

```
Authorization: Bearer <token>
```

Token diatur lewat env, berpasangan `nama:token` dan dipisah koma:

```
EXTERNAL_API_TOKENS="mpj-fest:<token-acak-panjang>,aplikasi-lain:<token-lain>"
```

Satu konsumen satu token supaya bisa dicabut sendiri-sendiri. **Kalau env ini kosong,
seluruh endpoint `/api/external` membalas 503** — ditutup, bukan dibuka bebas.

Rate limit: **120 permintaan per menit** per IP.

## Endpoint

### `GET /api/external/institutions`

Daftar lembaga.

| Param | Keterangan |
|---|---|
| `search` | Cari berdasarkan nama lembaga |
| `region` | Terima id wilayah maupun namanya (`MALANG`, `MALANG RAYA`) |
| `is_active` | `true`/`false` |
| `page` | Halaman, mulai 1 |
| `per_page` | Default 25, maksimal 100 |

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
      "alamat": "Jl. Sumber Pasir 99A, Sumberpasir, Pakis",
      "nip": "2601008",
      "is_active": true
    }
  ],
  "meta": { "current_page": 1, "last_page": 5, "per_page": 25, "total": 15 }
}
```

Simpan `id` sebagai `institution.mpjapps_id`.

### `GET /api/external/institutions/{id}`

Detail satu lembaga. Bentuk objeknya sama dengan di atas, dibungkus `{ "data": {...} }`.

### `GET /api/external/institutions/{id}/members`

Anggota sebuah lembaga.

| Param | Keterangan |
|---|---|
| `status` | Default `aktif`. Isi `all` untuk seluruhnya, termasuk yang belum diaktivasi |
| `page`, `per_page` | Sama seperti di atas |

```json
{
  "data": [
    {
      "id": "9eb87626-2e5f-4ae8-bf7d-e4b21130035a",
      "member_id": "9eb87626-2e5f-4ae8-bf7d-e4b21130035a",
      "niam": "260100801",
      "nama": "Ilham Islamuddin",
      "email": "ilhamislamuddin@gmail.com",
      "whatsapp": "0987654321",
      "jenis_kelamin": null,
      "foto": null,
      "institution_id": "1581e4b4-be9c-4fde-808e-32fbb34d979a",
      "status": "aktif",
      "jabatan": "Koordinator",
      "is_admin_lembaga": true
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 25, "total": 3 }
}
```

`jenis_kelamin` belum ada kolomnya di MPJApps. Dikirim tetap sebagai `null` agar bentuk
responsnya tidak berubah saat kolomnya ditambahkan nanti.

Anggota yang masih menunggu verifikasi berstatus `nonaktif` karena NIAM-nya belum terbit.

### `GET /api/external/members/{id}/institutions`

Lembaga tempat seorang anggota terdaftar. Objeknya sama dengan lembaga, ditambah
`status`, `jabatan`, dan `is_admin_lembaga`.

## Endpoint lama yang diperkaya

Keduanya **tidak berubah bentuk** — hanya bertambah field, sehingga pemakai yang sudah
jalan tidak perlu menyesuaikan apa pun.

### `GET /api-event/v1/event/niam/validate/{niam}`

Bertambah di dalam `crew`: `institution_id`, `institution_name`, `institution_nip`,
`jabatan`, `is_admin_lembaga`, `whatsapp`, `email`, `membership_status`. `photo_path`
kini terisi kalau fotonya ada.

### `GET /api/public/pesantren?search=`

Bertambah `institution_id`, `region_id`, `kota`, `nip`, `status`, `is_active`.

`id` tetap **id pengajuan**, dipertahankan karena sudah dipakai. Referensi lembaga yang
stabil ada di **`institution_id`** — itu yang harus disimpan, sebab satu lembaga bisa
punya lebih dari satu pengajuan.

## Format error

```json
{ "status": "error", "message": "Lembaga tidak ditemukan.", "errors": {} }
```

| Kode | Arti |
|---|---|
| 401 | Token tidak disertakan atau tidak dikenali |
| 404 | Lembaga atau anggota tidak ditemukan |
| 429 | Melebihi rate limit |
| 503 | `EXTERNAL_API_TOKENS` belum diisi di server |

## Catatan untuk konsumen

- Endpoint ini dipanggil dari backend, bukan browser. Tokennya tidak boleh sampai ke sisi klien.
- Webhook perubahan data belum tersedia. Sinkronisasi untuk sekarang lewat pull terjadwal.
- Perubahan atau penghapusan field akan diinformasikan lebih dulu; penambahan field bisa terjadi kapan saja, jadi jangan pakai parser yang menolak field tak dikenal.
