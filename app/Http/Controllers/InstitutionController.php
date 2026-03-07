<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\PesantrenClaim;
use App\Models\Profile;
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
            'namaPesantren'    => 'required|string',
            'namaPengasuh'     => 'required|string',
            'alamatLengkap'    => 'required|string',
            'cityId'           => 'required|uuid',
            'kecamatan'        => 'required|string',
            'namaPengelola'    => 'required|string',
            'emailPengelola'   => 'required|email',
            'noWhatsapp'       => 'required|string|min:8',
            'dokumenBuktiUrl'  => 'nullable|string',
        ]);

        $city = City::with('region')->find($data['cityId']);
        if (!$city) return response()->json(['message' => 'City not found'], 404);

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $data, $city) {
            Profile::where('id', $user->id)->update([
                'role'           => 'user',
                'nama_pesantren' => $data['namaPesantren'],
                'nama_pengasuh'  => $data['namaPengasuh'],
                'alamat_singkat' => $data['alamatLengkap'],
                'city_id'        => $city->id,
                'region_id'      => $city->region_id,
                'no_wa_pendaftar'=> $data['noWhatsapp'],
                'status_account' => 'pending',
            ]);

            $existing = PesantrenClaim::where('user_id', $user->id)->first();

            $claimData = [
                'pesantren_name'   => $data['namaPesantren'],
                'status'           => 'pending',
                'jenis_pengajuan'  => 'pesantren_baru',
                'region_id'        => $city->region_id,
                'kecamatan'        => $data['kecamatan'],
                'nama_pengelola'   => $data['namaPengelola'],
                'email_pengelola'  => $data['emailPengelola'],
                'dokumen_bukti_url'=> $data['dokumenBuktiUrl'] ?? null,
            ];

            if ($existing) {
                $existing->update($claimData);
            } else {
                PesantrenClaim::create(array_merge(['id' => \Illuminate\Support\Str::uuid(), 'user_id' => $user->id], $claimData));
            }
        });

        return response()->json([
            'success' => true,
            'region'  => [
                'id'   => $city->region->id,
                'name' => $city->region->name,
                'code' => $city->region->code,
            ],
        ]);
    }

    public function location(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        Profile::where('id', $user->id)->update(array_filter([
            'latitude'  => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ], fn($v) => $v !== null));

        return response()->json(['success' => true]);
    }

    public function pendingStatus(Request $request)
    {
        $user = auth()->user();

        $claim = PesantrenClaim::with('region:id,name')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

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
                'admin_phone' => '6281234567890',
            ] : null,
        ]);
    }
}
