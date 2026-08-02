<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pesantren_profiles') || Schema::hasColumn('pesantren_profiles', 'paid_slot_quantity')) {
            return;
        }

        Schema::table('pesantren_profiles', function (Blueprint $table) {
            $table->unsignedInteger('paid_slot_quantity')->default(0)->after('profile_level');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pesantren_profiles') || !Schema::hasColumn('pesantren_profiles', 'paid_slot_quantity')) {
            return;
        }

        Schema::table('pesantren_profiles', function (Blueprint $table) {
            $table->dropColumn('paid_slot_quantity');
        });
    }
};
