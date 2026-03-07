<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('email')->unique();
                $table->string('password_hash');
                $table->dateTime('created_at', 0)->useCurrent();
                $table->dateTime('updated_at', 0)->useCurrent()->useCurrentOnUpdate();
            });
        }

        if (!Schema::hasTable('regions')) {
            Schema::create('regions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('code')->unique();
                $table->dateTime('created_at', 0)->useCurrent();
            });
        }

        if (!Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->uuid('region_id');
                $table->dateTime('created_at', 0)->useCurrent();
                $table->foreign('region_id')->references('id')->on('regions');
            });
        }

        if (!Schema::hasTable('profiles')) {
            Schema::create('profiles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->enum('role', ['user', 'admin_regional', 'admin_pusat', 'admin_finance', 'coordinator', 'crew'])->default('user');
                $table->enum('status_account', ['pending', 'active', 'rejected'])->default('pending');
                $table->enum('status_payment', ['paid', 'unpaid', 'expired'])->default('unpaid');
                $table->enum('profile_level', ['basic', 'silver', 'gold', 'platinum'])->default('basic');
                $table->string('nama_pesantren')->nullable();
                $table->string('nama_pengasuh')->nullable();
                $table->string('nama_media')->nullable();
                $table->text('alamat_singkat')->nullable();
                $table->string('no_wa_pendaftar')->nullable();
                $table->string('nip')->nullable();
                $table->uuid('city_id')->nullable();
                $table->uuid('region_id')->nullable();
                $table->string('logo_url')->nullable();
                $table->string('foto_pengasuh_url')->nullable();
                $table->string('sk_pesantren_url')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->integer('jumlah_santri')->nullable();
                $table->string('tipe_pesantren')->nullable();
                $table->json('program_unggulan')->nullable();
                $table->text('sejarah')->nullable();
                $table->text('visi_misi')->nullable();
                $table->json('social_links')->nullable();
                $table->boolean('is_alumni')->default(false);
                $table->string('niam')->nullable()->unique();
                $table->text('alamat_lengkap')->nullable();
                $table->string('kecamatan')->nullable();
                $table->string('desa')->nullable();
                $table->string('kode_pos')->nullable();
                $table->string('maps_link')->nullable();
                $table->string('ketua_media')->nullable();
                $table->string('tahun_berdiri')->nullable();
                $table->integer('jumlah_kru')->nullable();
                $table->string('logo_media_path')->nullable();
                $table->string('foto_gedung_path')->nullable();
                $table->string('website')->nullable();
                $table->string('instagram')->nullable();
                $table->string('facebook')->nullable();
                $table->string('youtube')->nullable();
                $table->string('tiktok')->nullable();
                $table->json('jenjang_pendidikan')->nullable();
                $table->dateTime('created_at', 0)->useCurrent();
                $table->dateTime('updated_at', 0)->useCurrent()->useCurrentOnUpdate();
                $table->foreign('id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('city_id')->references('id')->on('cities');
                $table->foreign('region_id')->references('id')->on('regions');
            });
        }

        if (!Schema::hasTable('user_roles')) {
            Schema::create('user_roles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->enum('role', ['user', 'admin_regional', 'admin_pusat', 'admin_finance', 'coordinator', 'crew'])->default('user');
                $table->dateTime('created_at', 0)->useCurrent();
                $table->index('user_id');
            });
        }

        if (!Schema::hasTable('pesantren_claims')) {
            Schema::create('pesantren_claims', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('pesantren_name');
                $table->enum('jenis_pengajuan', ['klaim', 'pesantren_baru'])->default('pesantren_baru');
                $table->enum('status', ['pending', 'regional_approved', 'pusat_approved', 'approved', 'rejected'])->default('pending');
                $table->uuid('region_id')->nullable();
                $table->string('kecamatan')->nullable();
                $table->string('nama_pengelola')->nullable();
                $table->string('email_pengelola')->nullable();
                $table->string('dokumen_bukti_url')->nullable();
                $table->string('mpj_id_number')->nullable();
                $table->text('notes')->nullable();
                $table->uuid('approved_by')->nullable();
                $table->dateTime('approved_at', 0)->nullable();
                $table->dateTime('regional_approved_at', 0)->nullable();
                $table->dateTime('claimed_at', 0)->useCurrent();
                $table->dateTime('created_at', 0)->useCurrent();
                $table->dateTime('updated_at', 0)->useCurrent()->useCurrentOnUpdate();
                $table->foreign('region_id')->references('id')->on('regions');
                $table->foreign('user_id')->references('id')->on('profiles');
            });
        }

        if (!Schema::hasTable('jabatan_codes')) {
            Schema::create('jabatan_codes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('code');
                $table->text('description')->nullable();
                $table->dateTime('created_at', 0)->useCurrent();
            });
        }

        if (!Schema::hasTable('crews')) {
            Schema::create('crews', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('profile_id');
                $table->string('nama');
                $table->string('jabatan')->nullable();
                $table->uuid('jabatan_code_id')->nullable();
                $table->string('niam')->nullable();
                $table->json('skill')->nullable();
                $table->integer('xp_level')->default(0);
                $table->dateTime('created_at', 0)->useCurrent();
                $table->dateTime('updated_at', 0)->useCurrent()->useCurrentOnUpdate();
                $table->foreign('profile_id')->references('id')->on('profiles');
                $table->foreign('jabatan_code_id')->references('id')->on('jabatan_codes');
            });
        }

        if (!Schema::hasTable('pricing_packages')) {
            Schema::create('pricing_packages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->enum('category', ['registration', 'renewal', 'upgrade']);
                $table->integer('harga_paket');
                $table->integer('harga_diskon')->nullable();
                $table->boolean('is_active')->default(true);
                $table->dateTime('created_at', 0)->useCurrent();
                $table->dateTime('updated_at', 0)->useCurrent()->useCurrentOnUpdate();
            });
        }

        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('pesantren_claim_id');
                $table->uuid('pricing_package_id')->nullable();
                $table->integer('base_amount');
                $table->integer('unique_code');
                $table->integer('total_amount');
                $table->enum('status', ['pending_payment', 'pending_verification', 'verified', 'rejected'])->default('pending_payment');
                $table->string('proof_file_url')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->uuid('verified_by')->nullable();
                $table->dateTime('verified_at', 0)->nullable();
                $table->dateTime('created_at', 0)->useCurrent();
                $table->dateTime('updated_at', 0)->useCurrent()->useCurrentOnUpdate();
                $table->foreign('user_id')->references('id')->on('profiles');
                $table->foreign('pesantren_claim_id')->references('id')->on('pesantren_claims');
                $table->foreign('pricing_package_id')->references('id')->on('pricing_packages');
            });
        }

        if (!Schema::hasTable('otp_verifications')) {
            Schema::create('otp_verifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('user_phone');
                $table->string('otp_code');
                $table->uuid('pesantren_claim_id')->nullable();
                $table->boolean('is_verified')->default(false);
                $table->integer('attempts')->default(0);
                $table->dateTime('expires_at', 0);
                $table->dateTime('verified_at', 0)->nullable();
                $table->dateTime('created_at', 0)->useCurrent();
                $table->foreign('pesantren_claim_id')->references('id')->on('pesantren_claims');
            });
        }

        if (!Schema::hasTable('follow_up_logs')) {
            Schema::create('follow_up_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('admin_id');
                $table->uuid('claim_id');
                $table->uuid('region_id');
                $table->string('action_type')->default('whatsapp_followup');
                $table->dateTime('created_at', 0)->useCurrent();
                $table->foreign('claim_id')->references('id')->on('pesantren_claims');
                $table->foreign('region_id')->references('id')->on('regions');
            });
        }

        if (!Schema::hasTable('password_reset_requests')) {
            Schema::create('password_reset_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('email');
                $table->string('status')->default('pending');
                $table->uuid('processed_by')->nullable();
                $table->dateTime('processed_at', 0)->nullable();
                $table->dateTime('created_at', 0)->useCurrent();
            });
        }

        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key');
                $table->json('value');
                $table->text('description')->nullable();
                $table->dateTime('created_at', 0)->useCurrent();
                $table->dateTime('updated_at', 0)->useCurrent()->useCurrentOnUpdate();
            });
        }

        if (!Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->text('description')->nullable();
                $table->dateTime('date', 0);
                $table->string('location')->nullable();
                $table->string('status')->default('upcoming');
                $table->dateTime('created_at', 0)->useCurrent();
                $table->dateTime('updated_at', 0)->useCurrent()->useCurrentOnUpdate();
            });
        }

        if (!Schema::hasTable('event_reports')) {
            Schema::create('event_reports', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('event_id');
                $table->uuid('region_id');
                $table->integer('participation_count')->default(0);
                $table->text('notes')->nullable();
                $table->string('photo_url')->nullable();
                $table->dateTime('submitted_at', 0)->useCurrent();
                $table->foreign('event_id')->references('id')->on('events');
                $table->foreign('region_id')->references('id')->on('regions');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reports');
        Schema::dropIfExists('events');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('password_reset_requests');
        Schema::dropIfExists('follow_up_logs');
        Schema::dropIfExists('otp_verifications');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('pricing_packages');
        Schema::dropIfExists('crews');
        Schema::dropIfExists('jabatan_codes');
        Schema::dropIfExists('pesantren_claims');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('users');
    }
};
