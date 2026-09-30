<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HubResourceFileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['hub_resources', 'pesantren_profiles', 'user_roles', 'roles', 'users'] as $table) {
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
            $table->uuid('role_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('pesantren_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
        });

        Schema::create('hub_resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('general');
            $table->string('resource_type')->default('file');
            $table->string('file_url')->nullable();
            $table->string('external_url')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->json('visibility_scopes')->nullable();
            $table->boolean('is_published')->default(true);
            $table->integer('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function test_hub_upload_is_stored_and_served_from_uploads_route(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->adminPusat(), 'api')->post('/api/admin/hub/resources', [
            'title' => 'Panduan MPJ',
            'category' => 'panduan',
            'resource_type' => 'file',
            'visibility_scopes' => ['all'],
            'file' => UploadedFile::fake()->create('panduan.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated();
        $downloadUrl = $response->json('resource.download_url');
        $this->assertIsString($downloadUrl);
        $this->assertStringStartsWith('/uploads/hub-resources/', $downloadUrl);

        $relativePath = ltrim(str_replace('/uploads/', '', $downloadUrl), '/');
        Storage::disk('public')->assertExists($relativePath);
        $this->get($downloadUrl)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_failed_hub_upload_does_not_create_resource_row(): void
    {
        config(['filesystems.disks.public.root' => '/dev/null/mpj-hub']);
        Storage::forgetDisk('public');

        $this->actingAs($this->adminPusat(), 'api')->post('/api/admin/hub/resources', [
            'title' => 'File Gagal',
            'category' => 'panduan',
            'resource_type' => 'file',
            'visibility_scopes' => ['all'],
            'file' => UploadedFile::fake()->create('gagal.pdf', 100, 'application/pdf'),
        ])->assertStatus(500)->assertJsonPath('message', 'File gagal disimpan');

        $this->assertDatabaseCount('hub_resources', 0);
    }

    private function adminPusat(): User
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => 'secret',
        ]);

        $role = Role::create([
            'id' => (string) Str::uuid(),
            'nama' => 'Admin Pusat',
            'is_super_admin' => false,
            'akses' => [
                'mpj-hub' => ['view' => true, 'create' => true, 'update' => false, 'delete' => false],
            ],
        ]);

        UserRole::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
        ]);

        return $user;
    }
}
