<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel events tidak pernah menyimpan wilayah pemiliknya, sehingga
 * PUT /api/events/regional/{id} tidak bisa memverifikasi apakah event yang
 * diubah memang milik wilayah admin yang bersangkutan. Menurunkan kepemilikan
 * dari created_by tidak memadai: kolom itu NULL pada semua event lama, dan
 * ikut berpindah kalau admin pembuatnya dimutasi ke wilayah lain.
 *
 * region_id NULL berarti event nasional (dibuat Admin Pusat) dan tetap
 * terlihat oleh semua wilayah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events') || Schema::hasColumn('events', 'region_id')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->uuid('region_id')->nullable()->after('id');
            $table->index('region_id');
        });

        $this->backfillFromCreatedBy();
    }

    public function down(): void
    {
        if (!Schema::hasTable('events') || !Schema::hasColumn('events', 'region_id')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['region_id']);
            $table->dropColumn('region_id');
        });
    }

    /**
     * Event lama yang punya created_by masih bisa ditelusuri wilayahnya lewat
     * profil pembuatnya. Yang tidak punya created_by dibiarkan NULL dan
     * diperlakukan sebagai event nasional, supaya tidak ada event yang mendadak
     * hilang dari daftar wilayah mana pun.
     */
    private function backfillFromCreatedBy(): void
    {
        if (!Schema::hasColumn('events', 'created_by')) {
            return;
        }

        DB::table('events')
            ->whereNotNull('created_by')
            ->whereNull('region_id')
            ->orderBy('id')
            ->chunkById(200, function ($events) {
                foreach ($events as $event) {
                    $regionId = DB::table('pesantren_profiles')
                        ->where('user_id', $event->created_by)
                        ->value('region_id');

                    if ($regionId) {
                        DB::table('events')->where('id', $event->id)->update(['region_id' => $regionId]);
                    }
                }
            });
    }
};
