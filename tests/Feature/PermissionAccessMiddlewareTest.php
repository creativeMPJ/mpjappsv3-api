<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PermissionAccessMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['audit_logs', 'pricing_packages', 'payments', 'pesantren_profiles', 'user_roles', 'roles', 'users'] as $table) {
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
            $table->string('status_account')->default('active');
            $table->string('status_payment')->default('paid');
            $table->string('profile_level')->default('basic');
            $table->uuid('region_id')->nullable();
            $table->string('nama_pesantren')->nullable();
            $table->timestamps();
        });

        Schema::create('pricing_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('category');
            $table->integer('harga_paket');
            $table->integer('harga_diskon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status');
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_user_id')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('action');
            $table->string('target_type');
            $table->uuid('target_id')->nullable();
            $table->string('target_name')->nullable();
            $table->text('details')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function test_price_package_create_requires_create_permission(): void
    {
        $user = $this->userWithAccess('Finance View Only', [
            'harga' => $this->access(view: true, create: false, update: false, delete: false),
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/admin/pricing-packages', [
            'name' => 'Paket Registrasi',
            'category' => 'registration',
            'harga_paket' => 100000,
            'harga_diskon' => null,
            'is_active' => true,
        ]);

        $response->assertForbidden();
        $this->assertSame(0, \DB::table('pricing_packages')->count());
    }

    public function test_price_package_create_allows_create_permission(): void
    {
        $user = $this->userWithAccess('Admin Pusat', [
            'administrasi' => $this->access(view: true, create: false, update: false, delete: false),
            'harga' => $this->access(view: true, create: true, update: true, delete: false),
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/admin/pricing-packages', [
            'name' => 'Paket Registrasi',
            'category' => 'registration',
            'hargaPaket' => 100000,
            'hargaDiskon' => 90000,
            'isActive' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, \DB::table('pricing_packages')->count());
        $this->assertTrue(\DB::table('pricing_packages')->where('name', 'Paket Registrasi')->exists());
        $this->assertTrue(\DB::table('audit_logs')->where('action', 'pricing_package_created')->exists());
    }

    public function test_regional_delete_requires_delete_permission(): void
    {
        $user = $this->userWithAccess('Regional Operator', [
            'master-regional' => $this->access(view: true, create: true, update: true, delete: false),
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->deleteJson('/api/admin/regional-management/regions/' . Str::uuid());

        $response->assertForbidden();
    }

    public function test_role_create_requires_hak_akses_create_permission(): void
    {
        $user = $this->userWithAccess('Role Viewer', [
            'hak-akses' => $this->access(view: true, create: false, update: false, delete: false),
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/roles', [
            'nama' => 'Custom Role',
            'is_super_admin' => false,
            'akses' => ['harga' => $this->access(view: true, create: false, update: false, delete: false)],
        ]);

        $response->assertForbidden();
        $this->assertFalse(Role::where('nama', 'Custom Role')->exists());
    }

    public function test_finance_stats_requires_dashboard_access(): void
    {
        $user = $this->userWithAccess('Admin Keuangan', [
            'payment' => $this->access(view: true, create: true, update: true, delete: true),
        ]);

        $this->actingAs($user, 'api')->getJson('/api/finance/stats')->assertForbidden();
    }

    public function test_finance_stats_allows_dashboard_access(): void
    {
        $user = $this->userWithAccess('Admin Keuangan', [
            'finance' => $this->access(view: true, create: false, update: false, delete: false),
        ]);

        $this->actingAs($user, 'api')->getJson('/api/finance/stats')
            ->assertOk()
            ->assertJsonPath('total_income', 0)
            ->assertJsonPath('pending_verification', 0)
            ->assertJsonPath('approved_today', 0)
            ->assertJsonPath('rejected_today', 0);
    }

    public function test_finance_access_migration_updates_existing_role(): void
    {
        $role = Role::create([
            'id' => (string) Str::uuid(),
            'nama' => 'Admin Keuangan',
            'is_super_admin' => false,
            'akses' => [
                'payment' => $this->access(view: true, create: true, update: true, delete: true),
            ],
        ]);

        $migration = require database_path('migrations/2026_09_29_000001_grant_finance_dashboard_access.php');
        $migration->up();
        $migration->up();

        $this->assertSame(
            $this->access(view: true, create: false, update: false, delete: false),
            $role->fresh()->akses['finance']
        );
    }

    private function userWithAccess(string $roleName, array $access): User
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => 'secret',
        ]);

        $role = Role::create([
            'id' => (string) Str::uuid(),
            'nama' => $roleName,
            'is_super_admin' => false,
            'akses' => $access,
        ]);

        UserRole::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function access(bool $view, bool $create, bool $update, bool $delete): array
    {
        return compact('view', 'create', 'update', 'delete');
    }
}
