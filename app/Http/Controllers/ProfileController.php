<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\Payment;
use App\Models\PesantrenProfile;
use App\Models\User;
use App\Support\FinanceActivationService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Resolve PesantrenProfile untuk user yang sedang login.
     * Mendukung dua jenis user: pesantren owner dan crew member.
     */
    private function resolveProfile(User $user, bool $withRelations = false): ?PesantrenProfile
    {
        /** @var PesantrenProfile|null $profile */
        $profile = $withRelations
            ? PesantrenProfile::with(['region', 'regency'])->where('user_id', $user->id)->first()
            : PesantrenProfile::where('user_id', $user->id)->first();

        // Crew member: tidak punya profile sendiri, ambil dari pesantren tempat bertugas
        if (!$profile && $user->reff_type === 'crew' && $user->reff_id) {
            /** @var Crew|null $crew */
            $crew = Crew::find($user->reff_id);
            if ($crew) {
                /** @var PesantrenProfile|null $profile */
                $profile = $withRelations
                    ? PesantrenProfile::with(['region', 'regency'])->find($crew->profile_id)
                    : PesantrenProfile::find($crew->profile_id);
            }
        }

        return $profile;
    }

    public function getPesantren(Request $request)
    {
        $user    = auth()->user();
        $profile = $this->resolveProfile($user, withRelations: true);

        if (!$profile) {
            return response()->json(['message' => 'Profile tidak ditemukan'], 404);
        }

        return response()->json([
            'profile' => [
                'namaPesantren'     => $profile->nama_pesantren,
                'namaPengasuh'      => $profile->nama_pengasuh,
                'alamatSingkat'     => $profile->alamat_singkat,
                'regionName'        => $profile->region?->name ?? null,
                'cityName'          => $profile->regency?->name ?? null,
                'profileLevel'      => $profile->profile_level ?? 'basic',

                'logoPesantrenUrl'  => $profile->logo_url,
                'namaMedia'         => $profile->nama_media,
                'instagram'         => $profile->instagram,
                'youtube'           => $profile->youtube,
                'tiktok'            => $profile->tiktok,
                'website'           => $profile->website,
                'fotoPengasuhUrl'   => $profile->foto_pengasuh_url,
                'dawuhPengasuh'     => $profile->dawuh_pengasuh ?? null,
                'jumlahSantri'      => $profile->jumlah_santri,
                'tahunBerdiri'      => $profile->tahun_berdiri,
                'latitude'          => $profile->latitude,
                'longitude'         => $profile->longitude,

                'visiMisi'          => $profile->visi_misi,
                'sejarahSingkat'    => $profile->sejarah,
                'tipePesantren'     => $profile->tipe_pesantren,
                'jenjangPendidikan' => $profile->jenjang_pendidikan,
                'programUnggulan'   => $profile->program_unggulan,
                'fotoGedungUrl'     => $profile->foto_gedung_path,
                'logoMediaUrl'      => $profile->logo_media_path,
            ],
        ]);
    }

    public function updatePesantren(Request $request)
    {
        $user    = auth()->user();
        $profile = $this->resolveProfile($user);

        if (!$profile) {
            return response()->json(['message' => 'Profile tidak ditemukan'], 404);
        }

        $step = (int) $request->input('step', 1);

        if ($step === 1) {
            $data = $request->validate([
                'namaPesantren' => 'nullable|string',
                'namaPengasuh'  => 'nullable|string',
                'alamatSingkat' => 'nullable|string',
            ]);

            $profile->update([
                'nama_pesantren' => $data['namaPesantren'] ?? $profile->nama_pesantren,
                'nama_pengasuh'  => $data['namaPengasuh']  ?? $profile->nama_pengasuh,
                'alamat_singkat' => $data['alamatSingkat'] ?? $profile->alamat_singkat,
            ]);

            if ($profile->nama_pesantren && $profile->nama_pengasuh && $profile->alamat_singkat) {
                $profile->update(['profile_level' => 'silver']);
            }

        } elseif ($step === 2) {
            $data = $request->validate([
                'namaMedia'    => 'nullable|string',
                'instagram'    => 'nullable|string',
                'youtube'      => 'nullable|string',
                'tiktok'       => 'nullable|string',
                'website'      => 'nullable|string',
                'dawuhPengasuh'=> 'nullable|string',
                'jumlahSantri' => 'nullable|integer',
                'tahunBerdiri' => 'nullable|integer',
                'latitude'     => 'nullable|string',
                'longitude'    => 'nullable|string',
            ]);

            $profile->update([
                'nama_media'    => $data['namaMedia']    ?? $profile->nama_media,
                'instagram'     => $data['instagram']    ?? $profile->instagram,
                'youtube'       => $data['youtube']      ?? $profile->youtube,
                'tiktok'        => $data['tiktok']       ?? $profile->tiktok,
                'website'       => $data['website']      ?? $profile->website,
                'dawuh_pengasuh'=> $data['dawuhPengasuh']?? $profile->dawuh_pengasuh,
                'jumlah_santri' => $data['jumlahSantri'] ?? $profile->jumlah_santri,
                'tahun_berdiri' => $data['tahunBerdiri'] ?? $profile->tahun_berdiri,
                'latitude'      => $data['latitude']     ?? $profile->latitude,
                'longitude'     => $data['longitude']    ?? $profile->longitude,
            ]);

            // Upgrade Gold diproses melalui invoice paket Finance dan approval pembayaran.

        } elseif ($step === 3) {
            $data = $request->validate([
                'visiMisi'         => 'nullable|string',
                'sejarahSingkat'   => 'nullable|string',
                'tipePesantren'    => 'nullable|string',
                'jenjangPendidikan'=> 'nullable|string',
                'programUnggulan'  => 'nullable|string',
            ]);

            $profile->update([
                'visi_misi'         => $data['visiMisi']         ?? $profile->visi_misi,
                'sejarah'           => $data['sejarahSingkat']   ?? $profile->sejarah,
                'tipe_pesantren'    => $data['tipePesantren']    ?? $profile->tipe_pesantren,
                'jenjang_pendidikan'=> $data['jenjangPendidikan']?? $profile->jenjang_pendidikan,
                'program_unggulan'  => $data['programUnggulan']  ?? $profile->program_unggulan,
            ]);

            // Upgrade Platinum diproses melalui invoice paket Finance dan approval pembayaran.
        }

        $profile->refresh();

        return response()->json([
            'success'      => true,
            'profileLevel' => $profile->profile_level,
        ]);
    }

    public function requestUpgrade(Request $request)
    {
        $user = auth()->user();
        $profile = $this->resolveProfile($user);

        if (!$profile) {
            return response()->json(['message' => 'Profile tidak ditemukan'], 404);
        }

        if ($profile->status_account !== 'active' || $profile->status_payment !== 'paid' || !$profile->nip) {
            return response()->json(['message' => 'Akun pesantren harus aktif sebelum mengajukan upgrade.'], 422);
        }

        $data = $request->validate([
            'targetLevel' => 'required|in:gold,platinum',
        ]);

        $rank = ['basic' => 0, 'silver' => 1, 'gold' => 2, 'platinum' => 3];
        $currentRank = $rank[$profile->profile_level ?? 'basic'] ?? 0;
        $targetRank = $rank[$data['targetLevel']] ?? 0;

        if ($targetRank <= $currentRank) {
            return response()->json(['message' => 'Level tujuan harus lebih tinggi dari level saat ini.'], 422);
        }

        $existingUpgrade = Payment::where('payment_type', FinanceActivationService::TYPE_PROFILE_UPGRADE)
            ->where('reference_type', FinanceActivationService::REFERENCE_PROFILE)
            ->where('reference_id', $profile->id)
            ->whereIn('status', [
                FinanceActivationService::STATUS_PENDING,
                FinanceActivationService::STATUS_WAITING_VERIFICATION,
                FinanceActivationService::STATUS_REJECTED,
            ])
            ->orderBy('created_at', 'desc')
            ->first();

        if (
            $existingUpgrade &&
            ($existingUpgrade->meta['target_level'] ?? null) !== $data['targetLevel']
        ) {
            if (FinanceActivationService::normalizePaymentStatus($existingUpgrade->status) === FinanceActivationService::STATUS_WAITING_VERIFICATION) {
                return response()->json([
                    'message' => 'Invoice upgrade sebelumnya sedang menunggu verifikasi finance.',
                ], 422);
            }

            $existingUpgrade->update([
                'status' => FinanceActivationService::STATUS_CANCELLED,
                'meta' => array_merge($existingUpgrade->meta ?? [], [
                    'cancelled_reason' => 'Diganti dengan pengajuan upgrade level baru.',
                    'cancelled_at' => now()->toISOString(),
                ]),
            ]);
        }

        $payment = FinanceActivationService::ensureProfilePackageInvoice(
            $profile,
            'upgrade',
            FinanceActivationService::TYPE_PROFILE_UPGRADE,
            $user,
            [
                'target_level' => $data['targetLevel'],
                'current_level' => $profile->profile_level,
            ]
        );

        $payment->load('pricingPackage');

        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'invoiceNumber' => $payment->invoice_number,
                'status' => FinanceActivationService::normalizePaymentStatus($payment->status),
                'totalAmount' => $payment->total_amount,
                'paymentType' => $payment->payment_type,
                'pricingPackageName' => $payment->pricingPackage?->name,
                'pricingPackageCategory' => $payment->pricingPackage?->category,
            ],
        ]);
    }

    public function requestRenewal(Request $request)
    {
        $user = auth()->user();
        $profile = $this->resolveProfile($user);

        if (!$profile) {
            return response()->json(['message' => 'Profile tidak ditemukan'], 404);
        }

        if ($profile->status_account !== 'active' || $profile->status_payment !== 'paid' || !$profile->nip) {
            return response()->json(['message' => 'Akun pesantren harus aktif sebelum mengajukan perpanjangan.'], 422);
        }

        $payment = FinanceActivationService::ensureProfilePackageInvoice(
            $profile,
            'renewal',
            FinanceActivationService::TYPE_PROFILE_RENEWAL,
            $user,
            [
                'renewal_requested_at' => now()->toISOString(),
                'profile_level' => $profile->profile_level,
            ]
        );

        $payment->load('pricingPackage');

        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'invoiceNumber' => $payment->invoice_number,
                'status' => FinanceActivationService::normalizePaymentStatus($payment->status),
                'totalAmount' => $payment->total_amount,
                'paymentType' => $payment->payment_type,
                'pricingPackageName' => $payment->pricingPackage?->name,
                'pricingPackageCategory' => $payment->pricingPackage?->category,
            ],
        ]);
    }

    public function uploadPesantren(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg|max:2048',
            'type' => 'required|in:logo_pesantren,foto_pengasuh,foto_gedung,logo_media',
        ]);

        $type     = $request->input('type');
        $file     = $request->file('file');
        $filename = time() . '.' . $file->getClientOriginalExtension();
        $path     = "pesantren/{$user->id}/{$type}";

        $file->storeAs($path, $filename, 'public');

        $url = "/uploads/{$path}/{$filename}";

        $fieldMap = [
            'logo_pesantren' => 'logo_url',
            'foto_pengasuh'  => 'foto_pengasuh_url',
            'foto_gedung'    => 'foto_gedung_path',
            'logo_media'     => 'logo_media_path',
        ];

        PesantrenProfile::where('id', $user->id)->update([$fieldMap[$type] => $url]);

        return response()->json(['url' => $url]);
    }
}
