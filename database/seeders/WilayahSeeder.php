<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WilayahSeeder extends Seeder
{
    const SQL_URL = 'https://raw.githubusercontent.com/edwardsamuel/Wilayah-Administratif-Indonesia/refs/heads/master/mysql/indonesia.sql';

    public function run(): void
    {
        if (Schema::hasTable('regencies') && DB::table('regencies')->exists()) {
            $this->command->info('WilayahSeeder skipped: regencies table already has data.');
            return;
        }

        $this->command->info('Downloading wilayah Indonesia SQL...');

        $sql = @file_get_contents(self::SQL_URL);

        if ($sql === false) {
            $this->command->error('Failed to download wilayah SQL. Check internet connection.');
            $this->command->warn('Run manually: curl -s ' . self::SQL_URL . ' | mysql -h HOST -u USER -p DATABASE --force');
            return;
        }

        $this->command->info('Importing wilayah data (provinces, regencies, districts, villages)...');
        $this->executeSqlDump($sql);

        $count = DB::table('regencies')->count();
        $this->command->info("Wilayah imported: {$count} regencies.");
    }

    private function executeSqlDump(string $sql): void
    {
        DB::beginTransaction();

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            $buffer = '';
            $inSingleQuote = false;
            $inDoubleQuote = false;
            $length = strlen($sql);

            for ($i = 0; $i < $length; $i++) {
                $char = $sql[$i];
                $prev = $i > 0 ? $sql[$i - 1] : null;

                if ($char === "'" && $prev !== '\\' && !$inDoubleQuote) {
                    $inSingleQuote = !$inSingleQuote;
                } elseif ($char === '"' && $prev !== '\\' && !$inSingleQuote) {
                    $inDoubleQuote = !$inDoubleQuote;
                }

                $buffer .= $char;

                if ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
                    $statement = trim($buffer);
                    $buffer = '';

                    $this->executeStatement($statement);
                }
            }

            $remainder = trim($buffer);
            if ($remainder !== '') {
                $this->executeStatement($remainder);
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function executeStatement(string $statement): void
    {
        if ($statement === '' || str_starts_with($statement, '--')) {
            return;
        }

        if (preg_match('/^(CREATE DATABASE|USE\\s)/i', $statement)) {
            return;
        }

        if (preg_match('/^INSERT\s+INTO/i', $statement)) {
            $statement = preg_replace('/^INSERT\s+INTO/i', 'INSERT IGNORE INTO', $statement, 1) ?? $statement;
        }

        DB::unprepared($statement);
    }
}
