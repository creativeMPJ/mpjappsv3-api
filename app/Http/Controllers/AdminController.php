<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\JabatanCode;
use App\Models\Payment;
use App\Models\PesantrenClaim;
use App\Models\PricingPackage;
use App\Models\Profile;
use App\Models\Regency;
use App\Models\Region;
use App\Models\SystemSetting;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    private function assertPusat()
    {
        $user    = auth()->user();
        $profile = Profile::find($user->id);
        if (!$profile || $profile->role !== 'admin_pusat') {
            abort(403, 'Forbidden');
        }
        return $profile;
    }

    private function assertPusatOrFinance()
    {
        $user    = auth()->user();
        $profile = Profile::find($user->id);
        if (!$profile || !in_array($profile->role, ['admin_pusat', 'admin_finance'])) {
            abort(403, 'Forbidden');
        }
        return $profile;
    }

    private function upsertUserRole(string $userId, string $role)
    {
        $existing = UserRole::where('user_id', $userId)->orderBy('created_at', 'desc')->first();
        if ($existing) {
            $existing->update(['role' => $role]);
        } else {
            UserRole::create(['id' => Str::uuid(), 'user_id' => $userId, 'role' => $role]);
        }
    }

    public function homeSummary(Request $request)
    {
        $this->assertPusat();

        $totalPesantren   = Profile::where('status_account', 'active')->count();
        $totalKru         = Crew::count();
        $totalWilayah     = Region::count();
        $pendingPayments  = Payment::where('status', 'pending_verification')->count();
        $verifiedPayments = Payment::where('status', 'verified')->sum('total_amount');

        $activeProfiles = Profile::where('status_account', 'active')->get(['profile_level']);
        $levelStats     = ['basic' => 0, 'silver' => 0, 'gold' => 0, 'platinum' => 0];
        foreach ($activeProfiles as $p) {
            if (isset($levelStats[$p->profile_level])) {
                $levelStats[$p->profile_level]++;
            }
        }

        $recentProfiles = Profile::with('region:id,name')
            ->orderBy('created_at', 'desc')
            ->take(8)
            ->get(['id', 'nama_pesantren', 'nip', 'status_account', 'profile_level', 'created_at', 'region_id']);

        return response()->json([
            'stats' => [
                'totalPesantren'  => $totalPesantren,
                'totalKru'        => $totalKru,
                'totalWilayah'    => $totalWilayah,
                'pendingPayments' => $pendingPayments,
                'totalIncome'     => (int) $verifiedPayments,
            ],
            'levelStats'   => $levelStats,
            'recentUsers'  => $recentProfiles->map(fn($p) => [
                'id'             => $p->id,
                'nama_pesantren' => $p->nama_pesantren,
                'nip'            => $p->nip,
                'region_name'    => $p->region?->name ?? '-',
                'status_account' => $p->status_account,
                'profile_level'  => $p->profile_level,
                'created_at'     => $p->created_at,
            ]),
        ]);
    }

    public function clearingHousePending(Request $request)
    {
        $this->assertPusat();

        $profiles = Profile::with('regency:id,name')
            ->where('status_account', 'pending')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'profiles' => $profiles->map(fn($p) => [
                'id'             => $p->id,
                'nama_pesantren' => $p->nama_pesantren,
                'nama_pengasuh'  => $p->nama_pengasuh,
                'regency_id'     => $p->regency_id,
                'created_at'     => $p->created_at,
                'regency'        => $p->regency ? ['name' => $p->regency->name] : null,
            ]),
        ]);
    }

    public function clearingHouseApprove(Request $request, string $id)
    {
        $this->assertPusat();

        $profile = Profile::with('region:id,code')->find($id);
        if (!$profile) return response()->json(['message' => 'Profil tidak ditemukan'], 404);

        $year = now()->format('y');
        $rr   = $profile->region?->code ?? '00';
        $seq  = str_pad(random_int(100, 999), 3, '0', STR_PAD_LEFT);
        $nip  = "{$year}{$rr}{$seq}";

        $profile->update(['status_account' => 'active', 'status_payment' => 'paid', 'nip' => $nip]);

        return response()->json(['success' => true, 'nip' => $nip]);
    }

    public function clearingHouseReject(Request $request, string $id)
    {
        $this->assertPusat();

        Profile::where('id', $id)->update(['status_account' => 'rejected']);

        return response()->json(['success' => true]);
    }

    public function pendingProfiles(Request $request)
    {
        $this->assertPusat();

        $profiles = Profile::with('region:id,name')
            ->where('status_account', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'pending_profiles' => $profiles->map(fn($p) => [
                'id'              => $p->id,
                'nama_pesantren'  => $p->nama_pesantren,
                'region_name'     => $p->region?->name ?? 'Unknown',
                'no_wa_pendaftar' => $p->no_wa_pendaftar,
                'created_at'      => $p->created_at,
                'status_account'  => $p->status_account,
            ]),
        ]);
    }

    public function adminSettingsData(Request $request)
    {
        $this->assertPusat();

        $admins = Crew::with('profile:id,role,region_id')
            ->whereHas('profile', fn($q) => $q->whereIn('role', ['admin_pusat', 'admin_regional', 'admin_finance']))
            ->get();

        $regions = Region::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'admins' => $admins->map(fn($c) => [
                'id'          => $c->id,
                'user_id'     => $c->profile?->id,
                'nama'        => $c->nama,
                'niam'        => $c->niam,
                'jabatan'     => $c->jabatan,
                'region_id'   => $c->profile?->region_id,
                'region_name' => $c->profile?->region?->name,
                'role'        => $c->profile?->role,
            ]),
            'regions' => $regions,
        ]);
    }

    public function adminSettingsSearchCrew(Request $request)
    {
        $this->assertPusat();

        $q = trim($request->query('query', ''));
        if (strlen($q) < 2) return response()->json(['crews' => []]);

        $crews = Crew::with(['profile' => fn($q2) => $q2->with(['region:id,name', 'user:id,email'])])
            ->where('nama', 'like', "%{$q}%")
            ->take(10)
            ->orderBy('nama')
            ->get();

        return response()->json([
            'crews' => $crews->map(fn($c) => [
                'id'           => $c->id,
                'profile_id'   => $c->profile?->id,
                'nama'         => $c->nama,
                'niam'         => $c->niam,
                'jabatan'      => $c->jabatan,
                'pesantren_name' => $c->profile?->nama_pesantren,
                'region_id'    => $c->profile?->region_id,
                'region_name'  => $c->profile?->region?->name,
                'email'        => $c->profile?->user?->email,
                'current_role' => $c->profile?->role,
            ]),
        ]);
    }

    public function adminSettingsAssign(Request $request)
    {
        $this->assertPusat();

        $data = $request->validate([
            'profileId' => 'required|uuid',
            'role'      => 'required|string',
            'regionId'  => 'nullable|uuid',
        ]);

        DB::transaction(function () use ($data) {
            if ($data['role'] === 'admin_regional' && !empty($data['regionId'])) {
                Profile::where('role', 'admin_regional')
                    ->where('region_id', $data['regionId'])
                    ->where('id', '!=', $data['profileId'])
                    ->update(['role' => 'user']);
            }

            Profile::where('id', $data['profileId'])->update([
                'role'      => $data['role'],
                'region_id' => $data['role'] === 'admin_regional' ? ($data['regionId'] ?? null) : DB::raw('region_id'),
            ]);

            $this->upsertUserRole($data['profileId'], $data['role']);
        });

        return response()->json(['success' => true]);
    }

    public function adminSettingsRemove(Request $request, string $userId)
    {
        $this->assertPusat();

        DB::transaction(function () use ($userId) {
            Profile::where('id', $userId)->update(['role' => 'user']);
            $this->upsertUserRole($userId, 'user');
        });

        return response()->json(['success' => true]);
    }

    public function masterData(Request $request)
    {
        $this->assertPusat();

        $profiles = Profile::with(['region:id,name', 'regency:id,name'])
            ->orderBy('nama_pesantren')
            ->get();

        $crews = Crew::with(['profile' => fn($q) => $q->with('region:id,name')])
            ->orderBy('nama')
            ->get();

        $regions = Region::orderBy('code')->get(['id', 'name', 'code']);

        return response()->json([
            'profiles' => $profiles->map(fn($p) => [
                'id'              => $p->id,
                'nama_pesantren'  => $p->nama_pesantren,
                'nama_media'      => $p->nama_media,
                'nip'             => $p->nip,
                'region_id'       => $p->region_id,
                'region_name'     => $p->region?->name,
                'regency_name'    => $p->regency?->name,
                'status_account'  => $p->status_account,
                'profile_level'   => $p->profile_level,
                'alamat_singkat'  => $p->alamat_singkat,
                'nama_pengasuh'   => $p->nama_pengasuh,
                'no_wa_pendaftar' => $p->no_wa_pendaftar,
            ]),
            'crews' => $crews->map(fn($c) => [
                'id'            => $c->id,
                'nama'          => $c->nama,
                'niam'          => $c->niam,
                'jabatan'       => $c->jabatan,
                'xp_level'      => $c->xp_level,
                'profile_id'    => $c->profile_id,
                'pesantren_name'=> $c->profile?->nama_pesantren,
                'region_id'     => $c->profile?->region_id,
                'region_name'   => $c->profile?->region?->name,
            ]),
            'regions' => $regions,
        ]);
    }

    public function masterDataUpdatePesantren(Request $request, string $id)
    {
        $this->assertPusat();

        $data = $request->validate([
            'nama_pesantren' => 'nullable|string',
            'nama_pengasuh'  => 'nullable|string',
            'alamat_singkat' => 'nullable|string',
        ]);

        Profile::where('id', $id)->update(array_filter([
            'nama_pesantren' => $data['nama_pesantren'] ?? null,
            'nama_pengasuh'  => $data['nama_pengasuh'] ?? null,
            'alamat_singkat' => $data['alamat_singkat'] ?? null,
        ], fn($v) => $v !== null));

        return response()->json(['success' => true]);
    }

    public function masterDataUpdateMedia(Request $request, string $id)
    {
        $this->assertPusat();

        $data = $request->validate([
            'nama_pesantren'  => 'nullable|string',
            'nama_media'      => 'nullable|string',
            'no_wa_pendaftar' => 'nullable|string',
        ]);

        Profile::where('id', $id)->update(array_filter([
            'nama_pesantren'  => $data['nama_pesantren'] ?? null,
            'nama_media'      => $data['nama_media'] ?? null,
            'no_wa_pendaftar' => $data['no_wa_pendaftar'] ?? null,
        ], fn($v) => $v !== null));

        return response()->json(['success' => true]);
    }

    public function masterDataUpdateCrew(Request $request, string $id)
    {
        $this->assertPusat();

        $data = $request->validate([
            'nama'    => 'nullable|string',
            'jabatan' => 'nullable|string',
        ]);

        Crew::where('id', $id)->update(array_filter([
            'nama'    => $data['nama'] ?? null,
            'jabatan' => $data['jabatan'] ?? null,
        ], fn($v) => $v !== null));

        return response()->json(['success' => true]);
    }

    public function masterDataDeleteCrew(Request $request, string $id)
    {
        $this->assertPusat();

        Crew::where('id', $id)->delete();

        return response()->json(['success' => true]);
    }

    public function masterDataImport(Request $request)
    {
        $this->assertPusat();

        $data = $request->validate([
            'type' => 'required|in:pesantren,media,kru',
            'rows' => 'required|array|min:1',
        ]);

        $type    = $data['type'];
        $rows    = $data['rows'];
        $results = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($rows as $i => $row) {
            $num = $i + 1;
            try {
                if ($type === 'pesantren') {
                    $nip = trim($row['NIP'] ?? $row['nip'] ?? '');
                    if (!$nip) { $results['errors'][] = "Baris {$num}: NIP kosong"; $results['skipped']++; continue; }
                    $profile = Profile::where('nip', $nip)->first();
                    if (!$profile) { $results['errors'][] = "Baris {$num}: NIP {$nip} tidak ditemukan"; $results['skipped']++; continue; }
                    $profile->update(array_filter([
                        'nama_pesantren' => $row['Nama Pesantren'] ?? $row['nama_pesantren'] ?? null,
                        'nama_pengasuh'  => $row['Nama Pengasuh'] ?? $row['nama_pengasuh'] ?? null,
                        'alamat_singkat' => $row['Alamat Singkat'] ?? $row['alamat_singkat'] ?? null,
                    ], fn($v) => $v !== null));
                } elseif ($type === 'media') {
                    $nip = trim($row['NIP'] ?? $row['nip'] ?? '');
                    if (!$nip) { $results['errors'][] = "Baris {$num}: NIP kosong"; $results['skipped']++; continue; }
                    $profile = Profile::where('nip', $nip)->first();
                    if (!$profile) { $results['errors'][] = "Baris {$num}: NIP {$nip} tidak ditemukan"; $results['skipped']++; continue; }
                    $profile->update(array_filter([
                        'nama_pesantren'  => $row['Nama Pesantren'] ?? $row['nama_pesantren'] ?? null,
                        'nama_media'      => $row['Nama Media'] ?? $row['nama_media'] ?? null,
                        'no_wa_pendaftar' => $row['No WA'] ?? $row['no_wa_pendaftar'] ?? null,
                    ], fn($v) => $v !== null));
                } elseif ($type === 'kru') {
                    $niam = trim($row['NIAM'] ?? $row['niam'] ?? '');
                    if (!$niam) { $results['errors'][] = "Baris {$num}: NIAM kosong"; $results['skipped']++; continue; }
                    $crew = Crew::where('niam', $niam)->first();
                    if (!$crew) { $results['errors'][] = "Baris {$num}: NIAM {$niam} tidak ditemukan"; $results['skipped']++; continue; }
                    $crew->update(array_filter([
                        'nama'    => $row['Nama Kru'] ?? $row['nama'] ?? null,
                        'jabatan' => $row['Jabatan'] ?? $row['jabatan'] ?? null,
                    ], fn($v) => $v !== null));
                }
                $results['imported']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Baris {$num}: " . $e->getMessage();
                $results['skipped']++;
            }
        }

        return response()->json(array_merge(['success' => true], $results));
    }

    public function jabatanCodes(Request $request)
    {
        $this->assertPusat();

        $items = JabatanCode::orderBy('code')->get();

        return response()->json([
            'jabatan_codes' => $items->map(fn($c) => [
                'id'          => $c->id,
                'code'        => $c->code,
                'name'        => $c->name,
                'description' => $c->description,
                'created_at'  => $c->created_at,
            ]),
        ]);
    }

    public function createJabatanCode(Request $request)
    {
        $this->assertPusat();

        $data = $request->validate([
            'code'        => 'required|string|regex:/^[A-Z]{2,3}$/',
            'name'        => 'required|string',
            'description' => 'nullable|string',
        ]);

        $created = JabatanCode::create([
            'id'          => Str::uuid(),
            'code'        => $data['code'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return response()->json([
            'jabatan_code' => [
                'id'          => $created->id,
                'code'        => $created->code,
                'name'        => $created->name,
                'description' => $created->description,
                'created_at'  => $created->created_at,
            ],
        ]);
    }

    public function updateJabatanCode(Request $request, string $id)
    {
        $this->assertPusat();

        $data = $request->validate([
            'code'        => 'required|string|regex:/^[A-Z]{2,3}$/',
            'name'        => 'required|string',
            'description' => 'nullable|string',
        ]);

        $item = JabatanCode::find($id);
        if (!$item) return response()->json(['message' => 'ID tidak valid'], 400);

        $item->update([
            'code'        => $data['code'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return response()->json([
            'jabatan_code' => [
                'id'          => $item->id,
                'code'        => $item->code,
                'name'        => $item->name,
                'description' => $item->description,
                'created_at'  => $item->created_at,
            ],
        ]);
    }

    public function deleteJabatanCode(Request $request, string $id)
    {
        $this->assertPusat();

        JabatanCode::where('id', $id)->delete();

        return response()->json(['success' => true]);
    }

    public function globalSearch(Request $request)
    {
        $this->assertPusat();

        $q = trim($request->query('query', ''));
        if (!$q) return response()->json(['results' => []]);

        $claims = PesantrenClaim::with('region:id,name')
            ->where('mpj_id_number', 'like', "%{$q}%")
            ->take(10)
            ->get();

        $crews = Crew::with('profile:id,nama_pesantren,status_account')
            ->where('niam', 'like', "%{$q}%")
            ->take(10)
            ->get();

        return response()->json([
            'results' => array_merge(
                $claims->filter(fn($c) => $c->mpj_id_number)->map(fn($c) => [
                    'type'     => 'pesantren',
                    'id'       => $c->id,
                    'nomorId'  => $c->mpj_id_number,
                    'nama'     => $c->pesantren_name,
                    'status'   => $c->status,
                    'region'   => $c->region?->name,
                ])->values()->all(),
                $crews->filter(fn($c) => $c->niam)->map(fn($c) => [
                    'type'         => 'crew',
                    'id'           => $c->id,
                    'nomorId'      => $c->niam,
                    'nama'         => $c->nama,
                    'jabatan'      => $c->jabatan,
                    'lembagaInduk' => $c->profile?->nama_pesantren,
                    'status'       => $c->profile?->status_account,
                ])->values()->all()
            ),
        ]);
    }

    public function pusatAssistants(Request $request)
    {
        $this->assertPusat();

        $assistants = Crew::with(['profile' => fn($q) => $q->with(['region:id,name', 'user:id,email'])])
            ->whereHas('profile', fn($q) => $q->where('role', 'admin_pusat'))
            ->orderBy('created_at', 'desc')
            ->get();

        $available = Crew::with(['profile' => fn($q) => $q->with(['region:id,name', 'user:id,email'])])
            ->whereHas('profile', fn($q) => $q->where('role', '!=', 'admin_pusat'))
            ->orderBy('nama')
            ->get();

        return response()->json([
            'assistants' => $assistants->map(fn($c) => [
                'id'            => $c->id,
                'crew_id'       => $c->id,
                'nama'          => $c->nama,
                'email'         => $c->profile?->user?->email ?? '',
                'niam'          => $c->niam,
                'pesantren_name'=> $c->profile?->nama_pesantren ?? '-',
                'region_name'   => $c->profile?->region?->name ?? '-',
                'appointed_at'  => $c->updated_at,
                'appointed_by'  => 'Admin Pusat',
            ]),
            'available_crews' => $available->map(fn($c) => [
                'id'            => $c->id,
                'nama'          => $c->nama,
                'niam'          => $c->niam,
                'pesantren_name'=> $c->profile?->nama_pesantren ?? '-',
                'region_name'   => $c->profile?->region?->name ?? '-',
                'email'         => $c->profile?->user?->email ?? '',
                'profile_id'    => $c->profile?->id,
            ]),
        ]);
    }

    public function addPusatAssistant(Request $request)
    {
        $this->assertPusat();

        $data = $request->validate(['crewId' => 'required|uuid']);
        $crew = Crew::find($data['crewId']);
        if (!$crew) return response()->json(['message' => 'Kru tidak ditemukan'], 404);

        DB::transaction(function () use ($crew) {
            Profile::where('id', $crew->profile_id)->update(['role' => 'admin_pusat']);
            $this->upsertUserRole($crew->profile_id, 'admin_pusat');
        });

        return response()->json(['success' => true]);
    }

    public function removePusatAssistant(Request $request, string $crewId)
    {
        $this->assertPusat();

        $crew = Crew::find($crewId);
        if (!$crew) return response()->json(['message' => 'Kru tidak ditemukan'], 404);

        DB::transaction(function () use ($crew) {
            Profile::where('id', $crew->profile_id)->update(['role' => 'user']);
            $this->upsertUserRole($crew->profile_id, 'user');
        });

        return response()->json(['success' => true]);
    }

    public function regionalManagementData(Request $request)
    {
        $this->assertPusat();

        $regions = Region::orderBy('name')->get(['id', 'name', 'code']);
        $cities  = Regency::orderBy('name')->get(['id', 'name', 'province_id']);
        $users   = Profile::with('region:id,name')->orderBy('nama_pesantren')
            ->get(['id', 'nama_pesantren', 'nama_pengasuh', 'region_id', 'role', 'status_account']);

        return response()->json([
            'regions' => $regions->map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'code' => $r->code]),
            'cities'  => $cities->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'province_id' => $c->province_id]),
            'users'   => $users->map(fn($u) => [
                'id'             => $u->id,
                'nama_pesantren' => $u->nama_pesantren,
                'nama_pengasuh'  => $u->nama_pengasuh,
                'region_id'      => $u->region_id,
                'region_name'    => $u->region?->name,
                'role'           => $u->role,
                'status_account' => $u->status_account,
            ]),
        ]);
    }

    public function addRegion(Request $request)
    {
        $this->assertPusat();

        $data = $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|regex:/^\d{2}$/',
        ]);

        if (Region::where('code', $data['code'])->exists()) {
            return response()->json(['message' => 'Kode regional sudah digunakan'], 409);
        }

        $region = Region::create(['id' => Str::uuid(), 'name' => $data['name'], 'code' => $data['code']]);

        return response()->json(['region' => ['id' => $region->id, 'name' => $region->name, 'code' => $region->code]]);
    }

    public function deleteRegion(Request $request, string $id)
    {
        $this->assertPusat();

        Region::where('id', $id)->delete();

        return response()->json(['success' => true]);
    }

    public function addCity(Request $request)
    {
        return response()->json(['message' => 'Data kabupaten/kota dikelola dari data wilayah Indonesia'], 405);
    }

    public function deleteCity(Request $request, string $id)
    {
        return response()->json(['message' => 'Data kabupaten/kota dikelola dari data wilayah Indonesia'], 405);
    }

    public function assignRegionalAdmin(Request $request)
    {
        $this->assertPusat();

        $data = $request->validate([
            'userId'   => 'required|uuid',
            'regionId' => 'required|uuid',
        ]);

        $region = Region::find($data['regionId']);
        if (!$region) return response()->json(['message' => 'Regional tidak ditemukan'], 404);

        DB::transaction(function () use ($data) {
            Profile::where('id', $data['userId'])->update([
                'role'      => 'admin_regional',
                'region_id' => $data['regionId'],
            ]);
            $this->upsertUserRole($data['userId'], 'admin_regional');
        });

        return response()->json(['success' => true, 'region' => ['id' => $region->id, 'name' => $region->name]]);
    }

    public function usersManagement(Request $request)
    {
        $this->assertPusat();

        $users = Profile::with('region:id,name')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'nama_pesantren', 'nama_pengasuh', 'role', 'status_account', 'status_payment', 'region_id']);

        return response()->json([
            'users' => $users->map(fn($u) => [
                'id'              => $u->id,
                'nama_pesantren'  => $u->nama_pesantren,
                'nama_pengasuh'   => $u->nama_pengasuh,
                'role'            => $u->role,
                'status_account'  => $u->status_account,
                'status_payment'  => $u->status_payment,
                'region_id'       => $u->region_id,
                'region_name'     => $u->region?->name ?? '-',
            ]),
        ]);
    }

    public function updateUser(Request $request, string $id)
    {
        $this->assertPusat();

        $data = $request->validate([
            'role'          => 'required|string',
            'statusAccount' => 'required|in:pending,active,rejected',
            'statusPayment' => 'required|in:paid,unpaid',
        ]);

        DB::transaction(function () use ($id, $data) {
            Profile::where('id', $id)->update([
                'role'           => $data['role'],
                'status_account' => $data['statusAccount'],
                'status_payment' => $data['statusPayment'],
            ]);
            $this->upsertUserRole($id, $data['role']);
        });

        return response()->json(['success' => true]);
    }

    public function bankSettings(Request $request)
    {
        $this->assertPusat();

        return response()->json([
            'bankName'          => (string) SystemSetting::getValue('bank_name', ''),
            'bankAccountNumber' => (string) SystemSetting::getValue('bank_account_number', ''),
            'bankAccountName'   => (string) SystemSetting::getValue('bank_account_name', ''),
        ]);
    }

    public function updateBankSettings(Request $request)
    {
        $this->assertPusat();

        $data = $request->validate([
            'bankName'          => 'required|string',
            'bankAccountNumber' => 'required|string',
            'bankAccountName'   => 'required|string',
        ]);

        $updates = [
            'bank_name'           => ['value' => $data['bankName'],          'desc' => 'Nama bank untuk pembayaran'],
            'bank_account_number' => ['value' => $data['bankAccountNumber'], 'desc' => 'Nomor rekening bank'],
            'bank_account_name'   => ['value' => $data['bankAccountName'],   'desc' => 'Nama pemilik rekening'],
        ];

        DB::transaction(function () use ($updates) {
            foreach ($updates as $key => $item) {
                $existing = \App\Models\SystemSetting::where('key', $key)->first();
                if ($existing) {
                    $existing->update(['value' => json_encode($item['value']), 'description' => $item['desc']]);
                } else {
                    \App\Models\SystemSetting::create([
                        'id'          => Str::uuid(),
                        'key'         => $key,
                        'value'       => json_encode($item['value']),
                        'description' => $item['desc'],
                    ]);
                }
            }
        });

        return response()->json(['success' => true]);
    }

    public function superStats(Request $request)
    {
        $this->assertPusat();

        $totalUsers    = Profile::count();
        $totalPesantren= Profile::whereNotNull('nama_pesantren')->count();
        $paidUsers     = Profile::where('status_payment', 'paid')->count();
        $revenue       = $paidUsers * 350000;

        return response()->json([
            'total_users'        => $totalUsers,
            'total_pesantren'    => $totalPesantren,
            'paid_users'         => $paidUsers,
            'estimated_revenue'  => $revenue,
        ]);
    }

    public function regionDetail(Request $request, string $id)
    {
        $this->assertPusat();

        $region = Region::with('cities:id,name,region_id')->find($id);
        if (!$region) return response()->json(['message' => 'Regional tidak ditemukan'], 404);

        $memberCount  = Profile::where('region_id', $id)->where('role', 'user')->count();
        $pesantrenCount = Profile::where('region_id', $id)->whereNotNull('nama_pesantren')->count();
        $adminCount   = Profile::where('region_id', $id)->where('role', 'admin_regional')->count();
        $recentProfiles = Profile::where('region_id', $id)
            ->whereNotNull('nama_pesantren')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get(['id', 'nama_pesantren', 'nama_pengasuh', 'status_account', 'created_at']);

        return response()->json([
            'region' => [
                'id'     => $region->id,
                'name'   => $region->name,
                'code'   => $region->code,
                'cities' => $region->cities->map(fn($c) => ['name' => $c->name]),
            ],
            'stats' => [
                'member_count'   => $memberCount,
                'pesantren_count'=> $pesantrenCount,
                'admin_count'    => $adminCount,
            ],
            'recent_pesantren' => $recentProfiles->map(fn($p) => [
                'id'             => $p->id,
                'nama_pesantren' => $p->nama_pesantren,
                'nama_pengasuh'  => $p->nama_pengasuh,
                'status_account' => $p->status_account,
                'created_at'     => $p->created_at,
            ]),
        ]);
    }

    public function priceSettings(Request $request)
    {
        $this->assertPusat();

        return response()->json([
            'registrationPrice' => (int) SystemSetting::getValue('registration_base_price', 50000),
            'claimPrice'        => (int) SystemSetting::getValue('claim_base_price', 20000),
        ]);
    }

    public function updatePriceSettings(Request $request)
    {
        $this->assertPusat();

        $data = $request->validate([
            'registrationPrice' => 'required|integer|min:1',
            'claimPrice'        => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($data) {
            foreach ([
                'registration_base_price' => [$data['registrationPrice'], 'Harga dasar pendaftaran pesantren baru'],
                'claim_base_price'        => [$data['claimPrice'], 'Harga dasar klaim akun lama'],
            ] as $key => [$value, $desc]) {
                $existing = \App\Models\SystemSetting::where('key', $key)->first();
                if ($existing) {
                    $existing->update(['value' => json_encode($value)]);
                } else {
                    \App\Models\SystemSetting::create([
                        'id' => Str::uuid(), 'key' => $key,
                        'value' => json_encode($value), 'description' => $desc,
                    ]);
                }
            }
        });

        return response()->json(['success' => true]);
    }

    public function latePaymentCount(Request $request)
    {
        $this->assertPusat();

        $sevenDaysAgo = now()->subDays(7);

        $lateClaims = PesantrenClaim::where('status', 'regional_approved')
            ->whereNotNull('regional_approved_at')
            ->where('regional_approved_at', '<', $sevenDaysAgo)
            ->pluck('user_id');

        if ($lateClaims->isEmpty()) return response()->json(['count' => 0]);

        $count = Profile::whereIn('id', $lateClaims)->where('status_payment', 'unpaid')->count();

        return response()->json(['count' => $count]);
    }

    public function claims(Request $request)
    {
        $this->assertPusat();

        $claims = PesantrenClaim::with('region:id,name')
            // ->whereIn('status', ['regional_approved', 'pusat_approved'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'claims' => $claims->map(fn($c) => [
                'id'              => $c->id,
                'user_id'         => $c->user_id,
                'pesantren_name'  => $c->pesantren_name,
                'nama_pengelola'  => $c->nama_pengelola,
                'jenis_pengajuan' => $c->jenis_pengajuan,
                'status'          => $c->status,
                'created_at'      => $c->created_at,
                'region_id'       => $c->region_id,
                'mpj_id_number'   => $c->mpj_id_number,
                'region_name'     => $c->region?->name ?? '-',
            ]),
        ]);
    }

    public function payments(Request $request)
    {
        $this->assertPusatOrFinance();

        $payments = Payment::with([
            'claim:id,pesantren_name,nama_pengelola,jenis_pengajuan,region_id,mpj_id_number',
            'user:id,no_wa_pendaftar',
        ])->orderBy('created_at', 'desc')->get();

        return response()->json([
            'payments' => $payments->map(fn($p) => [
                'id'                  => $p->id,
                'user_id'             => $p->user_id,
                'pesantren_claim_id'  => $p->pesantren_claim_id,
                'base_amount'         => $p->base_amount,
                'unique_code'         => $p->unique_code,
                'total_amount'        => $p->total_amount,
                'proof_file_url'      => $p->proof_file_url,
                'status'              => $p->status,
                'created_at'          => $p->created_at,
                'rejection_reason'    => $p->rejection_reason,
                'verified_by'         => $p->verified_by,
                'verified_at'         => $p->verified_at,
                'pesantren_claims'    => [
                    'pesantren_name'  => $p->claim?->pesantren_name,
                    'nama_pengelola'  => $p->claim?->nama_pengelola,
                    'jenis_pengajuan' => $p->claim?->jenis_pengajuan,
                    'region_id'       => $p->claim?->region_id,
                    'mpj_id_number'   => $p->claim?->mpj_id_number,
                ],
                'profiles' => ['no_wa_pendaftar' => $p->user?->no_wa_pendaftar],
            ]),
        ]);
    }

    public function rejectPayment(Request $request, string $id)
    {
        $this->assertPusatOrFinance();

        $data = $request->validate(['reason' => 'required|string|min:1']);

        Payment::where('id', $id)->update([
            'status'           => 'pending_payment',
            'rejection_reason' => $data['reason'],
            'proof_file_url'   => null,
        ]);

        return response()->json(['success' => true]);
    }

    public function approvePayment(Request $request, string $id)
    {
        $user = auth()->user();
        $this->assertPusatOrFinance();

        $payment = Payment::with(['claim', 'user:id,no_wa_pendaftar'])->find($id);
        if (!$payment) return response()->json(['message' => 'Payment not found'], 404);
        if (!$payment->claim?->region_id) return response()->json(['message' => 'Region ID tidak ditemukan'], 422);

        $nip = DB::transaction(function () use ($payment, $user) {
            $region = Region::find($payment->claim->region_id);
            if (!$region || !preg_match('/^\d{2}$/', $region->code)) {
                abort(422, 'Kode RR Belum Valid');
            }

            $count    = PesantrenClaim::where('region_id', $payment->claim->region_id)
                ->whereNotNull('mpj_id_number')->count();
            $year     = now()->format('y');
            $seq      = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
            $generatedNip = "{$year}{$region->code}{$seq}";

            $payment->update([
                'status'           => 'verified',
                'verified_by'      => $user->id,
                'verified_at'      => now(),
                'rejection_reason' => null,
            ]);

            PesantrenClaim::where('id', $payment->pesantren_claim_id)->update([
                'status'         => 'approved',
                'approved_by'    => $user->id,
                'approved_at'    => now(),
                'mpj_id_number'  => $generatedNip,
            ]);

            Profile::where('id', $payment->user_id)->update([
                'status_account' => 'active',
                'status_payment' => 'paid',
                'nip'            => $generatedNip,
                'nama_pesantren' => $payment->claim->pesantren_name,
            ]);

            return $generatedNip;
        });

        return response()->json([
            'success'       => true,
            'nip'           => $nip,
            'phoneNumber'   => $payment->user?->no_wa_pendaftar,
            'pesantrenName' => $payment->claim->pesantren_name,
        ]);
    }

    public function levelingProfiles(Request $request)
    {
        $this->assertPusat();

        $profiles = Profile::with('region:id,name')
            ->where('status_account', 'active')
            ->whereIn('profile_level', ['silver', 'gold'])
            ->orderBy('nama_pesantren')
            ->get();

        return response()->json([
            'profiles' => $profiles->map(fn($p) => [
                'id'                 => $p->id,
                'nama_pesantren'     => $p->nama_pesantren,
                'nip'                => $p->nip,
                'profile_level'      => $p->profile_level,
                'sejarah'            => $p->sejarah,
                'visi_misi'          => $p->visi_misi,
                'logo_url'           => $p->logo_url,
                'foto_pengasuh_url'  => $p->foto_pengasuh_url,
                'region_name'        => $p->region?->name ?? '-',
            ]),
        ]);
    }

    public function promotePlatinum(Request $request, string $id)
    {
        $this->assertPusat();

        Profile::where('id', $id)->update(['profile_level' => 'platinum']);

        return response()->json(['success' => true]);
    }

    public function pricingPackages(Request $request)
    {
        $this->assertPusatOrFinance();

        $packages = PricingPackage::orderByRaw("category ASC, harga_paket ASC")->get();

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

    public function createPricingPackage(Request $request)
    {
        $this->assertPusatOrFinance();

        $data = $request->validate([
            'name'        => 'required|string',
            'category'    => 'required|in:registration,renewal,upgrade',
            'hargaPaket'  => 'required|integer|min:1',
            'hargaDiskon' => 'nullable|integer|min:1',
            'isActive'    => 'nullable|boolean',
        ]);

        $pkg = PricingPackage::create([
            'id'           => Str::uuid(),
            'name'         => $data['name'],
            'category'     => $data['category'],
            'harga_paket'  => $data['hargaPaket'],
            'harga_diskon' => $data['hargaDiskon'] ?? null,
            'is_active'    => $data['isActive'] ?? true,
        ]);

        return response()->json(['success' => true, 'id' => $pkg->id]);
    }

    public function updatePricingPackage(Request $request, string $id)
    {
        $this->assertPusatOrFinance();

        $data = $request->validate([
            'name'        => 'nullable|string',
            'category'    => 'nullable|in:registration,renewal,upgrade',
            'hargaPaket'  => 'nullable|integer|min:1',
            'hargaDiskon' => 'nullable|integer|min:1',
            'isActive'    => 'nullable|boolean',
        ]);

        $pkg = PricingPackage::find($id);
        if (!$pkg) return response()->json(['message' => 'ID tidak valid'], 400);

        $pkg->update(array_filter([
            'name'         => $data['name'] ?? null,
            'category'     => $data['category'] ?? null,
            'harga_paket'  => $data['hargaPaket'] ?? null,
            'harga_diskon' => $data['hargaDiskon'] ?? null,
            'is_active'    => $data['isActive'] ?? null,
        ], fn($v) => $v !== null));

        return response()->json(['success' => true]);
    }

    public function togglePricingPackage(Request $request, string $id)
    {
        $this->assertPusatOrFinance();

        $pkg = PricingPackage::find($id);
        if (!$pkg) return response()->json(['message' => 'Paket tidak ditemukan'], 404);

        $pkg->update(['is_active' => !$pkg->is_active]);

        return response()->json(['success' => true, 'is_active' => !$pkg->is_active]);
    }
}
