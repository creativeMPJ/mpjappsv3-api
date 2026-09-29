<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateAccess(true);
    }

    public function down(): void
    {
        $this->updateAccess(false);
    }

    private function updateAccess(bool $grant): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'akses')) {
            return;
        }

        $role = DB::table('roles')->where('nama', 'Admin Keuangan')->first();
        if (! $role) {
            return;
        }

        $akses = json_decode($role->akses, true);
        if (! is_array($akses)) {
            return;
        }

        if ($grant) {
            $current = is_array($akses['finance'] ?? null) ? $akses['finance'] : [];
            $akses['finance'] = array_merge([
                'view' => false,
                'create' => false,
                'update' => false,
                'delete' => false,
            ], $current, ['view' => true]);
        } else {
            unset($akses['finance']);
        }

        DB::table('roles')->where('id', $role->id)->update([
            'akses' => json_encode($akses),
        ]);
    }
};
