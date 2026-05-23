<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regional_reports', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('region_id', 36);
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('report_date')->nullable();
            $table->string('file_url');
            $table->enum('status', ['submitted', 'reviewed'])->default('submitted');
            $table->char('created_by', 36)->nullable();
            $table->timestamps();
        });

        Schema::create('militansi_levels', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name');
            $table->integer('min_xp')->default(0);
            $table->string('color', 20)->default('#94a3b8');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('militansi_rules', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('action_key')->unique();
            $table->string('label');
            $table->integer('xp_value')->default(0);
            $table->enum('limit_type', ['no_limit', 'daily', 'once'])->default('no_limit');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('militansi_levels')->insert([
            ['id' => (string) Str::uuid(), 'name' => 'Muhibbin', 'min_xp' => 0, 'color' => '#94a3b8', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'name' => 'Penggerak', 'min_xp' => 500, 'color' => '#22c55e', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'name' => 'Aktivis', 'min_xp' => 1500, 'color' => '#3b82f6', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'name' => 'Khodim', 'min_xp' => 3000, 'color' => '#8b5cf6', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'name' => 'Tokoh', 'min_xp' => 5000, 'color' => '#f59e0b', 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('militansi_rules')->insert([
            ['id' => (string) Str::uuid(), 'action_key' => 'attend_event_regional', 'label' => 'Hadir Event Regional', 'xp_value' => 10, 'limit_type' => 'no_limit', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'action_key' => 'attend_event_nasional', 'label' => 'Hadir Event Nasional', 'xp_value' => 25, 'limit_type' => 'no_limit', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'action_key' => 'upload_konten_dakwah', 'label' => 'Upload Konten Dakwah', 'xp_value' => 5, 'limit_type' => 'daily', 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'action_key' => 'submit_regional_report', 'label' => 'Kirim Laporan Regional', 'xp_value' => 30, 'limit_type' => 'no_limit', 'is_active' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('militansi_rules');
        Schema::dropIfExists('militansi_levels');
        Schema::dropIfExists('regional_reports');
    }
};
