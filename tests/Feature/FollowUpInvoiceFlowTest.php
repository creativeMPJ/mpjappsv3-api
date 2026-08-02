<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PesantrenProfile;
use App\Models\PricingPackage;
use App\Models\User;
use App\Support\FinanceActivationService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Tests\TestCase;

class FollowUpInvoiceFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['payment_logs', 'payments', 'pesantren_claims', 'pricing_packages', 'pesantren_profiles', 'users'] as $table) {
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

        Schema::create('pesantren_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('status_account')->default('pending');
            $table->string('status_payment')->default('unpaid');
            $table->string('profile_level')->default('basic');
            $table->unsignedInteger('paid_slot_quantity')->default(0);
            $table->string('nama_pesantren')->nullable();
            $table->string('nip')->nullable();
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

        Schema::create('pesantren_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('pesantren_name');
            $table->string('status')->default('approved');
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
            $table->string('status')->default(FinanceActivationService::STATUS_PENDING);
            $table->string('payment_type')->default(FinanceActivationService::TYPE_INSTITUTION_ACTIVATION);
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('invoice_number')->nullable()->unique();
            $table->string('transaction_reference')->nullable();
            $table->string('proof_file_url')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('rejected_by')->nullable();
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
    }

    public function test_profile_upgrade_replaces_pending_invoice_when_target_level_changes(): void
    {
        [$user, $profile] = $this->activeProfile('silver');
        $this->pricingPackage('upgrade', 150000);

        $existing = $this->payment($profile, FinanceActivationService::TYPE_PROFILE_UPGRADE, [
            'status' => FinanceActivationService::STATUS_PENDING,
            'meta' => ['target_level' => 'gold'],
            'invoice_number' => 'INV-UPG-OLD',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->postJson('/api/profile/upgrade/request', ['targetLevel' => 'platinum']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payment.paymentType', FinanceActivationService::TYPE_PROFILE_UPGRADE);

        $this->assertSame(FinanceActivationService::STATUS_CANCELLED, $existing->fresh()->status);
        $this->assertSame('platinum', Payment::where('invoice_number', $response->json('payment.invoiceNumber'))->first()->meta['target_level']);
    }

    public function test_profile_upgrade_blocks_target_change_when_invoice_waits_for_finance(): void
    {
        [$user, $profile] = $this->activeProfile('silver');
        $this->pricingPackage('upgrade', 150000);

        $this->payment($profile, FinanceActivationService::TYPE_PROFILE_UPGRADE, [
            'status' => FinanceActivationService::STATUS_WAITING_VERIFICATION,
            'meta' => ['target_level' => 'gold'],
            'invoice_number' => 'INV-UPG-WAITING',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->postJson('/api/profile/upgrade/request', ['targetLevel' => 'platinum']);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Invoice upgrade sebelumnya sedang menunggu verifikasi finance.');

        $this->assertSame(1, Payment::count());
    }

    public function test_slot_addon_recalculates_pending_invoice_quantity(): void
    {
        [$user, $profile] = $this->activeProfile();
        $package = $this->pricingPackage('crew_addon', 25000);

        $payment = $this->payment($profile, FinanceActivationService::TYPE_SLOT_ADDON, [
            'pricing_package_id' => $package->id,
            'base_amount' => 25000,
            'total_amount' => 25123,
            'unique_code' => 123,
            'meta' => ['slot_quantity' => 1],
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->postJson('/api/media/slot-addons/request', ['quantity' => 4]);

        $response->assertOk()
            ->assertJsonPath('payment.quantity', 4)
            ->assertJsonPath('payment.totalAmount', 100123);

        $payment->refresh();
        $this->assertSame(100000, $payment->base_amount);
        $this->assertSame(4, $payment->meta['slot_quantity']);
    }

    public function test_slot_addon_blocks_changes_when_invoice_waits_for_finance(): void
    {
        [$user, $profile] = $this->activeProfile();
        $package = $this->pricingPackage('crew_addon', 25000);

        $this->payment($profile, FinanceActivationService::TYPE_SLOT_ADDON, [
            'pricing_package_id' => $package->id,
            'status' => FinanceActivationService::STATUS_WAITING_VERIFICATION,
            'meta' => ['slot_quantity' => 1],
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->postJson('/api/media/slot-addons/request', ['quantity' => 5]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Invoice slot tambahan sedang menunggu verifikasi finance.');

        $this->assertSame(1, Payment::first()->meta['slot_quantity']);
    }

    private function activeProfile(string $level = 'basic'): array
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => 'secret',
        ]);

        $profile = PesantrenProfile::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status_account' => 'active',
            'status_payment' => 'paid',
            'profile_level' => $level,
            'paid_slot_quantity' => 0,
            'nama_pesantren' => 'Pesantren Test',
            'nip' => 'MPJ-01-0001',
        ]);

        return [$user, $profile];
    }

    private function pricingPackage(string $category, int $amount): PricingPackage
    {
        return PricingPackage::create([
            'id' => (string) Str::uuid(),
            'name' => 'Paket ' . $category,
            'category' => $category,
            'harga_paket' => $amount,
            'harga_diskon' => null,
            'is_active' => true,
        ]);
    }

    private function payment(PesantrenProfile $profile, string $type, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'id' => (string) Str::uuid(),
            'user_id' => $profile->id,
            'pesantren_claim_id' => null,
            'pricing_package_id' => null,
            'base_amount' => 50000,
            'unique_code' => 111,
            'total_amount' => 50111,
            'status' => FinanceActivationService::STATUS_PENDING,
            'payment_type' => $type,
            'reference_type' => FinanceActivationService::REFERENCE_PROFILE,
            'reference_id' => $profile->id,
            'invoice_number' => 'INV-' . Str::upper(Str::random(8)),
            'created_by' => $profile->user_id,
            'meta' => [],
        ], $overrides));
    }
}
