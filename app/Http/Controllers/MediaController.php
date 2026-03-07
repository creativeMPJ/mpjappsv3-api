<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\JabatanCode;
use App\Models\PesantrenClaim;
use App\Models\Profile;
use App\Models\User;
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
        $user  = auth()->user();
        $crews = Crew::with('jabatanCode:id,name,code')
            ->where('profile_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'crews' => $crews->map(fn($c) => [
                'id'              => $c->id,
                'nama'            => $c->nama,
                'jabatan'         => $c->jabatan,
                'niam'            => $c->niam,
                'xp_level'        => $c->xp_level,
                'jabatan_code_id' => $c->jabatan_code_id,
                'jabatan_code'    => $c->jabatanCode,
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

        $profile = Profile::find($user->id);
        if (!$profile) return response()->json(['message' => 'Profile tidak ditemukan'], 404);

        $count = Crew::where('profile_id', $user->id)->count();
        if ($count >= 3) {
            return response()->json(['message' => 'Slot gratis sudah penuh (3/3). Upgrade untuk menambah kru.'], 403);
        }

        $jabatanName = $data['jabatan'] ?? null;
        $niam        = null;

        if (!empty($data['jabatanCodeId'])) {
            $code = JabatanCode::find($data['jabatanCodeId']);
            if ($code) {
                $jabatanName = $code->name;
                if ($profile->nip) {
                    $seq  = str_pad($count + 1, 2, '0', STR_PAD_LEFT);
                    $niam = $code->code . $profile->nip . $seq;
                }
            }
        }

        $crew = Crew::create([
            'id'              => Str::uuid(),
            'profile_id'      => $user->id,
            'nama'            => $data['nama'],
            'jabatan'         => $jabatanName,
            'jabatan_code_id' => $data['jabatanCodeId'] ?? null,
            'niam'            => $niam,
        ]);

        $crew->load('jabatanCode:id,name,code');

        return response()->json([
            'crew' => [
                'id'              => $crew->id,
                'nama'            => $crew->nama,
                'jabatan'         => $crew->jabatan,
                'niam'            => $crew->niam,
                'xp_level'        => $crew->xp_level,
                'jabatan_code_id' => $crew->jabatan_code_id,
                'jabatan_code'    => $crew->jabatanCode,
            ],
        ]);
    }

    public function updateCrew(Request $request, string $id)
    {
        $user = auth()->user();
        $data = $request->validate([
            'nama'    => 'required|string',
            'jabatan' => 'nullable|string',
        ]);

        $crew = Crew::where('id', $id)->where('profile_id', $user->id)->first();
        if (!$crew) return response()->json(['message' => 'Kru tidak ditemukan'], 404);

        $crew->update(['nama' => $data['nama'], 'jabatan' => $data['jabatan'] ?? null]);

        return response()->json([
            'crew' => [
                'id'      => $crew->id,
                'nama'    => $crew->nama,
                'jabatan' => $crew->jabatan,
                'niam'    => $crew->niam,
                'xp_level'=> $crew->xp_level,
            ],
        ]);
    }

    public function deleteCrew(Request $request, string $id)
    {
        $user = auth()->user();
        $crew = Crew::where('id', $id)->where('profile_id', $user->id)->first();
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

        $koordinator = Crew::where('profile_id', $user->id)
            ->where('jabatan', 'Koordinator')
            ->select('nama', 'niam', 'jabatan', 'xp_level')
            ->first();

        return response()->json([
            'regionalApprovedAt' => $claim?->regional_approved_at,
            'pusatApprovedAt'    => $claim?->approved_at,
            'koordinator'        => $koordinator ? [
                'nama'     => $koordinator->nama,
                'niam'     => $koordinator->niam,
                'jabatan'  => $koordinator->jabatan ?? 'Koordinator',
                'xp_level' => $koordinator->xp_level ?? 0,
            ] : null,
        ]);
    }

    public function profileSettings(Request $request)
    {
        $user  = auth()->user();
        $profile = Profile::find($user->id);
        $claim = PesantrenClaim::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->select('nama_pengelola')
            ->first();

        return response()->json([
            'namaPengelola' => $claim?->nama_pengelola,
            'email'         => $user->email,
            'noWaPendaftar' => $profile?->no_wa_pendaftar,
        ]);
    }
}
