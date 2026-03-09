# Auth Endpoints

Base prefix: `/api/auth`

---

## POST /api/auth/register

Mendaftarkan akun baru dan langsung mengembalikan JWT token.

**Auth:** Tidak diperlukan
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `email` | string | Ya | Harus unik, format email |
| `password` | string | Ya | Minimal 6 karakter |
| `namaPesantren` | string | Tidak | Nama pesantren |
| `namaPengasuh` | string | Tidak | Nama pengasuh pesantren |

**Contoh Request:**
```json
{
  "email": "pengelola@pesantren.com",
  "password": "rahasia123",
  "namaPesantren": "Pesantren Al-Ikhlas",
  "namaPengasuh": "KH. Ahmad Fauzi"
}
```

**Response 201:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user": {
    "id": "uuid",
    "email": "pengelola@pesantren.com",
    "role": "user"
  }
}
```

**Error Responses:**
- `422` — Email sudah terdaftar atau validasi gagal

---

## POST /api/auth/login

Login dan mendapatkan JWT token.

**Auth:** Tidak diperlukan
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `email` | string | Ya | Email terdaftar |
| `password` | string | Ya | Password |

**Contoh Request:**
```json
{
  "email": "admin@gmail.com",
  "password": "bismillah"
}
```

**Response 200:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user": {
    "id": "uuid",
    "email": "admin@gmail.com",
    "role": "admin_pusat"
  }
}
```

**Error Responses:**
- `401` — `{ "message": "Invalid credentials" }`

---

## GET /api/auth/me

Mendapatkan informasi pengguna yang sedang login.

**Auth:** Diperlukan

**Response 200:**
```json
{
  "user": {
    "id": "uuid",
    "email": "pengelola@pesantren.com",
    "role": "user",
    "statusAccount": "active",
    "statusPayment": "unpaid",
    "profileLevel": "basic",
    "nip": "1234567890",
    "namaPesantren": "Pesantren Al-Ikhlas",
    "namaPengasuh": "KH. Ahmad Fauzi",
    "namaMedia": "Media Al-Ikhlas",
    "alamatSingkat": "Jl. Pesantren No. 1, Jombang",
    "regionId": "uuid",
    "logoUrl": "/uploads/logos/pesantren.png"
  }
}
```

**Error Responses:**
- `401` — Token tidak valid
- `404` — `{ "message": "User not found" }`

---

## POST /api/auth/change-password

Mengganti password pengguna yang sedang login.

**Auth:** Diperlukan
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `currentPassword` | string | Ya | Password saat ini |
| `newPassword` | string | Ya | Password baru, minimal 6 karakter |

**Contoh Request:**
```json
{
  "currentPassword": "oldpassword",
  "newPassword": "newpassword123"
}
```

**Response 200:**
```json
{
  "success": true
}
```

**Error Responses:**
- `401` — `{ "message": "Current password is invalid" }`

---

## POST /api/auth/forgot-password

Mengajukan permintaan reset password ke admin.

**Auth:** Tidak diperlukan
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `email` | string | Ya | Email terdaftar |

**Contoh Request:**
```json
{
  "email": "pengelola@pesantren.com"
}
```

**Response 200:**

> Selalu mengembalikan respons sukses, meski email tidak terdaftar (untuk keamanan).

```json
{
  "success": true,
  "message": "Jika akun terdaftar, permintaan reset password telah dikirim ke admin."
}
```
