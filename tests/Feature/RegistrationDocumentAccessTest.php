<?php

namespace Tests\Feature;

use App\Models\PesantrenClaim;
use App\Models\PesantrenProfile;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationDocumentAccessTest extends TestCase
{
    private string $regionA;
    private string $regionB;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['audit_logs', 'crews', 'pesantren_claims', 'pesantren_profiles', 'regions', 'user_roles', 'roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('reff_type')->nullable();
            $table->uuid('reff_id')->nullable();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama');
            $table->boolean('is_super_admin')->default(false);
            $table->json('akses');
            $table->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('pesantren_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->uuid('region_id')->nullable();
            $table->string('nama_pesantren')->nullable();
            $table->string('nama_pengasuh')->nullable();
            $table->string('status_account')->default('pending');
            $table->string('status_payment')->default('unpaid');
            $table->timestamps();
        });

        Schema::create('pesantren_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('pesantren_name');
            $table->string('jenis_pengajuan');
            $table->string('status')->default('pending');
            $table->uuid('region_id')->nullable();
            $table->string('nama_pengelola')->nullable();
            $table->string('dokumen_bukti_url')->nullable();
            $table->timestamps();
        });

        Schema::create('crews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('profile_id')->nullable();
            $table->string('no_wa')->nullable();
            $table->boolean('is_pic')->default(false);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('target_type')->nullable();
            $table->uuid('target_id')->nullable();
            $table->string('action');
            $table->string('actor_role')->nullable();
            $table->text('details')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        $this->regionA = (string) Str::uuid();
        $this->regionB = (string) Str::uuid();

        $now = now();
        \DB::table('regions')->insert([
            ['id' => $this->regionA, 'name' => 'Regional A', 'created_at' => $now, 'updated_at' => $now],
            ['id' => $this->regionB, 'name' => 'Regional B', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function test_regional_admin_can_preview_registration_document_in_own_region(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('registration-documents/applicant/document.jpg', 'image-bytes');

        $regional = $this->userWithRole('Admin Regional', false, [
            'validasi-pendaftar' => ['view' => true],
        ], $this->regionA);
        $claim = $this->claim($this->regionA, '/uploads/registration-documents/applicant/document.jpg');

        $response = $this
            ->actingAs($regional, 'api')
            ->get("/api/documents/klaim/{$claim->id}");

        $response->assertOk();
        $cacheControl = $response->headers->get('Cache-Control') ?? '';
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_regional_admin_cannot_preview_registration_document_from_other_region(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('registration-documents/applicant/document.jpg', 'image-bytes');

        $regional = $this->userWithRole('Admin Regional', false, [
            'validasi-pendaftar' => ['view' => true],
        ], $this->regionA);
        $claim = $this->claim($this->regionB, '/uploads/registration-documents/applicant/document.jpg');

        $this
            ->actingAs($regional, 'api')
            ->getJson("/api/documents/klaim/{$claim->id}")
            ->assertNotFound();
    }

    public function test_admin_pusat_can_preview_registration_document_from_any_region(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('registration-documents/applicant/document.jpg', 'image-bytes');

        $pusat = $this->userWithRole('Admin Pusat', true, [], $this->regionA);
        $claim = $this->claim($this->regionB, '/uploads/registration-documents/applicant/document.jpg');

        $this
            ->actingAs($pusat, 'api')
            ->get("/api/documents/klaim/{$claim->id}")
            ->assertOk();
    }

    public function test_other_roles_cannot_preview_registration_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('registration-documents/applicant/document.jpg', 'image-bytes');

        $user = $this->userWithRole('Pengguna Pesantren', false, [], $this->regionA);
        $claim = $this->claim($this->regionA, '/uploads/registration-documents/applicant/document.jpg');

        $this
            ->actingAs($user, 'api')
            ->getJson("/api/documents/klaim/{$claim->id}")
            ->assertNotFound();
    }

    public function test_public_uploads_route_does_not_serve_registration_documents(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('registration-documents/applicant/document.jpg', 'image-bytes');

        $this->get('/uploads/registration-documents/applicant/document.jpg')
            ->assertNotFound();
    }

    public function test_missing_registration_document_returns_safe_unavailable_state(): void
    {
        Storage::fake('local');

        $regional = $this->userWithRole('Admin Regional', false, [
            'validasi-pendaftar' => ['view' => true],
        ], $this->regionA);
        $claim = $this->claim($this->regionA, '/uploads/registration-documents/applicant/missing.jpg');

        $this
            ->actingAs($regional, 'api')
            ->getJson("/api/documents/klaim/{$claim->id}")
            ->assertNotFound()
            ->assertJsonPath('document_status', 'missing')
            ->assertJsonPath('message', 'Dokumen tidak tersedia.');
    }

    public function test_pending_claims_returns_preview_endpoint_and_not_raw_storage_key(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('registration-documents/applicant/document.jpg', 'image-bytes');

        $regional = $this->userWithRole('Admin Regional', false, [
            'validasi-pendaftar' => ['view' => true],
        ], $this->regionA);
        $claim = $this->claim($this->regionA, '/uploads/registration-documents/applicant/document.jpg');

        $response = $this
            ->actingAs($regional, 'api')
            ->getJson('/api/regional/pending-claims?status=all');

        $response->assertOk()
            ->assertJsonPath('claims.0.id', $claim->id)
            ->assertJsonPath('claims.0.dokumen_preview_url', "/api/documents/klaim/{$claim->id}")
            ->assertJsonPath('claims.0.dokumen_status', 'available');

        $this->assertArrayNotHasKey('dokumen_bukti_url', $response->json('claims.0'));
    }

    private function userWithRole(string $roleName, bool $isSuperAdmin, array $access, ?string $regionId): User
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => 'secret',
        ]);

        $role = Role::create([
            'id' => (string) Str::uuid(),
            'nama' => $roleName,
            'is_super_admin' => $isSuperAdmin,
            'akses' => $access,
        ]);

        UserRole::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
        ]);

        PesantrenProfile::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'region_id' => $regionId,
            'nama_pesantren' => 'Pesantren Test',
            'nama_pengasuh' => 'Pengasuh Test',
        ]);

        return $user;
    }

    private function claim(string $regionId, ?string $documentUrl): PesantrenClaim
    {
        return PesantrenClaim::create([
            'id' => (string) Str::uuid(),
            'pesantren_name' => 'Pesantren Pemohon',
            'jenis_pengajuan' => 'pesantren_baru',
            'status' => 'pending',
            'region_id' => $regionId,
            'nama_pengelola' => 'Pengelola',
            'dokumen_bukti_url' => $documentUrl,
        ]);
    }
}
