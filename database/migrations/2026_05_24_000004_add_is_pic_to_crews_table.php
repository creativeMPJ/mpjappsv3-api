<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crews', function (Blueprint $table) {
            $table->boolean('is_pic')->default(false)->after('status');
        });

        // Tandai crew paling lama di setiap pesantren sebagai PIC
        DB::statement("
            UPDATE crews c
            INNER JOIN (
                SELECT profile_id, MIN(created_at) AS first_created
                FROM crews
                GROUP BY profile_id
            ) AS first ON c.profile_id = first.profile_id AND c.created_at = first.first_created
            SET c.is_pic = 1
        ");
    }

    public function down(): void
    {
        Schema::table('crews', function (Blueprint $table) {
            $table->dropColumn('is_pic');
        });
    }
};
