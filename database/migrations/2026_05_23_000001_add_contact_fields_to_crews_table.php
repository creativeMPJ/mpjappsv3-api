<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crews', function (Blueprint $table) {
            if (!Schema::hasColumn('crews', 'email')) {
                $table->string('email')->nullable()->after('nama');
            }

            if (!Schema::hasColumn('crews', 'jabatan_media')) {
                $table->string('jabatan_media')->nullable()->after('jabatan');
            }

            if (!Schema::hasColumn('crews', 'catatan')) {
                $table->text('catatan')->nullable()->after('skill');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crews', function (Blueprint $table) {
            if (Schema::hasColumn('crews', 'catatan')) {
                $table->dropColumn('catatan');
            }

            if (Schema::hasColumn('crews', 'jabatan_media')) {
                $table->dropColumn('jabatan_media');
            }

            if (Schema::hasColumn('crews', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
