<?php

namespace Database\Seeders;

use App\Models\PesantrenDirectory;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PesantrenDirectorySeeder extends Seeder
{
    // Sheet names to skip (non-regional sheets)
    private const SKIP_SHEETS = ['REKAP'];

    public function run(): void
    {
        if (!Schema::hasTable('regencies')) {
            $this->command->warn('PesantrenDirectorySeeder skipped: regencies table not found. Import wilayah SQL first.');
            return;
        }

        $file        = base_path('docs/DATA ANGGOTA MPJ FULL - 2025.xlsx');
        $spreadsheet = IOFactory::load($file);

        // Cache regions by code for fast lookup
        $regions = Region::get(['id', 'code'])->keyBy('code');

        // Cache regencies for fuzzy lookup (uppercase name → id)
        $regencies = DB::table('regencies')->get(['id', 'name']);

        $total   = 0;
        $skipped = 0;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetName = $sheet->getTitle();

            if (in_array($sheetName, self::SKIP_SHEETS)) {
                continue;
            }

            $row = 2; // data starts at row 2
            while (true) {
                $tahun         = $sheet->getCell("A{$row}")->getValue();
                $kodeRegional  = $sheet->getCell("B{$row}")->getValue();
                $namaPesantren = trim((string) $sheet->getCell("E{$row}")->getValue());
                $alamat        = trim((string) $sheet->getCell("F{$row}")->getValue());
                $kotaKab       = trim((string) $sheet->getCell("G{$row}")->getValue());
                $namaPengasuh  = trim((string) $sheet->getCell("H{$row}")->getValue());
                $noWa          = $sheet->getCell("K{$row}")->getValue();
                $mapsLink      = trim((string) $sheet->getCell("M{$row}")->getValue());
                $emailAdmin    = trim((string) $sheet->getCell("N{$row}")->getValue());

                // Stop if no pesantren name found
                if (!$namaPesantren) {
                    break;
                }

                $kode = str_pad((int) $kodeRegional, 2, '0', STR_PAD_LEFT);

                // Skip duplicates
                $exists = PesantrenDirectory::where('nama_pesantren', $namaPesantren)
                    ->where('kode_regional', $kode)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    $row++;
                    continue;
                }

                // Lookup region_id
                $regionId = $regions->get($kode)?->id;

                // Lookup regency_id by fuzzy match
                $regencyId = $this->matchRegency($regencies, $kotaKab);

                // Normalize phone number
                $phone = $this->normalizePhone($noWa);

                PesantrenDirectory::create([
                    'id'             => (string) Str::uuid(),
                    'nama_pesantren' => $namaPesantren,
                    'nama_pengasuh'  => $namaPengasuh ?: null,
                    'alamat'         => $alamat ?: null,
                    'kota_kabupaten' => $kotaKab ?: null,
                    'regency_id'     => $regencyId,
                    'region_id'      => $regionId,
                    'no_wa_admin'    => $phone,
                    'email_admin'    => $emailAdmin ?: null,
                    'maps_link'      => $mapsLink ?: null,
                    'kode_regional'  => $kode,
                    'source_year'    => $tahun ? (int) $tahun : null,
                ]);

                $total++;
                $row++;
            }
        }

        $this->command->info("PesantrenDirectory seeded: {$total} inserted, {$skipped} skipped.");
    }

    private function matchRegency($regencies, string $kotaKab): ?string
    {
        if (!$kotaKab) return null;

        $search = strtoupper(trim($kotaKab));
        $isKota = str_contains($search, 'KOTA');

        // Extract main city name (remove "Kab." / "Kota" prefix)
        $mainName = preg_replace('/^(KAB\.|KABUPATEN|KOTA)\s*/i', '', $search);
        $mainName = trim($mainName);

        foreach ($regencies as $r) {
            $rName = strtoupper($r->name);

            if ($isKota) {
                if (str_contains($rName, 'KOTA') && str_contains($rName, strtoupper($mainName))) {
                    return $r->id;
                }
            } else {
                if (!str_contains($rName, 'KOTA') && str_contains($rName, strtoupper($mainName))) {
                    return $r->id;
                }
            }
        }

        return null;
    }

    private function normalizePhone($value): ?string
    {
        if (!$value) return null;

        $phone = preg_replace('/\D/', '', (string) $value);

        if (!$phone) return null;

        // Convert float-like values (e.g. 81234567890.0)
        $phone = rtrim($phone, '0') ?: $phone;

        // Ensure starts with 62
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }
}
