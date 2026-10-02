<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PesantrenProfile;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentProofDocumentAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['payments', 'pesantren_profiles', 'user_roles', 'roles', 'users'] as $table) {
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

        Schema::create('pesantren_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->uuid('region_id')->nullable();
            $table->string('nama_pesantren')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('status');
            $table->string('proof_file_url')->nullable();
            $table->timestamps();
        });
    }

    public function test_finance_can_preview_private_payment_proof(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payment-proofs/proof.pdf', '%PDF-test');
        $owner = $this->userWithRole('Pengguna Pesantren', []);
        $finance = $this->userWithRole('Admin Keuangan', [
            'verifikasi' => ['view' => true, 'create' => false, 'update' => true, 'delete' => false],
        ]);
        $payment = $this->payment($owner, '/uploads/payment-proofs/proof.pdf');

        $this->actingAs($finance, 'api')
            ->get("/api/documents/pembayaran/{$payment->id}")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_owner_can_preview_own_private_payment_proof(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payment-proofs/proof.jpg', 'image-bytes');
        $owner = $this->userWithRole('Pengguna Pesantren', []);
        $payment = $this->payment($owner, '/uploads/payment-proofs/proof.jpg');

        $this->actingAs($owner, 'api')
            ->get("/api/documents/pembayaran/{$payment->id}")
            ->assertOk();
    }

    public function test_unrelated_user_cannot_preview_payment_proof(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payment-proofs/proof.jpg', 'image-bytes');
        $owner = $this->userWithRole('Pengguna Pesantren', []);
        $other = $this->userWithRole('Pengguna Pesantren', []);
        $payment = $this->payment($owner, '/uploads/payment-proofs/proof.jpg');

        $this->actingAs($other, 'api')
            ->getJson("/api/documents/pembayaran/{$payment->id}")
            ->assertNotFound();
    }

    public function test_missing_private_payment_proof_returns_safe_status(): void
    {
        Storage::fake('local');
        $finance = $this->userWithRole('Admin Keuangan', [
            'finance' => ['view' => true, 'create' => false, 'update' => false, 'delete' => false],
        ]);
        $owner = $this->userWithRole('Pengguna Pesantren', []);
        $payment = $this->payment($owner, '/uploads/payment-proofs/missing.jpg');

        $this->actingAs($finance, 'api')
            ->getJson("/api/documents/pembayaran/{$payment->id}")
            ->assertNotFound()
            ->assertJsonPath('document_status', 'missing');
    }

    private function userWithRole(string $roleName, array $access): User
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
        PesantrenProfile::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'nama_pesantren' => 'Pesantren Test',
        ]);

        return $user;
    }

    private function payment(User $owner, string $proofUrl): Payment
    {
        $profile = PesantrenProfile::where('user_id', $owner->id)->firstOrFail();

        return Payment::create([
            'id' => (string) Str::uuid(),
            'user_id' => $profile->id,
            'status' => 'paid_unverified',
            'proof_file_url' => $proofUrl,
        ]);
    }
}
