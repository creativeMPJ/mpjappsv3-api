<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pricing_packages') || !Schema::hasColumn('pricing_packages', 'category')) {
            return;
        }

        DB::statement("ALTER TABLE pricing_packages MODIFY category VARCHAR(100) NOT NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('pricing_packages') || !Schema::hasColumn('pricing_packages', 'category')) {
            return;
        }

        DB::table('pricing_packages')
            ->whereNotIn('category', ['registration', 'renewal', 'upgrade'])
            ->update(['category' => 'upgrade']);

        DB::statement("ALTER TABLE pricing_packages MODIFY category ENUM('registration','renewal','upgrade') NOT NULL");
    }
};
