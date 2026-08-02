<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_speakers')) {
            return;
        }

        Schema::create('event_speakers', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('event_id', 36);
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('phone')->nullable();
            $table->string('photo_url')->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();

            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_speakers');
    }
};
