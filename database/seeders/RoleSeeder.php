<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    private function template(): array
    {
        return [
            'user-identitas'                     => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'user-administrasi'                  => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-pusat-administrasi'           => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'user-tim'                           => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'user-eid'                           => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'user-event'                         => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-pusat-manajemen-event'        => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-regional-manajemen-event'     => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-regional-validasi-pendaftar'  => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-finance-verifikasi'           => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-regional-laporan-dokumentasi' => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-regional-late-payment'        => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-regional-download-center'     => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-finance-laporan'              => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-pusat-master-data'            => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-pusat-master-regional'        => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-regional-data-master'         => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-finance-harga'                => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-finance-clearing'             => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-finance-regional-monitoring'  => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'super-admin-finance'                => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'user-hub'                           => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-pusat-mpj-hub'                => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-pusat-manajemen-militansi'    => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'super-admin-user-management'        => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'super-admin-hierarchy'              => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'super-admin-hak-akses'              => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'user-pengaturan'                    => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-pusat-pengaturan'             => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-regional-pengaturan'          => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'admin-finance-pengaturan'           => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            'super-admin-settings'               => ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
        ];
    }

    private function on(array &$akses, array $features, array $crud = ['view' => true, 'create' => true, 'update' => true, 'delete' => true]): void
    {
        foreach ($features as $feature) {
            $akses[$feature] = $crud;
        }
    }

    public function run(): void
    {
        $full    = ['view' => true,  'create' => true,  'update' => true,  'delete' => true];
        $viewOnly = ['view' => true, 'create' => false, 'update' => false, 'delete' => false];
        $noDelete = ['view' => true, 'create' => true,  'update' => true,  'delete' => false];

        // ── Admin Pusat ─────────────────────────────────────────────────────
        $adminPusat = $this->template();
        $this->on($adminPusat, [
            'admin-pusat-administrasi',
            'admin-pusat-manajemen-event',
            'admin-pusat-master-data',
            'admin-pusat-master-regional',
            'admin-pusat-mpj-hub',
            'admin-pusat-manajemen-militansi',
            'admin-pusat-pengaturan',
            'super-admin-finance',
            'super-admin-user-management',
            'super-admin-hierarchy',
            'super-admin-hak-akses',
            'super-admin-settings',
        ], $full);

        // ── Admin Wilayah ───────────────────────────────────────────────────
        $adminWilayah = $this->template();
        $this->on($adminWilayah, [
            'admin-regional-manajemen-event',
            'admin-regional-validasi-pendaftar',
            'admin-regional-laporan-dokumentasi',
            'admin-regional-late-payment',
            'admin-regional-download-center',
            'admin-regional-data-master',
            'admin-regional-pengaturan',
        ], $full);

        // ── Admin Keuangan ──────────────────────────────────────────────────
        $adminKeuangan = $this->template();
        $this->on($adminKeuangan, [
            'admin-finance-verifikasi',
            'admin-finance-laporan',
            'admin-finance-harga',
            'admin-finance-clearing',
            'admin-finance-regional-monitoring',
            'admin-finance-pengaturan',
        ], $full);

        // ── Koordinator ─────────────────────────────────────────────────────
        $koordinator = $this->template();
        $this->on($koordinator, ['admin-regional-data-master', 'admin-regional-laporan-dokumentasi'], $viewOnly);
        $this->on($koordinator, ['admin-regional-manajemen-event'], $noDelete);

        // ── Pengguna Pesantren ──────────────────────────────────────────────
        $penggunaPesantren = $this->template();
        $this->on($penggunaPesantren, [
            'user-identitas',
            'user-administrasi',
            'user-tim',
            'user-eid',
            'user-event',
            'user-hub',
            'user-pengaturan',
        ], $full);

        // ── Kru Pesantren ───────────────────────────────────────────────────
        $kruPesantren = $this->template();
        $this->on($kruPesantren, ['user-tim'], ['view' => true, 'create' => false, 'update' => true, 'delete' => false]);
        $this->on($kruPesantren, ['user-event'], $viewOnly);

        // ── Seed ────────────────────────────────────────────────────────────
        $roles = [
            ['nama' => 'Admin Pusat',         'is_super_admin' => true,  'akses' => $adminPusat],
            ['nama' => 'Admin Wilayah',        'is_super_admin' => false, 'akses' => $adminWilayah],
            ['nama' => 'Admin Keuangan',       'is_super_admin' => false, 'akses' => $adminKeuangan],
            ['nama' => 'Koordinator',          'is_super_admin' => false, 'akses' => $koordinator],
            ['nama' => 'Pengguna Pesantren',   'is_super_admin' => false, 'akses' => $penggunaPesantren],
            ['nama' => 'Kru Pesantren',        'is_super_admin' => false, 'akses' => $kruPesantren],
        ];

        foreach ($roles as $data) {
            Role::updateOrCreate(
                ['nama' => $data['nama']],
                [
                    'id'             => Role::where('nama', $data['nama'])->value('id') ?? (string) Str::uuid(),
                    'is_super_admin' => $data['is_super_admin'],
                    'akses'          => $data['akses'],
                ]
            );

            $this->command->info("RoleSeeder: role [{$data['nama']}] siap.");
        }

        $this->command->info('RoleSeeder selesai: ' . count($roles) . ' hak akses berhasil diseed.');
    }
}
