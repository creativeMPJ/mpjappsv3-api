<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->char('user_id', 36)->nullable()->change();
            $table->char('profile_id', 36)->nullable()->change();
            $table->char('crew_id', 36)->nullable()->change();
            $table->string('ticket_code')->nullable()->change();
            $table->unsignedInteger('price_amount')->nullable()->change();
            $table->char('payment_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Tidak di-revert karena data lama sudah ada
    }
};
