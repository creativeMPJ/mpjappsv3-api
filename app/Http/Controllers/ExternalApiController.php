<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\PesantrenProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * API integrasi untuk aplikasi lain yang memakai MPJApps sebagai master data
 * anggota dan lembaga (mis. MPJ Fest).
 *
 * Bentuk responsnya sengaja dibedakan dari endpoint internal: penamaan field
 * memakai istilah domain integrasi (institution/member), bukan nama kolom
 * database, supaya perubahan skema di sini tidak langsung merembet menjadi
 * perubahan kontrak di sisi konsumen.
 *
 * Yang dianggap "lembaga" adalah pesantren yang sudah punya profil di MPJApps,
 * bukan baris direktori hasil impor: hanya profil yang punya anggota, status
 * keanggotaan, dan NIP. Yang dianggap "anggota" adalah kru media pesantren.
 */
class ExternalApiController extends Controller
{
    /** Batas atas per_page sesuai kesepakatan integrasi. */
    private const MAX_PER_PAGE = 100;

    public function institutions(Request $request)
    {
        $query = PesantrenProfile::with(['region:id,name,code', 'regency:id,name'])
            ->whereNotNull('nama_pesantren')
            ->where('nama_pesantren', '!=', '');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where('nama_pesantren', 'like', "%{$search}%");
        }

        // Filter region menerima id maupun nama supaya konsumen tidak wajib
        // menyimpan UUID wilayah lebih dulu.
        if ($region = trim((string) $request->query('region', ''))) {
            $query->where(function (Builder $builder) use ($region) {
                $builder->where('region_id', $region)
                    ->orWhereHas('region', fn (Builder $r) => $r->where('name', 'like', "%{$region}%"));
            });
        }

        if (($isActive = $request->query('is_active')) !== null) {
            $wantsActive = filter_var($isActive, FILTER_VALIDATE_BOOLEAN);
            if ($wantsActive) {
                $query->where('status_account', 'active')
                    ->where('status_payment', 'paid')
                    ->whereNotNull('nip');
            } else {
                $query->where(function ($profileQuery) {
                    $profileQuery->where('status_account', '!=', 'active')
                        ->orWhere('status_payment', '!=', 'paid')
                        ->orWhereNull('nip');
                });
            }
        }

        $page = $query->orderBy('nama_pesantren')->paginate($this->perPage($request));

        return $this->paginated($page, fn (PesantrenProfile $p) => $this->institutionPayload($p));
    }

    public function institutionDetail(Request $request, string $id)
    {
        $profile = PesantrenProfile::with(['region:id,name,code', 'regency:id,name'])->find($id);

        if (!$profile) {
            return $this->notFound('Lembaga tidak ditemukan.');
        }

        return response()->json(['data' => $this->institutionPayload($profile)]);
    }

    /**
     * Anggota sebuah lembaga, lengkap dengan data relasinya.
     *
     * Default status "aktif" mengikuti permintaan integrasi: yang dipakai untuk
     * pendaftaran event hanya anggota aktif. Kirim status=all untuk mengambil
     * seluruhnya, termasuk yang masih menunggu aktivasi.
     */
    public function institutionMembers(Request $request, string $id)
    {
        $profile = PesantrenProfile::find($id, ['id', 'nama_pesantren']);

        if (!$profile) {
            return $this->notFound('Lembaga tidak ditemukan.');
        }

        $status = strtolower(trim((string) $request->query('status', 'aktif')));

        $query = Crew::with('jabatanCode:id,name,code')->where('profile_id', $profile->id);

        if ($status !== 'all' && $status !== 'semua') {
            $query->whereIn('status', $this->crewStatusesFor($status));
        }

        $page = $query->orderByDesc('is_pic')->orderBy('nama')->paginate($this->perPage($request));

        return $this->paginated($page, fn (Crew $crew) => $this->memberPayload($crew, $profile->id));
    }

    /**
     * Lembaga tempat seorang anggota terdaftar.
     *
     * Skema saat ini menaruh satu kru pada tepat satu profil, jadi hasilnya
     * paling banyak satu baris. Bentuknya tetap daftar supaya konsumen tidak
     * perlu berubah kalau nanti satu anggota bisa berada di banyak lembaga.
     */
    public function memberInstitutions(Request $request, string $id)
    {
        $crew = Crew::with('profile.region:id,name,code', 'profile.regency:id,name')->find($id);

        if (!$crew) {
            return $this->notFound('Anggota tidak ditemukan.');
        }

        $institutions = $crew->profile
            ? [array_merge(
                $this->institutionPayload($crew->profile),
                [
                    'status'           => $this->membershipStatus($crew->status),
                    'jabatan'          => $crew->jabatan_media ?: $crew->jabatan,
                    'is_admin_lembaga' => (bool) $crew->is_pic,
                ]
            )]
            : [];

        return response()->json(['data' => $institutions]);
    }

    // ── Bentuk payload ────────────────────────────────────────────────────

    private function institutionPayload(PesantrenProfile $profile): array
    {
        return [
            'id'        => $profile->id,
            'nama'      => $profile->nama_pesantren,
            'jenis'     => $profile->tipe_pesantren,
            'region'    => $profile->region?->name,
            'region_id' => $profile->region_id,
            'kota'      => $profile->regency?->name,
            'kecamatan' => $profile->kecamatan,
            'alamat'    => $profile->alamat_lengkap ?: $profile->alamat_singkat,
            'nip'       => $profile->nip,
            'is_active' => $profile->status_account === 'active',
        ];
    }

    private function memberPayload(Crew $crew, string $institutionId): array
    {
        return [
            'id'        => $crew->id,
            'member_id' => $crew->id,
            'niam'      => $crew->niam,
            'nama'      => $crew->nama,
            'email'     => $crew->email,
            'whatsapp'  => $crew->no_wa,
            // Belum ada kolomnya di MPJApps; dikirim tetap agar bentuk
            // responsnya stabil saat kolomnya ditambahkan nanti.
            'jenis_kelamin'    => null,
            'foto'             => $this->assetUrl($crew->photo_url),
            'institution_id'   => $institutionId,
            'status'           => $this->membershipStatus($crew->status),
            'jabatan'          => $crew->jabatan_media ?: $crew->jabatan ?: $crew->jabatanCode?->name,
            'is_admin_lembaga' => (bool) $crew->is_pic,
        ];
    }

    /**
     * Status internal kru dipetakan ke dua nilai yang dipakai konsumen. Kru
     * yang masih menunggu verifikasi belum boleh terbaca aktif karena NIAM-nya
     * belum terbit.
     */
    private function membershipStatus(?string $status): string
    {
        return $status === 'active' ? 'aktif' : 'nonaktif';
    }

    /** Kebalikan membershipStatus(), untuk menerjemahkan filter dari konsumen. */
    private function crewStatusesFor(string $status): array
    {
        return $status === 'aktif' || $status === 'active'
            ? ['active']
            : ['pending', 'rejected', 'inactive'];
    }

    private function assetUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return preg_match('#^https?://#i', $path)
            ? $path
            : rtrim(config('app.url'), '/') . '/' . ltrim($path, '/');
    }

    // ── Bentuk respons ────────────────────────────────────────────────────

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 25);

        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    private function paginated($page, callable $mapper)
    {
        return response()->json([
            'data' => collect($page->items())->map($mapper)->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    private function notFound(string $message)
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => new \stdClass(),
        ], 404);
    }
}
