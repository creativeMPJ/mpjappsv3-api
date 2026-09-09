<?php

namespace Tests\Feature;

use App\Models\Crew;
use App\Models\PesantrenProfile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mengunci kontrak API integrasi: konsumennya aplikasi lain, jadi perubahan
 * bentuk respons di sini merusak sistem yang tidak ikut ter-deploy bersama.
 */
class ExternalApiTest extends TestCase
{
    private const TOKEN = 'token-uji-integrasi';

    /**
     * Skema disiapkan manual, mengikuti pola test lain di repo ini: menjalankan
     * seluruh migrasi di SQLite gagal karena ada migrasi lama yang bergantung
     * pada information_schema milik MySQL.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.external_api.tokens' => ['mpj-fest' => self::TOKEN]]);

        foreach (['crews', 'pesantren_profiles', 'users', 'regions', 'regencies', 'jabatan_codes'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('reff_type')->nullable();
            $table->uuid('reff_id')->nullable();
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('regencies', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
        });

        Schema::create('jabatan_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
        });

        Schema::create('pesantren_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('status_account')->default('pending');
            $table->string('status_payment')->default('unpaid');
            $table->string('nama_pesantren')->nullable();
            $table->string('tipe_pesantren')->nullable();
            $table->string('alamat_singkat')->nullable();
            $table->string('alamat_lengkap')->nullable();
            $table->string('kecamatan')->nullable();
            $table->uuid('region_id')->nullable();
            $table->string('regency_id')->nullable();
            $table->string('nip')->nullable();
            $table->timestamps();
        });

        Schema::create('crews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('profile_id');
            $table->string('nama');
            $table->string('jabatan')->nullable();
            $table->string('jabatan_media')->nullable();
            $table->uuid('jabatan_code_id')->nullable();
            $table->string('email')->nullable();
            $table->string('no_wa')->nullable();
            $table->string('niam')->nullable();
            $table->string('photo_url')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('is_pic')->default(false);
            $table->timestamps();
        });
    }

    private function buatLembagaBeranggota(): array
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'email' => 'lembaga@contoh.test',
            'password_hash' => bcrypt('rahasia'),
        ]);

        $profile = PesantrenProfile::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'nama_pesantren' => 'PP Uji Integrasi',
            'status_account' => 'active',
            'status_payment' => 'paid',
            'nip' => '2699001',
        ]);

        $aktif = Crew::create([
            'id' => (string) Str::uuid(),
            'profile_id' => $profile->id,
            'nama' => 'Koordinator Uji',
            'jabatan' => 'Koordinator',
            'niam' => '269900101',
            'status' => 'active',
            'is_pic' => true,
        ]);

        Crew::create([
            'id' => (string) Str::uuid(),
            'profile_id' => $profile->id,
            'nama' => 'Kru Menunggu',
            'jabatan' => 'Editor',
            'status' => 'pending',
            'is_pic' => false,
        ]);

        return [$profile, $aktif];
    }

    public function test_menolak_permintaan_tanpa_token(): void
    {
        $this->getJson('/api/external/institutions')->assertStatus(401);
    }

    public function test_menolak_token_yang_tidak_dikenali(): void
    {
        $this->withHeader('X-Api-Key', 'token-asal')
            ->getJson('/api/external/institutions')
            ->assertStatus(401);
    }

    public function test_menutup_endpoint_saat_token_belum_dikonfigurasi(): void
    {
        config(['services.external_api.tokens' => []]);

        $this->withHeader('X-Api-Key', self::TOKEN)
            ->getJson('/api/external/institutions')
            ->assertStatus(503);
    }

    public function test_menerima_bearer_maupun_api_key(): void
    {
        $this->buatLembagaBeranggota();

        $this->withHeader('X-Api-Key', self::TOKEN)
            ->getJson('/api/external/institutions')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer ' . self::TOKEN)
            ->getJson('/api/external/institutions')
            ->assertOk();
    }

    public function test_daftar_lembaga_membawa_id_dan_meta_pagination(): void
    {
        [$profile] = $this->buatLembagaBeranggota();

        $response = $this->withHeader('X-Api-Key', self::TOKEN)
            ->getJson('/api/external/institutions?search=Uji Integrasi')
            ->assertOk()
            ->assertJsonPath('data.0.id', $profile->id)
            ->assertJsonPath('data.0.nama', 'PP Uji Integrasi')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('meta.total', 1);

        $this->assertSame(
            ['current_page', 'last_page', 'per_page', 'total'],
            array_keys($response->json('meta'))
        );
    }

    public function test_per_page_dibatasi_seratus(): void
    {
        $this->buatLembagaBeranggota();

        $this->withHeader('X-Api-Key', self::TOKEN)
            ->getJson('/api/external/institutions?per_page=5000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_anggota_default_hanya_yang_aktif(): void
    {
        [$profile, $aktif] = $this->buatLembagaBeranggota();

        $this->withHeader('X-Api-Key', self::TOKEN)
            ->getJson("/api/external/institutions/{$profile->id}/members")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.member_id', $aktif->id)
            ->assertJsonPath('data.0.status', 'aktif')
            ->assertJsonPath('data.0.is_admin_lembaga', true)
            ->assertJsonPath('data.0.institution_id', $profile->id);
    }

    public function test_status_all_memuat_anggota_yang_belum_aktif(): void
    {
        [$profile] = $this->buatLembagaBeranggota();

        $this->withHeader('X-Api-Key', self::TOKEN)
            ->getJson("/api/external/institutions/{$profile->id}/members?status=all")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_lembaga_tidak_ditemukan_memakai_format_error_baku(): void
    {
        $this->withHeader('X-Api-Key', self::TOKEN)
            ->getJson('/api/external/institutions/' . Str::uuid())
            ->assertStatus(404)
            ->assertJsonPath('status', 'error');
    }

    public function test_lembaga_milik_anggota(): void
    {
        [$profile, $aktif] = $this->buatLembagaBeranggota();

        $this->withHeader('X-Api-Key', self::TOKEN)
            ->getJson("/api/external/members/{$aktif->id}/institutions")
            ->assertOk()
            ->assertJsonPath('data.0.id', $profile->id)
            ->assertJsonPath('data.0.jabatan', 'Koordinator');
    }
}
