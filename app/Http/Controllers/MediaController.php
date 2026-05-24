<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\JabatanCode;
use App\Models\Payment;
use App\Models\PesantrenClaim;
use App\Models\PesantrenProfile;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserRole;
use App\Support\FinanceActivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function jabatanCodes()
    {
        $codes = JabatanCode::orderBy('name')->get(['id', 'name', 'code', 'description']);
        return response()->json(['jabatan_codes' => $codes]);
    }

    public function getCrew(Request $request)
    {
        $user    = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user->id)->first();
        if (!$profile) return response()->json(['crews' => []]);

        $crews = Crew::with('jabatanCode:id,name,code')
            ->where('profile_id', $profile->id)
            ->orderByDesc('is_pic')
            ->orderBy('created_at', 'asc')
            ->get();

        $latestInvoices = Payment::where('user_id', $profile->id)
            ->where('payment_type', FinanceActivationService::TYPE_CREW_ACTIVATION)
            ->where('reference_type', FinanceActivationService::REFERENCE_CREW)
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('reference_id')
            ->keyBy('reference_id');

        return response()->json([
            'crews' => $crews->map(fn($c) => [
                'id'              => $c->id,
                'nama'            => $c->nama,
                'jabatan'         => $c->jabatan,
                'email'           => $c->email,
                'whatsapp'        => $c->no_wa,
                'jabatan_media'   => $c->jabatan_media,
                'catatan'         => $c->catatan,
                'niam'            => $c->niam,
                'status'          => $c->status,
                'xp_level'        => $c->xp_level,
                'jabatan_code_id' => $c->jabatan_code_id,
                'jabatan_code'    => $c->jabatanCode,
                'is_pic'                => (bool) $c->is_pic,
                'activation_invoice_id' => $latestInvoices->get($c->id)?->id,
                'activation_invoice_status' => $latestInvoices->get($c->id)
                    ? FinanceActivationService::normalizePaymentStatus($latestInvoices->get($c->id)->status)
                    : null,
                'activation_invoice_number' => $latestInvoices->get($c->id)?->invoice_number,
            ]),
        ]);
    }

    public function createCrew(Request $request)
    {
        $user = auth()->user();
        $data = $request->validate([
            'nama'          => 'required|string',
            'jabatanCodeId' => 'nullable|uuid',
            'jabatan'       => 'nullable|string',
            'email'         => 'required|email|max:255|unique:users,email',
            'password'      => 'required|string|min:6',
            'whatsapp'      => 'nullable|string|max:30',
            'jabatanMedia'  => 'nullable|string|max:255',
            'catatan'       => 'nullable|string|max:1000',
        ], [
            'email.required'    => 'Email wajib diisi.',
            'email.unique'      => 'Email sudah terdaftar di sistem.',
            'password.required' => 'Password wajib diisi.',
            'password.min'      => 'Password minimal 6 karakter.',
        ]);

        $profile = PesantrenProfile::where('user_id', $user->id)->first();
        if (!$profile) return response()->json(['message' => 'Profile tidak ditemukan'], 404);

        $freeSlotQuantity = (int) SystemSetting::getValue('free_slot_quantity', 3);
        $count = Crew::where('profile_id', $profile->id)
            ->whereIn('status', ['active', 'pending'])
            ->count();

        if ($count >= $freeSlotQuantity) {
            return response()->json(['message' => "Slot gratis sudah penuh ({$freeSlotQuantity}/{$freeSlotQuantity}). Upgrade untuk menambah kru."], 403);
        }

        $jabatanName = $data['jabatan'] ?? null;

        if (!empty($data['jabatanCodeId'])) {
            $code = JabatanCode::find($data['jabatanCodeId']);
            if ($code) {
                $jabatanName = $code->name;
            }
        }

        $result = DB::transaction(function () use ($data, $profile, $jabatanName) {
            // 1. Buat akun login untuk crew
            $crewUser = User::create([
                'id'            => Str::uuid(),
                'email'         => strtolower($data['email']),
                'password_hash' => Hash::make($data['password']),
            ]);

            // 2. Assign role user
            UserRole::create([
                'id'         => Str::uuid(),
                'user_id'    => $crewUser->id,
                'role_id'    => Role::findByEnum('user')?->id,
                'created_at' => now(),
            ]);

            // 3. Buat record crew, langsung aktif (slot gratis)
            $crew = Crew::create([
                'id'              => Str::uuid(),
                'profile_id'      => $profile->id,
                'nama'            => $data['nama'],
                'jabatan'         => $jabatanName,
                'email'           => strtolower($data['email']),
                'no_wa'           => $data['whatsapp'] ?? null,
                'jabatan_media'   => $data['jabatanMedia'] ?? null,
                'catatan'         => $data['catatan'] ?? null,
                'jabatan_code_id' => $data['jabatanCodeId'] ?? null,
                'niam'            => null,
                'status'          => 'active',
                'is_pic'          => false,
            ]);

            // 4. Link user → crew
            $crewUser->update([
                'reff_type' => 'crew',
                'reff_id'   => $crew->id,
            ]);

            // 5. Generate NIAM langsung jika pesantren sudah punya NIP
            if ($profile->nip) {
                $niam = FinanceActivationService::issueCrewNiam($profile, $crew);
                $crew->update(['niam' => $niam]);
            }

            return $crew;
        });

        $result->load('jabatanCode:id,name,code');

        return response()->json([
            'crew' => [
                'id'              => $result->id,
                'nama'            => $result->nama,
                'jabatan'         => $result->jabatan,
                'email'           => $result->email,
                'whatsapp'        => $result->no_wa,
                'jabatan_media'   => $result->jabatan_media,
                'catatan'         => $result->catatan,
                'niam'            => $result->niam,
                'status'          => $result->status,
                'xp_level'        => $result->xp_level,
                'jabatan_code_id' => $result->jabatan_code_id,
                'jabatan_code'    => $result->jabatanCode,
            ],
            'invoice' => null,
        ]);
    }

    public function updateCrew(Request $request, string $id)
    {
        $user    = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user->id)->first();
        $data = $request->validate([
            'nama'         => 'required|string',
            'jabatan'      => 'nullable|string',
            'email'        => 'nullable|email|max:255',
            'whatsapp'     => 'nullable|string|max:30',
            'jabatanMedia' => 'nullable|string|max:255',
            'catatan'      => 'nullable|string|max:1000',
        ]);

        $crew = Crew::where('id', $id)->where('profile_id', $profile?->id)->first();
        if (!$crew) return response()->json(['message' => 'Kru tidak ditemukan'], 404);

        $crew->update([
            'nama'          => $data['nama'],
            'jabatan'       => $data['jabatan'] ?? null,
            'email'         => $data['email'] ?? null,
            'no_wa'         => $data['whatsapp'] ?? null,
            'jabatan_media' => $data['jabatanMedia'] ?? null,
            'catatan'       => $data['catatan'] ?? null,
        ]);

        return response()->json([
            'crew' => [
                'id'      => $crew->id,
                'nama'    => $crew->nama,
                'jabatan' => $crew->jabatan,
                'email'   => $crew->email,
                'whatsapp'=> $crew->no_wa,
                'jabatan_media' => $crew->jabatan_media,
                'catatan' => $crew->catatan,
                'niam'    => $crew->niam,
                'status'  => $crew->status,
                'xp_level'=> $crew->xp_level,
            ],
        ]);
    }

    public function deleteCrew(Request $request, string $id)
    {
        $user    = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user->id)->first();
        $crew    = Crew::where('id', $id)->where('profile_id', $profile?->id)->first();
        if (!$crew) return response()->json(['message' => 'Kru tidak ditemukan'], 404);

        $crew->delete();
        return response()->json(['success' => true]);
    }

    public function dashboardContext(Request $request)
    {
        $user = auth()->user();

        $claim = PesantrenClaim::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->select('regional_approved_at', 'approved_at', 'status')
            ->first();

        $profile     = PesantrenProfile::where('user_id', $user->id)->first();
        $koordinator = Crew::where('profile_id', $profile?->id)
            ->where('jabatan', 'Koordinator')
            ->select('nama', 'niam', 'jabatan', 'status', 'xp_level')
            ->first();

        return response()->json([
            'regionalApprovedAt' => $claim?->regional_approved_at,
            'pusatApprovedAt'    => $claim?->approved_at,
            'koordinator'        => $koordinator ? [
                'nama'     => $koordinator->nama,
                'niam'     => $koordinator->niam,
                'jabatan'  => $koordinator->jabatan ?? 'Koordinator',
                'status'   => $koordinator->status,
                'xp_level' => $koordinator->xp_level ?? 0,
            ] : null,
        ]);
    }

    public function slotConfig(Request $request)
    {
        $user = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user->id)->first();
        if (!$profile) {
            return response()->json([
                'freeSlotQuantity' => 3,
                'addonSlotPrice' => 0,
            ]);
        }

        return response()->json([
            'freeSlotQuantity' => (int) SystemSetting::getValue('free_slot_quantity', 3),
            'addonSlotPrice' => (int) SystemSetting::getValue('addon_slot_price', 0),
        ]);
    }

    public function profileSettings(Request $request)
    {
        $user  = auth()->user();
        $claim = PesantrenClaim::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->select('nama_pengelola')
            ->first();

        $linkedCrew = ($user->reff_type === 'crew' && $user->reff_id)
            ? Crew::find($user->reff_id)
            : null;

        return response()->json([
            'namaPengelola' => $claim?->nama_pengelola,
            'email'         => $user->email,
            'noWaPendaftar' => $linkedCrew?->no_wa,
        ]);
    }
}
