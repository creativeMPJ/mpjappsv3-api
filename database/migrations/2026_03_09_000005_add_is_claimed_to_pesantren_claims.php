<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pesantren_claims', function (Blueprint $table) {
            $table->boolean('is_claimed')->default(0)->after('regional_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('pesantren_claims', function (Blueprint $table) {
            $table->dropColumn('is_claimed');
        });
    }
};
