<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (!Schema::hasColumn('events', 'title')) {
                    $table->string('title', 500)->nullable()->after('id');
                }
                if (!Schema::hasColumn('events', 'category')) {
                    $table->string('category', 100)->nullable()->after('title');
                }
                if (!Schema::hasColumn('events', 'event_type')) {
                    $table->string('event_type', 100)->nullable()->after('category');
                }
                if (!Schema::hasColumn('events', 'poster_path')) {
                    $table->string('poster_path', 500)->nullable()->after('event_type');
                }
                if (!Schema::hasColumn('events', 'location_name')) {
                    $table->string('location_name', 500)->nullable()->after('location');
                }
                if (!Schema::hasColumn('events', 'location_gmaps')) {
                    $table->string('location_gmaps', 500)->nullable()->after('location_name');
                }
                if (!Schema::hasColumn('events', 'start_date')) {
                    $table->dateTime('start_date')->nullable()->after('date');
                }
                if (!Schema::hasColumn('events', 'registration_deadline')) {
                    $table->dateTime('registration_deadline')->nullable()->after('start_date');
                }
                if (!Schema::hasColumn('events', 'is_open_for_public')) {
                    $table->boolean('is_open_for_public')->default(true)->after('registration_deadline');
                }
                if (!Schema::hasColumn('events', 'is_paid')) {
                    $table->boolean('is_paid')->default(false)->after('is_open_for_public');
                }
                if (!Schema::hasColumn('events', 'price_niam')) {
                    $table->unsignedInteger('price_niam')->default(0)->after('is_paid');
                }
                if (!Schema::hasColumn('events', 'price_public')) {
                    $table->unsignedInteger('price_public')->default(0)->after('price_niam');
                }
                if (!Schema::hasColumn('events', 'max_participants')) {
                    $table->unsignedInteger('max_participants')->nullable()->after('price_public');
                }
                if (!Schema::hasColumn('events', 'current_participants')) {
                    $table->unsignedInteger('current_participants')->default(0)->after('max_participants');
                }
                if (!Schema::hasColumn('events', 'status_pendaftaran')) {
                    $table->string('status_pendaftaran', 32)->default('open')->after('current_participants');
                }
                if (!Schema::hasColumn('events', 'payment_method')) {
                    $table->string('payment_method', 32)->default('manual')->after('status');
                }
                if (!Schema::hasColumn('events', 'gateway_provider')) {
                    $table->string('gateway_provider', 50)->nullable()->after('payment_method');
                }
                if (!Schema::hasColumn('events', 'gateway_config')) {
                    $table->json('gateway_config')->nullable()->after('gateway_provider');
                }
                if (!Schema::hasColumn('events', 'bank_account_id')) {
                    $table->uuid('bank_account_id')->nullable()->after('gateway_config');
                }
                if (!Schema::hasColumn('events', 'speaker_id')) {
                    $table->uuid('speaker_id')->nullable()->after('bank_account_id');
                }
                if (!Schema::hasColumn('events', 'gdrive_lpj')) {
                    $table->string('gdrive_lpj', 500)->nullable()->after('speaker_id');
                }
                if (!Schema::hasColumn('events', 'created_by')) {
                    $table->uuid('created_by')->nullable()->after('gdrive_lpj');
                }
            });
        }

        if (Schema::hasTable('event_registrations')) {
            Schema::table('event_registrations', function (Blueprint $table) {
                if (!Schema::hasColumn('event_registrations', 'guest_id')) {
                    $table->uuid('guest_id')->nullable()->after('crew_id');
                }
                if (!Schema::hasColumn('event_registrations', 'registration_path')) {
                    $table->string('registration_path', 16)->nullable()->after('registration_type');
                }
                if (!Schema::hasColumn('event_registrations', 'qr_token')) {
                    $table->string('qr_token', 100)->nullable()->unique()->after('ticket_code');
                }
                if (!Schema::hasColumn('event_registrations', 'payment_status')) {
                    $table->string('payment_status', 32)->default('Free')->after('ticket_status');
                }
                if (!Schema::hasColumn('event_registrations', 'attendance_status')) {
                    $table->string('attendance_status', 32)->default('Registered')->after('payment_status');
                }
                if (!Schema::hasColumn('event_registrations', 'unique_amount')) {
                    $table->unsignedInteger('unique_amount')->default(0)->after('price_amount');
                }
                if (!Schema::hasColumn('event_registrations', 'payment_proof_path')) {
                    $table->string('payment_proof_path', 500)->nullable()->after('payment_id');
                }
                if (!Schema::hasColumn('event_registrations', 'attended_at')) {
                    $table->dateTime('attended_at')->nullable()->after('payment_proof_path');
                }
                if (!Schema::hasColumn('event_registrations', 'nama')) {
                    $table->string('nama')->nullable()->after('participant_email');
                }
                if (!Schema::hasColumn('event_registrations', 'no_whatsapp')) {
                    $table->string('no_whatsapp', 50)->nullable()->after('nama');
                }
                if (!Schema::hasColumn('event_registrations', 'status')) {
                    $table->string('status', 32)->default('pending')->after('bukti_pembayaran_path');
                }
            });
        }

        if (Schema::hasTable('payments')) {
            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE payments MODIFY user_id CHAR(36) NULL");
            }

            Schema::table('payments', function (Blueprint $table) {
                if (!Schema::hasColumn('payments', 'participant_id')) {
                    $table->uuid('participant_id')->nullable()->after('id');
                }
                if (!Schema::hasColumn('payments', 'amount')) {
                    $table->unsignedInteger('amount')->nullable()->after('total_amount');
                }
                if (!Schema::hasColumn('payments', 'proof_path')) {
                    $table->string('proof_path', 500)->nullable()->after('proof_file_url');
                }
            });
        }

        if (!Schema::hasTable('event_guests')) {
            Schema::create('event_guests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('full_name');
                $table->string('institution_name')->nullable();
                $table->string('whatsapp', 50)->unique();
                $table->string('id_card_path', 500)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('speakers')) {
            Schema::create('speakers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('nama_lengkap');
                $table->text('alamat')->nullable();
                $table->json('keahlian')->nullable();
                $table->string('no_telp', 50)->nullable();
                $table->string('portfolio_url', 500)->nullable();
                $table->string('kategori', 100)->default('Lainnya');
                $table->string('foto_path', 500)->nullable();
                $table->text('bio')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('event_custom_fields')) {
            Schema::create('event_custom_fields', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('event_id');
                $table->string('label');
                $table->string('type', 32);
                $table->json('options')->nullable();
                $table->boolean('is_required')->default(false);
                $table->integer('order_num')->default(0);
                $table->timestamp('created_at')->nullable();
                $table->index('event_id');
            });
        }

        if (!Schema::hasTable('event_attendance_logs')) {
            Schema::create('event_attendance_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('event_id');
                $table->uuid('participant_id');
                $table->string('qr_token', 100);
                $table->string('scanned_by_name')->nullable();
                $table->string('scanner_device')->nullable();
                $table->timestamp('scanned_at');
                $table->boolean('success')->default(true);
                $table->string('failure_reason')->nullable();
                $table->timestamps();
                $table->index(['event_id', 'success']);
                $table->index('qr_token');
            });
        }

        if (!Schema::hasTable('event_finance_transactions')) {
            Schema::create('event_finance_transactions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('event_id');
                $table->string('type', 16);
                $table->string('source', 16)->default('manual');
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('amount')->default(0);
                $table->string('status', 16)->default('posted');
                $table->timestamp('transaction_date')->nullable();
                $table->uuid('participant_id')->nullable();
                $table->uuid('payment_id')->nullable();
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
                $table->index(['event_id', 'status']);
                $table->index(['type', 'source']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_finance_transactions');
        Schema::dropIfExists('event_attendance_logs');
        Schema::dropIfExists('event_custom_fields');
        Schema::dropIfExists('speakers');
        Schema::dropIfExists('event_guests');
    }
};
