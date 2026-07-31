<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    private function template(): array
    {
        $off = ['view' => false, 'create' => false, 'update' => false, 'delete' => false];

        return [
            // User / Admin Media
            'identitas'                      => $off,
            'pembayaran'                     => $off,
            'tim'                            => $off,
            'eid'                            => $off,
            'hub'                            => $off,
            'user-event'                     => $off,

            // Admin Pusat
            'administrasi'                   => $off,
            'master-data'                    => $off,
            'master-regional'                => $off,
            'militansi'                      => $off,
            'mpj-hub'                        => $off,
            'admin-pusat-manajemen-event'    => $off,

            // Admin Regional
            'data-master'                    => $off,
            'validasi-pendaftar'             => $off,
            'laporan'                        => $off,
            'late-payment'                   => $off,
            'download-center'                => $off,
            'admin-regional-manajemen-event' => $off,

            // Admin Finance
            // Nama key diselaraskan dengan yang benar-benar dibaca frontend
            // (CmsLayout + AKSES_KEY_MAP): verifikasi→payment, harga→master-keuangan,
            // clearing→kas. Sebelumnya key backend tidak pernah cocok sehingga
            // akses[key]?.view bernilai undefined dan menu keuangan selalu tampil.
            'payment'                        => $off,
            'laporan-keuangan'               => $off,
            'master-keuangan'                => $off,
            'kas'                            => $off,
            // Tidak punya menu di CmsLayout, tapi masih dirender sebagai toggle di
            // halaman Hak Akses. Tetap diseed eksplisit false supaya tidak undefined.
            'regional-monitoring'            => $off,

            // Super Admin
            'user-management'                => $off,
            'hierarchy'                      => $off,
            'finance'                        => $off,
            'hak-akses'                      => $off,

            // Shared (satu key untuk semua role)
            'pengaturan'                     => $off,
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
        // Menu: Verifikasi, Data Regional, Kelola Event, MPJ Hub, Pengaturan
        // + Super Admin keys
        $adminPusat = $this->template();
        $this->on($adminPusat, [
            'administrasi',           // Verifikasi
            'master-regional',        // Data Regional
            'admin-pusat-manajemen-event', // Kelola Event
            'hub',                    // MPJ Hub
            'pengaturan',
            // Finance detail
            'payment',
            'laporan-keuangan',
            'master-keuangan',
            'kas',
            'regional-monitoring',
            // Super Admin
            'master-data',
            'militansi',
            'mpj-hub',
            'finance',
            'user-management',
            'hierarchy',
            'hak-akses',
        ], $full);

        // ── Admin Wilayah ───────────────────────────────────────────────────
        // Menu: Verifikasi, Data Regional, Kelola Event, MPJ Hub, Pengaturan
        $adminWilayah = $this->template();
        $this->on($adminWilayah, [
            'validasi-pendaftar',            // Verifikasi
            'data-master',                   // Data Regional
            'laporan',                       // sub-fitur Data Regional
            'late-payment',                  // sub-fitur Data Regional
            'download-center',               // sub-fitur Data Regional
            'admin-regional-manajemen-event', // Kelola Event
            'hub',                           // MPJ Hub
            'pengaturan',
        ], $full);

        // ── Admin Keuangan ──────────────────────────────────────────────────
        // Menu: Verifikasi Pembayaran, Laporan, Harga, Clearing, Monitoring, Pengaturan
        $adminKeuangan = $this->template();
        $this->on($adminKeuangan, [
            'payment',             // Verifikasi Pembayaran
            'laporan-keuangan',    // Laporan
            'master-keuangan',     // Harga
            'kas',                 // Clearing
            'regional-monitoring', // Monitoring Regional
            'pengaturan',
        ], $full);

        // ── Koordinator ─────────────────────────────────────────────────────
        $koordinator = $this->template();
        $this->on($koordinator, ['data-master', 'laporan'], $viewOnly);
        $this->on($koordinator, ['admin-regional-manajemen-event'], $noDelete);

        // ── Pengguna Pesantren (Admin Media) ────────────────────────────────
        // Menu: E-ID Card, Profil Pesantren, Administrasi, Kelola Crew, Event, MPJ Hub, Pengaturan
        $penggunaPesantren = $this->template();
        $this->on($penggunaPesantren, [
            'eid',         // E-ID Card
            'identitas',   // Profil Pesantren
            'pembayaran',  // Administrasi
            'tim',         // Kelola Crew
            'user-event',  // Event
            'hub',         // MPJ Hub
            'pengaturan',
        ], $full);

        // ── Kru Pesantren ───────────────────────────────────────────────────
        // Menu: E-ID Card, Profil Crew, Militansi XP, Event, MPJ Hub, Pengaturan
        $kruPesantren = $this->template();
        $this->on($kruPesantren, ['eid'], $full);
        $this->on($kruPesantren, ['tim'], ['view' => true, 'create' => false, 'update' => true, 'delete' => false]);
        $this->on($kruPesantren, ['militansi'], $viewOnly);
        $this->on($kruPesantren, ['user-event'], $viewOnly);
        $this->on($kruPesantren, ['hub'], $viewOnly);
        $this->on($kruPesantren, ['pengaturan'], $full);

        // ── Seed ────────────────────────────────────────────────────────────
        $roles = [
            ['nama' => 'Admin Pusat',         'is_super_admin' => true,  'akses' => $adminPusat],
            ['nama' => 'Admin Regional',        'is_super_admin' => false, 'akses' => $adminWilayah],
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
