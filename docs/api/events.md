# Events Endpoints

Base prefix: `/api/events`

Semua endpoint di grup ini **memerlukan autentikasi** (JWT Bearer Token).

---

## GET /api/events

Mendapatkan semua event (untuk admin pusat / pengguna umum).

**Auth:** Diperlukan

**Response 200:**
```json
[
  {
    "id": "uuid",
    "name": "Musyawarah Tahunan MPJ",
    "description": "Musyawarah tahunan seluruh anggota MPJ",
    "date": "2026-04-15 08:00:00",
    "location": "Gedung Serbaguna, Jombang",
    "status": "upcoming",
    "created_at": "2026-03-01T00:00:00.000000Z",
    "updated_at": "2026-03-01T00:00:00.000000Z"
  }
]
```

> Diurutkan berdasarkan tanggal terbaru.
> Status: `upcoming` | `ongoing` | `completed` | `cancelled`

---

## POST /api/events

Membuat event baru.

**Auth:** Diperlukan
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `name` | string | Ya | Nama event |
| `description` | string | Tidak | Deskripsi event |
| `date` | date | Ya | Tanggal event (format: `YYYY-MM-DD` atau `YYYY-MM-DD HH:MM:SS`) |
| `location` | string | Tidak | Lokasi event |
| `status` | string | Tidak | Status event; default: `upcoming` |

**Contoh Request:**
```json
{
  "name": "Musyawarah Tahunan MPJ",
  "description": "Musyawarah tahunan seluruh anggota MPJ",
  "date": "2026-04-15",
  "location": "Gedung Serbaguna, Jombang"
}
```

**Response 200:**
```json
{
  "id": "uuid",
  "name": "Musyawarah Tahunan MPJ",
  "description": "Musyawarah tahunan seluruh anggota MPJ",
  "date": "2026-04-15 00:00:00",
  "location": "Gedung Serbaguna, Jombang",
  "status": "upcoming",
  "created_at": "2026-03-09T10:00:00.000000Z",
  "updated_at": "2026-03-09T10:00:00.000000Z"
}
```

---

## GET /api/events/{id}/reports

Mendapatkan laporan partisipasi semua wilayah untuk suatu event.

**Auth:** Diperlukan

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID event |

**Response 200:**
```json
{
  "event": {
    "id": "uuid",
    "name": "Musyawarah Tahunan MPJ",
    "date": "2026-04-15 00:00:00",
    "location": "Gedung Serbaguna, Jombang",
    "status": "upcoming"
  },
  "reports": [
    {
      "regionId": "uuid",
      "regionName": "Wilayah Jombang",
      "status": "Submitted",
      "report": {
        "id": "uuid",
        "event_id": "uuid",
        "region_id": "uuid",
        "participation_count": 25,
        "notes": "Acara berjalan lancar",
        "photo_url": "/uploads/events/foto.jpg",
        "submitted_at": "2026-04-16T09:00:00.000000Z"
      }
    },
    {
      "regionId": "uuid",
      "regionName": "Wilayah Surabaya",
      "status": "Pending",
      "report": null
    }
  ]
}
```

**Error Responses:**
- `404` — `{ "message": "Event not found" }`

---

## POST /api/events/{id}/report

Menyimpan atau memperbarui laporan partisipasi suatu wilayah untuk event (oleh admin pusat).

**Auth:** Diperlukan
**Content-Type:** `application/json`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID event |

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `regionId` | string | Ya | ID wilayah |
| `participationCount` | integer | Ya | Jumlah peserta (min. 0) |
| `notes` | string | Tidak | Catatan laporan |
| `photoUrl` | string | Tidak | URL foto dokumentasi |

**Contoh Request:**
```json
{
  "regionId": "uuid",
  "participationCount": 25,
  "notes": "Acara berjalan lancar",
  "photoUrl": "/uploads/events/foto.jpg"
}
```

**Response 200:**
```json
{
  "id": "uuid",
  "event_id": "uuid",
  "region_id": "uuid",
  "participation_count": 25,
  "notes": "Acara berjalan lancar",
  "photo_url": "/uploads/events/foto.jpg",
  "submitted_at": "2026-03-09T10:00:00.000000Z"
}
```

> Jika laporan untuk wilayah ini sudah ada, data diperbarui (upsert).

---

## GET /api/events/regional

Mendapatkan semua event beserta status laporan wilayah admin yang login.

**Auth:** Diperlukan | Role: `admin_regional`

**Response 200:**
```json
{
  "events": [
    {
      "id": "uuid",
      "name": "Musyawarah Tahunan MPJ",
      "description": "Musyawarah tahunan seluruh anggota MPJ",
      "date": "2026-04-15 00:00:00",
      "location": "Gedung Serbaguna, Jombang",
      "status": "upcoming",
      "created_at": "2026-03-01T00:00:00.000000Z",
      "report_count": 12,
      "my_report": {
        "id": "uuid",
        "event_id": "uuid",
        "participation_count": 25,
        "notes": "Acara berjalan lancar",
        "submitted_at": "2026-04-16T09:00:00.000000Z"
      }
    }
  ]
}
```

> `my_report` adalah `null` jika wilayah admin belum mengisi laporan untuk event tersebut.
> `report_count` adalah total wilayah yang sudah mengisi laporan.

---

## POST /api/events/regional

Membuat event baru (oleh admin wilayah).

**Auth:** Diperlukan | Role: `admin_regional`
**Content-Type:** `application/json`

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `name` | string | Ya | Nama event |
| `description` | string | Tidak | Deskripsi event |
| `date` | date | Ya | Tanggal event |
| `location` | string | Tidak | Lokasi event |

**Response 200:**
```json
{
  "success": true,
  "event": {
    "id": "uuid",
    "name": "Event Wilayah",
    "description": null,
    "date": "2026-04-20 00:00:00",
    "location": "Jombang",
    "status": "upcoming",
    "created_at": "2026-03-09T10:00:00.000000Z",
    "updated_at": "2026-03-09T10:00:00.000000Z"
  }
}
```

---

## PUT /api/events/regional/{id}

Memperbarui data event (oleh admin wilayah).

**Auth:** Diperlukan | Role: `admin_regional`
**Content-Type:** `application/json`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID event |

**Request Body:** (semua opsional)

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `name` | string | Tidak | Nama event |
| `description` | string | Tidak | Deskripsi |
| `date` | date | Tidak | Tanggal event |
| `location` | string | Tidak | Lokasi |
| `status` | string | Tidak | Status event |

**Response 200:**
```json
{
  "success": true,
  "event": {
    "id": "uuid",
    "name": "Event Wilayah Updated",
    "status": "ongoing"
  }
}
```

**Error Responses:**
- `400` — `{ "message": "ID tidak valid" }`

---

## POST /api/events/regional/{id}/report

Menyimpan atau memperbarui laporan partisipasi untuk wilayah admin yang login.

**Auth:** Diperlukan | Role: `admin_regional`
**Content-Type:** `application/json`

**Path Parameter:**

| Parameter | Type | Keterangan |
|---|---|---|
| `id` | string (uuid) | ID event |

**Request Body:**

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `participationCount` | integer | Ya | Jumlah peserta (min. 0) |
| `notes` | string | Tidak | Catatan laporan |

**Contoh Request:**
```json
{
  "participationCount": 30,
  "notes": "Peserta antusias dan acara berjalan tertib"
}
```

**Response 200:**
```json
{
  "success": true,
  "report": {
    "id": "uuid",
    "event_id": "uuid",
    "region_id": "uuid",
    "participation_count": 30,
    "notes": "Peserta antusias dan acara berjalan tertib",
    "submitted_at": "2026-03-09T10:00:00.000000Z"
  }
}
```
