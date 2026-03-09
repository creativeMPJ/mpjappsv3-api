<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pesantren_directory')) {
            Schema::create('pesantren_directory', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('nama_pesantren');
                $table->string('nama_pengasuh')->nullable();
                $table->text('alamat')->nullable();
                $table->string('kota_kabupaten')->nullable();
                $table->uuid('region_id')->nullable();
                $table->string('no_wa_admin')->nullable();
                $table->string('email_admin')->nullable();
                $table->text('maps_link')->nullable();
                $table->string('kode_regional', 2)->nullable();
                $table->boolean('is_claimed')->default(false);
                $table->smallInteger('source_year')->nullable();
                $table->timestamps();
            });
        }

        // Add regency_id with utf8mb3 to match regencies table
        if (!Schema::hasColumn('pesantren_directory', 'regency_id')) {
            // No FK — regencies table is populated from external SQL, not migrations
            DB::statement("ALTER TABLE pesantren_directory ADD COLUMN regency_id char(4) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NULL AFTER kota_kabupaten");
        }

        // FK to regions
        Schema::table('pesantren_directory', function (Blueprint $table) {
            $table->foreign('region_id')->references('id')->on('regions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesantren_directory');
    }
};
