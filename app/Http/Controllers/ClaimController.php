<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\OtpVerification;
use App\Models\PesantrenClaim;
use App\Models\PesantrenProfile;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClaimController extends Controller
{
    private function currentRoleName(): ?string
    {
        return auth()->user()?->activeRole()?->nama;
    }

    private function currentProfile(): ?PesantrenProfile
    {
        return PesantrenProfile::where('user_id', auth()->id())->first();
    }

    /**
     * Klaim hanya boleh dilihat pemiliknya, Admin Regional di wilayah klaim,
     * atau Admin Pusat. Tanpa ini siapa pun yang menebak UUID klaim bisa
     * membaca nama pesantren dan nama pengelola milik orang lain.
     */
    private function canAccessClaim(PesantrenClaim $claim): bool
    {
        $role = $this->currentRoleName();

        if ($role === 'Admin Pusat') {
            return true;
        }

        $profile = $this->currentProfile();
        if (!$profile) {
            return false;
        }

        // pesantren_claims.user_id menyimpan id profil, bukan id user.
        if ($claim->user_id === $profile->id) {
            return true;
        }

        return $role === 'Admin Regional'
            && $profile->region_id
            && $claim->region_id === $profile->region_id;
    }

    public function pendingCount(Request $request)
    {
        $user    = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user->id)->first();

        if (!$user || !AccessControl::has($user, 'validasi-pendaftar') || !$profile?->region_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $count = PesantrenClaim::where('region_id', $profile->region_id)
            ->where('status', 'pending')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function search(Request $request)
    {
        // Endpoint ini mengembalikan nama & email pengelola, jadi hanya boleh
        // dipakai admin. Tanpa penjagaan ini user pesantren biasa bisa menarik
        // data klaim seluruh wilayah.
        $role    = $this->currentRoleName();
        $profile = $this->currentProfile();

        if ($role !== 'Admin Pusat' && $role !== 'Admin Regional') {
            return response()->json(['message' => 'Anda tidak berhak mengakses pencarian klaim'], 403);
        }

        if ($role === 'Admin Regional' && !$profile?->region_id) {
            return response()->json(['message' => 'Akun Admin Regional belum terhubung ke wilayah mana pun'], 403);
        }

        $q = trim($request->query('query', ''));
        if (!$q) return response()->json(['results' => []]);

        $results = PesantrenClaim::where(function ($query) use ($q) {
                $query->where('pesantren_name', 'like', "%{$q}%")
                      ->orWhere('email_pengelola', 'like', "%{$q}%");
            })
            ->when($role === 'Admin Regional', fn($query) => $query->where('region_id', $profile->region_id))
            ->whereNotIn('status', ['approved', 'pusat_approved'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'results' => $results->map(fn($r) => [
                'id'              => $r->id,
                'pesantren_name'  => $r->pesantren_name,
                'kecamatan'       => $r->kecamatan,
                'nama_pengelola'  => $r->nama_pengelola,
                'email_pengelola' => $r->email_pengelola,
                'region_id'       => $r->region_id,
                'user_id'         => $r->user_id,
                'status'          => $r->status,
            ]),
        ]);
    }

    public function sendOtp(Request $request)
    {
        $data  = $request->validate(['claimId' => 'required|uuid']);
        $claim = PesantrenClaim::find($data['claimId']);

        if (!$claim) return response()->json(['message' => 'Claim tidak ditemukan'], 404);

        // pesantren_claims.user_id stores pesantren_profiles.id in this schema.
        $profile = PesantrenProfile::find($claim->user_id);
        $claimUser = $profile ? User::find($profile->user_id) : null;
        $phone     = '';
        if ($claimUser && $claimUser->reff_type === 'crew' && $claimUser->reff_id) {
            $phone = trim(Crew::find($claimUser->reff_id)?->no_wa ?? '');
        }
        if (!$phone) {
            $phone   = trim($profile?->no_wa_pendaftar ?? '');
        }

        if (!$phone) {
            return response()->json(['message' => 'Nomor WhatsApp tidak tersedia untuk akun ini'], 400);
        }

        $oneHourAgo = now()->subHour();
        $count = OtpVerification::where('user_phone', $phone)
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        if ($count >= 3) {
            return response()->json(['message' => 'Terlalu banyak permintaan OTP. Coba lagi dalam 1 jam.'], 429);
        }

        OtpVerification::where('user_phone', $phone)->where('is_verified', false)
            ->update(['is_verified' => true]);

        $otpCode  = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        $otp = OtpVerification::create([
            'id'                  => Str::uuid(),
            'user_phone'          => $phone,
            'otp_code'            => $otpCode,
            'pesantren_claim_id'  => $claim->id,
            'expires_at'          => $expiresAt,
        ]);

        $masked = '***' . substr(preg_replace('/\D/', '', $phone), -4);

        $response = [
            'success'      => true,
            'message'      => 'Kode OTP telah dikirim ke nomor WhatsApp yang terdaftar',
            'otp_id'       => $otp->id,
            'expires_at'   => $expiresAt->toISOString(),
            'phone_masked' => $masked,
        ];

        // Kode OTP hanya dikembalikan di luar production sebagai alat bantu
        // pengembangan; di server production nilainya tidak pernah ikut terkirim.
        if (!app()->environment('production')) {
            $response['development_otp'] = $otpCode;
        }

        return response()->json($response);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'otpCode' => 'required|digits:6',
            'otpId'   => 'nullable|uuid',
            'claimId' => 'nullable|uuid',
        ]);

        $query = OtpVerification::where('is_verified', false)->where('expires_at', '>', now());

        if (!empty($data['otpId'])) $query->where('id', $data['otpId']);
        if (!empty($data['claimId'])) $query->where('pesantren_claim_id', $data['claimId']);

        $otp = $query->orderBy('created_at', 'desc')->first();

        if (!$otp) {
            return response()->json(['error' => 'Kode OTP tidak ditemukan atau sudah kadaluarsa', 'expired' => true], 400);
        }

        if ($otp->attempts >= 5) {
            return response()->json(['error' => 'Terlalu banyak percobaan. Silakan minta kode OTP baru.', 'max_attempts' => true], 400);
        }

        if ($otp->otp_code !== $data['otpCode']) {
            $otp->increment('attempts');
            return response()->json([
                'error'              => 'Kode OTP salah',
                'attempts_remaining' => 5 - ($otp->attempts),
            ], 400);
        }

        $otp->update(['is_verified' => true, 'verified_at' => now()]);

        $claim = null;
        if ($otp->pesantren_claim_id) {
            $claim = PesantrenClaim::find($otp->pesantren_claim_id);

            if ($claim) {
                $nextStatus = in_array($claim->status, ['regional_approved', 'approved', 'pusat_approved'], true)
                    ? $claim->status
                    : 'pending';

                $claim->update([
                    'status' => $nextStatus,
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json([
            'success'             => true,
            'message'             => 'Verifikasi berhasil',
            'pesantren_claim_id'  => $otp->pesantren_claim_id,
            'claim_status'        => $claim?->status,
            'next_step'           => $claim && in_array($claim->status, ['regional_approved', 'approved', 'pusat_approved'], true)
                ? ($claim->jenis_pengajuan === 'klaim' ? 'dashboard' : 'payment')
                : 'wait_regional_review',
        ]);
    }

    public function contact(Request $request, string $claimId)
    {
        $claim = PesantrenClaim::find($claimId);

        // 404 juga untuk yang tidak berhak, supaya keberadaan klaim tidak bocor.
        if (!$claim || !$this->canAccessClaim($claim)) {
            return response()->json(['message' => 'Claim tidak ditemukan'], 404);
        }

        $adminPhone = '6281234567890';

        if ($claim->region_id) {
            // Role tidak disimpan di pesantren_profiles, melainkan di user_roles → roles.
            $regionalAdmin = PesantrenProfile::where('region_id', $claim->region_id)
                ->whereIn('user_id', function ($sub) {
                    $sub->select('user_roles.user_id')
                        ->from('user_roles')
                        ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                        ->where('roles.nama', 'Admin Regional');
                })
                ->whereNotNull('no_wa_pendaftar')
                ->orderBy('updated_at', 'desc')
                ->first();

            if ($regionalAdmin?->no_wa_pendaftar) {
                $adminPhone = $regionalAdmin->no_wa_pendaftar;
            }
        }

        return response()->json([
            'claim'  => [
                'id'             => $claim->id,
                'pesantren_name' => $claim->pesantren_name,
                'nama_pengaju'   => $claim->nama_pengelola,
            ],
            'region' => ['admin_phone' => $adminPhone],
        ]);
    }
}
