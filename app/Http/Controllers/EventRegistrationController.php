<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\PesantrenProfile;
use Illuminate\Http\Request;

class EventRegistrationController extends Controller
{
    /**
     * Upload surat delegasi atau bukti pembayaran.
     * POST /api/public/event-registration/upload
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpeg,jpg,png,webp|max:2048',
            'type' => 'required|in:surat_delegasi,bukti_pembayaran',
        ]);

        $file = $request->file('file');
        $type = $request->input('type');
        $ext  = $file->getClientOriginalExtension();
        $year = now()->year;
        $folder = "event-registration/{$year}/{$type}";
        $filename = time() . '_' . uniqid() . ".{$ext}";

        $file->storeAs($folder, $filename, 'public');

        return response()->json([
            'path' => "/storage/{$folder}/{$filename}",
        ]);
    }

    /**
     * Submit form pendaftaran peserta event.
     * POST /api/public/event-registration
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id'             => 'required|uuid|exists:events,id',
            'nama'                 => 'required|string|max:255',
            'no_whatsapp'          => 'required|string|max:20|regex:/^[0-9+]+$/',
            'asal_pesantren'       => 'required|string|max:255',
            'alamat_pesantren'     => 'required|string',
            'pesantren_profile_id' => 'nullable|uuid|exists:pesantren_profiles,id',
            'kemampuan_bidang'     => 'required|in:sutradara_script,dop_editor,keduanya',
            'tingkat_kemampuan'    => 'required|in:basic,intermediate,advanced',
            'pengalaman'           => 'required|string|min:30',
            'link_karya'           => 'required|url|max:500',
            'surat_delegasi_path'  => 'required|string',
            'bukti_pembayaran_path'=> 'required|string',
        ]);

        // Map ke kolom yang ada di tabel event_registrations
        $registration = EventRegistration::create([
            'event_id'             => $validated['event_id'],
            'registration_type'    => 'public',
            'ticket_status'        => 'pending_payment',
            'participant_name'     => $validated['nama'],
            'participant_phone'    => $validated['no_whatsapp'],
            'asal_pesantren'       => $validated['asal_pesantren'],
            'alamat_pesantren'     => $validated['alamat_pesantren'],
            'pesantren_profile_id' => $validated['pesantren_profile_id'] ?? null,
            'kemampuan_bidang'     => $validated['kemampuan_bidang'],
            'tingkat_kemampuan'    => $validated['tingkat_kemampuan'],
            'pengalaman'           => $validated['pengalaman'],
            'link_karya'           => $validated['link_karya'],
            'surat_delegasi_path'  => $validated['surat_delegasi_path'],
            'bukti_pembayaran_path'=> $validated['bukti_pembayaran_path'],
        ]);

        return response()->json([
            'success'         => true,
            'message'         => 'Pendaftaran berhasil dikirim. Panitia akan menghubungi kamu via WhatsApp.',
            'registration_id' => $registration->id,
        ], 201);
    }

    /**
     * Cek status pendaftaran berdasarkan ID (untuk halaman success).
     * GET /api/public/event-registration/{id}
     */
    public function show(string $id)
    {
        // Kolom `nama` dan `status` tidak pernah dibuat di migration —
        // yang ada adalah participant_name dan ticket_status.
        $reg = EventRegistration::with('event:id,name,date,location')
            ->select('id', 'event_id', 'participant_name', 'ticket_status', 'created_at')
            ->find($id);

        if (!$reg) {
            return response()->json(['message' => 'Pendaftaran tidak ditemukan'], 404);
        }

        return response()->json([
            'registration' => [
                'id'         => $reg->id,
                'event_id'   => $reg->event_id,
                'nama'       => $reg->participant_name,
                'status'     => $reg->ticket_status,
                'created_at' => $reg->created_at,
                'event'      => $reg->event,
            ],
        ]);
    }
}
