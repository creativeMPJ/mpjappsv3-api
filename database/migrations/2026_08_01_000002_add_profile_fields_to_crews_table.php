<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('crews')) {
            return;
        }

        Schema::table('crews', function (Blueprint $table) {
            if (!Schema::hasColumn('crews', 'nama_panggilan')) {
                $table->string('nama_panggilan')->nullable()->after('nama');
            }
            if (!Schema::hasColumn('crews', 'alamat_asal')) {
                $table->text('alamat_asal')->nullable()->after('no_wa');
            }
            if (!Schema::hasColumn('crews', 'prinsip_hidup')) {
                $table->text('prinsip_hidup')->nullable()->after('alamat_asal');
            }
            if (!Schema::hasColumn('crews', 'photo_url')) {
                $table->string('photo_url')->nullable()->after('prinsip_hidup');
            }
            if (!Schema::hasColumn('crews', 'cv_url')) {
                $table->string('cv_url')->nullable()->after('photo_url');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('crews')) {
            return;
        }

        Schema::table('crews', function (Blueprint $table) {
            foreach (['cv_url', 'photo_url', 'prinsip_hidup', 'alamat_asal', 'nama_panggilan'] as $column) {
                if (Schema::hasColumn('crews', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
