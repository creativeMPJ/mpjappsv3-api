<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\OtpVerification;
use App\Models\PesantrenClaim;
use App\Models\PesantrenDirectory;
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
        // Endpoint ini dipakai halaman klaim publik, jadi tidak boleh dikunci ke
        // admin. Yang dibatasi adalah isinya: email pengelola dan user_id tidak
        // dipakai UI klaim dan hanya dikirim ke admin, supaya endpoint terbuka
        // ini tidak bisa dipakai memanen alamat email seluruh pesantren.
        $role    = $this->currentRoleName();
        $profile = $this->currentProfile();
        $isAdmin = in_array($role, ['Admin Pusat', 'Admin Regional'], true);

        if ($role === 'Admin Regional' && !$profile?->region_id) {
            return response()->json(['message' => 'Akun Admin Regional belum terhubung ke wilayah mana pun'], 403);
        }

        $q = trim($request->query('query', ''));
        if (!$q) return response()->json(['results' => []]);

        $results = PesantrenClaim::where(function ($query) use ($q, $isAdmin) {
                $query->where('pesantren_name', 'like', "%{$q}%");

                // Pencocokan email hanya untuk admin. Kalau dibuka untuk publik,
                // endpoint ini bisa dipakai menebak apakah sebuah alamat email
                // terdaftar, cukup dari ada tidaknya hasil.
                if ($isAdmin) {
                    $query->orWhere('email_pengelola', 'like', "%{$q}%");
                }
            })
            ->when($role === 'Admin Regional', fn($query) => $query->where('region_id', $profile->region_id))
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        $claimItems = $results->map(function ($r) use ($isAdmin) {
            $item = [
                'id'             => $r->id,
                // Halaman klaim memakai penanda ini untuk memilih alur lanjutan:
                // 'claim' punya pengelola terdaftar sehingga lanjut ke OTP,
                // 'directory' belum punya akun sehingga lanjut ke form klaim.
                'source'         => 'claim',
                'pesantren_name' => $r->pesantren_name,
                'kecamatan'      => $r->kecamatan,
                // Ditampilkan di langkah konfirmasi "ini pesantren Anda?",
                // jadi tetap dikirim ke pemakai publik.
                'nama_pengelola' => $r->nama_pengelola,
                'region_id'      => $r->region_id,
                'status'         => $r->status,
            ];

            if ($isAdmin) {
                $item['email_pengelola'] = $r->email_pengelola;
                $item['user_id']         = $r->user_id;
            }

            return $item;
        });

        // Mayoritas pesantren hanya ada di direktori hasil impor dan belum
        // pernah punya baris pesantren_claims. Tanpa cabang di bawah ini,
        // pencarian selalu balas "data tidak ditemukan" padahal pesantrennya
        // terdaftar dan justru itulah yang seharusnya bisa diklaim.
        //
        // Baris direktori yang klaimnya sudah muncul di hasil di atas dibuang
        // supaya satu pesantren tidak tampil dua kali di daftar pilihan.
        $alreadyListed = $results->pluck('pesantren_directory_id')->filter()->values();

        $directoryResults = PesantrenDirectory::with('regency:id,name')
            ->where('nama_pesantren', 'like', "%{$q}%")
            ->whereNotIn('id', $alreadyListed)
            ->when($role === 'Admin Regional', fn($query) => $query->where('region_id', $profile->region_id))
            ->orderBy('nama_pesantren')
            ->take(10)
            ->get();

        $claimedDirectoryIds = PesantrenClaim::whereIn('pesantren_directory_id', $directoryResults->pluck('id'))
            ->whereIn('status', ['pending', 'regional_approved', 'approved', 'pusat_approved'])
            ->pluck('pesantren_directory_id')
            ->flip();

        $directoryItems = $directoryResults->map(fn($d) => [
            'id'             => $d->id,
            'source'         => 'directory',
            'pesantren_name' => $d->nama_pesantren,
            'kecamatan'      => $d->kota_kabupaten ?? $d->regency?->name,
            'nama_pengelola' => $d->nama_pengasuh,
            'region_id'      => $d->region_id,
            'regency_id'     => $d->regency_id,
            'alamat'         => $d->alamat,
            // Direktori dianggap sudah diklaim bila kolomnya menyatakan begitu
            // atau ada pengajuan aktif yang menunjuk ke baris ini.
            'status'         => ((bool) $d->is_claimed || $claimedDirectoryIds->has($d->id))
                ? 'approved'
                : 'unclaimed',
        ]);

        return response()->json([
            'results' => $claimItems->concat($directoryItems)->take(20)->values(),
        ]);
    }

    public function sendOtp(Request $request)
    {
        $data  = $request->validate(['claimId' => 'required|uuid']);
        $claim = PesantrenClaim::find($data['claimId']);

        if (!$claim) return response()->json(['message' => 'Claim tidak ditemukan'], 404);

        if (in_array($claim->status, ['approved', 'pusat_approved'], true)) {
            return response()->json([
                'message' => 'Pesantren ini sudah diklaim dan tidak bisa diklaim ulang.',
            ], 409);
        }

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
