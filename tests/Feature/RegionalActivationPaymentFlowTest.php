<?php

namespace Tests\Feature;

use App\Models\Crew;
use App\Models\Payment;
use App\Models\PesantrenClaim;
use App\Models\PesantrenProfile;
use App\Models\PricingPackage;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Support\FinanceActivationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegionalActivationPaymentFlowTest extends TestCase
{
    private string $regionId;
    private User $regional;
    private User $owner;
    private User $finance;
    private PesantrenProfile $profile;
    private PesantrenClaim $claim;
    private PricingPackage $package;
    private Crew $ownerCrew;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'audit_logs', 'payment_logs', 'payments', 'crews', 'jabatan_codes',
            'pesantren_claims', 'pricing_packages', 'system_settings',
            'regions', 'pesantren_profiles', 'user_roles', 'roles', 'users',
        ] as $table) {
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
            $table->string('code')->nullable();
            $table->timestamps();
        });

        Schema::create('pesantren_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->uuid('region_id')->nullable();
            $table->string('nama_pesantren')->nullable();
            $table->string('nama_pengasuh')->nullable();
            $table->string('no_wa_pendaftar')->nullable();
            $table->string('status_account')->default('pending');
            $table->string('status_payment')->default('unpaid');
            $table->string('profile_level')->default('basic');
            $table->string('nip')->nullable();
            $table->timestamps();
        });

        Schema::create('pesantren_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('pricing_package_id')->nullable();
            $table->string('pesantren_name');
            $table->string('nama_pengelola')->nullable();
            $table->string('jenis_pengajuan');
            $table->string('status')->default('pending');
            $table->uuid('region_id')->nullable();
            $table->string('mpj_id_number')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('regional_approved_at')->nullable();
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
            $table->uuid('user_id');
            $table->uuid('pesantren_claim_id')->nullable();
            $table->uuid('pricing_package_id')->nullable();
            $table->integer('base_amount');
            $table->integer('unique_code');
            $table->integer('total_amount');
            $table->string('status');
            $table->string('payment_type');
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('invoice_number')->nullable()->unique();
            $table->string('proof_file_url')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->uuid('created_by')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');
            $table->uuid('actor_user_id')->nullable();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('crews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('profile_id')->nullable();
            $table->string('nama')->nullable();
            $table->string('jabatan')->nullable();
            $table->uuid('jabatan_code_id')->nullable();
            $table->string('status')->nullable();
            $table->string('niam')->nullable();
            $table->string('no_wa')->nullable();
            $table->timestamps();
        });

        Schema::create('jabatan_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code');
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_user_id')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('action');
            $table->string('target_type')->nullable();
            $table->uuid('target_id')->nullable();
            $table->string('target_name')->nullable();
            $table->text('details')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        $this->seedFlowData();
    }

    public function test_registration_activation_flow_issues_nip_and_owner_niam_only_after_finance_verification(): void
    {
        Storage::fake('public');

        $this->actingAs($this->regional, 'api')
            ->postJson("/api/regional/claims/{$this->claim->id}/approve", [
                'pricingPackageId' => $this->package->id,
            ])
            ->assertOk();

        $this->claim->refresh();
        $this->profile->refresh();

        $this->assertSame('regional_approved', $this->claim->status);
        $this->assertSame('pending', $this->profile->status_account);
        $this->assertSame('unpaid', $this->profile->status_payment);
        $this->assertNull($this->profile->nip);
        $this->assertNull($this->ownerCrew->fresh()->niam);

        $current = $this->actingAs($this->owner, 'api')
            ->getJson('/api/payments/current')
            ->assertOk()
            ->assertJsonPath('claim.status', 'regional_approved')
            ->assertJsonPath('payment.status', FinanceActivationService::STATUS_PENDING)
            ->assertJsonPath('paymentContact.label', 'Admin Finance');

        $paymentId = $current->json('payment.id');
        $invoiceNumber = $current->json('payment.invoiceNumber');

        $this->assertNotEmpty($paymentId);
        $this->assertNotEmpty($invoiceNumber);

        $this->actingAs($this->owner, 'api')
            ->post('/api/payments/submit-proof', [
                'paymentId' => $paymentId,
                'senderName' => 'Pengelola Test',
                'file' => UploadedFile::fake()->image('proof.jpg')->size(120),
            ])
            ->assertOk();

        $payment = Payment::find($paymentId);
        $this->assertSame(FinanceActivationService::STATUS_PAID_UNVERIFIED, $payment->status);
        $this->assertSame($invoiceNumber, $payment->invoice_number);
        $this->assertNull($this->profile->fresh()->nip);

        $this->actingAs($this->finance, 'api')
            ->postJson("/api/admin/payments/{$paymentId}/approve")
            ->assertOk()
            ->assertJsonPath('activationState', 'active');

        $this->profile->refresh();
        $this->claim->refresh();
        $this->ownerCrew->refresh();

        $this->assertSame('approved', $this->claim->status);
        $this->assertSame('active', $this->profile->status_account);
        $this->assertSame('paid', $this->profile->status_payment);
        $this->assertMatchesRegularExpression('/^\d{7}$/', $this->profile->nip);
        $this->assertSame($this->profile->nip, $this->claim->mpj_id_number);
        $this->assertSame('active', $this->ownerCrew->status);
        $this->assertStringStartsWith($this->profile->nip, $this->ownerCrew->niam);
    }

    public function test_rejected_payment_reuses_same_invoice_for_resubmission(): void
    {
        Storage::fake('public');

        $this->actingAs($this->regional, 'api')
            ->postJson("/api/regional/claims/{$this->claim->id}/approve", [
                'pricingPackageId' => $this->package->id,
            ])
            ->assertOk();

        $paymentId = $this->actingAs($this->owner, 'api')
            ->getJson('/api/payments/current')
            ->json('payment.id');
        $invoiceNumber = Payment::find($paymentId)->invoice_number;

        $this->actingAs($this->owner, 'api')
            ->post('/api/payments/submit-proof', [
                'paymentId' => $paymentId,
                'senderName' => 'Pengelola Test',
                'file' => UploadedFile::fake()->image('proof.jpg')->size(120),
            ])
            ->assertOk();

        $this->actingAs($this->finance, 'api')
            ->postJson("/api/admin/payments/{$paymentId}/reject", ['reason' => 'Nominal tidak sesuai'])
            ->assertOk();

        $this->actingAs($this->owner, 'api')
            ->getJson('/api/payments/current')
            ->assertOk()
            ->assertJsonPath('payment.id', $paymentId)
            ->assertJsonPath('payment.invoiceNumber', $invoiceNumber)
            ->assertJsonPath('payment.status', FinanceActivationService::STATUS_REJECTED)
            ->assertJsonPath('payment.rejectionReason', 'Nominal tidak sesuai');
    }

    private function seedFlowData(): void
    {
        $this->regionId = (string) Str::uuid();
        \DB::table('regions')->insert([
            'id' => $this->regionId,
            'name' => 'Regional Test',
            'code' => '01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $regionalRole = $this->role('Admin Regional', false, [
            'validasi-pendaftar' => ['view' => true, 'create' => false, 'update' => true, 'delete' => false],
        ]);
        $financeRole = $this->role('Admin Keuangan', false, [
            'verifikasi' => ['view' => true, 'create' => false, 'update' => true, 'delete' => false],
        ]);
        $userRole = $this->role('Pengguna Pesantren', false, []);

        $this->regional = $this->user('regional@example.test', $regionalRole, $this->regionId, 'Admin Regional');
        $this->finance = $this->user('finance@example.test', $financeRole, $this->regionId, 'Admin Finance', '628111222333');
        PesantrenProfile::where('user_id', $this->finance->id)->update(['status_account' => 'active']);
        $this->owner = $this->user('owner@example.test', $userRole, $this->regionId, 'Pesantren Pemohon');

        $this->ownerCrew = Crew::create([
            'id' => (string) Str::uuid(),
            'profile_id' => null,
            'nama' => 'Pengelola Test',
            'jabatan' => 'Ketua Media',
            'status' => 'pending',
        ]);
        $this->owner->update(['reff_type' => 'crew', 'reff_id' => $this->ownerCrew->id]);

        $this->profile = PesantrenProfile::where('user_id', $this->owner->id)->first();
        $this->ownerCrew->update(['profile_id' => $this->profile->id]);

        $this->claim = PesantrenClaim::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->profile->id,
            'pesantren_name' => 'Pesantren Pemohon',
            'nama_pengelola' => 'Pengelola Test',
            'jenis_pengajuan' => 'pesantren_baru',
            'status' => 'pending',
            'region_id' => $this->regionId,
        ]);

        $this->package = PricingPackage::create([
            'id' => (string) Str::uuid(),
            'name' => 'Registrasi Basic',
            'category' => 'registration',
            'harga_paket' => 50000,
            'harga_diskon' => null,
            'is_active' => true,
        ]);
    }

    private function role(string $name, bool $super, array $access): Role
    {
        return Role::create([
            'id' => (string) Str::uuid(),
            'nama' => $name,
            'is_super_admin' => $super,
            'akses' => $access,
        ]);
    }

    private function user(string $email, Role $role, string $regionId, string $profileName, ?string $phone = null): User
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'email' => $email,
            'password_hash' => 'secret',
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
            'nama_pesantren' => $profileName,
            'nama_pengasuh' => 'Pengasuh',
            'no_wa_pendaftar' => $phone,
            'status_account' => 'pending',
            'status_payment' => 'unpaid',
        ]);

        return $user;
    }
}
