<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PesantrenProfile;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Support\FinanceActivationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentVerificationQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['payment_logs', 'payments', 'crews', 'pesantren_claims', 'pricing_packages', 'regions', 'pesantren_profiles', 'user_roles', 'roles', 'users'] as $table) {
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
            $table->string('no_wa_pendaftar')->nullable();
            $table->string('status_account')->default('pending');
            $table->string('status_payment')->default('unpaid');
            $table->string('nip')->nullable();
            $table->string('nama_pesantren')->nullable();
            $table->string('nama_pengasuh')->nullable();
            $table->uuid('region_id')->nullable();
            $table->timestamps();
        });

        Schema::create('pesantren_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pesantren_name');
            $table->string('nama_pengelola');
            $table->string('jenis_pengajuan');
            $table->uuid('region_id')->nullable();
            $table->string('mpj_id_number')->nullable();
            $table->timestamps();
        });

        Schema::create('pricing_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('category');
            $table->timestamps();
        });

        Schema::create('crews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama')->nullable();
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
            $table->string('invoice_number')->nullable();
            $table->string('proof_file_url')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');
            $table->timestamps();
        });
    }

    public function test_verification_queue_excludes_payments_without_proof(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('payment-proofs/ready.pdf', 'proof');

        [$finance, $profile] = $this->financeUser();
        $withoutProof = $this->payment($profile, null);
        $ready = $this->payment($profile, '/uploads/payment-proofs/ready.pdf');
        $this->payment($profile, '/uploads/payment-proofs/pending.pdf', FinanceActivationService::STATUS_PENDING);

        $response = $this
            ->actingAs($finance, 'api')
            ->getJson('/api/admin/payments?payment_status=waiting_verification');

        $response->assertOk()
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.id', $ready->id)
            ->assertJsonPath('payments.0.proof_status', 'available');

        $this->assertNotSame($withoutProof->id, $response->json('payments.0.id'));
    }

    public function test_payment_without_proof_cannot_be_approved(): void
    {
        [$finance, $profile] = $this->financeUser();
        $payment = $this->payment($profile, null);

        $this->actingAs($finance, 'api')
            ->postJson("/api/admin/payments/{$payment->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('proof_status', 'none')
            ->assertJsonPath('message', 'Bukti transfer belum diunggah.');
    }

    private function financeUser(): array
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => 'secret',
        ]);

        $role = Role::create([
            'id' => (string) Str::uuid(),
            'nama' => 'Admin Keuangan',
            'is_super_admin' => false,
            'akses' => [
                'verifikasi' => ['view' => true, 'create' => false, 'update' => true, 'delete' => false],
            ],
        ]);

        UserRole::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        $profile = PesantrenProfile::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'nama_pesantren' => 'Pesantren Test',
            'nama_pengasuh' => 'Pengelola Test',
        ]);

        return [$user, $profile];
    }

    private function payment(PesantrenProfile $profile, ?string $proof, string $status = FinanceActivationService::STATUS_WAITING_VERIFICATION): Payment
    {
        return Payment::create([
            'id' => (string) Str::uuid(),
            'user_id' => $profile->id,
            'base_amount' => 100000,
            'unique_code' => 123,
            'total_amount' => 100123,
            'status' => $status,
            'payment_type' => FinanceActivationService::TYPE_INSTITUTION_ACTIVATION,
            'reference_type' => FinanceActivationService::REFERENCE_PROFILE,
            'reference_id' => $profile->id,
            'invoice_number' => 'INV-' . Str::upper(Str::random(8)),
            'proof_file_url' => $proof,
            'meta' => [],
        ]);
    }
}
