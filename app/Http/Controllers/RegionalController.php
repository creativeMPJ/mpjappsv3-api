<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\FollowUpLog;
use App\Models\Payment;
use App\Models\PesantrenClaim;
use App\Models\PricingPackage;
use App\Models\Profile;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegionalController extends Controller
{
    private function assertRegional()
    {
        $user    = auth()->user();
        $profile = Profile::find($user->id);

        if (!$profile || $profile->role !== 'admin_regional' || !$profile->region_id) {
            abort(403, 'Forbidden');
        }

        return $profile->region_id;
    }

    public function masterData(Request $request)
    {
        $regionId = $this->assertRegional();

        $profiles = Profile::where('region_id', $regionId)
            ->orderBy('nama_pesantren')
            ->get(['id', 'nama_pesantren', 'nama_pengasuh', 'status_account', 'status_payment', 'profile_level', 'no_wa_pendaftar', 'nip']);

        $crews = Crew::with('profile:id,nama_pesantren,region_id')
            ->whereHas('profile', fn($q) => $q->where('region_id', $regionId))
            ->orderBy('nama')
            ->get(['id', 'profile_id', 'nama', 'jabatan', 'niam', 'xp_level']);

        return response()->json([
            'profiles' => $profiles->map(fn($p) => [
                'id'             => $p->id,
                'nama_pesantren' => $p->nama_pesantren,
                'nama_pengasuh'  => $p->nama_pengasuh,
                'status_account' => $p->status_account,
                'status_payment' => $p->status_payment,
                'profile_level'  => $p->profile_level,
                'no_wa_pendaftar'=> $p->no_wa_pendaftar,
                'nip'            => $p->nip,
            ]),
            'crews' => $crews->map(fn($c) => [
                'id'            => $c->id,
                'nama'          => $c->nama,
                'jabatan'       => $c->jabatan,
                'niam'          => $c->niam,
                'xp_level'      => $c->xp_level,
                'pesantren_name'=> $c->profile?->nama_pesantren,
            ]),
        ]);
    }

    public function pendingClaims(Request $request)
    {
        $regionId = $this->assertRegional();

        $claims = PesantrenClaim::with('profile')
            ->where('region_id', $regionId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'claims' => $claims->map(fn($c) => [
                'id'               => $c->id,
                'user_id'          => $c->user_id,
                'pesantren_name'   => $c->pesantren_name,
                'status'           => $c->status,
                'region_id'        => $c->region_id,
                'kecamatan'        => $c->kecamatan,
                'nama_pengelola'   => $c->nama_pengelola,
                'email_pengelola'  => $c->email_pengelola,
                'dokumen_bukti_url'=> $c->dokumen_bukti_url,
                'notes'            => $c->notes,
                'claimed_at'       => $c->claimed_at,
                'created_at'       => $c->created_at,
                'jenis_pengajuan'  => $c->jenis_pengajuan,
                'nama_pengasuh'    => $c->profile?->nama_pengasuh,
                'alamat_singkat'   => $c->profile?->alamat_singkat,
                'no_wa_pendaftar'  => $c->profile?->no_wa_pendaftar,
                'niam'             => $c->profile?->niam,
                'is_alumni'        => $c->profile?->is_alumni,
                'alamat_lengkap'   => $c->profile?->alamat_lengkap,
                'desa'             => $c->profile?->desa,
                'kode_pos'         => $c->profile?->kode_pos,
                'maps_link'        => $c->profile?->maps_link,
                'ketua_media'      => $c->profile?->ketua_media,
                'tahun_berdiri'    => $c->profile?->tahun_berdiri,
                'jumlah_kru'       => $c->profile?->jumlah_kru,
                'logo_media_url'   => $c->profile?->logo_media_path,
                'foto_gedung_url'  => $c->profile?->foto_gedung_path,
                'social_links'     => $c->profile?->social_links,
                'website'          => $c->profile?->website,
                'instagram'        => $c->profile?->instagram,
                'facebook'         => $c->profile?->facebook,
                'youtube'          => $c->profile?->youtube,
                'tiktok'           => $c->profile?->tiktok,
                'jenjang_pendidikan' => $c->profile?->jenjang_pendidikan,
                'kecamatan_profile'  => $c->profile?->kecamatan,
            ]),
        ]);
    }

    public function pricingPackages(Request $request)
    {
        $this->assertRegional();

        $packages = PricingPackage::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'packages' => $packages->map(fn($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'category'    => $p->category,
                'harga_paket' => $p->harga_paket,
                'harga_diskon'=> $p->harga_diskon,
                'is_active'   => $p->is_active,
                'created_at'  => $p->created_at,
            ]),
        ]);
    }

    public function approveClaim(Request $request, string $id)
    {
        $regionId = $this->assertRegional();
        $user     = auth()->user();

        $data = $request->validate([
            'pricingPackageId' => 'nullable|uuid',
        ]);

        $claim = PesantrenClaim::find($id);
        if (!$claim || $claim->region_id !== $regionId) {
            return response()->json(['message' => 'Claim tidak ditemukan'], 404);
        }

        DB::transaction(function () use ($claim, $data, $user) {
            $claim->update([
                'status'               => 'regional_approved',
                'regional_approved_at' => now(),
                'notes'                => null,
            ]);

            if ($claim->jenis_pengajuan === 'klaim') {
                Profile::where('id', $claim->user_id)->update(['status_account' => 'active']);
            } else {
                $existingPayment = Payment::where('pesantren_claim_id', $claim->id)->first();

                if (!$existingPayment) {
                    $baseAmount       = 50000;
                    $pricingPackageId = null;

                    if (!empty($data['pricingPackageId'])) {
                        $pkg = PricingPackage::where('id', $data['pricingPackageId'])->where('is_active', true)->first();
                        if ($pkg) {
                            $baseAmount       = $pkg->harga_diskon ?? $pkg->harga_paket;
                            $pricingPackageId = $pkg->id;
                        }
                    } else {
                        $defaultPkg = PricingPackage::where('category', 'registration')
                            ->where('is_active', true)
                            ->orderBy('created_at')
                            ->first();
                        if ($defaultPkg) {
                            $baseAmount       = $defaultPkg->harga_diskon ?? $defaultPkg->harga_paket;
                            $pricingPackageId = $defaultPkg->id;
                        }
                    }

                    $uniqueCode = random_int(1, 999);

                    Payment::create([
                        'id'                 => Str::uuid(),
                        'user_id'            => $claim->user_id,
                        'pesantren_claim_id' => $claim->id,
                        'pricing_package_id' => $pricingPackageId,
                        'base_amount'        => $baseAmount,
                        'unique_code'        => $uniqueCode,
                        'total_amount'       => $baseAmount + $uniqueCode,
                        'status'             => 'pending_payment',
                    ]);
                }
            }
        });

        return response()->json(['success' => true]);
    }

    public function rejectClaim(Request $request, string $id)
    {
        $regionId = $this->assertRegional();

        $data = $request->validate([
            'reason' => 'required|string|min:1',
        ]);

        $claim = PesantrenClaim::find($id);
        if (!$claim || $claim->region_id !== $regionId) {
            return response()->json(['message' => 'Claim tidak ditemukan'], 404);
        }

        DB::transaction(function () use ($claim, $data) {
            $claim->update([
                'status' => 'rejected',
                'notes'  => $data['reason'],
            ]);

            Profile::where('id', $claim->user_id)->update(['status_account' => 'rejected']);
        });

        return response()->json(['success' => true]);
    }

    public function latePayments(Request $request)
    {
        $regionId    = $this->assertRegional();
        $sevenDaysAgo = now()->subDays(7);

        $claims = PesantrenClaim::with('profile:id,no_wa_pendaftar')
            ->where('region_id', $regionId)
            ->where('status', 'regional_approved')
            ->whereNotNull('regional_approved_at')
            ->where('regional_approved_at', '<', $sevenDaysAgo)
            ->orderBy('regional_approved_at')
            ->get();

        $claimIds   = $claims->pluck('id')->all();
        $payments   = $claimIds
            ? Payment::whereIn('pesantren_claim_id', $claimIds)->get(['pesantren_claim_id', 'status'])
            : collect();
        $paymentMap = $payments->keyBy('pesantren_claim_id');

        $filtered = $claims->filter(fn($c) => ($paymentMap[$c->id]->status ?? null) !== 'verified');

        return response()->json([
            'claims' => $filtered->values()->map(fn($c) => [
                'id'                   => $c->id,
                'user_id'              => $c->user_id,
                'pesantren_name'       => $c->pesantren_name,
                'nama_pengelola'       => $c->nama_pengelola,
                'regional_approved_at' => $c->regional_approved_at,
                'jenis_pengajuan'      => $c->jenis_pengajuan,
                'no_wa_pendaftar'      => $c->profile?->no_wa_pendaftar,
                'days_overdue'         => max(0, (int) now()->diffInDays($c->regional_approved_at->addDays(7), false) * -1),
            ]),
        ]);
    }

    public function followUp(Request $request, string $claimId)
    {
        $regionId = $this->assertRegional();
        $user     = auth()->user();

        FollowUpLog::create([
            'id'          => Str::uuid(),
            'admin_id'    => $user->id,
            'claim_id'    => $claimId,
            'region_id'   => $regionId,
            'action_type' => 'whatsapp_followup',
        ]);

        return response()->json(['success' => true]);
    }

    public function performance(Request $request)
    {
        $regionId = $this->assertRegional();
        $weekAgo  = now()->subDays(7);

        $approvedClaims = PesantrenClaim::where('region_id', $regionId)
            ->whereIn('status', ['approved', 'pusat_approved'])
            ->count();

        $paidProfiles = Profile::where('region_id', $regionId)
            ->where('status_payment', 'paid')
            ->count();

        $lateClaims = PesantrenClaim::where('region_id', $regionId)
            ->where('status', 'regional_approved')
            ->whereNotNull('regional_approved_at')
            ->get(['regional_approved_at']);

        $weeklyFollowUps = FollowUpLog::where('region_id', $regionId)
            ->where('created_at', '>=', $weekAgo)
            ->count();

        $pendingFollowUp = $lateClaims->filter(
            fn($c) => $c->regional_approved_at->lt(now()->subDays(7))
        )->count();

        $stuckOver14Days = $lateClaims->filter(
            fn($c) => $c->regional_approved_at->lt(now()->subDays(14))
        )->count();

        return response()->json([
            'totalVerified'   => $approvedClaims,
            'premiumConverted'=> $paidProfiles,
            'conversionRate'  => $approvedClaims > 0 ? round(($paidProfiles / $approvedClaims) * 100, 1) : 0,
            'pendingFollowUp' => $pendingFollowUp,
            'weeklyFollowUps' => $weeklyFollowUps,
            'stuckOver14Days' => $stuckOver14Days,
        ]);
    }

    public function leaderboard(Request $request)
    {
        $myRegionId = $this->assertRegional();

        $regions = Region::orderBy('name')->get(['id', 'name']);

        $stats = $regions->map(function ($r) {
            $verified = PesantrenClaim::where('region_id', $r->id)
                ->whereIn('status', ['approved', 'pusat_approved'])
                ->count();

            $paid = Profile::where('region_id', $r->id)
                ->where('status_payment', 'paid')
                ->count();

            return [
                'region_id'       => $r->id,
                'region_name'     => $r->name,
                'total_verified'  => $verified,
                'total_paid'      => $paid,
                'conversion_rate' => $verified > 0 ? round(($paid / $verified) * 100, 1) : 0,
            ];
        });

        $sorted = $stats->sortByDesc('conversion_rate')
            ->sortByDesc('total_paid')
            ->values();

        return response()->json([
            'leaderboard'    => $sorted,
            'user_region_id' => $myRegionId,
        ]);
    }
}
