<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\AuditLog;
use App\Models\FollowUpLog;
use App\Support\FinanceActivationService;
use App\Models\HubResource;
use App\Models\MilitansiLevel;
use App\Models\Payment;
use App\Models\PesantrenClaim;
use App\Models\PesantrenDirectory;
use App\Models\PricingPackage;
use App\Models\PesantrenProfile;
use App\Models\RegionalReport;
use App\Models\Region;
use App\Support\AccessControl;
use App\Support\AuditLogger;
use App\Support\BerkasDokumen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RegionalController extends Controller
{
    private const REGIONAL_ACCESS_KEYS = [
        'validasi-pendaftar',
        'data-master',
        'laporan',
        'late-payment',
        'download-center',
        'admin-regional-manajemen-event',
        'hub',
    ];

    private function assertRegional()
    {
        $user    = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user->id)->first();

        if (!$user || !AccessControl::hasAny($user, self::REGIONAL_ACCESS_KEYS)) {
            abort(403, 'Forbidden');
        }

        // Admin Regional yang berhak tetapi belum dipetakan ke wilayah mana pun
        // sebelumnya ikut dibalas 403 polos, sehingga dashboard menyimpulkan
        // "gagal memuat" lalu menampilkan seluruh statistik sebagai 0. Padahal
        // yang kurang hanya penugasan wilayah, dan itu harus dikerjakan Admin
        // Pusat, bukan diperbaiki sendiri oleh pemilik akun.
        if (!$profile?->region_id) {
            abort(response()->json([
                'message' => 'Akun ini belum ditugaskan ke wilayah mana pun. Hubungi Admin Pusat untuk menetapkan regionalnya.',
                'reason'  => 'region_not_assigned',
            ], 409));
        }

        return $profile->region_id;
    }

    /**
     * Membatasi daftar yang bisa membesar tanpa mengubah bentuk response.
     *
     * Bentuk key lama tetap array biasa supaya frontend yang masih memfilter di
     * sisi klien tidak rusak; metadata halaman dikirim terpisah di key
     * 'pagination'. Ukuran halaman dibaca per-daftar (mis. profiles_per_page)
     * lalu jatuh ke parameter umum per_page, sebab satu response bisa memuat
     * lebih dari satu daftar yang perlu dipaginasi sendiri-sendiri.
     */
    private function paginateList($query, Request $request, string $key, array $columns = ['*'], int $defaultPerPage = 200, int $maxPerPage = 1000): array
    {
        $perPage = (int) ($request->query($key . '_per_page') ?? $request->query('per_page') ?? $defaultPerPage);
        // Dibatasi supaya per_page=999999 tidak mengembalikan lagi payload tanpa batas.
        $perPage = max(1, min($perPage, $maxPerPage));

        $page = max(1, (int) ($request->query($key . '_page') ?? $request->query('page') ?? 1));

        $paginator = $query->paginate($perPage, $columns, $key . '_page', $page);

        return [
            $paginator->getCollection(),
            [
                'page'      => $paginator->currentPage(),
                'per_page'  => $paginator->perPage(),
                'total'     => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from'      => $paginator->firstItem(),
                'to'        => $paginator->lastItem(),
                'has_more'  => $paginator->hasMorePages(),
            ],
        ];
    }

    public function masterData(Request $request)
    {
        $regionId = $this->assertRegional();

        [$profiles, $profilesMeta] = $this->paginateList(
            PesantrenProfile::where('region_id', $regionId)->orderBy('nama_pesantren'),
            $request,
            'profiles',
            ['id', 'nama_pesantren', 'nama_pengasuh', 'status_account', 'status_payment', 'profile_level', 'no_wa_pendaftar', 'nip']
        );

        [$crews, $crewsMeta] = $this->paginateList(
            Crew::with('profile:id,nama_pesantren,region_id')
                ->whereHas('profile', fn($q) => $q->where('region_id', $regionId))
                ->orderBy('nama'),
            $request,
            'crews',
            ['id', 'profile_id', 'nama', 'jabatan', 'niam', 'status', 'xp_level']
        );

        return response()->json([
            'pagination' => [
                'profiles' => $profilesMeta,
                'crews'    => $crewsMeta,
            ],
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
                'status'        => $c->status,
                'xp_level'      => $c->xp_level,
                'pesantren_name'=> $c->profile?->nama_pesantren,
            ]),
        ]);
    }

    public function pendingClaims(Request $request)
    {
        $regionId = $this->assertRegional();

        [$claims, $claimsMeta] = $this->paginateList(
            PesantrenClaim::with('profile')
                ->where('region_id', $regionId)
                ->when(
                    $request->query('status', 'pending') !== 'all',
                    fn($query) => $query->where('status', $request->query('status', 'pending'))
                )
                ->orderBy('created_at', 'desc'),
            $request,
            'claims'
        );

        // Nomor WA pendaftar dulu hanya tersimpan di kru PIC yang dibuat saat
        // registrasi, bukan di profil, sehingga kolom profil kosong untuk hampir
        // semua pendaftar lama. pesantren_claims.user_id berisi id profil.
        $picPhones = Crew::whereIn('profile_id', $claims->pluck('user_id')->filter()->values())
            ->whereNotNull('no_wa')
            ->where('no_wa', '!=', '')
            ->orderByDesc('is_pic')
            ->orderBy('created_at')
            ->get(['profile_id', 'no_wa'])
            ->groupBy('profile_id')
            ->map(fn($group) => $group->first()->no_wa);

        $verificationLogs = AuditLog::where('target_type', 'pesantren_claim')
            ->whereIn('target_id', $claims->pluck('id')->filter()->values())
            ->orderBy('created_at')
            ->get()
            ->groupBy('target_id');

        return response()->json([
            'pagination' => ['claims' => $claimsMeta],
            'claims' => $claims->map(fn($c) => [
                'id'               => $c->id,
                'user_id'          => $c->user_id,
                'pesantren_name'   => $c->pesantren_name,
                'status'           => $c->status,
                'region_id'        => $c->region_id,
                'kecamatan'        => $c->kecamatan,
                'nama_pengelola'   => $c->nama_pengelola,
                'email_pengelola'  => $c->email_pengelola,
                // Klien menerima endpoint entitas, bukan storage key internal.
                // Endpoint ini tetap memeriksa role dan kesesuaian Regional.
                'dokumen_preview_url' => "/api/documents/klaim/{$c->id}",
                'dokumen_status'      => BerkasDokumen::status($c->dokumen_bukti_url),
                'notes'            => $c->notes,
                'claimed_at'       => $c->claimed_at,
                'regional_approved_at' => $c->regional_approved_at,
                'created_at'       => $c->created_at,
                'jenis_pengajuan'  => $c->jenis_pengajuan,
                'nama_pengasuh'    => $c->profile?->nama_pengasuh,
                'alamat_singkat'   => $c->profile?->alamat_singkat,
                'no_wa_pendaftar'  => $c->profile?->no_wa_pendaftar ?: $picPhones->get($c->user_id),
                'is_alumni'        => $c->profile?->is_alumni,
                // Kolom alamat_lengkap dan kecamatan pada profil baru mulai diisi
                // saat pengajuan dibuat. Pendaftar yang mendaftar sebelum itu hanya
                // punya alamat_singkat dan kecamatan di baris klaim, sehingga tanpa
                // cadangan ini detail validasi tampil "-" untuk hampir semua data.
                'alamat_lengkap'   => $c->profile?->alamat_lengkap ?: $c->profile?->alamat_singkat,
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
                'kecamatan_profile'  => $c->profile?->kecamatan ?: $c->kecamatan,
                'verification_logs'   => ($verificationLogs->get($c->id) ?? collect())->map(fn($log) => [
                    'id'         => $log->id,
                    'action'     => $log->action,
                    'actor_role' => $log->actor_role,
                    'details'    => $log->details,
                    'meta'       => $log->meta,
                    'created_at' => $log->created_at,
                ])->values(),
            ]),
        ]);
    }

    public function pricingPackages(Request $request)
    {
        $this->assertRegional();

        $packages = PricingPackage::where('is_active', true)
            ->where('category', 'registration')
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

    /**
     * Transisi status klaim hanya boleh dari 'pending'. Status lain berarti
     * pengajuan sudah pernah diproses dan tidak boleh diubah lagi lewat
     * endpoint approve/reject.
     */
    private function assertClaimPending(PesantrenClaim $claim): void
    {
        if ($claim->status === 'pending') {
            return;
        }

        abort(response()->json([
            'message' => "Pengajuan ini berstatus {$claim->status} sehingga tidak bisa diproses lagi.",
            'status'  => $claim->status,
        ], 409));
    }

    public function approveClaim(Request $request, string $id)
    {
        $regionId = $this->assertRegional();

        DB::transaction(function () use ($request, $id, $regionId) {
            $claim = PesantrenClaim::whereKey($id)->lockForUpdate()->first();

            if (!$claim || $claim->region_id !== $regionId) {
                abort(response()->json(['message' => 'Claim tidak ditemukan'], 404));
            }

            // Hanya pengajuan yang masih menunggu yang boleh disetujui. Tanpa ini
            // klaim yang sudah ditolak bisa disetujui ulang, dan baris 'notes' di
            // bawah menghapus alasan penolakannya tanpa jejak. Approve dua kali
            // juga me-reset regional_approved_at sehingga hitungan keterlambatan
            // di latePayments() dan performance() kembali nol.
            $this->assertClaimPending($claim);

            // Validasi paket harga berada di dalam transaksi karena bergantung
            // pada jenis_pengajuan dari baris klaim yang sudah dikunci.
            $pricingPackage = null;
            if ($claim->jenis_pengajuan === 'pesantren_baru') {
                $data = $request->validate([
                    'pricingPackageId' => 'required|uuid',
                ]);

                $pricingPackage = PricingPackage::where('id', $data['pricingPackageId'])
                    ->where('category', 'registration')
                    ->where('is_active', true)
                    ->first();

                if (!$pricingPackage) {
                    abort(response()->json(['message' => 'Paket harga pendaftaran tidak valid atau tidak aktif.'], 422));
                }
            }

            $claim->update([
                'status'               => 'regional_approved',
                'regional_approved_at' => now(),
                'pricing_package_id'   => $pricingPackage?->id,
                'notes'                => null,
            ]);

            AuditLogger::record(
                auth()->user(),
                'regional_claim_approved',
                'pesantren_claim',
                $claim->id,
                $claim->pesantren_name,
                'Pengajuan disetujui oleh regional.',
                [
                    'region_id' => $regionId,
                    'jenis_pengajuan' => $claim->jenis_pengajuan,
                    'pricing_package_id' => $pricingPackage?->id,
                ]
            );

            if ($claim->jenis_pengajuan === 'klaim') {
                PesantrenProfile::where('id', $claim->user_id)->update([
                    // Persetujuan Regional hanya meloloskan klaim ke tahap
                    // pembayaran. Aktivasi final tetap dilakukan backend setelah
                    // pembayaran diverifikasi dan NIP tersedia.
                    'status_account' => 'pending',
                    'status_payment' => 'unpaid',
                ]);

                // Tandai direktori supaya tidak bisa diklaim ulang dan badge
                // "Sudah Diklaim" muncul di landing page.
                if ($claim->pesantren_directory_id) {
                    PesantrenDirectory::where('id', $claim->pesantren_directory_id)
                        ->update(['is_claimed' => true]);
                }
            } else {
                PesantrenProfile::where('id', $claim->user_id)->update([
                    'status_account' => 'pending',
                    'status_payment' => 'unpaid',
                ]);
                // Payment dibuat secara lazy oleh ensureInstitutionActivationInvoice()
                // saat user pertama kali mengakses halaman pembayaran (/api/payments/summary).
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

        DB::transaction(function () use ($id, $regionId, $data) {
            $claim = PesantrenClaim::whereKey($id)->lockForUpdate()->first();

            if (!$claim || $claim->region_id !== $regionId) {
                abort(response()->json(['message' => 'Claim tidak ditemukan'], 404));
            }

            $this->assertClaimPending($claim);

            $claim->update([
                'status' => 'rejected',
                'notes'  => $data['reason'],
            ]);

            AuditLogger::record(
                auth()->user(),
                'regional_claim_rejected',
                'pesantren_claim',
                $claim->id,
                $claim->pesantren_name,
                'Pengajuan ditolak oleh regional.',
                [
                    'region_id' => $regionId,
                    'reason' => $data['reason'],
                ]
            );

            PesantrenProfile::where('id', $claim->user_id)->update(['status_account' => 'rejected']);
        });

        return response()->json(['success' => true]);
    }

    public function latePayments(Request $request)
    {
        $regionId    = $this->assertRegional();
        $sevenDaysAgo = now()->subDays(7);

        // Klaim yang sudah lunas dibuang lewat subquery, bukan lewat filter di PHP.
        // Kalau penyaringan tetap dilakukan setelah data diambil, jumlah baris per
        // halaman jadi tidak menentu dan angka total pada metadata ikut salah.
        $verifiedClaimIds = Payment::query()
            ->select('pesantren_claim_id')
            ->whereNotNull('pesantren_claim_id')
            ->where('status', 'verified');

        [$claims, $claimsMeta] = $this->paginateList(
            PesantrenClaim::with('profile:id,no_wa_pendaftar')
                ->where('region_id', $regionId)
                ->where('status', 'regional_approved')
                ->whereNotNull('regional_approved_at')
                ->where('regional_approved_at', '<', $sevenDaysAgo)
                ->whereNotIn('id', $verifiedClaimIds)
                ->orderBy('regional_approved_at'),
            $request,
            'claims'
        );

        return response()->json([
            'pagination' => ['claims' => $claimsMeta],
            'claims' => $claims->values()->map(fn($c) => [
                'id'                   => $c->id,
                'user_id'              => $c->user_id,
                'pesantren_name'       => $c->pesantren_name,
                'nama_pengelola'       => $c->nama_pengelola,
                'regional_approved_at' => $c->regional_approved_at,
                'jenis_pengajuan'      => $c->jenis_pengajuan,
                'no_wa_pendaftar'      => $c->profile?->no_wa_pendaftar,
                // copy() wajib: Carbon bersifat mutable dan objek yang sama sudah
                // dipakai di field regional_approved_at di atas. Tanpa copy(),
                // addDays(7) ikut menggeser nilai yang dikirim ke klien.
                'days_overdue'         => max(0, (int) now()->diffInDays($c->regional_approved_at->copy()->addDays(7), false) * -1),
            ]),
        ]);
    }

    public function followUp(Request $request, string $claimId)
    {
        $regionId = $this->assertRegional();
        $user     = auth()->user();

        // claimId sebelumnya ditulis mentah ke log. Akibatnya id acak melanggar
        // foreign key dan menghasilkan 500, dan admin bisa mencatat follow-up
        // untuk klaim wilayah lain sehingga angka weeklyFollowUps di
        // performance() menggelembung.
        $claim = PesantrenClaim::whereKey($claimId)->first();
        if (!$claim || $claim->region_id !== $regionId) {
            return response()->json(['message' => 'Claim tidak ditemukan'], 404);
        }

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
            ->whereIn('status', ['regional_approved', 'approved', 'pusat_approved'])
            ->count();

        $paidProfiles = PesantrenProfile::where('region_id', $regionId)
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

        // Dua COUNT per region di dalam map() berarti 2N query. Hitungannya
        // dipindah ke subquery agregat sehingga seluruh leaderboard cukup 1 query.
        $regions = Region::query()
            ->select('id', 'name')
            ->selectSub(
                PesantrenClaim::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('pesantren_claims.region_id', 'regions.id')
                    ->whereIn('status', ['regional_approved', 'approved', 'pusat_approved']),
                'total_verified'
            )
            ->selectSub(
                PesantrenProfile::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('pesantren_profiles.region_id', 'regions.id')
                    ->where('status_payment', 'paid'),
                'total_paid'
            )
            ->orderBy('name')
            ->get();

        $stats = $regions->map(function ($r) {
            $verified = (int) $r->total_verified;
            $paid     = (int) $r->total_paid;

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

    public function reports(Request $request)
    {
        $regionId = $this->assertRegional();

        [$reports, $reportsMeta] = $this->paginateList(
            RegionalReport::where('region_id', $regionId)
                ->orderByDesc('report_date')
                ->orderByDesc('created_at'),
            $request,
            'reports'
        );

        return response()->json([
            'pagination' => ['reports' => $reportsMeta],
            'reports' => $reports->map(fn($report) => [
                'id' => $report->id,
                'title' => $report->title,
                'description' => $report->description,
                'report_date' => $report->report_date,
                'file_url' => $report->file_url,
                'status' => $report->status,
                'created_at' => $report->created_at,
            ])->values(),
        ]);
    }

    public function submitReport(Request $request)
    {
        $regionId = $this->assertRegional();
        $user = auth()->user();

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'report_date' => 'nullable|date',
            'file' => 'required|file|max:10240',
        ]);

        $reportId = (string) Str::uuid();
        $file = $request->file('file');
        $path = 'regional-reports/' . $regionId . '/' . now()->format('Y/m');
        $filename = $reportId . '.' . $file->getClientOriginalExtension();
        $file->storeAs($path, $filename, 'public');

        $report = RegionalReport::create([
            'id' => $reportId,
            'region_id' => $regionId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'report_date' => $data['report_date'] ?? now()->toDateString(),
            'file_url' => '/uploads/' . $path . '/' . $filename,
            'status' => 'submitted',
            'created_by' => $user?->id,
        ]);

        $rule = \App\Models\MilitansiRule::where('action_key', 'submit_regional_report')->where('is_active', true)->first();
        if ($rule) {
            Crew::whereHas('profile', fn($query) => $query->where('user_id', $user?->id))
                ->increment('xp_level', $rule->xp_value);
        }

        return response()->json([
            'success' => true,
            'report' => [
                'id' => $report->id,
                'title' => $report->title,
                'description' => $report->description,
                'report_date' => $report->report_date,
                'file_url' => $report->file_url,
                'status' => $report->status,
                'created_at' => $report->created_at,
            ],
        ], 201);
    }

    public function deleteReport(Request $request, string $id)
    {
        $regionId = $this->assertRegional();

        $report = RegionalReport::where('region_id', $regionId)->find($id);
        if (!$report) {
            return response()->json(['message' => 'Laporan tidak ditemukan'], 404);
        }

        if ($report->file_url && str_starts_with($report->file_url, '/uploads/')) {
            $relativePath = ltrim(str_replace('/uploads/', '', $report->file_url), '/');
            Storage::disk('public')->delete($relativePath);
        }

        $report->delete();

        return response()->json(['success' => true]);
    }

    public function downloadCenter(Request $request)
    {
        $this->assertRegional();

        [$resources, $resourcesMeta] = $this->paginateList(
            HubResource::query()
                ->where('is_published', true)
                ->where(function ($query) {
                    $query->whereNull('visibility_scopes')
                        ->orWhereJsonContains('visibility_scopes', 'all')
                        ->orWhereJsonContains('visibility_scopes', 'admin_regional');
                })
                ->orderBy('sort_order')
                ->orderByDesc('created_at'),
            $request,
            'resources'
        );

        return response()->json([
            'pagination' => ['resources' => $resourcesMeta],
            'resources' => $resources->map(fn($resource) => [
                'id' => $resource->id,
                'title' => $resource->title,
                'description' => $resource->description,
                'category' => $resource->category,
                'resource_type' => $resource->resource_type,
                'download_url' => $resource->resource_type === 'link' ? $resource->external_url : $resource->file_url,
                'file_size' => $resource->file_size,
                'created_at' => $resource->created_at,
            ])->values(),
        ]);
    }

    public function militansiOverview(Request $request)
    {
        $regionId = $this->assertRegional();

        $levels = MilitansiLevel::orderBy('min_xp')->get(['id', 'name', 'min_xp', 'color']);

        $crewQuery = fn() => Crew::query()
            ->whereHas('profile', fn($query) => $query->where('region_id', $regionId));

        // Ringkasan dihitung lewat agregat di database supaya tetap mencakup
        // SELURUH kru walaupun daftarnya sudah dibatasi per halaman.
        $summary = [
            'total_crews'  => $crewQuery()->count(),
            'active_crews' => $crewQuery()->where('status', 'active')->count(),
            'average_xp'   => (int) round($crewQuery()->avg('xp_level') ?? 0),
        ];

        // Sebelumnya seluruh kru diambil lalu dipotong take(20) di PHP; batasnya
        // sekarang ada di query. Default 20 mempertahankan perilaku lama.
        [$crews, $crewsMeta] = $this->paginateList(
            Crew::with('profile:id,region_id,nama_pesantren')
                ->whereHas('profile', fn($query) => $query->where('region_id', $regionId))
                ->orderByDesc('xp_level')
                ->orderBy('nama'),
            $request,
            'leaderboard',
            ['id', 'profile_id', 'nama', 'jabatan', 'status', 'niam', 'xp_level'],
            20,
            200
        );

        // Peringkat harus melanjutkan halaman sebelumnya, bukan mulai dari 1 lagi.
        $rankOffset = ($crewsMeta['page'] - 1) * $crewsMeta['per_page'];

        return response()->json([
            'pagination' => ['leaderboard' => $crewsMeta],
            'summary' => $summary,
            'levels' => $levels,
            'leaderboard' => $crews->values()->map(function ($crew, $index) use ($levels, $rankOffset) {
                $level = $levels->filter(fn($item) => $crew->xp_level >= $item->min_xp)->sortByDesc('min_xp')->first();
                return [
                    'rank' => $rankOffset + $index + 1,
                    'name' => $crew->nama,
                    'jabatan' => $crew->jabatan,
                    'niam' => $crew->niam,
                    'xp_level' => $crew->xp_level,
                    'status' => $crew->status,
                    'pesantren_name' => $crew->profile?->nama_pesantren,
                    'level' => $level?->name ?? 'Muhibbin',
                    'level_color' => $level?->color ?? '#94a3b8',
                ];
            }),
        ]);
    }
}
