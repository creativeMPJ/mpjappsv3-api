<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventRegistration;
use App\Models\EventReport;
use App\Models\Payment;
use App\Models\Crew;
use App\Models\PesantrenProfile;
use App\Models\Region;
use App\Models\SystemSetting;
use App\Support\FinanceActivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends Controller
{
    private function mapRegistration(EventRegistration $registration): array
    {
        $payment = $registration->payment;
        $certificateCode = 'CERT-' . strtoupper(substr(str_replace('-', '', $registration->id), 0, 10));

        return [
            'id' => $registration->id,
            'event_id' => $registration->event_id,
            'registration_type' => $registration->registration_type,
            'ticket_code' => $registration->ticket_code,
            'ticket_status' => $registration->ticket_status,
            'price_amount' => $registration->price_amount,
            'payment_id' => $registration->payment_id,
            'participant_name' => $registration->participant_name,
            'participant_phone' => $registration->participant_phone,
            'participant_email' => $registration->participant_email,
            'niam' => $registration->niam,
            'notes' => $registration->notes,
            'created_at' => $registration->created_at,
            'certificate_code' => $certificateCode,
            'certificate_available' => $registration->ticket_status === 'attended' && (bool) ($registration->event?->certificate_enabled ?? false),
            'payment' => $payment ? [
                'id' => $payment->id,
                'payment_type' => $payment->payment_type,
                'status' => FinanceActivationService::normalizePaymentStatus($payment->status),
                'invoice_number' => $payment->invoice_number,
                'total_amount' => $payment->total_amount,
                'proof_file_url' => $payment->proof_file_url,
                'rejection_reason' => $payment->rejection_reason,
                'submitted_at' => $payment->submitted_at,
                'verified_at' => $payment->verified_at,
            ] : null,
            'event' => $registration->event ? [
                'id' => $registration->event->id,
                'name' => $registration->event->name,
                'description' => $registration->event->description,
                'date' => $registration->event->date,
                'location' => $registration->event->location,
                'status' => $registration->event->status,
                'member_price' => $registration->event->member_price,
                'public_price' => $registration->event->public_price,
                'certificate_enabled' => (bool) $registration->event->certificate_enabled,
            ] : null,
        ];
    }

    private function resolveRegistrationContext(): array
    {
        $user = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user?->id)->first();
        $crew = $profile ? Crew::where('profile_id', $profile->id)->orderByDesc('xp_level')->first() : null;
        $isMember = $crew && $crew->status === 'active' && !empty($crew->niam);
        $registrationType = $isMember ? 'member' : 'public';

        return [
            'user' => $user,
            'profile' => $profile,
            'crew' => $crew,
            'registration_type' => $registrationType,
            'default_member_price' => FinanceActivationService::getEventMemberPrice(),
            'default_public_price' => FinanceActivationService::getEventPublicPrice(),
        ];
    }

    private function buildTicketCode(string $eventId): string
    {
        do {
            $ticketCode = 'EVT-' . strtoupper(substr(str_replace('-', '', $eventId), 0, 6)) . '-' . strtoupper(Str::random(6));
        } while (EventRegistration::where('ticket_code', $ticketCode)->exists());

        return $ticketCode;
    }

    private function assertRegional()
    {
        $user    = auth()->user();
        $role    = $user->activeRole();
        $profile = PesantrenProfile::where('user_id', $user->id)->first();

        if (!$role || $role->nama !== 'Admin Regional' || !$profile?->region_id) {
            abort(403, 'Forbidden');
        }
        return $profile->region_id;
    }

    public function index(Request $request)
    {
        $events = Event::orderBy('date', 'desc')->get();
        return response()->json($events->map(fn($event) => [
            'id' => $event->id,
            'name' => $event->name,
            'description' => $event->description,
            'date' => $event->date,
            'location' => $event->location,
            'status' => $event->status,
            'member_price' => $event->member_price,
            'public_price' => $event->public_price,
            'certificate_enabled' => (bool) $event->certificate_enabled,
        ]));
    }

    public function show(Request $request, string $id)
    {
        $event = Event::find($id);
        if (!$event) return response()->json(['message' => 'Event not found'], 404);

        return response()->json([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'description' => $event->description,
                'date' => $event->date,
                'location' => $event->location,
                'status' => $event->status,
                'member_price' => $event->member_price,
                'public_price' => $event->public_price,
                'certificate_enabled' => (bool) $event->certificate_enabled,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string',
            'description' => 'nullable|string',
            'date'        => 'required|date',
            'location'    => 'nullable|string',
            'status'      => 'nullable|string',
            'member_price' => 'nullable|integer|min:0',
            'public_price' => 'nullable|integer|min:0',
            'certificate_enabled' => 'nullable|boolean',
        ]);

        $event = Event::create(array_merge(['id' => Str::uuid()], $data, [
            'status' => $data['status'] ?? 'upcoming',
            'member_price' => $data['member_price'] ?? FinanceActivationService::getEventMemberPrice(),
            'public_price' => $data['public_price'] ?? FinanceActivationService::getEventPublicPrice(),
            'certificate_enabled' => $data['certificate_enabled'] ?? true,
        ]));

        return response()->json($event);
    }

    public function reports(Request $request, string $id)
    {
        $event = Event::find($id);
        if (!$event) return response()->json(['message' => 'Event not found'], 404);

        $regions = Region::orderBy('name')->get();
        $reports = EventReport::where('event_id', $id)->get()->keyBy('region_id');

        $result = $regions->map(fn($r) => [
            'regionId'   => $r->id,
            'regionName' => $r->name,
            'status'     => isset($reports[$r->id]) ? 'Submitted' : 'Pending',
            'report'     => $reports[$r->id] ?? null,
        ]);

        return response()->json(['event' => $event, 'reports' => $result]);
    }

    public function submitReport(Request $request, string $id)
    {
        $data = $request->validate([
            'regionId'          => 'required|string',
            'participationCount' => 'required|integer|min:0',
            'notes'             => 'nullable|string',
            'photoUrl'          => 'nullable|string',
        ]);

        $existing = EventReport::where('event_id', $id)->where('region_id', $data['regionId'])->first();

        if ($existing) {
            $existing->update([
                'participation_count' => $data['participationCount'],
                'notes'               => $data['notes'] ?? null,
                'photo_url'           => $data['photoUrl'] ?? null,
                'submitted_at'        => now(),
            ]);
            return response()->json($existing);
        }

        $report = EventReport::create([
            'id'                  => Str::uuid(),
            'event_id'            => $id,
            'region_id'           => $data['regionId'],
            'participation_count' => $data['participationCount'],
            'notes'               => $data['notes'] ?? null,
            'photo_url'           => $data['photoUrl'] ?? null,
        ]);

        return response()->json($report);
    }

    // ── Regional endpoints ──

    public function regionalIndex(Request $request)
    {
        $regionId = $this->assertRegional();

        $events = Event::orderBy('date', 'desc')->get();

        $myReports = EventReport::where('region_id', $regionId)
            ->get(['id', 'event_id', 'participation_count', 'notes', 'submitted_at'])
            ->keyBy('event_id');

        $reportCounts = EventReport::selectRaw('event_id, count(*) as cnt')
            ->groupBy('event_id')
            ->pluck('cnt', 'event_id');

        return response()->json([
            'events' => $events->map(fn($e) => [
                'id'           => $e->id,
                'name'         => $e->name,
                'description'  => $e->description,
                'date'         => $e->date,
                'location'     => $e->location,
                'status'       => $e->status,
                'member_price' => $e->member_price,
                'public_price' => $e->public_price,
                'certificate_enabled' => (bool) $e->certificate_enabled,
                'created_at'   => $e->created_at,
                'report_count' => $reportCounts[$e->id] ?? 0,
                'my_report'    => $myReports[$e->id] ?? null,
            ]),
        ]);
    }

    public function regionalStore(Request $request)
    {
        $this->assertRegional();

        $data = $request->validate([
            'name'        => 'required|string',
            'description' => 'nullable|string',
            'date'        => 'required|date',
            'location'    => 'nullable|string',
            'member_price' => 'nullable|integer|min:0',
            'public_price' => 'nullable|integer|min:0',
            'certificate_enabled' => 'nullable|boolean',
        ]);

        $event = Event::create(array_merge(['id' => Str::uuid(), 'status' => 'upcoming'], $data, [
            'member_price' => $data['member_price'] ?? FinanceActivationService::getEventMemberPrice(),
            'public_price' => $data['public_price'] ?? FinanceActivationService::getEventPublicPrice(),
            'certificate_enabled' => $data['certificate_enabled'] ?? true,
        ]));

        return response()->json(['success' => true, 'event' => $event]);
    }

    public function regionalUpdate(Request $request, string $id)
    {
        $this->assertRegional();

        $data = $request->validate([
            'name'        => 'nullable|string',
            'description' => 'nullable|string',
            'date'        => 'nullable|date',
            'location'    => 'nullable|string',
            'status'      => 'nullable|string',
            'member_price' => 'nullable|integer|min:0',
            'public_price' => 'nullable|integer|min:0',
            'certificate_enabled' => 'nullable|boolean',
        ]);

        $event = Event::find($id);
        if (!$event) return response()->json(['message' => 'ID tidak valid'], 400);

        $event->update(array_filter($data, fn($v) => $v !== null));

        return response()->json(['success' => true, 'event' => $event]);
    }

    public function regionalSubmitReport(Request $request, string $id)
    {
        $regionId = $this->assertRegional();

        $data = $request->validate([
            'participationCount' => 'required|integer|min:0',
            'notes'              => 'nullable|string',
        ]);

        $existing = EventReport::where('event_id', $id)->where('region_id', $regionId)->first();

        if ($existing) {
            $existing->update([
                'participation_count' => $data['participationCount'],
                'notes'               => $data['notes'] ?? null,
                'submitted_at'        => now(),
            ]);
            return response()->json(['success' => true, 'report' => $existing]);
        }

        $report = EventReport::create([
            'id'                  => Str::uuid(),
            'event_id'            => $id,
            'region_id'           => $regionId,
            'participation_count' => $data['participationCount'],
            'notes'               => $data['notes'] ?? null,
        ]);

        return response()->json(['success' => true, 'report' => $report]);
    }

    public function participants(Request $request, string $id)
    {
        $event = Event::find($id);
        if (!$event) return response()->json(['message' => 'Event not found'], 404);

        $registrations = EventRegistration::with(['event', 'payment'])
            ->where('event_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'participants' => $registrations->map(fn($registration) => $this->mapRegistration($registration))->values(),
        ]);
    }

    public function register(Request $request, string $id)
    {
        $event = Event::find($id);
        if (!$event) return response()->json(['message' => 'Event not found'], 404);

        $context = $this->resolveRegistrationContext();
        $user = $context['user'];
        $profile = $context['profile'];
        $crew = $context['crew'];

        $existing = EventRegistration::where('event_id', $id)
            ->where('user_id', $user?->id)
            ->whereNotIn('ticket_status', ['cancelled'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'registration' => $this->mapRegistration($existing->load(['event', 'payment'])),
                'alreadyRegistered' => true,
            ]);
        }

        $data = $request->validate([
            'participantName' => 'nullable|string|max:255',
            'participantPhone' => 'nullable|string|max:50',
            'participantEmail' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
        ]);

        $priceAmount = (int) ($context['registration_type'] === 'member'
            ? ($event->member_price ?? $context['default_member_price'] ?? 0)
            : ($event->public_price ?? $context['default_public_price'] ?? 0));
        $payment = null;

        if ($priceAmount > 0) {
            $uniqueCode = random_int(100, 999);
            $payment = Payment::create([
                'id' => Str::uuid(),
                'user_id' => $profile?->id,
                'payment_type' => FinanceActivationService::TYPE_EVENT_REGISTRATION,
                'reference_type' => FinanceActivationService::REFERENCE_EVENT_REGISTRATION,
                'reference_id' => null,
                'invoice_number' => FinanceActivationService::buildInvoiceNumber(FinanceActivationService::TYPE_EVENT_REGISTRATION),
                'base_amount' => $priceAmount,
                'unique_code' => $uniqueCode,
                'total_amount' => $priceAmount + $uniqueCode,
                'status' => FinanceActivationService::STATUS_PENDING,
                'created_by' => $user?->id,
                'meta' => [
                    'event_id' => $event->id,
                    'event_name' => $event->name,
                    'registration_type' => $context['registration_type'],
                ],
            ]);
        }

        $registration = EventRegistration::create([
            'id' => Str::uuid(),
            'event_id' => $id,
            'user_id' => $user?->id,
            'profile_id' => $profile?->id,
            'crew_id' => $crew?->id,
            'registration_type' => $context['registration_type'],
            'ticket_code' => $this->buildTicketCode($id),
            'ticket_status' => $priceAmount > 0 ? 'pending_payment' : 'paid',
            'price_amount' => $priceAmount,
            'payment_id' => $payment?->id,
            'participant_name' => $data['participantName'] ?? $crew?->nama ?? $profile?->nama_pengasuh ?? $profile?->nama_pesantren ?? 'Peserta Event',
            'participant_phone' => $data['participantPhone'] ?? $profile?->no_wa_pendaftar,
            'participant_email' => $data['participantEmail'] ?? $user?->email,
            'niam' => $crew?->niam,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($payment) {
            $payment->update(['reference_id' => $registration->id]);
            FinanceActivationService::logPaymentStatusChange(
                $payment->fresh(),
                $user?->id,
                'invoice_created',
                null,
                FinanceActivationService::STATUS_PENDING,
                'Invoice registrasi event dibuat.',
                [
                    'event_registration_id' => $registration->id,
                    'event_id' => $event->id,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'registration' => $this->mapRegistration($registration->load(['event', 'payment'])),
        ], 201);
    }

    public function myRegistrations(Request $request)
    {
        $user = auth()->user();

        $registrations = EventRegistration::with(['event', 'payment'])
            ->where('user_id', $user?->id)
            ->whereNotIn('ticket_status', ['attended', 'cancelled'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'registrations' => $registrations->map(fn($registration) => $this->mapRegistration($registration))->values(),
        ]);
    }

    public function myHistory(Request $request)
    {
        $user = auth()->user();

        $registrations = EventRegistration::with(['event', 'payment'])
            ->where('user_id', $user?->id)
            ->whereIn('ticket_status', ['attended', 'cancelled'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'registrations' => $registrations->map(fn($registration) => $this->mapRegistration($registration))->values(),
        ]);
    }

    public function myTicket(Request $request, string $registrationId)
    {
        $user = auth()->user();

        $registration = EventRegistration::with(['event', 'payment'])
            ->where('id', $registrationId)
            ->where('user_id', $user?->id)
            ->first();

        if (!$registration) {
            return response()->json(['message' => 'Ticket not found'], 404);
        }

        return response()->json([
            'ticket' => $this->mapRegistration($registration),
            'bankInfo' => [
                'bank' => (string) SystemSetting::getValue('bank_name', 'Bank Syariah Indonesia (BSI)'),
                'accountNumber' => (string) SystemSetting::getValue('bank_account_number', '7171234567890'),
                'accountName' => (string) SystemSetting::getValue('bank_account_name', 'MEDIA PONDOK JAWA TIMUR'),
            ],
        ]);
    }

    public function myCertificates(Request $request)
    {
        $user = auth()->user();

        $registrations = EventRegistration::with(['event', 'payment'])
            ->where('user_id', $user?->id)
            ->where('ticket_status', 'attended')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn($registration) => (bool) ($registration->event?->certificate_enabled ?? false))
            ->values();

        return response()->json([
            'certificates' => $registrations->map(fn($registration) => $this->mapRegistration($registration))->values(),
        ]);
    }

    public function checkTicket(Request $request, string $id)
    {
        $ticketCode = $request->validate([
            'ticketCode' => 'required|string',
        ])['ticketCode'];

        $registration = EventRegistration::with(['event', 'payment'])
            ->where('event_id', $id)
            ->where('ticket_code', $ticketCode)
            ->first();

        if (!$registration) {
            return response()->json(['valid' => false, 'message' => 'Tiket tidak ditemukan'], 404);
        }

        if ($registration->ticket_status === 'attended') {
            $checkin = EventCheckin::where('event_registration_id', $registration->id)->first();
            return response()->json([
                'valid' => false,
                'alreadyCheckedIn' => true,
                'registration' => $this->mapRegistration($registration),
                'checked_in_at' => $checkin?->checked_in_at,
            ]);
        }

        if ($registration->ticket_status !== 'paid') {
            return response()->json([
                'valid' => false,
                'message' => 'Tiket belum aktif untuk check-in',
                'registration' => $this->mapRegistration($registration),
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'registration' => $this->mapRegistration($registration),
        ]);
    }

    public function checkIn(Request $request, string $id)
    {
        $user = auth()->user();
        $ticketCode = $request->validate([
            'ticketCode' => 'required|string',
        ])['ticketCode'];

        $registration = EventRegistration::where('event_id', $id)
            ->where('ticket_code', $ticketCode)
            ->first();

        if (!$registration) {
            return response()->json(['message' => 'Tiket tidak ditemukan'], 404);
        }

        if ($registration->ticket_status === 'attended') {
            return response()->json(['message' => 'Tiket sudah pernah check-in'], 409);
        }

        if ($registration->ticket_status !== 'paid') {
            return response()->json(['message' => 'Tiket belum aktif untuk check-in'], 422);
        }

        EventCheckin::create([
            'id' => Str::uuid(),
            'event_registration_id' => $registration->id,
            'checked_in_by' => $user?->id,
        ]);

        $registration->update(['ticket_status' => 'attended']);

        return response()->json([
            'success' => true,
            'registration' => $this->mapRegistration($registration->fresh()->load(['event', 'payment'])),
        ]);
    }
}
