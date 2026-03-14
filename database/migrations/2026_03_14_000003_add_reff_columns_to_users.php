<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('reff_type')->nullable()->after('password_hash');
            $table->uuid('reff_id')->nullable()->after('reff_type');
            $table->index(['reff_type', 'reff_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['reff_type', 'reff_id']);
            $table->dropColumn(['reff_type', 'reff_id']);
        });
    }
};
