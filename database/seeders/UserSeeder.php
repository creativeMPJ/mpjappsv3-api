<?php

namespace Database\Seeders;

use App\Models\Crew;
use App\Models\PesantrenProfile;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['email' => 'admin@gmail.com',       'role' => 'admin_pusat',    'status_account' => 'active'],
            ['email' => 'pusat@mpj.id',           'role' => 'admin_pusat',    'status_account' => 'active'],
            ['email' => 'finance@mpj.id',         'role' => 'admin_finance',  'status_account' => 'active'],
            ['email' => 'regional@mpj.id',        'role' => 'admin_regional', 'status_account' => 'active'],
            ['email' => 'test@example.com',       'role' => 'user',           'status_account' => 'pending'],
            ['email' => 'user@mpj.id',            'role' => 'user',           'status_account' => 'pending'],
            ['email' => 'testuser99@test.com',    'role' => 'user',           'status_account' => 'pending'],
        ];

        $firstRegion = Region::first();

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                ['id' => (string) Str::uuid(), 'password_hash' => Hash::make('bismillah')]
            );

            $extra = [];
            if ($data['role'] === 'admin_regional' && $firstRegion) {
                $extra['region_id'] = $firstRegion->id;
            }

            PesantrenProfile::firstOrCreate(
                ['id' => $user->id],
                array_merge(['role' => $data['role'], 'status_account' => $data['status_account']], $extra)
            );

            // Untuk role user, buat crew awal dan set reff_type/reff_id
            if ($data['role'] === 'user' && !$user->reff_id) {
                $crew = Crew::firstOrCreate(
                    ['profile_id' => $user->id, 'nama' => 'Pengasuh'],
                    ['id' => (string) Str::uuid()]
                );

                User::where('id', $user->id)->update([
                    'reff_type' => 'crew',
                    'reff_id'   => $crew->id,
                ]);
            }
        }
    }
}
