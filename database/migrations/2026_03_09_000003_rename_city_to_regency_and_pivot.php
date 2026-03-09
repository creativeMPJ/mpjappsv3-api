<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Rename profiles.city_id → profiles.regency_id
        $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND CONSTRAINT_NAME='profiles_city_id_foreign'");
        if ($fks) {
            DB::statement("ALTER TABLE profiles DROP FOREIGN KEY profiles_city_id_foreign");
        }

        $cols = DB::select("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND COLUMN_NAME='city_id'");
        if ($cols) {
            // No FK — regencies table is populated from external SQL, not migrations
            DB::statement("ALTER TABLE profiles CHANGE city_id regency_id char(4) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NULL");
        }

        // 2. Create region_regencies pivot table
        if (!Schema::hasTable('region_regencies')) {
            DB::statement("
                CREATE TABLE region_regencies (
                    region_id  char(36) NOT NULL,
                    regency_id char(4)  CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
                    PRIMARY KEY (region_id, regency_id),
                    CONSTRAINT rr_region_id_foreign  FOREIGN KEY (region_id)  REFERENCES regions(id)   ON DELETE CASCADE
                    -- No FK on regency_id — regencies table is populated from external SQL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('region_regencies');

        $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND CONSTRAINT_NAME='profiles_regency_id_foreign'");
        if ($fks) {
            DB::statement("ALTER TABLE profiles DROP FOREIGN KEY profiles_regency_id_foreign");
        }

        $cols = DB::select("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND COLUMN_NAME='regency_id'");
        if ($cols) {
            DB::statement("ALTER TABLE profiles CHANGE regency_id city_id char(4) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NULL");
        }
    }
};
