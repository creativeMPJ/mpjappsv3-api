<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::rename('profiles', 'pesantren_profiles');

        Schema::table('pesantren_profiles', function (Blueprint $table) {
            $table->dropUnique('profiles_niam_unique');
            $table->dropColumn('niam');
        });
    }

    public function down(): void
    {
        Schema::table('pesantren_profiles', function (Blueprint $table) {
            $table->string('niam')->nullable()->unique();
        });

        Schema::rename('pesantren_profiles', 'profiles');
    }
};
