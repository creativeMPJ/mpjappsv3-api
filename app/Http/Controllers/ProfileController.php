<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function getPesantren(Request $request)
    {
        $user    = auth()->user();
        $profile = Profile::with(['region', 'regency'])->find($user->id);

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
        $profile = Profile::find($user->id);

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

            $hasSosmed   = $profile->instagram || $profile->youtube || $profile->tiktok || $profile->website;
            $hasLocation = $profile->latitude && $profile->longitude;
            $step1Done   = $profile->nama_pesantren && $profile->nama_pengasuh && $profile->alamat_singkat;

            if ($step1Done && $hasSosmed && $hasLocation) {
                $profile->update(['profile_level' => 'gold']);
            }

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

            $step2Done = ($profile->instagram || $profile->youtube || $profile->tiktok || $profile->website)
                && $profile->latitude && $profile->longitude;
            $step1Done = $profile->nama_pesantren && $profile->nama_pengasuh && $profile->alamat_singkat;

            if ($step1Done && $step2Done && $profile->visi_misi && $profile->sejarah) {
                $profile->update(['profile_level' => 'platinum']);
            }
        }

        $profile->refresh();

        return response()->json([
            'success'      => true,
            'profileLevel' => $profile->profile_level,
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

        Profile::where('id', $user->id)->update([$fieldMap[$type] => $url]);

        return response()->json(['url' => $url]);
    }
}
