# MPJ Apps v3 — API Contract Documentation

## Base URL

```
http://{host}/api
```

## Authentication

All protected endpoints require a JWT token in the `Authorization` header:

```
Authorization: Bearer {token}
```

Tokens are obtained via `POST /api/auth/login` or `POST /api/auth/register`.

## Content Types

- JSON requests: `Content-Type: application/json`
- File upload requests: `Content-Type: multipart/form-data`

## Roles

| Role | Keterangan |
|---|---|
| `user` | Perwakilan pesantren / pengguna umum |
| `admin_regional` | Admin wilayah |
| `admin_pusat` | Admin pusat (superadmin) |
| `admin_finance` | Admin keuangan |
| `coordinator` | Koordinator |
| `crew` | Anggota kru |

## Standard Error Responses

```json
{ "message": "Unauthenticated." }         // 401 - Token tidak ada / tidak valid
{ "message": "Forbidden" }                // 403 - Role tidak cukup
{ "message": "..." }                      // 404 - Resource tidak ditemukan
{ "errors": { "field": ["..."] } }        // 422 - Validation error
```

---

## Endpoint Groups

| File | Prefix | Keterangan |
|---|---|---|
| [auth.md](auth.md) | `/api/auth` | Registrasi, login, profil pengguna |
| [public.md](public.md) | `/api/public` | Data publik (tanpa autentikasi) |
| [claims.md](claims.md) | `/api/claims` | Pengajuan klaim pesantren |
| [payments.md](payments.md) | `/api/payments` | Pembayaran |
| [media.md](media.md) | `/api/media` | Dashboard pengguna & manajemen kru |
| [institution.md](institution.md) | `/api/institution` | Pendaftaran institusi |
| [regional.md](regional.md) | `/api/regional` | Panel admin wilayah |
| [admin.md](admin.md) | `/api/admin` | Panel admin pusat |
| [events.md](events.md) | `/api/events` | Manajemen event |

---

## Catatan Arsitektur Database

### Data Wilayah Indonesia
Tabel `provinces`, `regencies`, `districts`, `villages` **tidak dibuat via migration** — diisi dari sumber eksternal:
```bash
curl -s https://raw.githubusercontent.com/edwardsamuel/Wilayah-Administratif-Indonesia/refs/heads/master/mysql/indonesia.sql \
  | mysql -h localhost -u root -p nama_database --force
```

Hierarki:
```
provinces (id: 2 digit)
  └── regencies (id: 4 digit, FK: province_id)
```

Endpoint `/api/public/cities` dan `/api/public/cities/{id}/region` mengambil data dari tabel `regencies` meskipun URL path-nya menggunakan kata "cities".

---

### Tabel `pesantren_directory`

Endpoint pencarian tersedia di `GET /api/public/directory-search` (lihat [public.md](public.md)). Tabel ini berisi data direktori pesantren dari sumber eksternal dengan kolom:

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | uuid | Primary key |
| `nama_pesantren` | string | Nama pesantren |
| `nama_pengasuh` | string | Nama pengasuh |
| `alamat` | string | Alamat pesantren |
| `kota_kabupaten` | string | Nama kota/kabupaten (teks) |
| `regency_id` | char(4) | FK ke tabel `regencies` |
| `region_id` | uuid | FK ke tabel `regions` |
| `no_wa_admin` | string | Nomor WhatsApp admin |
| `email_admin` | string | Email admin |
| `maps_link` | string | Link Google Maps |
| `kode_regional` | string | Kode regional MPJ |
| `is_claimed` | boolean | Sudah diklaim oleh pengguna? |
| `source_year` | smallint | Tahun data sumber |
| `deleted_at` | timestamp | Soft delete |
