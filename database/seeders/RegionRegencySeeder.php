<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RegionRegencySeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('regencies')) {
            $this->command->warn('RegionRegencySeeder skipped: regencies table not found. Import wilayah SQL first.');
            return;
        }

        $file        = base_path('docs/DATA ANGGOTA MPJ FULL - 2025.xlsx');
        $spreadsheet = IOFactory::load($file);
        $sheet       = $spreadsheet->getSheetByName('REKAP');

        $regions   = Region::get(['id', 'code'])->keyBy('code');
        $regencies = DB::table('regencies')->get(['id', 'name']);

        $inserted = 0;
        $row = 5;

        while (true) {
            $no        = $sheet->getCell("A{$row}")->getValue();
            $kotaText  = trim((string) $sheet->getCell("C{$row}")->getValue());

            if (!$no || strtoupper(trim((string) $no)) === 'TOTAL') {
                break;
            }

            $code   = str_pad((int) $no, 2, '0', STR_PAD_LEFT);
            $region = $regions->get($code);

            if (!$region || !$kotaText) {
                $row++;
                continue;
            }

            // "Kab./Kota X" → both KABUPATEN X and KOTA X
            $hasBoth  = (bool) preg_match('/Kab\.\/Kota/i', $kotaText);
            $cleaned  = preg_replace('/^Kab\.\/Kota\s*/i', '', $kotaText);
            $parts    = array_map('trim', explode(',', $cleaned));

            foreach ($parts as $part) {
                $candidates = $hasBoth
                    ? $this->matchBoth($regencies, $part)
                    : [$this->matchRegency($regencies, $part)];

                foreach ($candidates as $regencyId) {
                    if (!$regencyId) continue;

                    $exists = DB::table('region_regencies')
                        ->where('region_id', $region->id)
                        ->where('regency_id', $regencyId)
                        ->exists();

                    if (!$exists) {
                        DB::table('region_regencies')->insert([
                            'region_id'  => $region->id,
                            'regency_id' => $regencyId,
                        ]);
                        $inserted++;
                    }
                }
            }

            $row++;
        }

        $this->command->info("RegionRegency seeded: {$inserted} mappings inserted.");
    }

    private function matchBoth($regencies, string $name): array
    {
        $results = [];
        foreach (['KABUPATEN', 'KOTA'] as $prefix) {
            $id = $this->matchRegency($regencies, "{$prefix} {$name}");
            if ($id) $results[] = $id;
        }
        return $results ?: [$this->matchRegency($regencies, $name)];
    }

    private function matchRegency($regencies, string $name): ?string
    {
        $search  = strtoupper(trim($name));
        $isKota  = str_contains($search, 'KOTA');
        $cleaned = preg_replace('/^(KOTA|KAB\.\/KOTA|KAB\.|KABUPATEN)\s*/i', '', $search);
        $cleaned = trim($cleaned);

        // Exact match: "KABUPATEN {name}" or "KOTA {name}"
        foreach ($regencies as $r) {
            $rName    = strtoupper($r->name);
            $rCleaned = preg_replace('/^(KOTA|KABUPATEN)\s+/', '', $rName);

            if ($rCleaned !== $cleaned) continue;

            if ($isKota && str_starts_with($rName, 'KOTA')) return $r->id;
            if (!$isKota && str_starts_with($rName, 'KABUPATEN')) return $r->id;
        }

        // "Kab./Kota X" → try both kota and kabupaten
        foreach ($regencies as $r) {
            $rName    = strtoupper($r->name);
            $rCleaned = preg_replace('/^(KOTA|KABUPATEN)\s+/', '', $rName);
            if ($rCleaned === $cleaned) return $r->id;
        }

        return null;
    }
}
