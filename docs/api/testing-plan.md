# Testing Plan — Auth Refactor

Dokumen ini berisi rencana testing untuk memverifikasi semua perubahan dari refactor sistem autentikasi:
- `pesantren_profiles` sekarang punya `id` sendiri + kolom `user_id` (tidak lagi `id = user_id`)
- Kolom `role` dihapus dari `pesantren_profiles`
- Semua pengecekan role melalui `users → user_roles → roles`
- Response login/me membawa field `akses` (array permission)

---

## Akun Testing

| Email | Password | Role |
|-------|----------|------|
| `pusat@mpj.id` | `bismillah` | Admin Pusat |
| `regional.01@gmail.com` | `bismillah` | Admin Wilayah |
| `finance@mpj.id` | `bismillah` | Admin Keuangan |
| `user@mpj.id` | `bismillah` | Pengguna Pesantren |

---

## Fase 1 — Auth & Token

| # | Endpoint | Akun | Yang Dicek |
|---|----------|------|------------|
| 1 | `POST /auth/login` | pusat@mpj.id | Response ada `role: "Admin Pusat"` + array `akses` |
| 2 | `POST /auth/login` | regional.01@gmail.com | `role: "Admin Wilayah"` + akses sesuai |
| 3 | `POST /auth/login` | finance@mpj.id | `role: "Admin Keuangan"` + akses sesuai |
| 4 | `POST /auth/login` | user@mpj.id | `role: "Pengguna Pesantren"` + akses sesuai |
| 5 | `GET /auth/me` | semua akun | Response konsisten dengan login |

---

## Fase 2 — Hak Akses (Role Guard)

| # | Endpoint | Akun | Yang Dicek |
|---|----------|------|------------|
| 6 | `GET /roles` | pusat@mpj.id | Berhasil (super admin) |
| 7 | `GET /roles` | regional.01@gmail.com | Harus **403 Forbidden** |
| 8 | `GET /admin/home-summary` | pusat@mpj.id | Berhasil |
| 9 | `GET /admin/home-summary` | user@mpj.id | Harus **403 Forbidden** |
| 10 | `GET /regional/master-data` | regional.01@gmail.com | Berhasil |
| 11 | `GET /regional/master-data` | pusat@mpj.id | Harus **403 Forbidden** |

---

## Fase 3 — Admin Pusat (Fitur yang Diubah)

| # | Endpoint | Yang Dicek |
|---|----------|------------|
| 12 | `GET /admin/admin-settings/data` | List admin dengan `role` dari user_roles |
| 13 | `GET /admin/pusat-assistants` | Daftar asisten & available |
| 14 | `POST /admin/pusat-assistants` | Tambah asisten → cek user_roles berubah |
| 15 | `DELETE /admin/pusat-assistants/{crewId}` | Hapus asisten → role kembali ke Pengguna Pesantren |
| 16 | `GET /admin/regional-management/data` | Kolom `role` tampil nama bukan enum |
| 17 | `POST /admin/regional-management/assign-admin` | Assign admin wilayah → cek user_roles |
| 18 | `GET /admin/users-management` | Kolom `role` tampil nama bukan enum |
| 19 | `POST /admin/users/{id}` | Update status account/payment, `role` di user_roles ikut berubah |
| 20 | `GET /admin/regions/{id}/detail` | `member_count` & `admin_count` akurat |

---

## Fase 4 — Admin Wilayah

| # | Endpoint | Yang Dicek |
|---|----------|------------|
| 21 | `GET /regional/pending-claims` | Data klaim sesuai region |
| 22 | `POST /regional/claims/{id}/approve` | Approve → payment terbuat |
| 23 | `GET /regional/late-payments` | List pembayaran terlambat |
| 24 | `GET /regional/performance` | Statistik akurat |
| 25 | `GET /regional/leaderboard` | Ranking antar wilayah |

---

## Fase 5 — Media / Crew (Pengguna Pesantren)

| # | Endpoint | Yang Dicek |
|---|----------|------------|
| 26 | `GET /media/crew` | List crew sesuai profile user |
| 27 | `POST /media/crew` | Buat crew baru — `profile_id` pakai `profile.id` bukan `user.id` |
| 28 | `PUT /media/crew/{id}` | Update crew milik sendiri |
| 29 | `DELETE /media/crew/{id}` | Hapus crew milik sendiri |
| 30 | `GET /media/dashboard-context` | Koordinator tampil dengan benar |

---

## Fase 6 — Register Akun Baru

| # | Langkah | Yang Dicek |
|---|---------|------------|
| 31 | `POST /auth/register` email baru | Response ada `role` + `akses` |
| 32 | Login dengan akun baru | Token valid |
| 33 | `GET /media/crew` | Crew awal (Pengasuh) sudah ada |

---

## Urutan Testing

```
Fase 1 → Fase 2 → Fase 3 → Fase 4 → Fase 5 → Fase 6
```

Mulai dari Fase 1 untuk verifikasi token sudah membawa `akses` dengan benar, baru lanjut ke fitur-fitur lain.
