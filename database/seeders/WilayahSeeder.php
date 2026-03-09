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

        // Execute via mysql CLI for reliable handling of large SQL files
        $config   = config('database.connections.mysql');
        $host     = $config['host'];
        $port     = $config['port'];
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];

        $tmpFile = tempnam(sys_get_temp_dir(), 'wilayah_') . '.sql';
        file_put_contents($tmpFile, $sql);

        $passArg = $password ? "-p" . escapeshellarg($password) : '';
        $cmd     = sprintf(
            'mysql -h %s -P %s -u %s %s %s --force < %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            $passArg,
            escapeshellarg($database),
            escapeshellarg($tmpFile)
        );

        exec($cmd, $output, $exitCode);
        unlink($tmpFile);

        if ($exitCode !== 0) {
            $this->command->error('mysql import failed (exit code: ' . $exitCode . ')');
            foreach ($output as $line) {
                $this->command->warn($line);
            }
            return;
        }

        $count = DB::table('regencies')->count();
        $this->command->info("Wilayah imported: {$count} regencies.");
    }
}
