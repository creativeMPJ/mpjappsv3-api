<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_resources', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('general');
            $table->enum('resource_type', ['file', 'link'])->default('file');
            $table->string('file_url')->nullable();
            $table->string('external_url')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->json('visibility_scopes')->nullable();
            $table->boolean('is_published')->default(true);
            $table->integer('sort_order')->default(0);
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_resources');
    }
};
