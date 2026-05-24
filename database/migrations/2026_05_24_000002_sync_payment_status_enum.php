<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY status VARCHAR(32) NOT NULL");
        DB::table('payments')->where('status', 'pending_payment')->update(['status' => 'pending']);
        DB::table('payments')->where('status', 'pending_verification')->update(['status' => 'waiting_verification']);
        DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','waiting_verification','verified','rejected','expired','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY status VARCHAR(32) NOT NULL");
        DB::table('payments')->where('status', 'pending')->update(['status' => 'pending_payment']);
        DB::table('payments')->where('status', 'waiting_verification')->update(['status' => 'pending_verification']);
        DB::statement("ALTER TABLE payments MODIFY status ENUM('pending_payment','pending_verification','verified','rejected') NOT NULL DEFAULT 'pending_payment'");
    }
};
