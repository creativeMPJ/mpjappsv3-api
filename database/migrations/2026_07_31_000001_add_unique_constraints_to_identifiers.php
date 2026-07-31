<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NIP dan NIAM adalah identitas publik: dipakai di URL direktori
 * (/pesantren/{nip} dan /pesantren/{nip}/crew/{suffix}) serta dicetak di
 * kartu E-ID. Sebelumnya tidak ada satu pun unique constraint, sementara
 * generatornya berbasis count() dan random_int, sehingga duplikat bisa
 * tersimpan diam-diam dan halaman publik menampilkan orang yang salah.
 *
 * Generator sudah diperbaiki agar memakai nomor tertinggi + verifikasi
 * keunikan. Constraint ini adalah jaring pengaman terakhirnya.
 */
return new class extends Migration
{
    /**
     * Kolom nullable: MySQL mengizinkan banyak baris NULL pada unique index,
     * jadi baris yang belum punya identitas tidak terpengaruh.
     */
    private array $targets = [
        ['table' => 'crews',             'column' => 'niam',          'index' => 'crews_niam_unique'],
        ['table' => 'pesantren_profiles', 'column' => 'nip',           'index' => 'pesantren_profiles_nip_unique'],
        ['table' => 'pesantren_claims',   'column' => 'mpj_id_number', 'index' => 'pesantren_claims_mpj_id_number_unique'],
    ];

    public function up(): void
    {
        $this->guardAgainstExistingDuplicates();

        foreach ($this->targets as $target) {
            if (!Schema::hasTable($target['table']) || !Schema::hasColumn($target['table'], $target['column'])) {
                continue;
            }

            Schema::table($target['table'], function (Blueprint $table) use ($target) {
                $table->unique($target['column'], $target['index']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $target) {
            if (!Schema::hasTable($target['table'])) {
                continue;
            }

            Schema::table($target['table'], function (Blueprint $table) use ($target) {
                $table->dropUnique($target['index']);
            });
        }
    }

    /**
     * Menambah unique index pada data yang sudah punya duplikat akan gagal
     * dengan error SQL mentah yang tidak menyebutkan baris mana yang bermasalah.
     * Pemeriksaan ini berhenti lebih dulu dan menyebutkan nilai duplikatnya,
     * supaya datanya bisa dibereskan sebelum migration diulang.
     */
    private function guardAgainstExistingDuplicates(): void
    {
        $problems = [];

        foreach ($this->targets as $target) {
            if (!Schema::hasTable($target['table']) || !Schema::hasColumn($target['table'], $target['column'])) {
                continue;
            }

            $duplicates = DB::table($target['table'])
                ->select($target['column'], DB::raw('COUNT(*) as jumlah'))
                ->whereNotNull($target['column'])
                ->where($target['column'], '!=', '')
                ->groupBy($target['column'])
                ->havingRaw('COUNT(*) > 1')
                ->pluck('jumlah', $target['column']);

            foreach ($duplicates as $value => $jumlah) {
                $problems[] = "{$target['table']}.{$target['column']} = '{$value}' dipakai {$jumlah} baris";
            }
        }

        if (!$problems) {
            return;
        }

        throw new RuntimeException(
            "Migration dibatalkan: masih ada identitas duplikat di database.\n"
            . "Perbaiki baris berikut lebih dulu, lalu jalankan migration ini kembali.\n- "
            . implode("\n- ", $problems)
        );
    }
};
