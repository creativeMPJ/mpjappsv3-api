<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $file        = base_path('docs/DATA ANGGOTA MPJ FULL - 2025.xlsx');
        $spreadsheet = IOFactory::load($file);
        $sheet       = $spreadsheet->getSheetByName('REKAP');

        $row = 5; // data starts at row 5 (after headers)
        while (true) {
            $no   = $sheet->getCell("A{$row}")->getValue();
            $name = $sheet->getCell("B{$row}")->getValue();

            if (!$no || !$name || strtoupper(trim((string) $no)) === 'TOTAL') {
                break;
            }

            $code = str_pad((int) $no, 2, '0', STR_PAD_LEFT);
            $name = strtoupper(trim((string) $name));

            if (!Region::where('code', $code)->exists()) {
                Region::create([
                    'id'   => (string) Str::uuid(),
                    'name' => $name,
                    'code' => $code,
                ]);
            }

            $row++;
        }

        $this->command->info("Regions seeded: " . Region::count() . " records.");
    }
}
