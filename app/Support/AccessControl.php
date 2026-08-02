<?php

namespace App\Support;

use App\Models\User;

class AccessControl
{
    private const ACTIONS = ['view', 'create', 'update', 'delete'];

    private const ACCESS_KEY_MAP = [
        'user-identitas' => ['identitas'],
        'user-administrasi' => ['pembayaran'],
        'user-tim' => ['tim'],
        'user-eid' => ['eid'],
        'user-event' => ['user-events'],
        'user-hub' => ['hub'],
        'user-pengaturan' => ['pengaturan'],
        'admin-pusat-administrasi' => ['administrasi'],
        'admin-pusat-master-data' => ['master-data'],
        'admin-pusat-master-regional' => ['regional-settings', 'master-regional'],
        'admin-pusat-manajemen-event' => ['pusat-events', 'admin-pusat-manajemen-event'],
        'admin-pusat-manajemen-militansi' => ['militansi'],
        'admin-pusat-mpj-hub' => ['mpj-hub'],
        'admin-pusat-pengaturan' => ['pengaturan'],
        'admin-pusat-event-narasumber' => ['admin-pusat-event-narasumber'],
        'admin-pusat-event-peserta' => ['admin-pusat-event-peserta'],
        'admin-pusat-event-master-data' => ['admin-pusat-event-master-data'],
        'admin-pusat-event-scan' => ['admin-pusat-event-scan'],
        'admin-regional-data-master' => ['regional-data', 'data-master'],
        'admin-regional-validasi-pendaftar' => ['validasi-pendaftar'],
        'admin-regional-manajemen-event' => ['regional-events', 'admin-regional-manajemen-event'],
        'admin-regional-laporan-dokumentasi' => ['laporan'],
        'admin-regional-late-payment' => ['late-payment'],
        'admin-regional-download-center' => ['download-center'],
        'admin-regional-pengaturan' => ['pengaturan'],
        'admin-finance-verifikasi' => ['verifikasi'],
        'admin-finance-laporan' => ['laporan-keuangan'],
        'admin-finance-harga' => ['harga'],
        'admin-finance-clearing' => ['clearing'],
        'admin-finance-regional-monitoring' => ['regional-monitoring'],
        'admin-finance-pengaturan' => ['pengaturan'],
        'finance' => ['finance-dashboard'],
        'super-admin-user-management' => ['user-management'],
        'super-admin-hierarchy' => ['hierarchy'],
        'super-admin-finance' => ['finance', 'finance-dashboard'],
        'super-admin-hak-akses' => ['hak-akses'],
        'super-admin-settings' => ['pengaturan'],
    ];

    private const ACCESS_KEY_ALIASES = [
        'user-event' => ['user-events'],
        'user-events' => ['user-event'],
        'master-regional' => ['regional-settings'],
        'regional-settings' => ['master-regional'],
        'admin-pusat-manajemen-event' => ['pusat-events'],
        'pusat-events' => ['admin-pusat-manajemen-event'],
        'admin-regional-manajemen-event' => ['regional-events'],
        'regional-events' => ['admin-regional-manajemen-event'],
        'data-master' => ['regional-data'],
        'regional-data' => ['data-master'],
        'finance' => ['finance-dashboard'],
        'finance-dashboard' => ['finance'],
    ];

    public static function has(User $user, string $key, string $action = 'view'): bool
    {
        $role = $user->activeRole();
        if (!$role) {
            return false;
        }

        if ($role->is_super_admin) {
            return true;
        }

        $action = in_array($action, self::ACTIONS, true) ? $action : 'view';
        $access = is_array($role->akses) ? $role->akses : [];

        foreach (self::resolveKeys($key) as $candidate) {
            if (($access[$candidate][$action] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    public static function hasAny(User $user, array $keys, string $action = 'view'): bool
    {
        foreach ($keys as $key) {
            if (self::has($user, $key, $action)) {
                return true;
            }
        }

        return false;
    }

    public static function resolveKeys(string $key): array
    {
        $seen = [];
        $queue = [$key];

        while ($queue) {
            $current = array_shift($queue);
            if (!$current || isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;

            foreach (self::ACCESS_KEY_MAP[$current] ?? [] as $candidate) {
                if (!isset($seen[$candidate])) {
                    $queue[] = $candidate;
                }
            }

            foreach (self::ACCESS_KEY_ALIASES[$current] ?? [] as $candidate) {
                if (!isset($seen[$candidate])) {
                    $queue[] = $candidate;
                }
            }
        }

        return array_keys($seen);
    }
}
