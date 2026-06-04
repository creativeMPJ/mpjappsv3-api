<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('event_registrations', 'asal_pesantren')) {
                $table->string('asal_pesantren')->nullable()->after('participant_email');
            }
            if (!Schema::hasColumn('event_registrations', 'alamat_pesantren')) {
                $table->text('alamat_pesantren')->nullable()->after('asal_pesantren');
            }
            if (!Schema::hasColumn('event_registrations', 'pesantren_profile_id')) {
                $table->uuid('pesantren_profile_id')->nullable()->after('alamat_pesantren');
            }
            if (!Schema::hasColumn('event_registrations', 'kemampuan_bidang')) {
                $table->enum('kemampuan_bidang', ['sutradara_script', 'dop_editor', 'keduanya'])->nullable()->after('pesantren_profile_id');
            }
            if (!Schema::hasColumn('event_registrations', 'tingkat_kemampuan')) {
                $table->enum('tingkat_kemampuan', ['basic', 'intermediate', 'advanced'])->nullable()->after('kemampuan_bidang');
            }
            if (!Schema::hasColumn('event_registrations', 'pengalaman')) {
                $table->text('pengalaman')->nullable()->after('tingkat_kemampuan');
            }
            if (!Schema::hasColumn('event_registrations', 'link_karya')) {
                $table->string('link_karya')->nullable()->after('pengalaman');
            }
            if (!Schema::hasColumn('event_registrations', 'surat_delegasi_path')) {
                $table->string('surat_delegasi_path')->nullable()->after('link_karya');
            }
            if (!Schema::hasColumn('event_registrations', 'bukti_pembayaran_path')) {
                $table->string('bukti_pembayaran_path')->nullable()->after('surat_delegasi_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'asal_pesantren',
                'alamat_pesantren',
                'pesantren_profile_id',
                'kemampuan_bidang',
                'tingkat_kemampuan',
                'pengalaman',
                'link_karya',
                'surat_delegasi_path',
                'bukti_pembayaran_path',
            ]);
        });
    }
};
