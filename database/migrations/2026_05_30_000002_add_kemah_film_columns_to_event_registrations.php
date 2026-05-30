<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('asal_pesantren')->nullable()->after('participant_email');
            $table->text('alamat_pesantren')->nullable()->after('asal_pesantren');
            $table->uuid('pesantren_profile_id')->nullable()->after('alamat_pesantren');
            $table->enum('kemampuan_bidang', ['sutradara_script', 'dop_editor', 'keduanya'])->nullable()->after('pesantren_profile_id');
            $table->enum('tingkat_kemampuan', ['basic', 'intermediate', 'advanced'])->nullable()->after('kemampuan_bidang');
            $table->text('pengalaman')->nullable()->after('tingkat_kemampuan');
            $table->string('link_karya')->nullable()->after('pengalaman');
            $table->string('surat_delegasi_path')->nullable()->after('link_karya');
            $table->string('bukti_pembayaran_path')->nullable()->after('surat_delegasi_path');
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
