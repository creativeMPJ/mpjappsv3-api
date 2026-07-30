<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_registrations')) {
            return;
        }

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // FK ke events — wajib, pendaftaran selalu untuk event tertentu
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();

            // Data peserta
            $table->string('nama');
            $table->string('no_whatsapp');
            $table->string('asal_pesantren');   // teks bebas dari form
            $table->text('alamat_pesantren');

            // FK opsional — kalau pesantren asal sudah terdaftar di sistem
            $table->uuid('pesantren_profile_id')->nullable();
            $table->foreign('pesantren_profile_id')
                  ->references('id')
                  ->on('pesantren_profiles')
                  ->nullOnDelete();

            // Kemampuan — enum supaya nilainya terkontrol
            $table->enum('kemampuan_bidang', ['sutradara_script', 'dop_editor', 'keduanya']);
            $table->enum('tingkat_kemampuan', ['basic', 'intermediate', 'advanced']);
            $table->text('pengalaman');
            $table->string('link_karya');

            // Dokumen (path hasil upload ke storage)
            $table->string('surat_delegasi_path')->nullable();
            $table->string('bukti_pembayaran_path')->nullable();

            // Status review panitia
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Sengaja dikosongkan. Tabel event_registrations dibuat oleh migration
        // 2026_05_14_000004; up() di sini hanya jalan kalau tabel belum ada.
        // Drop di sini akan menghapus tabel milik migration lain saat rollback.
    }
};
