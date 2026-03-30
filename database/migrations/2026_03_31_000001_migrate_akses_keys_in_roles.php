<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Mapping lama → baru.
     * Key yang tidak ada di sini tidak diubah.
     * Key "pengaturan" diambil dari salah satu key lama yang nilainya true,
     * atau false jika semua false.
     */
    private array $keyMap = [
        // User / Admin Media
        'user-identitas'                     => 'identitas',
        'user-administrasi'                  => 'pembayaran',
        'user-tim'                           => 'tim',
        'user-eid'                           => 'eid',
        'user-hub'                           => 'hub',
        // user-event → tidak berubah

        // Admin Pusat
        'admin-pusat-administrasi'           => 'administrasi',
        'admin-pusat-master-data'            => 'master-data',
        'admin-pusat-master-regional'        => 'master-regional',
        'admin-pusat-manajemen-militansi'    => 'militansi',
        'admin-pusat-mpj-hub'                => 'mpj-hub',
        // admin-pusat-manajemen-event → tidak berubah

        // Admin Regional
        'admin-regional-data-master'         => 'data-master',
        'admin-regional-validasi-pendaftar'  => 'validasi-pendaftar',
        'admin-regional-laporan-dokumentasi' => 'laporan',
        'admin-regional-late-payment'        => 'late-payment',
        'admin-regional-download-center'     => 'download-center',
        // admin-regional-manajemen-event → tidak berubah

        // Admin Finance
        'admin-finance-verifikasi'           => 'verifikasi',
        'admin-finance-laporan'              => 'laporan-keuangan',
        'admin-finance-harga'                => 'harga',
        'admin-finance-clearing'             => 'clearing',
        'admin-finance-regional-monitoring'  => 'regional-monitoring',

        // Super Admin
        'super-admin-user-management'        => 'user-management',
        'super-admin-hierarchy'              => 'hierarchy',
        'super-admin-finance'                => 'finance',
        'super-admin-hak-akses'              => 'hak-akses',
    ];

    /** Key-key lama yang semuanya di-merge jadi satu key 'pengaturan'. */
    private array $pengaturanOldKeys = [
        'user-pengaturan',
        'admin-pusat-pengaturan',
        'admin-regional-pengaturan',
        'admin-finance-pengaturan',
        'super-admin-settings',
    ];

    public function up(): void
    {
        $roles = DB::table('roles')->get();

        foreach ($roles as $role) {
            $akses = json_decode($role->akses, true);

            if (!is_array($akses)) {
                continue;
            }

            $migrated = [];

            foreach ($akses as $oldKey => $value) {
                if (isset($this->keyMap[$oldKey])) {
                    // Rename ke key baru
                    $migrated[$this->keyMap[$oldKey]] = $value;
                } elseif (!in_array($oldKey, $this->pengaturanOldKeys)) {
                    // Key tidak berubah (user-event, admin-pusat-manajemen-event, dll)
                    $migrated[$oldKey] = $value;
                }
                // Key pengaturan lama di-skip di sini, diproses di bawah
            }

            // Merge semua key pengaturan lama → satu key 'pengaturan'
            // Ambil nilai true jika salah satu lama bernilai true
            $pengaturanValue = ['view' => false, 'create' => false, 'update' => false, 'delete' => false];
            foreach ($this->pengaturanOldKeys as $oldKey) {
                if (isset($akses[$oldKey]) && is_array($akses[$oldKey])) {
                    foreach (['view', 'create', 'update', 'delete'] as $perm) {
                        if (!empty($akses[$oldKey][$perm])) {
                            $pengaturanValue[$perm] = true;
                        }
                    }
                }
            }
            $migrated['pengaturan'] = $pengaturanValue;

            DB::table('roles')->where('id', $role->id)->update([
                'akses' => json_encode($migrated),
            ]);
        }
    }

    public function down(): void
    {
        // Reverse: rename key baru → lama
        // Untuk pengaturan: restore ke key lama yang relevan berdasarkan role nama
        $reverseMap = array_flip($this->keyMap);

        // Key yang tidak unik setelah flip (pengaturan) ditangani manual
        // Untuk down(), kita tidak bisa tahu pengaturan lama mana yang relevan
        // tanpa mengetahui role-nya — jadi kita restore ke semua key lama dengan nilai yang sama

        $roles = DB::table('roles')->get();

        foreach ($roles as $role) {
            $akses = json_decode($role->akses, true);

            if (!is_array($akses)) {
                continue;
            }

            $restored = [];

            foreach ($akses as $newKey => $value) {
                if ($newKey === 'pengaturan') {
                    // Restore ke semua key lama
                    foreach ($this->pengaturanOldKeys as $oldKey) {
                        $restored[$oldKey] = $value;
                    }
                } elseif (isset($reverseMap[$newKey])) {
                    $restored[$reverseMap[$newKey]] = $value;
                } else {
                    $restored[$newKey] = $value;
                }
            }

            DB::table('roles')->where('id', $role->id)->update([
                'akses' => json_encode($restored),
            ]);
        }
    }
};
