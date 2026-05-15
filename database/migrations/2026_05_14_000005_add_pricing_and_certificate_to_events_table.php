<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'member_price')) {
                $table->unsignedInteger('member_price')->default(0)->after('status');
            }

            if (!Schema::hasColumn('events', 'public_price')) {
                $table->unsignedInteger('public_price')->default(35000)->after('member_price');
            }

            if (!Schema::hasColumn('events', 'certificate_enabled')) {
                $table->boolean('certificate_enabled')->default(true)->after('public_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'certificate_enabled')) {
                $table->dropColumn('certificate_enabled');
            }

            if (Schema::hasColumn('events', 'public_price')) {
                $table->dropColumn('public_price');
            }

            if (Schema::hasColumn('events', 'member_price')) {
                $table->dropColumn('member_price');
            }
        });
    }
};
