<?php

namespace App\Http\Controllers;

use App\Models\PesantrenClaim;
use App\Models\PesantrenProfile;
use App\Support\AccessControl;
use App\Support\BerkasDokumen;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function dokumenKlaim(string $claimId)
    {
        $claim = PesantrenClaim::find($claimId);
        $user = auth()->user();

        if (!$claim || !$user) {
            return $this->tidakDitemukan();
        }

        $role = $user->activeRole();
        $adminPusat = (bool) $role?->is_super_admin;
        $profil = PesantrenProfile::where('user_id', $user->id)->first();
        $adminRegionalSesuaiWilayah = AccessControl::has($user, 'validasi-pendaftar')
            && $profil?->region_id
            && $claim->region_id === $profil->region_id;

        if (!$adminPusat && !$adminRegionalSesuaiWilayah) {
            // Jangan bocorkan apakah UUID klaim milik Regional lain memang ada.
            return $this->tidakDitemukan();
        }

        return $this->alirkan($claim->dokumen_bukti_url);
    }

    private function alirkan(?string $url)
    {
        $status = BerkasDokumen::status($url);

        if ($status !== BerkasDokumen::AVAILABLE) {
            return response()->json([
                'message' => 'Dokumen tidak tersedia.',
                'document_status' => $status,
            ], 404);
        }

        $disk = BerkasDokumen::diskBerkas($url);
        $path = BerkasDokumen::pathRelatif($url);

        if (!$disk || !$path) {
            return $this->tidakDitemukan();
        }

        return Storage::disk($disk)->response($path, basename($path), [
            'Cache-Control' => 'private, max-age=0, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function tidakDitemukan()
    {
        return response()->json(['message' => 'Dokumen tidak ditemukan.'], 404);
    }
}
