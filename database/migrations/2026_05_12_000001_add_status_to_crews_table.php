<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crews', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'inactive', 'alumni'])
                ->default('pending')
                ->after('no_wa');
        });

        DB::table('crews')
            ->whereNotNull('niam')
            ->update(['status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('crews', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
