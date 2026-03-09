<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\PesantrenClaim;
use App\Models\PesantrenDirectory;
use App\Models\Profile;
use App\Models\Regency;
use App\Models\Region;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function regions()
    {
        $regions = Region::orderBy('name')->get(['id', 'name', 'code']);
        return response()->json(['regions' => $regions]);
    }

    public function cities()
    {
        $cities = Regency::orderBy('name')->get(['id', 'name', 'province_id']);
        return response()->json(['cities' => $cities]);
    }

    public function cityRegion(Request $request, string $id)
    {
        $regency = Regency::with('province')->find($id);
        if (!$regency) return response()->json(['message' => 'City not found'], 404);

        return response()->json([
            'city'     => ['id' => $regency->id, 'name' => $regency->name],
            'province' => [
                'id'   => $regency->province->id,
                'name' => $regency->province->name,
            ],
        ]);
    }

    public function directory(Request $request)
    {
        $search   = trim($request->query('search', ''));
        $regionId = trim($request->query('regionId', ''));

        $query = Profile::with('region:id,name,code')
            ->where('status_account', 'active')
            ->whereNotNull('nip');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_pesantren', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', '%' . str_replace('.', '', $search) . '%');
            });
        }

        if ($regionId && $regionId !== 'all') {
            $query->where('region_id', $regionId);
        }

        $profiles = $query->orderBy('nama_pesantren')->take(300)->get(['id', 'nama_pesantren', 'logo_url', 'nip', 'profile_level', 'region_id']);

        return response()->json([
            'pesantren' => $profiles->map(fn($p) => [
                'id'              => $p->id,
                'nama_pesantren'  => $p->nama_pesantren,
                'logo_url'        => $p->logo_url,
                'nip'             => $p->nip,
                'profile_level'   => $p->profile_level,
                'region_id'       => $p->region_id,
                'region'          => $p->region ? ['name' => $p->region->name, 'code' => $p->region->code] : null,
            ]),
        ]);
    }

    public function pesantrenSearch(Request $request)
    {
        $search = trim($request->query('search', ''));
        if (!$search) return response()->json(['pesantren' => []]);

        $claims = PesantrenClaim::with(['region:id,name', 'profile:id,alamat_singkat'])
            ->whereIn('status', ['approved', 'pusat_approved'])
            ->where('pesantren_name', 'like', "%{$search}%")
            ->orderBy('pesantren_name')
            ->take(20)
            ->get();

        return response()->json([
            'pesantren' => $claims->map(fn($c) => [
                'id'     => $c->id,
                'name'   => $c->pesantren_name,
                'region' => $c->region?->name ?? '-',
                'alamat' => $c->profile?->alamat_singkat ?? '-',
            ]),
        ]);
    }

    public function pesantrenProfile(Request $request, string $nip)
    {
        $cleanNip = str_replace('.', '', $nip);

        $profile = Profile::with('region:id,name')
            ->where('nip', $cleanNip)
            ->where('status_account', 'active')
            ->first(['id', 'nama_pesantren', 'nama_pengasuh', 'nama_media', 'logo_url', 'nip', 'profile_level', 'region_id', 'status_account', 'social_links']);

        if (!$profile) return response()->json(['message' => 'Pesantren tidak ditemukan'], 404);

        $crews = Crew::where('profile_id', $profile->id)
            ->whereNotNull('niam')
            ->orderBy('niam')
            ->get(['id', 'nama', 'niam', 'jabatan']);

        return response()->json([
            'pesantren' => [
                'id'              => $profile->id,
                'nama_pesantren'  => $profile->nama_pesantren,
                'nama_pengasuh'   => $profile->nama_pengasuh,
                'nama_media'      => $profile->nama_media,
                'logo_url'        => $profile->logo_url,
                'nip'             => $profile->nip,
                'profile_level'   => $profile->profile_level,
                'region_id'       => $profile->region_id,
                'status_account'  => $profile->status_account,
                'social_links'    => $profile->social_links,
                'region'          => $profile->region ? ['name' => $profile->region->name] : null,
            ],
            'crews' => $crews->map(fn($c) => [
                'id'     => $c->id,
                'nama'   => $c->nama,
                'niam'   => $c->niam,
                'jabatan'=> $c->jabatan,
            ]),
        ]);
    }

    public function pesantrenCrew(Request $request, string $nip, string $niamSuffix)
    {
        $cleanNip = str_replace('.', '', $nip);
        $suffix   = str_pad($niamSuffix, 2, '0', STR_PAD_LEFT);

        $profile = Profile::where('nip', $cleanNip)->where('status_account', 'active')
            ->first(['id', 'nama_pesantren', 'nip', 'logo_url']);

        if (!$profile) return response()->json(['message' => 'Pesantren tidak ditemukan'], 404);

        $crews = Crew::where('profile_id', $profile->id)->whereNotNull('niam')
            ->get(['id', 'nama', 'niam', 'jabatan', 'xp_level']);

        $crew = $crews->first(fn($c) => str_ends_with($c->niam, $suffix) || $c->niam === $niamSuffix);
        if (!$crew) return response()->json(['message' => 'Kru tidak ditemukan'], 404);

        return response()->json([
            'crew' => [
                'id'       => $crew->id,
                'nama'     => $crew->nama,
                'niam'     => $crew->niam,
                'jabatan'  => $crew->jabatan,
                'xp_level' => $crew->xp_level,
                'profile'  => [
                    'id'             => $profile->id,
                    'nama_pesantren' => $profile->nama_pesantren,
                    'nip'            => $profile->nip,
                    'logo_url'       => $profile->logo_url,
                ],
            ],
        ]);
    }

    public function directorySearch(Request $request)
    {
        $search   = trim($request->query('search', ''));
        $regionId = trim($request->query('regionId', ''));

        $query = PesantrenDirectory::with('region:id,name,code')
            ->whereNull('deleted_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_pesantren', 'like', "%{$search}%")
                  ->orWhere('kota_kabupaten', 'like', "%{$search}%")
                  ->orWhere('alamat', 'like', "%{$search}%");
            });
        }

        if ($regionId && $regionId !== 'all') {
            $query->where('region_id', $regionId);
        }

        $results = $query->orderBy('nama_pesantren')->take(100)->get();

        return response()->json([
            'data' => $results->map(fn($p) => [
                'id'              => $p->id,
                'nama_pesantren'  => $p->nama_pesantren,
                'nama_pengasuh'   => $p->nama_pengasuh,
                'alamat'          => $p->alamat,
                'kota_kabupaten'  => $p->kota_kabupaten,
                'regency_id'      => $p->regency_id,
                'region_id'       => $p->region_id,
                'no_wa_admin'     => $p->no_wa_admin,
                'email_admin'     => $p->email_admin,
                'maps_link'       => $p->maps_link,
                'kode_regional'   => $p->kode_regional,
                'is_claimed'      => $p->is_claimed,
                'source_year'     => $p->source_year,
                'region'          => $p->region ? ['id' => $p->region->id, 'name' => $p->region->name, 'code' => $p->region->code] : null,
            ]),
        ]);
    }
}
