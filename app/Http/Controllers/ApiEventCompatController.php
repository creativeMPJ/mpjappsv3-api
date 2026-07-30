<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\Event;
use App\Models\EventAttendanceLog;
use App\Models\EventCustomField;
use App\Models\EventFinanceTransaction;
use App\Models\EventGuest;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\PesantrenProfile;
use App\Support\FinanceActivationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class ApiEventCompatController extends Controller
{
    private const EVENT_STATUSES = ['DRAFT', 'PENDING', 'APPROVED', 'LIVE', 'FINISHED', 'COMPLETED', 'REJECTED'];

    public function index()
    {
        $events = Event::query()
            ->with(['speaker', 'customFields'])
            ->whereIn('status', ['APPROVED', 'LIVE', 'FINISHED', 'COMPLETED', 'upcoming', 'active'])
            ->orderByRaw('COALESCE(start_date, date) asc')
            ->get()
            ->map(fn (Event $event) => $this->eventPayload($event));

        return $this->success($events);
    }

    public function show(string $id)
    {
        $event = Event::with(['speaker', 'customFields'])->findOrFail($id);
        return $this->success($this->eventPayload($event));
    }

    public function adminIndex(Request $request)
    {
        $this->assertAdmin($request);

        $query = Event::with(['speaker', 'customFields'])->latest('created_at');
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(fn ($builder) => $builder
                ->where('title', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"));
        }

        $events = $query->paginate((int) $request->query('per_page', 20));
        $events->setCollection($events->getCollection()->map(fn (Event $event) => $this->eventPayload($event)));

        return $this->success($events);
    }

    public function store(Request $request)
    {
        $this->assertAdmin($request);
        $data = $this->validateEvent($request);

        $event = DB::transaction(function () use ($data) {
            $fields = $data['custom_fields'] ?? [];
            unset($data['custom_fields']);

            $event = Event::create($this->eventWritePayload($data) + ['id' => (string) Str::uuid()]);
            $this->replaceCustomFields($event, $fields);

            return $event->fresh(['speaker', 'customFields']);
        });

        return $this->success($this->eventPayload($event), 'Event dibuat.', 201);
    }

    public function adminShow(Request $request, string $id)
    {
        $this->assertAdmin($request);
        return $this->show($id);
    }

    public function update(Request $request, string $id)
    {
        $this->assertAdmin($request);
        $data = $this->validateEvent($request, true);

        $event = DB::transaction(function () use ($id, $data) {
            $event = Event::findOrFail($id);
            $fields = $data['custom_fields'] ?? null;
            unset($data['custom_fields']);

            $event->update(array_filter($this->eventWritePayload($data, true), fn ($value) => $value !== null));
            if (is_array($fields)) {
                $this->replaceCustomFields($event, $fields);
            }

            return $event->fresh(['speaker', 'customFields']);
        });

        return $this->success($this->eventPayload($event), 'Event diperbarui.');
    }

    public function destroy(Request $request, string $id)
    {
        $this->assertAdmin($request);
        Event::findOrFail($id)->delete();

        return $this->message('Event dihapus.');
    }

    public function changeStatus(Request $request, string $id)
    {
        $this->assertAdmin($request);
        $data = $request->validate(['status' => 'required|in:'.implode(',', self::EVENT_STATUSES)]);
        $event = Event::findOrFail($id);
        $event->update(['status' => $data['status']]);

        return $this->success($this->eventPayload($event->fresh(['speaker', 'customFields'])), 'Status event diperbarui.');
    }

    public function syncCustomFields(Request $request, string $id)
    {
        $this->assertAdmin($request);
        $data = $request->validate([
            'fields' => 'required|array',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.type' => 'required|in:short_text,long_text,radio,dropdown,checkbox',
            'fields.*.options' => 'nullable|array',
            'fields.*.is_required' => 'boolean',
        ]);

        $event = Event::findOrFail($id);
        $this->replaceCustomFields($event, $data['fields']);

        return $this->success($event->customFields()->get()->map(fn ($field) => $this->customFieldPayload($field)), 'Custom fields disimpan.');
    }

    public function uploadPoster(Request $request, string $id)
    {
        $this->assertAdmin($request);
        $data = $request->validate(['poster' => 'required|image|max:6144']);
        $event = Event::findOrFail($id);
        $path = $this->storePublicFile($data['poster'], "events/{$event->id}/poster");
        $event->update(['poster_path' => $path]);

        return response()->json(['success' => true, 'message' => 'Poster diunggah.', 'url' => $path]);
    }

    public function validateNiam(string $niam)
    {
        $crew = Crew::with('profile')->where('niam', $niam)->first();
        if (!$crew) {
            return response()->json(['success' => false, 'message' => 'NIAM tidak ditemukan.', 'valid' => false], 404);
        }

        return response()->json([
            'valid' => true,
            'crew' => $this->crewPayload($crew),
        ]);
    }

    public function register(Request $request, string $id)
    {
        $data = $request->validate([
            'registration_path' => 'required|in:NIAM,UMUM',
            'niam' => 'required_if:registration_path,NIAM|string|max:50',
            'full_name' => 'required_if:registration_path,UMUM|string|max:255',
            'whatsapp' => 'required_if:registration_path,UMUM|string|max:50',
            'institution_name' => 'nullable|string|max:255',
            'id_card' => 'nullable|image|max:4096',
        ]);

        $participant = DB::transaction(function () use ($id, $data, $request) {
            $event = Event::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertEventCanRegister($event);

            $activeCount = EventRegistration::where('event_id', $event->id)
                ->where('attendance_status', '!=', 'Cancelled')
                ->lockForUpdate()
                ->count();

            if ($event->max_participants !== null && $activeCount >= $event->max_participants) {
                $event->update(['status_pendaftaran' => 'full', 'current_participants' => $activeCount]);
                abort(response()->json(['success' => false, 'message' => 'Kuota event sudah penuh.'], 422));
            }

            $crew = null;
            $guest = null;
            if ($data['registration_path'] === 'NIAM') {
                $crew = Crew::where('niam', $data['niam'])->first();
                if (!$crew) {
                    abort(response()->json(['success' => false, 'message' => 'NIAM tidak ditemukan.'], 404));
                }
                if (EventRegistration::where('event_id', $event->id)->where('crew_id', $crew->id)->exists()) {
                    abort(response()->json(['success' => false, 'message' => 'NIAM ini sudah terdaftar di event ini.'], 409));
                }
            } else {
                if (!$this->eventIsPublic($event)) {
                    abort(response()->json(['success' => false, 'message' => 'Event ini tidak terbuka untuk umum.'], 422));
                }

                $guest = EventGuest::firstOrNew(['whatsapp' => $data['whatsapp']]);
                $guest->full_name = $data['full_name'];
                $guest->institution_name = $data['institution_name'] ?? $guest->institution_name;
                if ($request->file('id_card')) {
                    $guest->id_card_path = $this->storePublicFile($request->file('id_card'), "events/{$event->id}/identity");
                }
                $guest->save();

                if (EventRegistration::where('event_id', $event->id)->where('guest_id', $guest->id)->exists()) {
                    abort(response()->json(['success' => false, 'message' => 'Nomor WhatsApp ini sudah terdaftar.'], 409));
                }
            }

            $basePrice = $data['registration_path'] === 'NIAM'
                ? (int) ($event->price_niam ?? $event->member_price ?? 0)
                : (int) ($event->price_public ?? $event->public_price ?? 0);
            $isPaid = (bool) $event->is_paid || $basePrice > 0;
            $uniqueAmount = $isPaid ? $basePrice + random_int(1, 999) : 0;
            $qrToken = $this->generateTicketToken();

            $participant = EventRegistration::create([
                'id' => (string) Str::uuid(),
                'event_id' => $event->id,
                'user_id' => null,
                'profile_id' => $crew?->profile_id,
                'crew_id' => $crew?->id,
                'guest_id' => $guest?->id,
                'registration_type' => $data['registration_path'] === 'NIAM' ? 'member' : 'public',
                'registration_path' => $data['registration_path'],
                'ticket_code' => $qrToken,
                'qr_token' => $qrToken,
                'ticket_status' => $isPaid ? 'pending_payment' : 'paid',
                'payment_status' => $isPaid ? 'Unpaid' : 'Free',
                'attendance_status' => 'Registered',
                'price_amount' => $basePrice,
                'unique_amount' => $uniqueAmount,
                'participant_name' => $crew?->nama ?? $guest?->full_name,
                'participant_phone' => $crew?->no_wa ?? $guest?->whatsapp,
                'niam' => $crew?->niam,
            ]);

            $payment = $this->createEventPayment($participant, $event, $uniqueAmount, $isPaid ? 'pending' : 'verified');
            $participant->update(['payment_id' => $payment->id]);

            $newCount = EventRegistration::where('event_id', $event->id)->where('attendance_status', '!=', 'Cancelled')->count();
            $event->update([
                'current_participants' => $newCount,
                'status_pendaftaran' => $event->max_participants !== null && $newCount >= $event->max_participants ? 'full' : ($event->status_pendaftaran ?: 'open'),
            ]);

            return $participant->fresh(['event', 'crew', 'guest', 'payment']);
        });

        return $this->success($this->participantPayload($participant), 'Pendaftaran berhasil.', 201);
    }

    public function ticket(string $token)
    {
        $participant = EventRegistration::with(['event', 'crew', 'guest', 'payment'])
            ->where('qr_token', $token)
            ->orWhere('ticket_code', $token)
            ->first();

        if (!$participant) {
            return response()->json(['success' => false, 'message' => 'Tiket tidak ditemukan.'], 404);
        }
        if (!in_array($this->participantPaymentStatus($participant), ['Paid', 'Free'], true)) {
            return response()->json(['success' => false, 'message' => 'Pembayaran belum dikonfirmasi.', 'status' => $this->participantPaymentStatus($participant)], 403);
        }

        return $this->success($this->participantPayload($participant));
    }

    public function uploadProof(Request $request)
    {
        $data = $request->validate([
            'qr_token' => 'required|string',
            'payment_proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:6144',
        ]);

        $participant = DB::transaction(function () use ($data) {
            $participant = EventRegistration::where('qr_token', $data['qr_token'])
                ->orWhere('ticket_code', $data['qr_token'])
                ->lockForUpdate()
                ->first();

            if (!$participant) {
                abort(response()->json(['success' => false, 'message' => 'Tiket tidak ditemukan.'], 404));
            }

            if ($this->participantPaymentStatus($participant) === 'Paid') {
                abort(response()->json(['success' => false, 'message' => 'Pembayaran sudah diverifikasi.'], 409));
            }

            $path = $this->storePublicFile(request()->file('payment_proof'), "events/{$participant->event_id}/payments");
            $payment = $participant->payment ?: $this->createEventPayment($participant, $participant->event, (int) $participant->unique_amount, 'pending');
            $payment->update([
                'status' => FinanceActivationService::STATUS_WAITING_VERIFICATION,
                'proof_file_url' => $path,
                'proof_path' => $path,
                'submitted_at' => now(),
                'rejection_reason' => null,
            ]);

            $participant->update([
                'payment_status' => 'Pending_Approval',
                'ticket_status' => 'waiting_verification',
                'payment_proof_path' => $path,
                'payment_id' => $payment->id,
            ]);

            return $participant->fresh(['event', 'crew', 'guest', 'payment']);
        });

        return $this->success($this->participantPayload($participant));
    }

    public function participants(Request $request, string $id)
    {
        $this->assertAdmin($request);

        $query = EventRegistration::with(['event', 'crew', 'guest', 'payment'])
            ->where('event_id', $id)
            ->latest('created_at');

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->query('payment_status'));
        }
        if ($request->filled('attendance_status')) {
            $query->where('attendance_status', $request->query('attendance_status'));
        }

        $participants = $query->paginate((int) $request->query('per_page', 50));
        $participants->setCollection($participants->getCollection()->map(fn ($participant) => $this->participantPayload($participant)));

        return $this->success($participants);
    }

    public function stats(Request $request, string $id)
    {
        $this->assertAdmin($request);

        $participants = EventRegistration::where('event_id', $id);

        return $this->success([
            'total_registered' => (clone $participants)->count(),
            'paid' => (clone $participants)->whereIn('payment_status', ['Paid', 'Free'])->count(),
            'pending_payment' => (clone $participants)->where('payment_status', 'Pending_Approval')->count(),
            'attended' => (clone $participants)->where('attendance_status', 'Attended')->count(),
        ]);
    }

    public function exportCsv(Request $request, string $id): StreamedResponse
    {
        $this->assertAdmin($request);
        $rows = EventRegistration::with(['crew', 'guest'])->where('event_id', $id)->get();

        return response()->stream(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nama', 'Jalur', 'Pembayaran', 'Kehadiran', 'QR Token']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row->display_name, $row->registration_path, $this->participantPaymentStatus($row), $row->attendance_status, $row->qr_token]);
            }
            fclose($handle);
        }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"peserta-{$id}.csv\""]);
    }

    public function proofPreview(Request $request, string $participantId)
    {
        $this->assertAdmin($request);
        $participant = EventRegistration::with('payment')->findOrFail($participantId);
        $path = $participant->payment_proof_path ?: $participant->payment?->proof_path ?: $participant->payment?->proof_file_url;

        if (!$path) {
            return response()->json(['success' => false, 'message' => 'Bukti transfer belum diupload.'], 422);
        }

        $relative = ltrim(Str::after($path, '/storage/'), '/');
        if (!Storage::disk('public')->exists($relative)) {
            return response()->json(['success' => false, 'message' => 'File bukti transfer tidak ditemukan.'], 404);
        }

        return response()->file(Storage::disk('public')->path($relative));
    }

    public function approve(Request $request, string $participantId)
    {
        $this->assertAdmin($request);
        $participant = EventRegistration::findOrFail($participantId);
        return $this->approveParticipant($participant);
    }

    public function reject(Request $request, string $participantId)
    {
        $this->assertAdmin($request);
        $data = $request->validate(['reason' => 'nullable|string', 'rejection_reason' => 'nullable|string']);
        $participant = EventRegistration::findOrFail($participantId);

        return $this->rejectParticipant($participant, $data['reason'] ?? $data['rejection_reason'] ?? null);
    }

    public function approvePayment(Request $request, string $paymentId)
    {
        $this->assertAdmin($request);
        $payment = Payment::findOrFail($paymentId);
        $participant = EventRegistration::where('payment_id', $payment->id)->orWhere('id', $payment->participant_id)->firstOrFail();

        return $this->approveParticipant($participant);
    }

    public function rejectPayment(Request $request, string $paymentId)
    {
        $this->assertAdmin($request);
        $data = $request->validate(['reason' => 'nullable|string', 'rejection_reason' => 'nullable|string']);
        $payment = Payment::findOrFail($paymentId);
        $participant = EventRegistration::where('payment_id', $payment->id)->orWhere('id', $payment->participant_id)->firstOrFail();

        return $this->rejectParticipant($participant, $data['reason'] ?? $data['rejection_reason'] ?? null);
    }

    public function cancel(Request $request, string $participantId)
    {
        $this->assertAdmin($request);
        $participant = EventRegistration::findOrFail($participantId);
        $participant->update(['attendance_status' => 'Cancelled', 'ticket_status' => 'cancelled']);

        return $this->success($this->participantPayload($participant->fresh(['event', 'crew', 'guest', 'payment'])));
    }

    public function verifyAttendance(Request $request, string $token)
    {
        $this->assertAdmin($request);
        $participant = EventRegistration::with(['event', 'crew', 'guest', 'payment'])
            ->where('qr_token', $token)
            ->orWhere('ticket_code', $token)
            ->first();

        if (!$participant) {
            return response()->json(['valid' => false, 'message' => 'Token tidak dikenali.'], 404);
        }

        return response()->json(['valid' => true, 'participant' => $this->participantPayload($participant)]);
    }

    public function checkIn(Request $request)
    {
        $this->assertAdmin($request);
        $data = $request->validate([
            'qr_token' => 'required|string',
            'scanner_name' => 'nullable|string',
            'scanner_device' => 'nullable|string',
        ]);

        $participant = EventRegistration::with(['event', 'crew', 'guest', 'payment'])
            ->where('qr_token', $data['qr_token'])
            ->orWhere('ticket_code', $data['qr_token'])
            ->first();

        if (!$participant) {
            return response()->json(['success' => false, 'message' => 'QR tidak dikenali atau tidak valid.'], 404);
        }
        if (!in_array($this->participantPaymentStatus($participant), ['Paid', 'Free'], true)) {
            $this->logAttendance($participant, $data, false, 'Pembayaran belum dikonfirmasi.');
            return response()->json(['success' => false, 'message' => 'Pembayaran peserta belum dikonfirmasi.'], 422);
        }
        if ($participant->attendance_status === 'Attended') {
            $this->logAttendance($participant, $data, false, 'Tiket sudah digunakan.');
            return response()->json(['success' => false, 'message' => 'QR Code ini sudah digunakan sebelumnya.', 'participant' => $this->participantPayload($participant)], 409);
        }
        if ($participant->attendance_status === 'Cancelled') {
            $this->logAttendance($participant, $data, false, 'Tiket dibatalkan.');
            return response()->json(['success' => false, 'message' => 'Tiket ini telah dibatalkan.'], 422);
        }

        $participant->update(['attendance_status' => 'Attended', 'ticket_status' => 'attended', 'attended_at' => now()]);
        $this->logAttendance($participant, $data, true, null);

        return response()->json([
            'success' => true,
            'message' => 'Check-in berhasil!',
            'participant' => $this->participantPayload($participant->fresh(['event', 'crew', 'guest', 'payment'])),
        ]);
    }

    public function attendanceLog(Request $request, string $eventId)
    {
        $this->assertAdmin($request);
        $participants = EventRegistration::with(['event', 'crew', 'guest', 'payment'])
            ->where('event_id', $eventId)
            ->where('attendance_status', 'Attended')
            ->latest('attended_at')
            ->get()
            ->map(fn ($participant) => $this->participantPayload($participant));

        return $this->success($participants);
    }

    public function financeSummary(Request $request)
    {
        $this->assertAdmin($request);
        return $this->success($this->financeSummaryPayload($request->query('event_id')));
    }

    public function financeRecap(Request $request)
    {
        $this->assertAdmin($request);
        $rows = Event::query()->get()->map(fn (Event $event) => [
            'event' => ['id' => $event->id, 'title' => $this->eventTitle($event)],
            'finance' => $this->financeSummaryPayload($event->id),
        ]);

        return $this->success($rows);
    }

    public function financeExport(Request $request): StreamedResponse
    {
        $this->assertAdmin($request);
        $rows = EventFinanceTransaction::where('status', 'posted')->latest('transaction_date')->get();

        return response()->stream(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Tanggal', 'Event ID', 'Tipe', 'Sumber', 'Judul', 'Nominal']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row->transaction_date, $row->event_id, $row->type, $row->source, $row->title, $row->amount]);
            }
            fclose($handle);
        }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="event-finance.csv"']);
    }

    public function financeTransactions(Request $request, string $eventId)
    {
        $this->assertAdmin($request);
        $transactions = EventFinanceTransaction::where('event_id', $eventId)->latest('transaction_date')->paginate(50);

        return $this->success($transactions);
    }

    public function storeFinanceTransaction(Request $request, string $eventId)
    {
        $this->assertAdmin($request);
        Event::findOrFail($eventId);
        $data = $this->validateFinanceTransaction($request);
        $transaction = EventFinanceTransaction::create($data + [
            'event_id' => $eventId,
            'source' => 'manual',
            'status' => 'posted',
            'created_by' => $this->jwtUser($request)?->id,
        ]);

        return $this->success($transaction, 'Transaksi disimpan.', 201);
    }

    public function updateFinanceTransaction(Request $request, string $eventId, string $transactionId)
    {
        $this->assertAdmin($request);
        $transaction = EventFinanceTransaction::where('event_id', $eventId)->where('id', $transactionId)->firstOrFail();
        if ($transaction->source !== 'manual') {
            return response()->json(['message' => 'Transaksi payment tidak bisa diedit manual.'], 422);
        }
        $transaction->update($this->validateFinanceTransaction($request, true) + ['updated_by' => $this->jwtUser($request)?->id]);

        return $this->success($transaction->fresh(), 'Transaksi diperbarui.');
    }

    public function voidFinanceTransaction(Request $request, string $eventId, string $transactionId)
    {
        $this->assertAdmin($request);
        $transaction = EventFinanceTransaction::where('event_id', $eventId)->where('id', $transactionId)->firstOrFail();
        if ($transaction->source !== 'manual') {
            return response()->json(['message' => 'Transaksi payment tidak bisa di-void manual.'], 422);
        }
        $transaction->update(['status' => 'void']);

        return $this->message('Transaksi dibatalkan.');
    }

    private function success($data, string $message = 'OK', int $status = 200)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    private function message(string $message, int $status = 200, array $extra = [])
    {
        return response()->json(['success' => true, 'message' => $message] + $extra, $status);
    }

    private function assertAdmin(Request $request): void
    {
        // Token statis hanya berlaku kalau EVENT_API_TOKEN diisi di .env.
        // Tanpa nilai default — default yang hardcoded sebelumnya membuat siapa pun
        // yang membaca source code punya akses admin event penuh.
        $expected = (string) config('services.event_api_token', '');
        $provided = $request->bearerToken() ?: $request->header('x-admin-token', '');
        if ($expected !== '' && $provided !== '' && hash_equals($expected, (string) $provided)) {
            return;
        }

        $user = $this->jwtUser($request);
        $role = $user?->activeRole();
        $allowed = ['Admin Pusat', 'Admin Regional', 'Admin Keuangan', 'Koordinator'];
        if ($role && (in_array($role->nama, $allowed, true) || $role->is_super_admin)) {
            return;
        }

        abort(response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401));
    }

    private function jwtUser(Request $request)
    {
        try {
            if (!$request->bearerToken()) {
                return null;
            }
            return JWTAuth::parseToken()->authenticate();
        } catch (\Throwable) {
            return null;
        }
    }

    private function validateEvent(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => "{$required}|string|max:500",
            'category' => "{$required}|in:Pelatihan,Seremonial,Rapat",
            'event_type' => 'nullable|in:Sistem Kelas,Non-Kelas',
            'description' => 'nullable|string',
            'location_name' => 'nullable|string|max:500',
            'location_gmaps' => 'nullable|string|max:500',
            'start_date' => "{$required}|date",
            'registration_deadline' => 'nullable|date',
            'is_open_for_public' => 'boolean',
            'is_paid' => 'boolean',
            'price_niam' => 'nullable|integer|min:0',
            'price_public' => 'nullable|integer|min:0',
            'max_participants' => 'nullable|integer|min:1',
            'status' => 'nullable|in:'.implode(',', self::EVENT_STATUSES),
            'payment_method' => 'nullable|in:manual,gateway',
            'bank_account_id' => 'nullable|string|max:36',
            'speaker_id' => 'nullable|string|max:36',
            'custom_fields' => 'nullable|array',
            'custom_fields.*.label' => 'required_with:custom_fields|string|max:255',
            'custom_fields.*.type' => 'required_with:custom_fields|in:short_text,long_text,radio,dropdown,checkbox',
            'custom_fields.*.options' => 'nullable|array',
            'custom_fields.*.is_required' => 'boolean',
        ]);
    }

    private function eventWritePayload(array $data, bool $partial = false): array
    {
        if ($partial) {
            $payload = [];
            $map = [
                'title' => ['title', 'name'],
                'category' => ['category'],
                'event_type' => ['event_type'],
                'description' => ['description'],
                'location_name' => ['location_name', 'location'],
                'location_gmaps' => ['location_gmaps'],
                'start_date' => ['start_date', 'date'],
                'registration_deadline' => ['registration_deadline'],
                'is_open_for_public' => ['is_open_for_public'],
                'is_paid' => ['is_paid'],
                'price_niam' => ['price_niam', 'member_price'],
                'price_public' => ['price_public', 'public_price'],
                'max_participants' => ['max_participants'],
                'status' => ['status'],
                'payment_method' => ['payment_method'],
                'bank_account_id' => ['bank_account_id'],
                'speaker_id' => ['speaker_id'],
            ];

            foreach ($map as $inputKey => $columns) {
                if (!array_key_exists($inputKey, $data)) {
                    continue;
                }
                foreach ($columns as $column) {
                    $payload[$column] = $data[$inputKey];
                }
            }

            return $payload;
        }

        $title = $data['title'] ?? null;
        $startDate = $data['start_date'] ?? null;
        $location = $data['location_name'] ?? null;
        $priceNiam = (int) ($data['price_niam'] ?? 0);
        $pricePublic = (int) ($data['price_public'] ?? 0);

        return [
            'title' => $title,
            'name' => $title,
            'category' => $data['category'] ?? null,
            'event_type' => $data['event_type'] ?? ($partial ? null : 'Non-Kelas'),
            'description' => $data['description'] ?? null,
            'location_name' => $location,
            'location' => $location,
            'location_gmaps' => $data['location_gmaps'] ?? null,
            'start_date' => $startDate,
            'date' => $startDate,
            'registration_deadline' => $data['registration_deadline'] ?? null,
            'is_open_for_public' => (bool) ($data['is_open_for_public'] ?? true),
            'is_paid' => (bool) ($data['is_paid'] ?? ($priceNiam > 0 || $pricePublic > 0)),
            'price_niam' => $priceNiam,
            'member_price' => $priceNiam,
            'price_public' => $pricePublic,
            'public_price' => $pricePublic,
            'max_participants' => $data['max_participants'] ?? null,
            'status' => $data['status'] ?? ($partial ? null : 'PENDING'),
            'status_pendaftaran' => 'open',
            'payment_method' => $data['payment_method'] ?? 'manual',
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'speaker_id' => $data['speaker_id'] ?? null,
        ];
    }

    private function replaceCustomFields(Event $event, array $fields): void
    {
        $event->customFields()->delete();
        foreach (array_values($fields) as $index => $field) {
            EventCustomField::create([
                'event_id' => $event->id,
                'label' => $field['label'],
                'type' => $field['type'],
                'options' => $field['options'] ?? [],
                'is_required' => (bool) ($field['is_required'] ?? false),
                'order_num' => $index,
            ]);
        }
    }

    private function eventPayload(Event $event): array
    {
        $startDate = $event->start_date ?: $event->date;

        return [
            'id' => $event->id,
            'title' => $this->eventTitle($event),
            'category' => $event->category ?: 'Pelatihan',
            'event_type' => $event->event_type ?: 'Non-Kelas',
            'poster_path' => $event->poster_path,
            'description' => $event->description,
            'location_name' => $event->location_name ?: $event->location,
            'location_gmaps' => $event->location_gmaps,
            'start_date' => optional($startDate)->toISOString(),
            'registration_deadline' => optional($event->registration_deadline)->toISOString(),
            'is_open_for_public' => (bool) ($event->is_open_for_public ?? true),
            'is_paid' => (bool) ($event->is_paid ?? (($event->price_niam ?: $event->member_price) > 0 || ($event->price_public ?: $event->public_price) > 0)),
            'price_niam' => (int) ($event->price_niam ?? $event->member_price ?? 0),
            'price_public' => (int) ($event->price_public ?? $event->public_price ?? 0),
            'max_participants' => $event->max_participants,
            'current_participants' => (int) ($event->current_participants ?? $event->registrations()->count()),
            'status_pendaftaran' => $event->status_pendaftaran ?: 'open',
            'status' => $this->eventStatus($event),
            'payment_method' => $event->payment_method ?: 'manual',
            'gateway_provider' => $event->gateway_provider,
            'gateway_config' => $event->gateway_config,
            'bank_account_id' => $event->bank_account_id,
            'speaker_id' => $event->speaker_id,
            'speaker' => $event->relationLoaded('speaker') && $event->speaker ? $event->speaker->toArray() : null,
            'custom_fields' => $event->relationLoaded('customFields')
                ? $event->customFields->map(fn ($field) => $this->customFieldPayload($field))->values()
                : [],
            'created_at' => optional($event->created_at)->toISOString(),
            'updated_at' => optional($event->updated_at)->toISOString(),
        ];
    }

    private function customFieldPayload(EventCustomField $field): array
    {
        return [
            'id' => $field->id,
            'event_id' => $field->event_id,
            'label' => $field->label,
            'type' => $field->type,
            'options' => $field->options ?: [],
            'is_required' => (bool) $field->is_required,
            'order_num' => (int) $field->order_num,
            'order' => (int) $field->order_num,
        ];
    }

    private function participantPayload(EventRegistration $participant): array
    {
        $payment = $participant->payment;
        $guest = $participant->guest;
        $crew = $participant->crew;
        $path = $participant->payment_proof_path ?: $payment?->proof_path ?: $payment?->proof_file_url;

        return [
            'id' => $participant->id,
            'event_id' => $participant->event_id,
            'crew_id' => $participant->crew_id,
            'guest_id' => $participant->guest_id,
            'registration_path' => $participant->registration_path ?: ($participant->registration_type === 'member' ? 'NIAM' : 'UMUM'),
            'payment_status' => $this->participantPaymentStatus($participant),
            'unique_amount' => (int) ($participant->unique_amount ?: $payment?->total_amount ?: 0),
            'payment_proof_path' => $path,
            'payment_proof_preview_url' => $path,
            'attendance_status' => $participant->attendance_status ?: ($participant->ticket_status === 'attended' ? 'Attended' : 'Registered'),
            'qr_token' => $participant->qr_token ?: $participant->ticket_code,
            'attended_at' => optional($participant->attended_at)->toISOString(),
            'display_name' => $participant->display_name,
            'crew' => $crew ? $this->crewPayload($crew) : null,
            'guest' => $guest ? $guest->toArray() : null,
            'payment' => $payment ? [
                'id' => $payment->id,
                'amount' => (int) ($payment->amount ?: $payment->total_amount ?: 0),
                'status' => $this->paymentStatusToApi($payment->status),
                'proof_path' => $payment->proof_path ?: $payment->proof_file_url,
                'preview_url' => $payment->proof_path ?: $payment->proof_file_url,
                'submitted_at' => optional($payment->submitted_at)->toISOString(),
                'verified_at' => optional($payment->verified_at)->toISOString(),
                'rejection_reason' => $payment->rejection_reason,
            ] : null,
        ];
    }

    private function crewPayload(Crew $crew): array
    {
        return [
            'id' => $crew->id,
            'niam' => $crew->niam,
            'full_name' => $crew->nama,
            'unit' => $crew->profile?->nama_pesantren,
            'photo_path' => null,
        ];
    }

    private function approveParticipant(EventRegistration $participant)
    {
        return DB::transaction(function () use ($participant) {
            $participant = EventRegistration::whereKey($participant->id)->lockForUpdate()->firstOrFail();
            $payment = $participant->payment;
            if ($payment) {
                $payment->update([
                    'status' => FinanceActivationService::STATUS_VERIFIED,
                    'verified_at' => now(),
                    'rejection_reason' => null,
                    'amount' => $participant->unique_amount,
                ]);
            }
            $participant->update(['payment_status' => 'Paid', 'ticket_status' => 'paid']);

            $this->syncPaymentFinanceTransaction($participant->fresh(['event', 'payment']));

            return $this->success($this->participantPayload($participant->fresh(['event', 'crew', 'guest', 'payment'])));
        });
    }

    private function rejectParticipant(EventRegistration $participant, ?string $reason)
    {
        return DB::transaction(function () use ($participant, $reason) {
            $participant = EventRegistration::whereKey($participant->id)->lockForUpdate()->firstOrFail();
            if ($participant->payment) {
                $participant->payment->update([
                    'status' => FinanceActivationService::STATUS_REJECTED,
                    'verified_at' => null,
                    'rejected_at' => now(),
                    'rejection_reason' => $reason,
                ]);
            }
            $participant->update(['payment_status' => 'Unpaid', 'ticket_status' => 'rejected']);

            return $this->success($this->participantPayload($participant->fresh(['event', 'crew', 'guest', 'payment'])));
        });
    }

    private function createEventPayment(EventRegistration $participant, ?Event $event, int $amount, string $status): Payment
    {
        return Payment::create([
            'id' => (string) Str::uuid(),
            'participant_id' => $participant->id,
            'user_id' => $participant->profile_id,
            'pesantren_claim_id' => null,
            'payment_type' => FinanceActivationService::TYPE_EVENT_REGISTRATION,
            'reference_type' => FinanceActivationService::REFERENCE_EVENT_REGISTRATION,
            'reference_id' => $participant->id,
            'invoice_number' => FinanceActivationService::buildInvoiceNumber(FinanceActivationService::TYPE_EVENT_REGISTRATION),
            'base_amount' => $amount,
            'unique_code' => 0,
            'total_amount' => $amount,
            'amount' => $amount,
            'status' => $status,
            'meta' => [
                'event_id' => $event?->id,
                'event_name' => $event ? $this->eventTitle($event) : null,
            ],
        ]);
    }

    private function syncPaymentFinanceTransaction(EventRegistration $participant): void
    {
        if (!$participant->payment || !$participant->event) {
            return;
        }

        EventFinanceTransaction::updateOrCreate(
            ['payment_id' => $participant->payment->id],
            [
                'event_id' => $participant->event_id,
                'participant_id' => $participant->id,
                'type' => 'income',
                'source' => 'payment',
                'title' => 'Pembayaran peserta '.$participant->display_name,
                'amount' => (int) ($participant->payment->amount ?: $participant->payment->total_amount),
                'status' => 'posted',
                'transaction_date' => now(),
            ]
        );
    }

    private function participantPaymentStatus(EventRegistration $participant): string
    {
        if ($participant->payment) {
            return $this->paymentStatusToApi($participant->payment->status);
        }

        return $participant->payment_status ?: ($participant->ticket_status === 'paid' ? 'Paid' : 'Free');
    }

    private function paymentStatusToApi(?string $status): string
    {
        return match ($status) {
            FinanceActivationService::STATUS_PENDING => 'Unpaid',
            FinanceActivationService::STATUS_WAITING_VERIFICATION => 'Pending_Approval',
            FinanceActivationService::STATUS_VERIFIED => 'Paid',
            FinanceActivationService::STATUS_REJECTED => 'Rejected',
            default => $status ?: 'Unpaid',
        };
    }

    private function assertEventCanRegister(Event $event): void
    {
        if (in_array($event->status, ['FINISHED', 'COMPLETED'], true)) {
            abort(response()->json(['success' => false, 'message' => 'Event sudah selesai.'], 422));
        }
        if (!in_array($this->eventStatus($event), ['APPROVED', 'LIVE'], true)) {
            abort(response()->json(['success' => false, 'message' => 'Event tidak menerima pendaftaran saat ini.'], 422));
        }
        if (($event->status_pendaftaran ?: 'open') !== 'open') {
            abort(response()->json(['success' => false, 'message' => 'Pendaftaran event sedang ditutup.'], 422));
        }
        if ($event->registration_deadline && now()->isAfter($event->registration_deadline)) {
            abort(response()->json(['success' => false, 'message' => 'Masa pendaftaran telah berakhir.'], 422));
        }
    }

    private function eventStatus(Event $event): string
    {
        return in_array($event->status, self::EVENT_STATUSES, true)
            ? $event->status
            : match ($event->status) {
                'upcoming', 'active' => 'LIVE',
                'finished' => 'FINISHED',
                default => 'APPROVED',
            };
    }

    private function eventIsPublic(Event $event): bool
    {
        return (bool) ($event->is_open_for_public ?? true);
    }

    private function eventTitle(Event $event): string
    {
        return $event->title ?: $event->name ?: 'Event MPJ';
    }

    private function generateTicketToken(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $token = 'EVT-'.strtoupper(Str::random(24));
            if (!EventRegistration::where('qr_token', $token)->orWhere('ticket_code', $token)->exists()) {
                return $token;
            }
        }

        return 'EVT-'.strtoupper((string) Str::uuid());
    }

    private function storePublicFile(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'public');
        return '/storage/'.$path;
    }

    private function logAttendance(EventRegistration $participant, array $data, bool $success, ?string $reason): void
    {
        EventAttendanceLog::create([
            'event_id' => $participant->event_id,
            'participant_id' => $participant->id,
            'qr_token' => $participant->qr_token ?: $participant->ticket_code,
            'scanned_by_name' => $data['scanner_name'] ?? null,
            'scanner_device' => $data['scanner_device'] ?? null,
            'scanned_at' => now(),
            'success' => $success,
            'failure_reason' => $reason,
        ]);
    }

    private function financeSummaryPayload(?string $eventId = null): array
    {
        $query = EventFinanceTransaction::where('status', 'posted');
        if ($eventId) {
            $query->where('event_id', $eventId);
        }
        $income = (clone $query)->where('type', 'income')->sum('amount');
        $expense = (clone $query)->where('type', 'expense')->sum('amount');

        return ['income' => (int) $income, 'expense' => (int) $expense, 'balance' => (int) $income - (int) $expense];
    }

    private function validateFinanceTransaction(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'type' => "{$required}|in:income,expense",
            'title' => "{$required}|string|max:255",
            'description' => 'nullable|string',
            'amount' => "{$required}|integer|min:0",
            'transaction_date' => 'nullable|date',
        ]);
    }
}
