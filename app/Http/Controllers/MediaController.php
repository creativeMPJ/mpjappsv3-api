<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\JabatanCode;
use App\Models\Payment;
use App\Models\PesantrenClaim;
use App\Models\PesantrenProfile;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\FinanceActivationService;
use Illuminate\Http\Request;
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
            ->orderBy('created_at', 'desc')
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
                'niam'            => $c->niam,
                'status'          => $c->status,
                'xp_level'        => $c->xp_level,
                'jabatan_code_id' => $c->jabatan_code_id,
                'jabatan_code'    => $c->jabatanCode,
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
        ]);

        $profile = PesantrenProfile::where('user_id', $user->id)->first();
        if (!$profile) return response()->json(['message' => 'Profile tidak ditemukan'], 404);

        $count = Crew::where('profile_id', $profile->id)->count();
        if ($count >= 3) {
            return response()->json(['message' => 'Slot gratis sudah penuh (3/3). Upgrade untuk menambah kru.'], 403);
        }

        $jabatanName = $data['jabatan'] ?? null;

        if (!empty($data['jabatanCodeId'])) {
            $code = JabatanCode::find($data['jabatanCodeId']);
            if ($code) {
                $jabatanName = $code->name;
            }
        }

        $crew = Crew::create([
            'id'              => Str::uuid(),
            'profile_id'      => $profile->id,
            'nama'            => $data['nama'],
            'jabatan'         => $jabatanName,
            'jabatan_code_id' => $data['jabatanCodeId'] ?? null,
            'niam'            => null,
            'status'          => 'pending',
        ]);

        $invoice = FinanceActivationService::ensureCrewActivationInvoice($profile, $crew, $user);

        $crew->load('jabatanCode:id,name,code');

        return response()->json([
            'crew' => [
                'id'              => $crew->id,
                'nama'            => $crew->nama,
                'jabatan'         => $crew->jabatan,
                'niam'            => $crew->niam,
                'status'          => $crew->status,
                'xp_level'        => $crew->xp_level,
                'jabatan_code_id' => $crew->jabatan_code_id,
                'jabatan_code'    => $crew->jabatanCode,
            ],
            'invoice' => [
                'id' => $invoice->id,
                'status' => FinanceActivationService::normalizePaymentStatus($invoice->status),
                'paymentType' => $invoice->payment_type,
                'invoiceNumber' => $invoice->invoice_number,
                'totalAmount' => $invoice->total_amount,
            ],
        ]);
    }

    public function updateCrew(Request $request, string $id)
    {
        $user    = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user->id)->first();
        $data = $request->validate([
            'nama'    => 'required|string',
            'jabatan' => 'nullable|string',
        ]);

        $crew = Crew::where('id', $id)->where('profile_id', $profile?->id)->first();
        if (!$crew) return response()->json(['message' => 'Kru tidak ditemukan'], 404);

        $crew->update(['nama' => $data['nama'], 'jabatan' => $data['jabatan'] ?? null]);

        return response()->json([
            'crew' => [
                'id'      => $crew->id,
                'nama'    => $crew->nama,
                'jabatan' => $crew->jabatan,
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
