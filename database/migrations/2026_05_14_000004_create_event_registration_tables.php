<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('event_id', 36);
            $table->char('user_id', 36);
            $table->char('profile_id', 36)->nullable();
            $table->char('crew_id', 36)->nullable();
            $table->enum('registration_type', ['member', 'public'])->default('public');
            $table->string('ticket_code')->unique();
            $table->enum('ticket_status', ['pending_payment', 'waiting_verification', 'paid', 'rejected', 'attended', 'cancelled'])->default('paid');
            $table->unsignedInteger('price_amount')->default(0);
            $table->char('payment_id', 36)->nullable();
            $table->string('participant_name');
            $table->string('participant_phone')->nullable();
            $table->string('participant_email')->nullable();
            $table->string('niam')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'user_id']);
            $table->index(['event_id', 'ticket_status']);
        });

        Schema::create('event_checkins', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('event_registration_id', 36);
            $table->char('checked_in_by', 36)->nullable();
            $table->dateTime('checked_in_at', 0)->useCurrent();

            $table->unique('event_registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_checkins');
        Schema::dropIfExists('event_registrations');
    }
};
