<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\HubResource;
use App\Models\EventRegistration;
use App\Models\JabatanCode;
use App\Models\MilitansiLevel;
use App\Models\MilitansiRule;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\PesantrenClaim;
use App\Models\PricingPackage;
use App\Models\PesantrenProfile;
use App\Models\Regency;
use App\Models\Region;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserRole;
use App\Support\FinanceActivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    private function assertPusat()
    {
        $user = auth()->user();
        $role = $user->activeRole();
        if (!$role || $role->nama !== 'Admin Pusat') {
            abort(403, 'Forbidden');
        }
        return PesantrenProfile::where('user_id', $user->id)->first();
    }

    private function assertPusatOrFinance()
    {
        $user = auth()->user();
        $role = $user->activeRole();
        if (!$role || !in_array($role->nama, ['Admin Pusat', 'Admin Keuangan'])) {
            abort(403, 'Forbidden');
        }
        return PesantrenProfile::where('user_id', $user->id)->first();
    }

    private function upsertUserRole(string $userId, string $role)
    {
        $roleModel = Role::findByEnum($role);
        $existing  = UserRole::where('user_id', $userId)->orderBy('created_at', 'desc')->first();
        if ($existing) {
            $existing->update(['role_id' => $roleModel?->id]);
        } else {
            UserRole::create(['id' => Str::uuid(), 'user_id' => $userId, 'role_id' => $roleModel?->id]);
        }
    }

    private function normalizeRoleName(?string $roleName): string
    {
        return match ($roleName) {
            'Admin Pusat' => 'admin_pusat',
            'Admin Regional' => 'admin_regional',
            'Admin Keuangan' => 'admin_finance',
            'Kru Pesantren' => 'crew',
            'Koordinator' => 'coordinator',
            default => 'user',
        };
    }

    private function determineActivationState(?PesantrenProfile $profile, ?Payment $payment = null, ?PesantrenClaim $claim = null): string
    {
        return FinanceActivationService::determineActivationState($profile, $payment, $claim);
    }

    private function mapHubResource(HubResource $resource): array
    {
        $downloadUrl = $resource->resource_type === 'link'
            ? $resource->external_url
            : $resource->file_url;

        return [
            'id' => $resource->id,
            'title' => $resource->title,
            'description' => $resource->description,
            'category' => $resource->category,
            'resource_type' => $resource->resource_type,
            'file_url' => $resource->file_url,
            'external_url' => $resource->external_url,
            'download_url' => $downloadUrl,
            'mime_type' => $resource->mime_type,
            'file_size' => $resource->file_size,
            'visibility_scopes' => $resource->visibility_scopes ?: ['all'],
            'is_published' => (bool) $resource->is_published,
            'sort_order' => $resource->sort_order,
            'created_at' => $resource->created_at,
            'updated_at' => $resource->updated_at,
        ];
    }

    private function issueInstitutionNip(Payment $payment): string
    {
        if ($payment->claim?->mpj_id_number) {
            return $payment->claim->mpj_id_number;
        }

        $region = Region::find($payment->claim?->region_id);
        if (!$region || !preg_match('/^\d{2}$/', $region->code)) {
            abort(422, 'Kode RR Belum Valid');
        }

        $count = PesantrenClaim::where('region_id', $payment->claim->region_id)
            ->whereNotNull('mpj_id_number')
            ->count();

        return now()->format('y') . $region->code . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    private function activatePrimaryCrew(Payment $payment, string $generatedNip): void
    {
        $profileUser = User::where('id', function ($q) use ($payment) {
            $q->select('user_id')->from('pesantren_profiles')->where('id', $payment->user_id);
        })->first();

        if (!$profileUser?->reff_id || $profileUser->reff_type !== 'crew') {
            return;
        }

        $crew = Crew::find($profileUser->reff_id);
        if (!$crew) {
            return;
        }

        $jabatanCodeId = $crew->jabatan_code_id;
        if (!$jabatanCodeId && $crew->jabatan) {
            $foundCode = JabatanCode::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($crew->jabatan) . '%'])->first();
            if ($foundCode) {
                $jabatanCodeId = $foundCode->id;
                $crew->update(['jabatan_code_id' => $jabatanCodeId]);
            }
        }

        $niam = $crew->niam;
        if (!$niam) {
            $profile = PesantrenProfile::find($payment->user_id);
            $niam = $profile
                ? FinanceActivationService::issueCrewNiam($profile, $crew)
                : $generatedNip . '01';
        }

        $crew->update([
            'status' => 'active',
            'niam'   => $niam,
        ]);
    }

    public function homeSummary(Request $request)
    {
        $this->assertPusat();

        $totalPesantren   = PesantrenProfile::where('status_account', 'active')->count();
        $totalKru         = Crew::where('status', 'active')->count();
        $totalWilayah     = Region::count();
        $pendingPayments  = Payment::whereIn('status', [
            FinanceActivationService::STATUS_PENDING,
            FinanceActivationService::STATUS_WAITING_VERIFICATION,
        ])->count();
        $verifiedPayments = Payment::where('status', 'verified')->sum('total_amount');

        $activeProfiles = PesantrenProfile::where('status_account', 'active')
            ->where('status_payment', 'paid')
            ->whereNotNull('nip')
            ->get(['id', 'profile_level']);
        $levelStats     = ['basic' => 0, 'silver' => 0, 'gold' => 0, 'platinum' => 0];
        foreach ($activeProfiles as $p) {
            if (isset($levelStats[$p->profile_level])) {
                $levelStats[$p->profile_level]++;
            }
        }

        $recentProfiles = PesantrenProfile::with('region:id,name')
            ->orderBy('created_at', 'desc')
            ->take(8)
            ->get(['id', 'nama_pesantren', 'nip', 'status_account', 'status_payment', 'profile_level', 'created_at', 'region_id']);

        $latestPayments = Payment::whereIn('user_id', $recentProfiles->pluck('id'))
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

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
                'status_payment' => $p->status_payment,
                'activation_state' => $this->determineActivationState($p, $latestPayments->get($p->id)),
                'profile_level'  => $p->profile_level,
                'created_at'     => $p->created_at,
            ]),
        ]);
    }

    public function clearingHousePending(Request $request)
    {
        $this->assertPusat();

        $profiles = PesantrenProfile::with('regency:id,name')
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

        $profile = PesantrenProfile::with('region:id,code')->find($id);
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

        PesantrenProfile::where('id', $id)->update(['status_account' => 'rejected']);

        return response()->json(['success' => true]);
    }

    public function pendingProfiles(Request $request)
    {
        $this->assertPusat();

        $profiles = PesantrenProfile::with('region:id,name')
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

        $admins = Crew::with(['profile' => fn($q) => $q->with(['region:id,name', 'user.userRoles' => fn($q2) => $q2->with('roleDetail')->orderBy('created_at', 'desc')])])
            ->whereHas('profile.user.userRoles.roleDetail', fn($q) => $q->whereIn('nama', ['Admin Pusat', 'Admin Regional', 'Admin Keuangan']))
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
                'role'        => $this->normalizeRoleName($c->profile?->user?->userRoles->first()?->roleDetail?->nama),
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
                'current_role' => $c->profile?->user?->activeRole()?->nama,
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
            $profile = PesantrenProfile::find($data['profileId']);
            if (!$profile) return;

            if ($data['role'] === 'admin_regional' && !empty($data['regionId'])) {
                $oldAdmins = PesantrenProfile::where('region_id', $data['regionId'])
                    ->where('id', '!=', $data['profileId'])
                    ->whereHas('user.userRoles.roleDetail', fn($q) => $q->where('nama', 'Admin Regional'))
                    ->get();
                foreach ($oldAdmins as $old) {
                    $this->upsertUserRole($old->user_id, 'user');
                }
            }

            $profile->update([
                'region_id' => $data['role'] === 'admin_regional' ? ($data['regionId'] ?? null) : $profile->region_id,
            ]);

            $this->upsertUserRole($profile->user_id, $data['role']);
        });

        return response()->json(['success' => true]);
    }

    public function adminSettingsRemove(Request $request, string $userId)
    {
        $this->assertPusat();

        DB::transaction(function () use ($userId) {
            $profile = PesantrenProfile::find($userId);
            if ($profile) $this->upsertUserRole($profile->user_id, 'user');
        });

        return response()->json(['success' => true]);
    }

    public function masterData(Request $request)
    {
        $this->assertPusat();

        $operationalRoleIds = Role::whereIn('nama', [
            'Pengguna Pesantren',
            'Kru Pesantren',
            'Koordinator',
        ])->pluck('id');

        $operationalUserIds = UserRole::whereIn('role_id', $operationalRoleIds)
            ->pluck('user_id');

        $profiles = PesantrenProfile::with(['region:id,name', 'regency:id,name'])
            ->whereIn('user_id', $operationalUserIds)
            ->where(function ($query) {
                $query->whereNotNull('nama_pesantren')
                    ->orWhereNotNull('nama_media')
                    ->orWhereNotNull('nip')
                    ->orWhereHas('crews');
            })
            ->orderByRaw('COALESCE(nama_pesantren, nama_media, id)')
            ->get();

        $crews = Crew::with(['profile' => fn($q) => $q->with('region:id,name')])
            ->whereIn('profile_id', $profiles->pluck('id'))
            ->orderBy('nama')
            ->get();

        $profileIds = $profiles->pluck('id');
        $latestPayments = Payment::whereIn('user_id', $profileIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

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
                'status_payment'  => $p->status_payment,
                'activation_state'=> $this->determineActivationState($p, $latestPayments->get($p->id)),
                'profile_level'   => $p->profile_level,
                'alamat_singkat'  => $p->alamat_singkat,
                'nama_pengasuh'   => $p->nama_pengasuh,
                'no_wa_pendaftar' => $p->no_wa_pendaftar,
            ]),
            'crews' => $crews->map(fn($c) => [
                'id'            => $c->id,
                'nama'          => $c->nama,
                'niam'          => $c->niam,
                'status'        => $c->status,
                'jabatan'       => $c->jabatan,
                'xp_level'      => $c->xp_level,
                'profile_id'    => $c->profile_id,
                'pesantren_name'=> $c->profile?->nama_pesantren,
                'region_id'     => $c->profile?->region_id,
                'region_name'   => $c->profile?->region?->name,
                'status_account'=> $c->profile?->status_account,
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

        PesantrenProfile::where('id', $id)->update(array_filter([
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

        PesantrenProfile::where('id', $id)->update(array_filter([
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
            'status'  => 'nullable|in:pending,active,inactive,alumni',
        ]);

        Crew::where('id', $id)->update(array_filter([
            'nama'    => $data['nama'] ?? null,
            'jabatan' => $data['jabatan'] ?? null,
            'status'  => $data['status'] ?? null,
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
                    $profile = PesantrenProfile::where('nip', $nip)->first();
                    if (!$profile) { $results['errors'][] = "Baris {$num}: NIP {$nip} tidak ditemukan"; $results['skipped']++; continue; }
                    $profile->update(array_filter([
                        'nama_pesantren' => $row['Nama Pesantren'] ?? $row['nama_pesantren'] ?? null,
                        'nama_pengasuh'  => $row['Nama Pengasuh'] ?? $row['nama_pengasuh'] ?? null,
                        'alamat_singkat' => $row['Alamat Singkat'] ?? $row['alamat_singkat'] ?? null,
                    ], fn($v) => $v !== null));
                } elseif ($type === 'media') {
                    $nip = trim($row['NIP'] ?? $row['nip'] ?? '');
                    if (!$nip) { $results['errors'][] = "Baris {$num}: NIP kosong"; $results['skipped']++; continue; }
                    $profile = PesantrenProfile::where('nip', $nip)->first();
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
            ->whereHas('profile.user.userRoles.roleDetail', fn($q) => $q->where('nama', 'Admin Pusat'))
            ->orderBy('created_at', 'desc')
            ->get();

        $available = Crew::with(['profile' => fn($q) => $q->with(['region:id,name', 'user:id,email'])])
            ->whereDoesntHave('profile.user.userRoles.roleDetail', fn($q) => $q->where('nama', 'Admin Pusat'))
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

        $profile = PesantrenProfile::find($crew->profile_id);
        if (!$profile) return response()->json(['message' => 'Profil tidak ditemukan'], 404);

        $this->upsertUserRole($profile->user_id, 'admin_pusat');

        return response()->json(['success' => true]);
    }

    public function removePusatAssistant(Request $request, string $crewId)
    {
        $this->assertPusat();

        $crew = Crew::find($crewId);
        if (!$crew) return response()->json(['message' => 'Kru tidak ditemukan'], 404);

        $profile = PesantrenProfile::find($crew->profile_id);
        if (!$profile) return response()->json(['message' => 'Profil tidak ditemukan'], 404);

        $this->upsertUserRole($profile->user_id, 'user');

        return response()->json(['success' => true]);
    }

    public function regionalManagementData(Request $request)
    {
        $this->assertPusat();

        $regions = Region::orderBy('name')->get(['id', 'name', 'code']);
        $cityRegionMap = DB::table('region_regencies')
            ->select(['regency_id', 'region_id'])
            ->get()
            ->keyBy('regency_id');
        $cities  = Regency::orderBy('name')->get(['id', 'name', 'province_id']);
        $users   = PesantrenProfile::with(['region:id,name', 'user.userRoles.roleDetail'])->orderBy('nama_pesantren')
            ->get(['id', 'user_id', 'nama_pesantren', 'nama_pengasuh', 'region_id', 'status_account']);

        return response()->json([
            'regions' => $regions->map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'code' => $r->code]),
            'cities'  => $cities->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'province_id' => $c->province_id,
                'region_id' => $cityRegionMap->get($c->id)?->region_id,
            ]),
            'users'   => $users->map(fn($u) => [
                'id'             => $u->id,
                'user_id'        => $u->user_id,
                'nama_pesantren' => $u->nama_pesantren,
                'nama_pengasuh'  => $u->nama_pengasuh,
                'email'          => $u->user?->email,
                'display_name'   => $u->nama_pesantren
                    ?? $u->nama_pengasuh
                    ?? $u->user?->email
                    ?? 'Belum diisi',
                'region_id'      => $u->region_id,
                'region_name'    => $u->region?->name,
                'role'           => $this->normalizeRoleName($u->user?->activeRole()?->nama),
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
        $this->assertPusat();

        $data = $request->validate([
            'regionId' => 'required|uuid',
            'name' => 'nullable|string',
            'regencyId' => 'nullable|string|size:4',
        ]);

        $region = Region::find($data['regionId']);
        if (!$region) {
            return response()->json(['message' => 'Regional tidak ditemukan'], 404);
        }

        $regency = !empty($data['regencyId'])
            ? Regency::find($data['regencyId'])
            : Regency::whereRaw('UPPER(name) = ?', [strtoupper(trim((string) ($data['name'] ?? '')))])
                ->first();

        if (!$regency) {
            return response()->json(['message' => 'Kabupaten/kota tidak ditemukan di master wilayah'], 404);
        }

        DB::table('region_regencies')->updateOrInsert(
            ['regency_id' => $regency->id],
            ['region_id' => $region->id]
        );

        return response()->json([
            'city' => [
                'id' => $regency->id,
                'name' => $regency->name,
                'province_id' => $regency->province_id,
                'region_id' => $region->id,
            ],
        ]);
    }

    public function deleteCity(Request $request, string $id)
    {
        $this->assertPusat();

        $regency = Regency::find($id);
        if (!$regency) {
            return response()->json(['message' => 'Kabupaten/kota tidak ditemukan'], 404);
        }

        $deleted = DB::table('region_regencies')
            ->where('regency_id', $id)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Mapping kota tidak ditemukan'], 404);
        }

        return response()->json(['success' => true]);
    }

    public function assignRegionalAdmin(Request $request)
    {
        $this->assertPusat();

        $data = $request->validate([
            'profileId' => 'nullable|uuid',
            'userId'   => 'nullable|uuid',
            'regionId' => 'required|uuid',
        ]);

        $region = Region::find($data['regionId']);
        if (!$region) return response()->json(['message' => 'Regional tidak ditemukan'], 404);

        $profile = !empty($data['profileId'])
            ? PesantrenProfile::find($data['profileId'])
            : PesantrenProfile::where('user_id', $data['userId'])->first();
        if (!$profile) return response()->json(['message' => 'Profil tidak ditemukan'], 404);

        DB::transaction(function () use ($profile, $data) {
            $profile->update(['region_id' => $data['regionId']]);
            $this->upsertUserRole($profile->user_id, 'admin_regional');
        });

        return response()->json(['success' => true, 'region' => ['id' => $region->id, 'name' => $region->name]]);
    }

    public function usersManagement(Request $request)
    {
        $this->assertPusat();

        $users = PesantrenProfile::with(['region:id,name', 'user.userRoles.roleDetail'])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'user_id', 'nama_pesantren', 'nama_pengasuh', 'status_account', 'status_payment', 'region_id']);

        return response()->json([
            'users' => $users->map(fn($u) => [
                'id'              => $u->id,
                'nama_pesantren'  => $u->nama_pesantren,
                'nama_pengasuh'   => $u->nama_pengasuh,
                'email'           => $u->user?->email,
                'display_name'    => $u->nama_pesantren
                    ?? $u->nama_pengasuh
                    ?? $u->user?->email
                    ?? 'Belum diisi',
                'role'            => $u->user?->activeRole()?->nama,
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

        $profile = PesantrenProfile::find($id);
        if (!$profile) return response()->json(['message' => 'Profil tidak ditemukan'], 404);

        DB::transaction(function () use ($profile, $data) {
            $profile->update([
                'status_account' => $data['statusAccount'],
                'status_payment' => $data['statusPayment'],
            ]);
            $this->upsertUserRole($profile->user_id, $data['role']);
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

        $totalUsers    = PesantrenProfile::count();
        $totalPesantren= PesantrenProfile::whereNotNull('nama_pesantren')->count();
        $paidUsers     = PesantrenProfile::where('status_payment', 'paid')->count();
        $revenue       = $paidUsers * 350000;

        return response()->json([
            'total_users'        => $totalUsers,
            'total_pesantren'    => $totalPesantren,
            'paid_users'         => $paidUsers,
            'estimated_revenue'  => $revenue,
        ]);
    }

    public function hubResources(Request $request)
    {
        $user = auth()->user();
        $roleName = $user?->activeRole()?->nama;
        $scope = $this->normalizeRoleName($roleName);

        $resources = HubResource::query()
            ->where('is_published', true)
            ->where(function ($query) use ($scope) {
                $query->whereNull('visibility_scopes')
                    ->orWhereJsonContains('visibility_scopes', 'all')
                    ->orWhereJsonContains('visibility_scopes', $scope);
            })
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        $categories = $resources
            ->groupBy('category')
            ->map(fn($items, $category) => [
                'key' => $category,
                'label' => Str::headline(str_replace('_', ' ', $category)),
                'count' => $items->count(),
            ])
            ->values();

        return response()->json([
            'resources' => $resources->map(fn($resource) => $this->mapHubResource($resource))->values(),
            'summary' => [
                'total' => $resources->count(),
                'categories' => $categories,
            ],
        ]);
    }

    public function adminHubResources(Request $request)
    {
        $this->assertPusat();

        $resources = HubResource::query()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'resources' => $resources->map(fn($resource) => $this->mapHubResource($resource))->values(),
        ]);
    }

    public function storeHubResource(Request $request)
    {
        $this->assertPusat();

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:100',
            'resource_type' => 'required|in:file,link',
            'visibility_scopes' => 'required|array|min:1',
            'visibility_scopes.*' => 'in:all,user,crew,admin_pusat,admin_regional,admin_finance,coordinator',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'external_url' => 'nullable|url|required_if:resource_type,link',
            'file' => 'nullable|file|max:10240|required_if:resource_type,file',
        ]);

        $actor = auth()->user();
        $resourceId = (string) Str::uuid();
        $fileUrl = null;
        $mimeType = null;
        $fileSize = null;

        if (($data['resource_type'] ?? 'file') === 'file') {
            $file = $request->file('file');
            $path = 'hub-resources/' . now()->format('Y/m');
            $filename = $resourceId . '.' . $file->getClientOriginalExtension();
            $file->storeAs($path, $filename, 'public');
            $fileUrl = '/uploads/' . $path . '/' . $filename;
            $mimeType = $file->getClientMimeType();
            $fileSize = $file->getSize();
        }

        $resource = HubResource::create([
            'id' => $resourceId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => Str::slug($data['category'], '_'),
            'resource_type' => $data['resource_type'],
            'file_url' => $fileUrl,
            'external_url' => $data['resource_type'] === 'link' ? ($data['external_url'] ?? null) : null,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'visibility_scopes' => array_values(array_unique($data['visibility_scopes'])),
            'is_published' => true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);

        return response()->json([
            'success' => true,
            'resource' => $this->mapHubResource($resource),
        ], 201);
    }

    public function militansiSummary(Request $request)
    {
        $this->assertPusat();

        $levels = MilitansiLevel::orderBy('min_xp')->get(['id', 'name', 'min_xp', 'color']);
        $rules = MilitansiRule::orderBy('sort_order')->orderBy('xp_value')->get(['id', 'action_key', 'label', 'xp_value', 'limit_type', 'is_active']);

        $crews = Crew::with('profile:id,region_id,nama_pesantren')
            ->whereNotNull('xp_level')
            ->orderByDesc('xp_level')
            ->orderBy('nama')
            ->get(['id', 'profile_id', 'nama', 'jabatan', 'status', 'niam', 'xp_level']);

        $regions = Region::get(['id', 'name'])->keyBy('id');

        return response()->json([
            'summary' => [
                'total_crews' => $crews->count(),
                'active_crews' => $crews->where('status', 'active')->count(),
                'average_xp' => (int) round($crews->avg('xp_level') ?? 0),
                'top_xp' => (int) ($crews->max('xp_level') ?? 0),
            ],
            'levels' => $levels,
            'rules' => $rules,
            'leaderboard' => $crews->take(50)->values()->map(function ($crew, $index) use ($levels, $regions) {
                $level = $levels->filter(fn($item) => $crew->xp_level >= $item->min_xp)->sortByDesc('min_xp')->first();
                return [
                    'rank' => $index + 1,
                    'name' => $crew->nama,
                    'jabatan' => $crew->jabatan,
                    'niam' => $crew->niam,
                    'xp_level' => $crew->xp_level,
                    'status' => $crew->status,
                    'pesantren_name' => $crew->profile?->nama_pesantren,
                    'region_name' => $regions->get($crew->profile?->region_id)?->name ?? '-',
                    'level' => $level?->name ?? 'Muhibbin',
                    'level_color' => $level?->color ?? '#94a3b8',
                ];
            }),
        ]);
    }

    public function myMilitansiOverview(Request $request)
    {
        $user = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user?->id)->first();
        $crew = Crew::where('profile_id', $profile?->id)->orderByDesc('xp_level')->first();
        $levels = MilitansiLevel::orderBy('min_xp')->get(['id', 'name', 'min_xp', 'color']);
        $rules = MilitansiRule::where('is_active', true)->orderBy('sort_order')->get(['id', 'action_key', 'label', 'xp_value', 'limit_type']);

        $leaderboardCrews = Crew::with('profile:id,region_id,nama_pesantren')
            ->orderByDesc('xp_level')
            ->orderBy('nama')
            ->take(10)
            ->get(['id', 'profile_id', 'nama', 'jabatan', 'status', 'niam', 'xp_level']);

        $myRank = $crew
            ? Crew::where('xp_level', '>', $crew->xp_level)->count() + 1
            : null;

        $currentLevel = $crew
            ? $levels->filter(fn($item) => $crew->xp_level >= $item->min_xp)->sortByDesc('min_xp')->first()
            : $levels->first();

        return response()->json([
            'me' => $crew ? [
                'id' => $crew->id,
                'name' => $crew->nama,
                'jabatan' => $crew->jabatan,
                'niam' => $crew->niam,
                'status' => $crew->status,
                'xp_level' => $crew->xp_level,
                'rank' => $myRank,
                'level' => $currentLevel?->name ?? 'Muhibbin',
                'level_color' => $currentLevel?->color ?? '#94a3b8',
                'pesantren_name' => $profile?->nama_pesantren,
            ] : null,
            'levels' => $levels,
            'rules' => $rules,
            'leaderboard' => $leaderboardCrews->values()->map(function ($item, $index) use ($levels) {
                $level = $levels->filter(fn($levelItem) => $item->xp_level >= $levelItem->min_xp)->sortByDesc('min_xp')->first();
                return [
                    'rank' => $index + 1,
                    'name' => $item->nama,
                    'jabatan' => $item->jabatan,
                    'xp_level' => $item->xp_level,
                    'status' => $item->status,
                    'pesantren_name' => $item->profile?->nama_pesantren,
                    'level' => $level?->name ?? 'Muhibbin',
                    'level_color' => $level?->color ?? '#94a3b8',
                ];
            }),
        ]);
    }

    public function deleteHubResource(Request $request, string $id)
    {
        $this->assertPusat();

        $resource = HubResource::find($id);
        if (!$resource) {
            return response()->json(['message' => 'Resource tidak ditemukan'], 404);
        }

        if ($resource->resource_type === 'file' && $resource->file_url && str_starts_with($resource->file_url, '/uploads/')) {
            $relativePath = ltrim(str_replace('/uploads/', '', $resource->file_url), '/');
            Storage::disk('public')->delete($relativePath);
        }

        $resource->delete();

        return response()->json(['success' => true]);
    }

    public function auditLogs(Request $request)
    {
        $this->assertPusat();

        $limit = max(1, min((int) $request->input('limit', 50), 200));

        $logs = PaymentLog::with([
            'payment.claim:id,pesantren_name,nama_pengelola,jenis_pengajuan,region_id',
            'payment.user:id,nama_pesantren,nama_pengasuh,region_id',
        ])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $actorIds = $logs->pluck('actor_user_id')->filter()->unique()->values();
        $actors = User::with(['userRoles.roleDetail'])
            ->whereIn('id', $actorIds)
            ->get()
            ->keyBy('id');

        $crewIds = $logs
            ->map(fn($log) => $log->payment && ($log->payment->reference_type === FinanceActivationService::REFERENCE_CREW)
                ? $log->payment->reference_id
                : null)
            ->filter()
            ->unique()
            ->values();

        $crews = Crew::whereIn('id', $crewIds)->get(['id', 'nama'])->keyBy('id');

        return response()->json([
            'logs' => $logs->map(function ($log) use ($actors, $crews) {
                $payment = $log->payment;
                $actor = $log->actor_user_id ? $actors->get($log->actor_user_id) : null;
                $roleName = $actor?->userRoles?->sortByDesc('created_at')->first()?->roleDetail?->nama;
                $paymentType = $payment?->payment_type ?? FinanceActivationService::TYPE_INSTITUTION_ACTIVATION;
                $targetType = $paymentType === FinanceActivationService::TYPE_CREW_ACTIVATION ? 'crew' : 'institution';
                $targetName = $targetType === 'crew'
                    ? ($crews->get($payment?->reference_id)?->nama ?? 'Kru')
                    : ($payment?->claim?->pesantren_name ?? $payment?->user?->nama_pesantren ?? 'Pesantren');

                return [
                    'id' => $log->id,
                    'timestamp' => $log->created_at,
                    'actor_id' => $log->actor_user_id,
                    'actor_name' => $actor?->email ?? 'System',
                    'actor_role' => $this->normalizeRoleName($roleName),
                    'actor_role_label' => $roleName ?? 'System',
                    'action' => $log->action,
                    'target_type' => $targetType,
                    'target_id' => $payment?->reference_id ?? $payment?->user_id ?? $payment?->pesantren_claim_id ?? $log->payment_id,
                    'target_name' => $targetName,
                    'payment_id' => $log->payment_id,
                    'payment_type' => $paymentType,
                    'invoice_number' => $payment?->invoice_number,
                    'from_status' => $log->from_status,
                    'to_status' => $log->to_status,
                    'details' => $log->notes,
                    'region_id' => $payment?->claim?->region_id ?? $payment?->user?->region_id,
                    'meta' => $log->meta,
                ];
            })->values(),
        ]);
    }

    public function regionDetail(Request $request, string $id)
    {
        $this->assertPusat();

        $region = Region::with(['regencies' => fn($q) => $q->select('id', 'name')])->find($id);
        if (!$region) return response()->json(['message' => 'Regional tidak ditemukan'], 404);

        $memberCount  = PesantrenProfile::where('region_id', $id)
            ->whereHas('user.userRoles.roleDetail', fn($q) => $q->where('nama', 'Pengguna Pesantren'))
            ->count();
        $pesantrenCount = PesantrenProfile::where('region_id', $id)->whereNotNull('nama_pesantren')->count();
        $adminCount   = PesantrenProfile::where('region_id', $id)
            ->whereHas('user.userRoles.roleDetail', fn($q) => $q->where('nama', 'Admin Regional'))
            ->count();
        $recentProfiles = PesantrenProfile::where('region_id', $id)
            ->whereNotNull('nama_pesantren')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get(['id', 'nama_pesantren', 'nama_pengasuh', 'status_account', 'created_at']);

        return response()->json([
            'region' => [
                'id'     => $region->id,
                'name'   => $region->name,
                'code'   => $region->code,
                'cities' => $region->regencies->map(fn($c) => ['name' => $c->name]),
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

        $count = PesantrenProfile::whereIn('id', $lateClaims)->where('status_payment', 'unpaid')->count();

        return response()->json(['count' => $count]);
    }

    public function claims(Request $request)
    {
        $this->assertPusat();

        $claims = PesantrenClaim::with(['region:id,name', 'profile:id,status_account,status_payment,nip'])
            // ->whereIn('status', ['regional_approved', 'pusat_approved'])
            ->orderBy('created_at', 'desc')
            ->get();

        $claimIds = $claims->pluck('id');
        $latestPayments = Payment::whereIn('pesantren_claim_id', $claimIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('pesantren_claim_id')
            ->keyBy('pesantren_claim_id');

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
                'profile_status_account' => $c->profile?->status_account,
                'profile_status_payment' => $c->profile?->status_payment,
                'activation_state' => $this->determineActivationState($c->profile, $latestPayments->get($c->id), $c),
            ]),
        ]);
    }

    public function payments(Request $request)
    {
        $this->assertPusatOrFinance();

        $payments = Payment::with([
            'claim:id,pesantren_name,nama_pengelola,jenis_pengajuan,region_id,mpj_id_number',
            'user:id,no_wa_pendaftar,status_account,status_payment,nip,nama_pesantren,nama_pengasuh,region_id',
            'paymentLogs:id,payment_id',
        ])
            ->when($request->filled('payment_type'), fn($query) => $query->where('payment_type', $request->input('payment_type')))
            ->when($request->filled('payment_status'), fn($query) => $query->where('status', $request->input('payment_status')))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'payments' => $payments->map(fn($p) => [
                'id'                  => $p->id,
                'user_id'             => $p->user_id,
                'pesantren_claim_id'  => $p->pesantren_claim_id,
                'payment_type'        => $p->payment_type ?? FinanceActivationService::TYPE_INSTITUTION_ACTIVATION,
                'reference_type'      => $p->reference_type ?? FinanceActivationService::REFERENCE_PROFILE,
                'reference_id'        => $p->reference_id ?? $p->user_id,
                'invoice_number'      => $p->invoice_number,
                'base_amount'         => $p->base_amount,
                'unique_code'         => $p->unique_code,
                'total_amount'        => $p->total_amount,
                'proof_file_url'      => $p->proof_file_url,
                'status'              => FinanceActivationService::normalizePaymentStatus($p->status),
                'created_at'          => $p->created_at,
                'rejection_reason'    => $p->rejection_reason,
                'verified_by'         => $p->verified_by,
                'verified_at'         => $p->verified_at,
                'submitted_at'        => $p->submitted_at,
                'activation_state'    => $this->determineActivationState($p->user, $p, $p->claim),
                'pesantren_claims'    => [
                    'pesantren_name'  => $p->claim?->pesantren_name ?? $p->user?->nama_pesantren,
                    'nama_pengelola'  => $p->claim?->nama_pengelola ?? $p->user?->nama_pengasuh,
                    'jenis_pengajuan' => $p->claim?->jenis_pengajuan ?? ($p->payment_type ?? FinanceActivationService::TYPE_INSTITUTION_ACTIVATION),
                    'region_id'       => $p->claim?->region_id ?? $p->user?->region_id,
                    'mpj_id_number'   => $p->claim?->mpj_id_number ?? $p->user?->nip,
                ],
                'profiles' => [
                    'no_wa_pendaftar' => $p->user?->no_wa_pendaftar,
                    'status_account'  => $p->user?->status_account,
                    'status_payment'  => $p->user?->status_payment,
                    'nip'             => $p->user?->nip,
                ],
                'payment_logs_count'  => $p->paymentLogs->count(),
            ]),
        ]);
    }

    public function rejectPayment(Request $request, string $id)
    {
        $this->assertPusatOrFinance();

        $data = $request->validate(['reason' => 'required|string|min:1']);
        $actor = auth()->user();
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $fromStatus = FinanceActivationService::normalizePaymentStatus($payment->status);

        $payment->update([
            'status'           => FinanceActivationService::STATUS_REJECTED,
            'rejection_reason' => $data['reason'],
            'rejected_by'      => $actor->id,
            'rejected_at'      => now(),
        ]);

        if (($payment->payment_type ?? null) === FinanceActivationService::TYPE_EVENT_REGISTRATION) {
            EventRegistration::where('id', $payment->reference_id)->update([
                'ticket_status' => 'rejected',
            ]);
        }

        FinanceActivationService::logPaymentStatusChange(
            $payment->fresh(),
            $actor->id,
            'rejected',
            $fromStatus,
            FinanceActivationService::STATUS_REJECTED,
            $data['reason']
        );

        return response()->json(['success' => true]);
    }

    public function approvePayment(Request $request, string $id)
    {
        $user = auth()->user();
        $this->assertPusatOrFinance();

        $payment = Payment::with(['claim', 'user:id,no_wa_pendaftar,nama_pesantren,status_account,status_payment,nip,region_id'])->find($id);
        if (!$payment) return response()->json(['message' => 'Payment not found'], 404);

        if (($payment->payment_type ?? FinanceActivationService::TYPE_INSTITUTION_ACTIVATION) === FinanceActivationService::TYPE_CREW_ACTIVATION) {
            $crew = FinanceActivationService::approveCrewActivation($payment, $user);

            return response()->json([
                'success' => true,
                'niam' => $crew->niam,
                'pesantrenName' => $payment->user?->nama_pesantren,
                'activationState' => 'active',
                'paymentType' => FinanceActivationService::TYPE_CREW_ACTIVATION,
            ]);
        }

        if (($payment->payment_type ?? null) === FinanceActivationService::TYPE_EVENT_REGISTRATION) {
            $fromStatus = FinanceActivationService::normalizePaymentStatus($payment->status);

            $payment->update([
                'status' => FinanceActivationService::STATUS_VERIFIED,
                'verified_by' => $user->id,
                'verified_at' => now(),
                'rejection_reason' => null,
            ]);

            EventRegistration::where('id', $payment->reference_id)->update([
                'ticket_status' => 'paid',
            ]);

            FinanceActivationService::logPaymentStatusChange(
                $payment->fresh(),
                $user->id,
                'approved',
                $fromStatus,
                FinanceActivationService::STATUS_VERIFIED,
                'Pembayaran registrasi event diverifikasi.'
            );

            return response()->json([
                'success' => true,
                'paymentType' => FinanceActivationService::TYPE_EVENT_REGISTRATION,
                'activationState' => 'paid',
            ]);
        }

        if (!$payment->claim?->region_id) return response()->json(['message' => 'Region ID tidak ditemukan'], 422);

        $nip = DB::transaction(function () use ($payment, $user) {
            $generatedNip = $this->issueInstitutionNip($payment);
            $fromStatus = FinanceActivationService::normalizePaymentStatus($payment->status);

            $payment->update([
                'status'           => FinanceActivationService::STATUS_VERIFIED,
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

            PesantrenProfile::where('id', $payment->user_id)->update([
                'status_account' => 'active',
                'status_payment' => 'paid',
                'nip'            => $generatedNip,
                'nama_pesantren' => $payment->claim->pesantren_name,
            ]);

            $this->activatePrimaryCrew($payment, $generatedNip);

            FinanceActivationService::logPaymentStatusChange(
                $payment->fresh(),
                $user->id,
                'approved',
                $fromStatus,
                FinanceActivationService::STATUS_VERIFIED,
                'Pembayaran aktivasi institusi diverifikasi.'
            );

            return $generatedNip;
        });

        return response()->json([
            'success'       => true,
            'nip'           => $nip,
            'phoneNumber'   => $payment->user?->no_wa_pendaftar,
            'pesantrenName' => $payment->claim->pesantren_name,
            'activationState' => 'active',
        ]);
    }

    public function paymentLogs(Request $request, string $id)
    {
        $this->assertPusatOrFinance();

        $payment = Payment::with('paymentLogs')->find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        return response()->json([
            'logs' => $payment->paymentLogs
                ->sortByDesc('created_at')
                ->values()
                ->map(fn($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'from_status' => $log->from_status,
                    'to_status' => $log->to_status,
                    'notes' => $log->notes,
                    'meta' => $log->meta,
                    'created_at' => $log->created_at,
                ]),
        ]);
    }

    public function cancelPayment(Request $request, string $id)
    {
        $this->assertPusatOrFinance();

        $payment = Payment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $actor = auth()->user();
        $fromStatus = FinanceActivationService::normalizePaymentStatus($payment->status);

        $payment->update([
            'status' => FinanceActivationService::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        FinanceActivationService::logPaymentStatusChange(
            $payment->fresh(),
            $actor?->id,
            'cancelled',
            $fromStatus,
            FinanceActivationService::STATUS_CANCELLED,
            'Invoice dibatalkan oleh admin.'
        );

        return response()->json(['success' => true]);
    }

    public function expirePayment(Request $request, string $id)
    {
        $this->assertPusatOrFinance();

        $payment = Payment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $actor = auth()->user();
        $fromStatus = FinanceActivationService::normalizePaymentStatus($payment->status);

        $payment->update([
            'status' => FinanceActivationService::STATUS_EXPIRED,
            'expired_at' => now(),
        ]);

        FinanceActivationService::logPaymentStatusChange(
            $payment->fresh(),
            $actor?->id,
            'expired',
            $fromStatus,
            FinanceActivationService::STATUS_EXPIRED,
            'Invoice kedaluwarsa.'
        );

        return response()->json(['success' => true]);
    }

    public function levelingProfiles(Request $request)
    {
        $this->assertPusat();

        $profiles = PesantrenProfile::with('region:id,name')
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

        PesantrenProfile::where('id', $id)->update(['profile_level' => 'platinum']);

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

    public function financeStats(Request $request)
    {
        $this->assertPusatOrFinance();

        return response()->json([
            'total_income'         => (int) Payment::where('status', 'verified')->sum('total_amount'),
            'pending_verification' => Payment::whereIn('status', [
                FinanceActivationService::STATUS_PENDING,
                FinanceActivationService::STATUS_WAITING_VERIFICATION,
            ])->count(),
            'approved_today'       => Payment::where('status', 'verified')->whereDate('verified_at', today())->count(),
            'rejected_today'       => Payment::where('status', 'rejected')->whereDate('updated_at', today())->count(),
        ]);
    }
}
