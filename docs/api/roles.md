# Roles & Hak Akses

Base prefix: `/api/roles`

**Auth:** Semua endpoint memerlukan JWT token dengan role `admin_pusat`

---

## GET /api/roles

Mengambil semua role beserta daftar aksesnya.

**Response 200:**
```json
[
  {
    "id": "uuid",
    "nama": "Admin Pusat",
    "is_super_admin": true,
    "akses": ["dashboard.view", "roles.create", "..."],
    "created_at": "2026-03-30T00:00:00Z",
    "updated_at": "2026-03-30T00:00:00Z"
  }
]
```

---

## GET /api/roles/{id}

Mengambil detail satu role.

**Response 200:**
```json
{
  "id": "uuid",
  "nama": "Admin Wilayah",
  "is_super_admin": false,
  "akses": ["regional.view_master_data", "events.view", "..."],
  "created_at": "2026-03-30T00:00:00Z",
  "updated_at": "2026-03-30T00:00:00Z"
}
```

**Response 404:**
```json
{ "message": "Not found" }
```

---

## POST /api/roles

Membuat role baru.

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `nama` | string | Ya | Nama role (unik) |
| `is_super_admin` | boolean | Tidak | Default `false` |
| `akses` | array | Ya | Daftar permission string |

**Contoh Request:**
```json
{
  "nama": "Moderator",
  "is_super_admin": false,
  "akses": ["master_data.view", "global_search"]
}
```

**Response 201:**
```json
{
  "id": "uuid",
  "nama": "Moderator",
  "is_super_admin": false,
  "akses": ["master_data.view", "global_search"]
}
```

---

## PUT /api/roles/{id}

Mengupdate role yang sudah ada.

**Request Body:** sama dengan POST (semua field opsional)

**Response 200:** data role yang sudah diupdate

---

## DELETE /api/roles/{id}

Menghapus role.

**Response 200:**
```json
{ "message": "Role deleted" }
```

---

## Daftar Permission String

Permission menggunakan format `modul.aksi`. Berikut daftar lengkap per role default:

### Admin Pusat (`is_super_admin: true`)
| Permission | Keterangan |
|---|---|
| `dashboard.view` | Lihat dashboard utama |
| `clearing_house.view_pending` | Lihat antrian clearing house |
| `clearing_house.approve` | Setujui klaim di clearing house |
| `clearing_house.reject` | Tolak klaim di clearing house |
| `profiles.view_pending` | Lihat profil pending |
| `master_data.view` | Lihat master data |
| `master_data.update_pesantren` | Update data pesantren |
| `master_data.update_media` | Update data media |
| `master_data.update_crew` | Update data kru |
| `master_data.delete_crew` | Hapus data kru |
| `master_data.import` | Import data massal |
| `jabatan_codes.view` | Lihat kode jabatan |
| `jabatan_codes.create` | Tambah kode jabatan |
| `jabatan_codes.update` | Update kode jabatan |
| `jabatan_codes.delete` | Hapus kode jabatan |
| `payments.view_all` | Lihat semua pembayaran |
| `payments.approve` | Verifikasi pembayaran |
| `payments.reject` | Tolak pembayaran |
| `payments.view_late_count` | Lihat jumlah telat bayar |
| `claims.view_all` | Lihat semua klaim |
| `regional_management.view` | Lihat manajemen wilayah |
| `regional_management.add_region` | Tambah wilayah |
| `regional_management.delete_region` | Hapus wilayah |
| `regional_management.add_city` | Tambah kota |
| `regional_management.delete_city` | Hapus kota |
| `regional_management.assign_admin` | Assign admin wilayah |
| `users.view` | Lihat daftar user |
| `users.update` | Update data user |
| `pusat_assistants.view` | Lihat asisten pusat |
| `pusat_assistants.add` | Tambah asisten pusat |
| `pusat_assistants.remove` | Hapus asisten pusat |
| `settings.view_bank` | Lihat info bank |
| `settings.update_bank` | Update info bank |
| `settings.view_price` | Lihat harga |
| `settings.update_price` | Update harga |
| `pricing_packages.view` | Lihat paket harga |
| `pricing_packages.create` | Tambah paket harga |
| `pricing_packages.update` | Update paket harga |
| `pricing_packages.toggle` | Aktif/nonaktif paket |
| `leveling.view_profiles` | Lihat profil leveling |
| `leveling.promote_platinum` | Promosi ke platinum |
| `global_search` | Pencarian global |
| `events.view` | Lihat event |
| `events.create` | Buat event |
| `events.view_reports` | Lihat laporan event |
| `events.submit_report` | Submit laporan event |
| `roles.view` | Lihat daftar role |
| `roles.create` | Buat role baru |
| `roles.update` | Update role |
| `roles.delete` | Hapus role |

### Admin Wilayah
| Permission | Keterangan |
|---|---|
| `claims.view_pending_count` | Lihat jumlah klaim pending |
| `claims.search` | Cari klaim |
| `regional.view_master_data` | Lihat master data wilayah |
| `regional.view_pending_claims` | Lihat klaim pending wilayah |
| `regional.view_pricing_packages` | Lihat paket harga |
| `regional.claims.approve` | Setujui klaim wilayah |
| `regional.claims.reject` | Tolak klaim wilayah |
| `regional.view_late_payments` | Lihat telat bayar wilayah |
| `regional.follow_up` | Follow up pembayaran |
| `regional.view_performance` | Lihat performa wilayah |
| `regional.view_leaderboard` | Lihat leaderboard |
| `events.view` | Lihat event |
| `events.create_regional` | Buat event wilayah |
| `events.update_regional` | Update event wilayah |
| `events.submit_report` | Submit laporan event |

### Admin Keuangan
| Permission | Keterangan |
|---|---|
| `payments.view_all` | Lihat semua pembayaran |
| `payments.approve` | Verifikasi pembayaran |
| `payments.reject` | Tolak pembayaran |
| `payments.view_late_count` | Lihat jumlah telat bayar |
| `finance.view_stats` | Lihat statistik keuangan |
| `pricing_packages.view` | Lihat paket harga |
| `master_data.view` | Lihat master data |
| `dashboard.view_home_summary` | Lihat ringkasan dashboard |

### Koordinator
| Permission | Keterangan |
|---|---|
| `regional.view_master_data` | Lihat master data wilayah |
| `regional.view_leaderboard` | Lihat leaderboard |
| `regional.view_performance` | Lihat performa wilayah |
| `events.view` | Lihat event |
| `events.create` | Buat event |
| `events.view_reports` | Lihat laporan event |
| `events.submit_report` | Submit laporan event |
| `master_data.view` | Lihat master data |
| `dashboard.view_home_summary` | Lihat ringkasan dashboard |
| `global_search` | Pencarian global |

### Pengguna Pesantren
| Permission | Keterangan |
|---|---|
| `pesantren.view_own` | Lihat profil pesantren sendiri |
| `pesantren.update_own` | Update profil pesantren sendiri |
| `pesantren.upload_media` | Upload media pesantren |
| `claims.search` | Cari klaim |
| `claims.send_otp` | Kirim OTP klaim |
| `claims.verify_otp` | Verifikasi OTP klaim |
| `claims.view_contact` | Lihat kontak klaim |
| `institution.view_ownership` | Lihat kepemilikan institusi |
| `institution.upload_document` | Upload dokumen institusi |
| `institution.submit_initial_data` | Submit data awal institusi |
| `institution.update_location` | Update lokasi institusi |
| `institution.view_pending_status` | Lihat status pending |
| `payments.view_current` | Lihat pembayaran aktif |
| `payments.view_summary` | Lihat ringkasan pembayaran |
| `payments.submit_proof` | Upload bukti pembayaran |
| `crews.view_own` | Lihat kru pesantren sendiri |
| `crews.create` | Tambah kru |
| `crews.update_own` | Update kru sendiri |
| `crews.delete_own` | Hapus kru sendiri |
| `dashboard.view_context` | Lihat dashboard konteks |

### Kru Pesantren
| Permission | Keterangan |
|---|---|
| `crews.view_own` | Lihat data kru sendiri |
| `crews.update_own` | Update data kru sendiri |
| `pesantren.view_own` | Lihat profil pesantren |
| `events.view` | Lihat event |
| `events.submit_report` | Submit laporan event |
| `dashboard.view_context` | Lihat dashboard konteks |
