<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\PesantrenClaim;
use App\Models\PesantrenDirectory;
use App\Models\PesantrenProfile;
use App\Models\Regency;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstitutionController extends Controller
{
    public function ownership(Request $request)
    {
        $user  = auth()->user();

        $claim = PesantrenClaim::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$claim) return response()->json(['claim' => null]);

        return response()->json([
            'claim' => [
                'id'              => $claim->id,
                'status'          => $claim->status,
                'pesantren_name'  => $claim->pesantren_name,
                'jenis_pengajuan' => $claim->jenis_pengajuan,
            ],
        ]);
    }

    public function uploadRegistrationDocument(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'file' => 'required|file|mimes:pdf,jpeg,png,webp|max:1024',
        ]);

        $file = $request->file('file');
        $ext  = $file->getClientOriginalExtension();
        $relativePath = "registration-documents/{$user->id}/" . time() . ".{$ext}";

        $file->storeAs(
            "registration-documents/{$user->id}",
            time() . ".{$ext}",
            'public'
        );

        return response()->json(['path' => '/uploads/' . $relativePath]);
    }

    public function initialData(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'jenisPengajuan'   => 'nullable|in:klaim,pesantren_baru',
            'pesantrenId'      => 'nullable|uuid',
            'namaPesantren'    => 'required_if:jenisPengajuan,pesantren_baru|nullable|string',
            'namaPengasuh'     => 'required|string',
            'alamatLengkap'    => 'required|string',
            'regencyId'        => 'required|string|size:4',
            'kecamatan'        => 'required|string',
            'namaPengelola'    => 'required|string',
            'emailPengelola'   => 'required|email',
            'noWhatsapp'       => 'required|string|min:8',
            'dokumenBuktiUrl'  => 'nullable|string',
        ]);

        $jenisPengajuan = $data['jenisPengajuan'] ?? 'pesantren_baru';
        $directory = null;

        if ($jenisPengajuan === 'klaim') {
            if (empty($data['pesantrenId'])) {
                return response()->json(['message' => 'Pesantren wajib dipilih dari direktori'], 422);
            }

            $directory = PesantrenDirectory::whereNull('deleted_at')->find($data['pesantrenId']);
            if (!$directory) {
                return response()->json(['message' => 'Pesantren direktori tidak ditemukan'], 404);
            }
        }

        $namaPesantren = $directory?->nama_pesantren ?? $data['namaPesantren'];

        $regency = Regency::find($data['regencyId']);
        if (!$regency) return response()->json(['message' => 'Regency not found'], 404);

        $region = $regency->regions()->first();

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $data, $regency, $region, $namaPesantren, $jenisPengajuan) {
            $profile = PesantrenProfile::where('user_id', $user->id)->first();

            if ($profile) {
                $profile->update([
                    'nama_pesantren' => $namaPesantren,
                    'nama_pengasuh'  => $data['namaPengasuh'],
                    'alamat_singkat' => $data['alamatLengkap'],
                    'regency_id'     => $regency->id,
                    'region_id'      => $region?->id,
                    'status_account' => 'pending',
                ]);
            }

            if ($user->reff_type === 'crew' && $user->reff_id) {
                Crew::where('id', $user->reff_id)->update(['no_wa' => $data['noWhatsapp']]);
            }

            $existing = $profile ? PesantrenClaim::where('user_id', $profile->id)->first() : null;

            $claimData = [
                'pesantren_name'     => $namaPesantren,
                'status'             => 'pending',
                'jenis_pengajuan'    => $jenisPengajuan,
                'pesantren_directory_id' => $data['pesantrenId'] ?? null,
                'region_id'          => $region?->id,
                'kecamatan'          => $data['kecamatan'],
                'nama_pengelola'     => $data['namaPengelola'],
                'email_pengelola'    => $data['emailPengelola'],
                'dokumen_bukti_url'  => $data['dokumenBuktiUrl'] ?? null,
            ];

            if ($existing) {
                $existing->update($claimData);
            } else {
                PesantrenClaim::create(array_merge(['id' => Str::uuid(), 'user_id' => $profile->id], $claimData));
            }
        });

        return response()->json([
            'success' => true,
            'region'  => $region ? [
                'id'   => $region->id,
                'name' => $region->name,
                'code' => $region->code,
            ] : null,
        ]);
    }

    public function location(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        PesantrenProfile::where('user_id', $user->id)->update(array_filter([
            'latitude'  => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ], fn($v) => $v !== null));

        return response()->json(['success' => true]);
    }

    public function pendingStatus(Request $request)
    {
        $user = auth()->user();

        $profile = PesantrenProfile::where('user_id', $user->id)->first();

        $claim = $profile
            ? PesantrenClaim::with('region:id,name')
                ->where('user_id', $profile->id)
                ->orderBy('created_at', 'desc')
                ->first()
            : null;

        if (!$claim) return response()->json(['claim' => null, 'region' => null]);

        return response()->json([
            'claim' => [
                'pesantren_name'  => $claim->pesantren_name,
                'nama_pengelola'  => $claim->nama_pengelola,
                'region_id'       => $claim->region_id,
                'status'          => $claim->status,
                'jenis_pengajuan' => $claim->jenis_pengajuan,
            ],
            'region' => $claim->region ? [
                'name'        => $claim->region->name,
                // 'admin_phone' => '6281234567890',
                'admin_phone' => '6289529566999',
            ] : null,
        ]);
    }
}
