<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop FK if exists
        $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND CONSTRAINT_NAME='profiles_city_id_foreign'");
        if ($fks) {
            DB::statement("ALTER TABLE profiles DROP FOREIGN KEY profiles_city_id_foreign");
        }

        // Drop column if exists (will re-add with correct charset)
        $cols = DB::select("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND COLUMN_NAME='city_id'");
        if ($cols) {
            DB::statement("ALTER TABLE profiles DROP COLUMN city_id");
        }

        // Add column with utf8mb3 to match regencies table
        // No FK constraint — regencies table is populated from external SQL, not migrations
        DB::statement("ALTER TABLE profiles ADD COLUMN city_id char(4) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NULL AFTER nip");

        // Drop old cities table
        Schema::dropIfExists('cities');
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropColumn('city_id');
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->uuid('region_id');
            $table->dateTime('created_at', 0)->useCurrent();
            $table->foreign('region_id')->references('id')->on('regions');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->uuid('city_id')->nullable()->after('nip');
            $table->foreign('city_id')->references('id')->on('cities');
        });
    }
};
