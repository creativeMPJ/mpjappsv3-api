<?php

namespace Database\Seeders;

use App\Models\PesantrenProfile;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegionalAdminSeeder extends Seeder
{
    public function run(): void
    {
        $regions = Region::orderBy('code')->get();

        if ($regions->isEmpty()) {
            $this->command->warn('RegionalAdminSeeder skipped: no regions found.');
            return;
        }

        $regionalRole = Role::findByEnum('admin_regional');
        $created      = 0;

        foreach ($regions as $region) {
            $code  = strtolower(str_pad($region->code, 2, '0', STR_PAD_LEFT));
            $email = "regional.{$code}@gmail.com";

            $user = User::firstOrCreate(
                ['email' => $email],
                ['id' => (string) Str::uuid(), 'password_hash' => Hash::make('bismillah')]
            );

            PesantrenProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'id'             => (string) Str::uuid(),
                    'status_account' => 'active',
                    'region_id'      => $region->id,
                ]
            );

            $exists = UserRole::where('user_id', $user->id)->where('role_id', $regionalRole?->id)->exists();
            if (!$exists) {
                UserRole::create([
                    'id'      => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'role_id' => $regionalRole?->id,
                ]);
            }

            $created++;
        }

        $this->command->info("RegionalAdminSeeder: {$created} regional admins seeded.");
        $this->command->info('Format login: regional.{kode}@gmail.com | password: bismillah');
    }
}
