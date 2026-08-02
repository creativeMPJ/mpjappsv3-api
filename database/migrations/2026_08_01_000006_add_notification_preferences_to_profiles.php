<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesantren_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('pesantren_profiles', 'notification_preferences')) {
                $table->json('notification_preferences')->nullable()->after('social_links');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pesantren_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('pesantren_profiles', 'notification_preferences')) {
                $table->dropColumn('notification_preferences');
            }
        });
    }
};
