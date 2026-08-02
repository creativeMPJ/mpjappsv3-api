<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pesantren_claims') || Schema::hasColumn('pesantren_claims', 'pricing_package_id')) {
            return;
        }

        Schema::table('pesantren_claims', function (Blueprint $table) {
            $table->uuid('pricing_package_id')->nullable()->after('regional_approved_at');
            $table->foreign('pricing_package_id')->references('id')->on('pricing_packages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pesantren_claims') || !Schema::hasColumn('pesantren_claims', 'pricing_package_id')) {
            return;
        }

        Schema::table('pesantren_claims', function (Blueprint $table) {
            $table->dropForeign(['pricing_package_id']);
            $table->dropColumn('pricing_package_id');
        });
    }
};
