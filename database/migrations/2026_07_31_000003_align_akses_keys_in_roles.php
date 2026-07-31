<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Menyelaraskan nama key keuangan pada kolom roles.akses dengan key yang
     * benar-benar dibaca frontend.
     *
     * SEBAB: CmsLayout membaca akses[key]?.view dan menyembunyikan menu hanya
     * jika nilainya === true (atau, khusus finance, jika !== false). Backend
     * menyimpan key 'verifikasi' / 'harga' / 'clearing', sedangkan frontend
     * membaca 'payment' / 'master-keuangan' / 'kas'. Karena key-nya tidak pernah
     * cocok, hasil pembacaan selalu undefined dan menu keuangan tidak pernah bisa
     * dimatikan oleh Super Admin.
     *
     * SEBAB migration ini ada di samping seeder: Super Admin dapat membuat atau
     * mengubah role lewat halaman Hak Akses, jadi baris roles yang sudah ada di
     * database tidak akan ikut terkoreksi hanya dengan menjalankan ulang seeder.
     *
     * CATATAN: key dashboard (user-beranda, admin-*-dashboard) sengaja tidak
     * disentuh karena CmsLayout menampilkannya berdasarkan role, bukan akses.
     */
    private array $keyMap = [
        'verifikasi' => 'payment',
        'harga'      => 'master-keuangan',
        'clearing'   => 'kas',
    ];

    public function up(): void
    {
        $this->applyMap($this->keyMap);
    }

    public function down(): void
    {
        // Kembalikan ke nama key semula supaya rollback tidak meninggalkan
        // database dalam kondisi campuran.
        $this->applyMap(array_flip($this->keyMap));
    }

    /**
     * Menulis ulang seluruh baris roles dengan peta nama key yang diberikan.
     *
     * Aman dijalankan berkali-kali: kalau key sumber sudah tidak ada, hasil
     * remap identik dengan data lama sehingga tidak ada UPDATE yang dikirim.
     */
    private function applyMap(array $map): void
    {
        // SEBAB: migration bisa dijalankan pada database yang belum punya tabel
        // atau kolomnya (mis. saat migrate:fresh dengan urutan berbeda).
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'akses')) {
            return;
        }

        DB::table('roles')->select('id', 'akses')->orderBy('id')->chunk(200, function ($roles) use ($map) {
            foreach ($roles as $role) {
                $akses = $this->decodeAkses($role->akses);

                if ($akses === null) {
                    continue;
                }

                $remapped = $this->remap($akses, $map);

                // Hanya tulis kalau memang berubah, supaya idempoten dan tidak
                // mengotori kolom updated_at tanpa alasan.
                if ($remapped === $akses) {
                    continue;
                }

                DB::table('roles')->where('id', $role->id)->update([
                    // SEBAB: query builder tidak melewati cast 'akses' => 'array'
                    // milik model Role, jadi encode dilakukan manual sekali saja
                    // agar tidak double-encode.
                    'akses' => json_encode($remapped),
                ]);
            }
        });
    }

    /**
     * Membaca kolom akses apa pun bentuk mentahnya (array hasil driver, string
     * JSON, atau string JSON yang terlanjur ter-encode dua kali).
     */
    private function decodeAkses(mixed $raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);

        // SEBAB: data lama bisa saja tersimpan double-encoded (string JSON yang
        // isinya string JSON lagi); satu lapis tambahan cukup untuk kasus itu.
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Mengganti nama key sambil mempertahankan nilai {view,create,update,delete}
     * apa adanya. Key di luar peta dibiarkan utuh, termasuk urutannya.
     */
    private function remap(array $akses, array $map): array
    {
        $result = [];

        foreach ($akses as $key => $value) {
            $target = $map[$key] ?? $key;

            $result[$target] = array_key_exists($target, $result)
                ? $this->pilihPalingLonggar($result[$target], $value)
                : $value;
        }

        return $result;
    }

    /**
     * Dipakai saat key lama dan key baru sama-sama ada pada satu role.
     *
     * SEBAB memilih yang lebih longgar per-permission (OR): kalau yang dipertahankan
     * adalah nilai yang lebih ketat, admin yang selama ini memang bekerja di menu
     * keuangan bisa mendadak kehilangan akses saat deploy, dan itu insiden yang
     * tidak kelihatan sampai ada yang mengeluh. Sebaliknya, kelebihan akses tetap
     * terlihat di halaman Hak Akses dan bisa dicabut Super Admin dalam sekali klik.
     * OR per-permission juga otomatis sama dengan "ambil yang lebih longgar" ketika
     * salah satu sisi memang mendominasi sisi lainnya.
     */
    private function pilihPalingLonggar(mixed $a, mixed $b): mixed
    {
        if (!is_array($a)) {
            return $b;
        }

        if (!is_array($b)) {
            return $a;
        }

        $merged = [];

        // Struktur {view,create,update,delete} dipertahankan: yang di-union hanya
        // key permission yang memang sudah ada di salah satu sisi.
        foreach (array_keys($a + $b) as $perm) {
            $merged[$perm] = !empty($a[$perm]) || !empty($b[$perm]);
        }

        return $merged;
    }
};
