<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop any FK attached to profiles.city_id regardless of environment-specific name
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'profiles'
              AND COLUMN_NAME = 'city_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        foreach ($foreignKeys as $foreignKey) {
            DB::statement("ALTER TABLE profiles DROP FOREIGN KEY `{$foreignKey->CONSTRAINT_NAME}`");
        }

        // Drop any remaining plain index on profiles.city_id
        $indexes = DB::select("
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'profiles'
              AND COLUMN_NAME = 'city_id'
              AND INDEX_NAME <> 'PRIMARY'
        ");
        foreach ($indexes as $index) {
            DB::statement("ALTER TABLE profiles DROP INDEX `{$index->INDEX_NAME}`");
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
