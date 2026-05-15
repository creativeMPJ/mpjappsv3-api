<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('crews') && !Schema::hasColumn('crews', 'status')) {
            Schema::table('crews', function (Blueprint $table) {
                $table->enum('status', ['pending', 'active', 'inactive', 'alumni'])
                    ->default('pending')
                    ->after('no_wa');
            });

            DB::table('crews')->whereNotNull('niam')->update(['status' => 'active']);
        }

        if (Schema::hasTable('payments')) {
            DB::statement("ALTER TABLE payments MODIFY status VARCHAR(32) NOT NULL");
            DB::table('payments')->where('status', 'pending_payment')->update(['status' => 'pending']);
            DB::table('payments')->where('status', 'pending_verification')->update(['status' => 'waiting_verification']);

            Schema::table('payments', function (Blueprint $table) {
                if (!Schema::hasColumn('payments', 'payment_type')) {
                    $table->string('payment_type')->default('institution_activation')->after('status');
                }
                if (!Schema::hasColumn('payments', 'reference_type')) {
                    $table->string('reference_type')->nullable()->after('payment_type');
                }
                if (!Schema::hasColumn('payments', 'reference_id')) {
                    $table->uuid('reference_id')->nullable()->after('reference_type');
                }
                if (!Schema::hasColumn('payments', 'invoice_number')) {
                    $table->string('invoice_number')->nullable()->after('reference_id');
                }
                if (!Schema::hasColumn('payments', 'transaction_reference')) {
                    $table->string('transaction_reference')->nullable()->after('invoice_number');
                }
                if (!Schema::hasColumn('payments', 'submitted_at')) {
                    $table->dateTime('submitted_at', 0)->nullable()->after('verified_at');
                }
                if (!Schema::hasColumn('payments', 'expired_at')) {
                    $table->dateTime('expired_at', 0)->nullable()->after('submitted_at');
                }
                if (!Schema::hasColumn('payments', 'cancelled_at')) {
                    $table->dateTime('cancelled_at', 0)->nullable()->after('expired_at');
                }
                if (!Schema::hasColumn('payments', 'rejected_at')) {
                    $table->dateTime('rejected_at', 0)->nullable()->after('cancelled_at');
                }
                if (!Schema::hasColumn('payments', 'created_by')) {
                    $table->uuid('created_by')->nullable()->after('rejected_at');
                }
                if (!Schema::hasColumn('payments', 'rejected_by')) {
                    $table->uuid('rejected_by')->nullable()->after('created_by');
                }
                if (!Schema::hasColumn('payments', 'meta')) {
                    $table->json('meta')->nullable()->after('rejected_by');
                }
            });

            DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','waiting_verification','verified','rejected','expired','cancelled') NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE payments MODIFY pesantren_claim_id CHAR(36) NULL");

            DB::table('payments')
                ->whereNull('payment_type')
                ->update(['payment_type' => 'institution_activation']);

            DB::table('payments')
                ->whereNull('reference_type')
                ->update(['reference_type' => 'profile']);

            DB::table('payments')
                ->whereNull('reference_id')
                ->update(['reference_id' => DB::raw('user_id')]);

            $payments = DB::table('payments')
                ->select('id', 'payment_type', 'invoice_number', 'reference_type', 'reference_id', 'user_id', 'created_by')
                ->get();

            foreach ($payments as $payment) {
                $updates = [];

                if (!$payment->invoice_number) {
                    $prefix = $payment->payment_type === 'crew_activation' ? 'CRA' : 'INA';
                    $updates['invoice_number'] = sprintf(
                        'INV-%s-%s-%04d',
                        $prefix,
                        now()->format('Ymd'),
                        random_int(1000, 9999)
                    );
                }

                if (!$payment->reference_type) {
                    $updates['reference_type'] = 'profile';
                }

                if (!$payment->reference_id) {
                    $updates['reference_id'] = $payment->user_id;
                }

                if (!$payment->created_by) {
                    $updates['created_by'] = $payment->user_id;
                }

                if (!empty($updates)) {
                    DB::table('payments')->where('id', $payment->id)->update($updates);
                }
            }

            Schema::table('payments', function (Blueprint $table) {
                $table->unique('invoice_number');
                $table->index(['payment_type', 'status']);
                $table->index(['reference_type', 'reference_id']);
            });
        }

        if (!Schema::hasTable('payment_logs')) {
            Schema::create('payment_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('payment_id');
                $table->uuid('actor_user_id')->nullable();
                $table->string('action');
                $table->string('from_status')->nullable();
                $table->string('to_status')->nullable();
                $table->text('notes')->nullable();
                $table->json('meta')->nullable();
                $table->dateTime('created_at', 0)->useCurrent();
                $table->dateTime('updated_at', 0)->useCurrent()->useCurrentOnUpdate();
                $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('system_settings') && !DB::table('system_settings')->where('key', 'crew_activation_price')->exists()) {
            DB::table('system_settings')->insert([
                'id' => (string) Illuminate\Support\Str::uuid(),
                'key' => 'crew_activation_price',
                'value' => json_encode(25000),
                'description' => 'Harga default aktivasi kru media',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_logs')) {
            Schema::dropIfExists('payment_logs');
        }
    }
};
