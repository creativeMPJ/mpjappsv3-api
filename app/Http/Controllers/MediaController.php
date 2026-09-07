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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    private function defaultNotificationPreferences(): array
    {
        return [
            'email' => true,
            'whatsapp' => true,
            'event' => true,
            'payment' => true,
        ];
    }

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

        if ($profile->status_account !== 'active' || $profile->status_payment !== 'paid' || !$profile->nip) {
            return response()->json([
                'message' => 'Institusi belum aktif penuh, kru belum bisa ditambahkan.',
            ], 422);
        }

        $freeSlotQuantity = (int) SystemSetting::getValue('free_slot_quantity', 3);
        $paidSlotQuantity = (int) ($profile->paid_slot_quantity ?? 0);
        $totalSlotQuantity = $freeSlotQuantity + $paidSlotQuantity;
        $count = Crew::where('profile_id', $profile->id)
            ->whereIn('status', ['active', 'pending'])
            ->count();

        if ($count >= $totalSlotQuantity) {
            return response()->json([
                'message' => "Slot kru sudah penuh ({$count}/{$totalSlotQuantity}). Ajukan pembelian slot tambahan terlebih dahulu.",
            ], 403);
        }

        $usesAddonSlot = $count >= $freeSlotQuantity;

        $jabatanName = $data['jabatan'] ?? null;

        if (!empty($data['jabatanCodeId'])) {
            $code = JabatanCode::find($data['jabatanCodeId']);
            if ($code) {
                $jabatanName = $code->name;
            }
        }

        $result = DB::transaction(function () use ($data, $profile, $jabatanName, $user, $usesAddonSlot, $totalSlotQuantity) {
            // Pengecekan di atas hanya untuk pesan yang informatif; dua request
            // bersamaan bisa sama-sama melewatinya. Di sini dihitung ulang sambil
            // mengunci baris supaya batas slot benar-benar tidak bisa ditembus.
            // Baris profil ikut dikunci karena saat belum ada kru sama sekali,
            // tidak ada baris crew yang bisa dijadikan titik kunci.
            PesantrenProfile::whereKey($profile->id)->lockForUpdate()->first();

            $lockedCount = Crew::where('profile_id', $profile->id)
                ->whereIn('status', ['active', 'pending'])
                ->lockForUpdate()
                ->count();

            if ($lockedCount >= $totalSlotQuantity) {
                return null;
            }

            // 1. Buat akun login untuk crew
            $crewUser = User::create([
                'id'            => Str::uuid(),
                'email'         => strtolower($data['email']),
                'password_hash' => Hash::make($data['password']),
            ]);

            // 2. Assign role kru pesantren
            UserRole::create([
                'id'         => Str::uuid(),
                'user_id'    => $crewUser->id,
                'role_id'    => Role::findByEnum('crew')?->id,
                'created_at' => now(),
            ]);

            // 3. Buat record crew pending. Aktivasi dan NIAM diterbitkan setelah invoice diverifikasi.
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
                'status'          => 'pending',
                'is_pic'          => false,
            ]);

            // 4. Link user → crew
            $crewUser->update([
                'reff_type' => 'crew',
                'reff_id'   => $crew->id,
            ]);

            $invoice = FinanceActivationService::ensureCrewActivationInvoice($profile, $crew, $user);
            $invoice->update([
                'base_amount' => $usesAddonSlot ? $invoice->base_amount : 0,
                'unique_code' => $usesAddonSlot ? $invoice->unique_code : 0,
                'total_amount' => $usesAddonSlot ? $invoice->total_amount : 0,
                'status' => $usesAddonSlot
                    ? $invoice->status
                    : FinanceActivationService::STATUS_WAITING_VERIFICATION,
                'submitted_at' => $usesAddonSlot ? $invoice->submitted_at : now(),
                'meta' => array_merge($invoice->meta ?? [], [
                    'slot_type' => $usesAddonSlot ? 'addon' : 'free',
                    'verification_note' => $usesAddonSlot
                        ? null
                        : 'Free slot Golden 3: tanpa invoice pembayaran, cukup verifikasi finance.',
                ]),
            ]);

            return [$crew, $invoice];
        });

        // null berarti pengecekan berkunci di dalam transaksi menolak: ada request
        // lain yang lebih dulu memakai sisa slot.
        if (!$result) {
            return response()->json([
                'message' => "Slot kru sudah penuh ({$totalSlotQuantity}/{$totalSlotQuantity}). Ajukan pembelian slot tambahan terlebih dahulu.",
            ], 403);
        }

        [$crew, $invoice] = $result;
        $result = $crew;
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
            'invoice' => [
                'id'             => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status'         => FinanceActivationService::normalizePaymentStatus($invoice->status),
                'total_amount'   => $invoice->total_amount,
                'payment_type'   => $invoice->payment_type,
                'slot_type'      => $usesAddonSlot ? 'addon' : 'free',
                'pricing_package_name' => $invoice->pricingPackage?->name,
            ],
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

        if ($crew->is_pic) {
            return response()->json([
                'message' => 'Kru ini adalah PIC pesantren dan tidak dapat dihapus. Tunjuk PIC lain terlebih dahulu.',
            ], 403);
        }

        $crew->delete();
        return response()->json(['success' => true]);
    }

    public function dashboardContext(Request $request)
    {
        $user = auth()->user();

        // Pengelola pesantren juga tercatat sebagai crew (PIC didaftarkan sebagai
        // Koordinator), jadi reff_type saja tidak cukup untuk membedakan. Yang
        // punya profil pesantren diperlakukan sebagai pengelola supaya tanggal
        // approval untuk piagam tetap terkirim.
        $ownedProfile = PesantrenProfile::where('user_id', $user->id)->first();

        // Crew member: kembalikan data diri sendiri (bukan koordinator pesantren)
        if (!$ownedProfile && $user->reff_type === 'crew' && $user->reff_id) {
            $crew = Crew::find($user->reff_id);
            return response()->json([
                'regionalApprovedAt' => null,
                'pusatApprovedAt'    => null,
                'koordinator'        => $crew ? [
                    'nama'           => $crew->nama,
                    'nama_panggilan' => $crew->nama_panggilan,
                    'niam'           => $crew->niam,
                    'jabatan'        => $crew->jabatan ?? 'Kru',
                    'status'         => $crew->status,
                    'xp_level'       => $crew->xp_level ?? 0,
                    'whatsapp'       => $crew->no_wa,
                    'alamat_asal'    => $crew->alamat_asal,
                    'prinsip_hidup'  => $crew->prinsip_hidup,
                    'photoUrl'       => $crew->photo_url,
                    'cvUrl'          => $crew->cv_url,
                ] : null,
            ]);
        }

        $profile = $ownedProfile;

        // pesantren_claims.user_id menyimpan id PROFIL, bukan id user.
        // Mencarinya dengan $user->id membuat klaim tidak pernah ketemu sehingga
        // tanggal approval selalu null di piagam.
        $claim = $profile
            ? PesantrenClaim::where('user_id', $profile->id)
                ->orderBy('created_at', 'desc')
                ->select('regional_approved_at', 'approved_at', 'status')
                ->first()
            : null;

        $koordinator = Crew::where('profile_id', $profile?->id)
            ->orderByDesc('is_pic')
            ->orderByRaw("CASE WHEN LOWER(COALESCE(jabatan, '')) IN ('koordinator', 'ketua', 'khodim') THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'asc')
            ->select('nama', 'nama_panggilan', 'niam', 'jabatan', 'status', 'xp_level', 'no_wa', 'alamat_asal', 'prinsip_hidup', 'photo_url', 'cv_url')
            ->first();

        return response()->json([
            'regionalApprovedAt' => $claim?->regional_approved_at,
            'pusatApprovedAt'    => $claim?->approved_at,
            'koordinator'        => $koordinator ? [
                'nama'           => $koordinator->nama,
                'nama_panggilan' => $koordinator->nama_panggilan,
                'niam'           => $koordinator->niam,
                'jabatan'        => $koordinator->jabatan ?? 'Koordinator',
                'status'         => $koordinator->status,
                'xp_level'       => $koordinator->xp_level ?? 0,
                'whatsapp'       => $koordinator->no_wa,
                'alamat_asal'    => $koordinator->alamat_asal,
                'prinsip_hidup'  => $koordinator->prinsip_hidup,
                'photoUrl'       => $koordinator->photo_url,
                'cvUrl'          => $koordinator->cv_url,
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
            'paidSlotQuantity' => (int) ($profile->paid_slot_quantity ?? 0),
            'totalSlotQuantity' => (int) SystemSetting::getValue('free_slot_quantity', 3) + (int) ($profile->paid_slot_quantity ?? 0),
            'addonSlotPrice' => (int) SystemSetting::getValue('addon_slot_price', 0),
        ]);
    }

    public function requestSlotAddon(Request $request)
    {
        $user = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user->id)->first();

        if (!$profile) {
            return response()->json(['message' => 'Profile tidak ditemukan'], 404);
        }

        if ($profile->status_account !== 'active' || $profile->status_payment !== 'paid' || !$profile->nip) {
            return response()->json(['message' => 'Institusi belum aktif penuh, slot tambahan belum bisa diajukan.'], 422);
        }

        $data = $request->validate([
            'quantity' => 'required|integer|min:1|max:20',
        ]);

        $payment = FinanceActivationService::ensureProfilePackageInvoice(
            $profile,
            'crew_addon',
            FinanceActivationService::TYPE_SLOT_ADDON,
            $user,
            [
                'slot_quantity' => $data['quantity'],
                'current_paid_slot_quantity' => (int) ($profile->paid_slot_quantity ?? 0),
            ]
        );

        $paymentStatus = FinanceActivationService::normalizePaymentStatus($payment->status);

        if (in_array($paymentStatus, [
            FinanceActivationService::STATUS_PENDING,
            FinanceActivationService::STATUS_REJECTED,
        ], true)) {
            $unitAmount = $payment->pricingPackage
                ? (int) ($payment->pricingPackage->harga_diskon ?? $payment->pricingPackage->harga_paket)
                : (int) SystemSetting::getValue('addon_slot_price', 0);

            $payment->update([
                'base_amount' => $unitAmount * $data['quantity'],
                'total_amount' => ($unitAmount * $data['quantity']) + $payment->unique_code,
                'status' => FinanceActivationService::STATUS_PENDING,
                'rejection_reason' => null,
                'meta' => array_merge($payment->meta ?? [], ['slot_quantity' => $data['quantity']]),
            ]);
        } elseif ($paymentStatus === FinanceActivationService::STATUS_WAITING_VERIFICATION) {
            return response()->json([
                'message' => 'Invoice slot tambahan sedang menunggu verifikasi finance.',
            ], 422);
        }

        $payment->load('pricingPackage');

        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'invoiceNumber' => $payment->invoice_number,
                'status' => FinanceActivationService::normalizePaymentStatus($payment->status),
                'totalAmount' => $payment->total_amount,
                'paymentType' => $payment->payment_type,
                'quantity' => $payment->meta['slot_quantity'] ?? $data['quantity'],
                'pricingPackageName' => $payment->pricingPackage?->name,
                'pricingPackageCategory' => $payment->pricingPackage?->category,
            ],
        ]);
    }

    public function profileSettings(Request $request)
    {
        $user = auth()->user();

        // pesantren_claims.user_id menyimpan id PROFIL, bukan id user.
        $profile = PesantrenProfile::where('user_id', $user->id)->first();

        $claim = $profile
            ? PesantrenClaim::where('user_id', $profile->id)
                ->orderBy('created_at', 'desc')
                ->select('nama_pengelola')
                ->first()
            : null;

        $linkedCrew = ($user->reff_type === 'crew' && $user->reff_id)
            ? Crew::find($user->reff_id)
            : null;

        return response()->json([
            'namaPengelola' => $claim?->nama_pengelola,
            'email'         => $user->email,
            'noWaPendaftar' => $linkedCrew?->no_wa,
            'namaPanggilan' => $linkedCrew?->nama_panggilan,
            'alamatAsal'    => $linkedCrew?->alamat_asal,
            'prinsipHidup'  => $linkedCrew?->prinsip_hidup,
            'photoUrl'      => $linkedCrew?->photo_url,
            'cvUrl'         => $linkedCrew?->cv_url,
        ]);
    }

    public function notificationPreferences(Request $request)
    {
        $user = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user->id)->first();

        if (!$profile) {
            return response()->json(['message' => 'Profile tidak ditemukan'], 404);
        }

        return response()->json([
            'preferences' => array_merge(
                $this->defaultNotificationPreferences(),
                $profile->notification_preferences ?? []
            ),
        ]);
    }

    public function updateNotificationPreferences(Request $request)
    {
        $user = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user->id)->first();

        if (!$profile) {
            return response()->json(['message' => 'Profile tidak ditemukan'], 404);
        }

        $data = $request->validate([
            'email' => 'required|boolean',
            'whatsapp' => 'required|boolean',
            'event' => 'required|boolean',
            'payment' => 'required|boolean',
        ]);

        $profile->update(['notification_preferences' => $data]);

        return response()->json([
            'success' => true,
            'preferences' => array_merge($this->defaultNotificationPreferences(), $data),
        ]);
    }

    public function updateProfileSettings(Request $request)
    {
        $user = auth()->user();
        $crew = ($user->reff_type === 'crew' && $user->reff_id)
            ? Crew::find($user->reff_id)
            : null;

        if (!$crew) {
            return response()->json(['message' => 'Profil kru tidak ditemukan'], 404);
        }

        $data = $request->validate([
            'namaPanggilan' => 'nullable|string|max:100',
            'whatsapp'      => 'nullable|string|max:32',
            'alamatAsal'    => 'nullable|string|max:1000',
            'prinsipHidup'  => 'nullable|string|max:1000',
        ]);

        $crew->update([
            'nama_panggilan' => $data['namaPanggilan'] ?? null,
            'no_wa'          => $data['whatsapp'] ?? null,
            'alamat_asal'    => $data['alamatAsal'] ?? null,
            'prinsip_hidup'  => $data['prinsipHidup'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    public function uploadCrewCv(Request $request)
    {
        $user = auth()->user();
        $crew = ($user->reff_type === 'crew' && $user->reff_id)
            ? Crew::find($user->reff_id)
            : null;

        if (!$crew) {
            return response()->json(['message' => 'Profil kru tidak ditemukan'], 404);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
        ]);

        $file = $request->file('file');
        $relativePath = $file->storeAs(
            'crew-cv/' . $crew->id,
            time() . '.' . $file->getClientOriginalExtension(),
            'public'
        );

        $crew->update(['cv_url' => Storage::url($relativePath)]);

        return response()->json([
            'success' => true,
            'cvUrl' => $crew->cv_url,
        ]);
    }

    public function uploadCrewPhoto(Request $request)
    {
        $user = auth()->user();
        $crew = ($user->reff_type === 'crew' && $user->reff_id)
            ? Crew::find($user->reff_id)
            : null;

        if (!$crew) {
            return response()->json(['message' => 'Profil kru tidak ditemukan'], 404);
        }

        $request->validate([
            'file' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $file = $request->file('file');
        $relativePath = $file->storeAs(
            'crew-photos/' . $crew->id,
            time() . '.' . $file->getClientOriginalExtension(),
            'public'
        );

        $crew->update(['photo_url' => Storage::url($relativePath)]);

        return response()->json([
            'success' => true,
            'photoUrl' => $crew->photo_url,
        ]);
    }
}
