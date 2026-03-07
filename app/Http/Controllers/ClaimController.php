<?php

namespace App\Http\Controllers;

use App\Models\OtpVerification;
use App\Models\PesantrenClaim;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClaimController extends Controller
{
    public function pendingCount(Request $request)
    {
        $user    = auth()->user();
        $profile = Profile::find($user->id);

        if (!$profile || $profile->role !== 'admin_regional' || !$profile->region_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $count = PesantrenClaim::where('region_id', $profile->region_id)
            ->where('status', 'pending')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function search(Request $request)
    {
        $q = trim($request->query('query', ''));
        if (!$q) return response()->json(['results' => []]);

        $results = PesantrenClaim::where(function ($query) use ($q) {
                $query->where('pesantren_name', 'like', "%{$q}%")
                      ->orWhere('email_pengelola', 'like', "%{$q}%");
            })
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

        $profile = Profile::find($claim->user_id);
        $phone   = trim($profile?->no_wa_pendaftar ?? '');

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

        return response()->json([
            'success'      => true,
            'message'      => 'Kode OTP telah dikirim ke nomor WhatsApp yang terdaftar',
            'otp_id'       => $otp->id,
            'expires_at'   => $expiresAt->toISOString(),
            'phone_masked' => $masked,
            'debug_otp'    => $otpCode,
        ]);
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

        if ($otp->pesantren_claim_id) {
            PesantrenClaim::where('id', $otp->pesantren_claim_id)
                ->update(['status' => 'pending', 'updated_at' => now()]);
        }

        return response()->json([
            'success'             => true,
            'message'             => 'Verifikasi berhasil',
            'pesantren_claim_id'  => $otp->pesantren_claim_id,
        ]);
    }

    public function contact(Request $request, string $claimId)
    {
        $claim = PesantrenClaim::find($claimId);
        if (!$claim) return response()->json(['message' => 'Claim tidak ditemukan'], 404);

        $adminPhone = '6281234567890';

        if ($claim->region_id) {
            $regionalAdmin = Profile::where('role', 'admin_regional')
                ->where('region_id', $claim->region_id)
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
