# API Event Frontend Compatibility

Dokumen ini untuk integrasi frontend `mpj_event` ke backend utama `mpjapps-laravel-api`.

## Base URL

Untuk frontend lama yang memakai `lib/api-event/client.ts`, arahkan base URL ke:

```text
http://{host}/api-event/v1
```

Contoh lokal:

```env
MPJ_EVENT_API_BASE_URL=http://127.0.0.1:8000/api-event/v1
NEXT_PUBLIC_MPJ_EVENT_API_BASE_URL=http://127.0.0.1:8000/api-event/v1
```

Jika frontend masih memakai admin token lama:

```env
MPJ_EVENT_API_ADMIN_TOKEN=mpj-event-admin-token
```

Backend juga membaca `EVENT_API_TOKEN`. Default fallback saat env kosong adalah `mpj-event-admin-token`.

## Authentication

Endpoint public tidak perlu token.

Endpoint admin menerima salah satu:

```text
Authorization: Bearer {EVENT_API_TOKEN}
```

atau:

```text
x-admin-token: {EVENT_API_TOKEN}
```

Endpoint admin juga menerima JWT login utama untuk role:

- Admin Pusat
- Admin Regional
- Admin Keuangan
- Koordinator

## Response Shape

Mayoritas endpoint kompatibel mengembalikan:

```json
{
  "success": true,
  "message": "OK",
  "data": {}
}
```

Error validasi Laravel tetap memakai status HTTP sesuai error, biasanya `422`.

## Public Event

### List Event

```http
GET /api-event/v1/event
```

Response `data` berisi array event.

### Detail Event

```http
GET /api-event/v1/event/{id}
```

### Validasi NIAM

```http
GET /api-event/v1/event/niam/validate/{niam}
```

Response sukses:

```json
{
  "valid": true,
  "crew": {
    "id": "uuid",
    "niam": "NIAM001",
    "full_name": "Nama Kru",
    "unit": "Nama Pesantren",
    "photo_path": null
  }
}
```

### Registrasi Event

```http
POST /api-event/v1/event/{id}/register
Content-Type: application/json
```

Payload NIAM:

```json
{
  "registration_path": "NIAM",
  "niam": "NIAM001"
}
```

Payload umum:

```json
{
  "registration_path": "UMUM",
  "full_name": "Nama Peserta",
  "whatsapp": "628123456789",
  "institution_name": "Nama Institusi"
}
```

Untuk upload identitas peserta umum, pakai `multipart/form-data` dan tambahkan field file `id_card`.

### Tiket

```http
GET /api-event/v1/event/ticket/{qr_token}
```

Tiket hanya aktif jika status pembayaran `Paid` atau `Free`.

### Upload Bukti Bayar

```http
POST /api-event/v1/event/payment/proof
Content-Type: multipart/form-data
```

Fields:

| Field | Wajib | Keterangan |
|---|---:|---|
| `qr_token` | Ya | Token tiket peserta |
| `payment_proof` | Ya | File `jpg`, `jpeg`, `png`, `webp`, atau `pdf`, maksimal 6 MB |

## Admin Event

Semua endpoint di bagian ini membutuhkan admin auth.

### List Admin

```http
GET /api-event/v1/event/admin/list
```

Query opsional:

| Query | Keterangan |
|---|---|
| `status` | Filter status event |
| `q` | Cari berdasarkan title/name |
| `per_page` | Default `20` |

### Create Event

```http
POST /api-event/v1/event/admin
Content-Type: application/json
```

Payload minimal:

```json
{
  "title": "Pelatihan Jurnalistik",
  "category": "Pelatihan",
  "start_date": "2026-06-10T09:00:00+07:00"
}
```

Payload lengkap yang didukung:

```json
{
  "title": "Pelatihan Jurnalistik",
  "category": "Pelatihan",
  "event_type": "Non-Kelas",
  "description": "Deskripsi event",
  "location_name": "Surabaya",
  "location_gmaps": "https://maps.google.com/...",
  "start_date": "2026-06-10T09:00:00+07:00",
  "registration_deadline": "2026-06-09T23:59:00+07:00",
  "is_open_for_public": true,
  "is_paid": true,
  "price_niam": 0,
  "price_public": 35000,
  "max_participants": 100,
  "status": "LIVE",
  "payment_method": "manual",
  "speaker_id": null,
  "custom_fields": [
    {
      "label": "Ukuran Kaos",
      "type": "dropdown",
      "options": ["S", "M", "L", "XL"],
      "is_required": true
    }
  ]
}
```

Nilai enum:

| Field | Nilai |
|---|---|
| `category` | `Pelatihan`, `Seremonial`, `Rapat` |
| `event_type` | `Sistem Kelas`, `Non-Kelas` |
| `status` | `DRAFT`, `PENDING`, `APPROVED`, `LIVE`, `FINISHED`, `COMPLETED`, `REJECTED` |
| `payment_method` | `manual`, `gateway` |
| `custom_fields.*.type` | `short_text`, `long_text`, `radio`, `dropdown`, `checkbox` |

### Update Event

```http
PUT /api-event/v1/event/admin/{id}
```

Payload sama seperti create, tetapi semua field opsional.

### Delete Event

```http
DELETE /api-event/v1/event/admin/{id}
```

### Ubah Status

```http
PATCH /api-event/v1/event/admin/{id}/status
Content-Type: application/json
```

```json
{ "status": "LIVE" }
```

### Custom Fields

```http
POST /api-event/v1/event/admin/{id}/custom-fields
Content-Type: application/json
```

```json
{
  "fields": [
    {
      "label": "Pertanyaan",
      "type": "short_text",
      "is_required": true
    }
  ]
}
```

### Upload Poster

```http
POST /api-event/v1/event/admin/{id}/poster
Content-Type: multipart/form-data
```

Fields:

| Field | Wajib | Keterangan |
|---|---:|---|
| `poster` | Ya | Image maksimal 6 MB |

Response:

```json
{
  "success": true,
  "message": "Poster diunggah.",
  "url": "/storage/events/{id}/poster/filename.jpg"
}
```

## Peserta & Pembayaran

### List Peserta Event

```http
GET /api-event/v1/event/admin/{id}/participants
```

Query opsional:

| Query | Keterangan |
|---|---|
| `payment_status` | `Free`, `Unpaid`, `Pending_Approval`, `Paid`, `Rejected` |
| `attendance_status` | `Registered`, `Attended`, `Cancelled` |
| `per_page` | Default `50` |

### Statistik Event

```http
GET /api-event/v1/event/admin/{id}/stats
```

### Export CSV Peserta

```http
GET /api-event/v1/event/admin/{id}/export-csv
```

### Preview Bukti Bayar

```http
GET /api-event/v1/event/admin/payments/{participantId}/proof
```

### Approve / Reject Payment Berdasarkan Participant

```http
POST /api-event/v1/event/admin/payments/{participantId}/approve
POST /api-event/v1/event/admin/payments/{participantId}/reject
```

Payload reject opsional:

```json
{ "reason": "Bukti transfer tidak valid" }
```

### Approve / Reject Payment Berdasarkan Payment ID

```http
POST /api-event/v1/event/payment/{paymentId}/approve
POST /api-event/v1/event/payment/{paymentId}/reject
```

### Cancel Peserta

```http
POST /api-event/v1/event/admin/participants/{participantId}/cancel
```

## Attendance

### Verify Ticket

```http
GET /api-event/v1/event/attendance/verify/{qr_token}
```

### Check-in

```http
POST /api-event/v1/event/attendance/check-in
Content-Type: application/json
```

```json
{
  "qr_token": "EVT-...",
  "scanner_name": "Admin Scanner",
  "scanner_device": "Chrome Android"
}
```

### Attendance Log

```http
GET /api-event/v1/event/attendance/{eventId}/log
```

## Finance Event

### Summary

```http
GET /api-event/v1/event/finance/summary
GET /api-event/v1/event/finance/summary?event_id={eventId}
```

### Recap Semua Event

```http
GET /api-event/v1/event/finance/recap
```

### Export Finance CSV

```http
GET /api-event/v1/event/finance/export
```

### List Transaksi

```http
GET /api-event/v1/event/{eventId}/finance/transactions
```

### Tambah Transaksi Manual

```http
POST /api-event/v1/event/{eventId}/finance/transactions
Content-Type: application/json
```

```json
{
  "type": "expense",
  "title": "Konsumsi",
  "description": "Snack peserta",
  "amount": 500000,
  "transaction_date": "2026-06-10T12:00:00+07:00"
}
```

### Update Transaksi Manual

```http
PUT /api-event/v1/event/{eventId}/finance/transactions/{transactionId}
```

### Void Transaksi Manual

```http
POST /api-event/v1/event/{eventId}/finance/transactions/{transactionId}/void
```

## Mapping Status Untuk Frontend

### Event

Backend kompatibel mengembalikan status event dalam format:

```text
DRAFT, PENDING, APPROVED, LIVE, FINISHED, COMPLETED, REJECTED
```

### Payment

Backend utama memakai status internal:

| Internal Laravel | Response Compatibility |
|---|---|
| `pending` | `Unpaid` |
| `waiting_verification` | `Pending_Approval` |
| `verified` | `Paid` |
| `rejected` | `Rejected` |

### Attendance

```text
Registered, Attended, Cancelled
```

## Catatan Integrasi Frontend

Untuk frontend `mpj_event`, perubahan minimal ada di environment:

```env
MPJ_EVENT_API_BASE_URL=http://127.0.0.1:8000/api-event/v1
NEXT_PUBLIC_MPJ_EVENT_API_BASE_URL=http://127.0.0.1:8000/api-event/v1
MPJ_EVENT_API_ADMIN_TOKEN=mpj-event-admin-token
```

Jika deploy production, ganti host ke domain backend utama:

```env
MPJ_EVENT_API_BASE_URL=https://domain-backend-utama/api-event/v1
NEXT_PUBLIC_MPJ_EVENT_API_BASE_URL=https://domain-backend-utama/api-event/v1
```

Endpoint ini dibuat agar `lib/api-event/*` di frontend lama tetap bisa dipakai dengan perubahan base URL saja.
