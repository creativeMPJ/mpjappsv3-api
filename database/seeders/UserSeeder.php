<?php

namespace Database\Seeders;

use App\Models\Crew;
use App\Models\PesantrenProfile;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $regions = Region::orderBy('code')->get()->values();

        $users = [
            ['email' => 'admin@gmail.com',       'role' => 'admin_pusat',    'status_account' => 'active', 'profile_level' => 'platinum'],
            ['email' => 'pusat@mpj.id',           'role' => 'admin_pusat',    'status_account' => 'active'],
            ['email' => 'finance@mpj.id',         'role' => 'admin_finance',  'status_account' => 'active'],
            ['email' => 'regional@mpj.id',        'role' => 'admin_regional', 'status_account' => 'active'],
            [
                'email' => 'test@example.com',
                'role' => 'user',
                'status_account' => 'pending',
                'status_payment' => 'unpaid',
                'profile_level' => 'basic',
                'nama_pesantren' => 'PP Nurul Uji',
                'nama_pengasuh' => 'KH. Ahmad Uji',
                'nama_media' => 'Media Nurul Uji',
                'alamat_singkat' => 'Jombang',
                'no_wa_pendaftar' => '081230000001',
                'region_index' => 0,
                'crew_nama' => 'Faris Uji',
                'crew_jabatan' => 'Koordinator',
                'crew_status' => 'pending',
                'crew_xp' => 120,
            ],
            [
                'email' => 'user@mpj.id',
                'role' => 'user',
                'status_account' => 'active',
                'status_payment' => 'paid',
                'profile_level' => 'platinum',
                'nama_pesantren' => 'PP Miftahul Browser',
                'nama_pengasuh' => 'KH. Hasyim Browser',
                'nama_media' => 'Miftahul Creative',
                'alamat_singkat' => 'Malang',
                'no_wa_pendaftar' => '081230000002',
                'nip' => '2601001',
                'region_index' => 1,
                'crew_nama' => 'Laila Browser',
                'crew_jabatan' => 'Koordinator',
                'crew_status' => 'active',
                'crew_niam' => '260100101',
                'crew_xp' => 2450,
            ],
            [
                'email' => 'testuser99@test.com',
                'role' => 'user',
                'status_account' => 'active',
                'status_payment' => 'unpaid',
                'profile_level' => 'silver',
                'nama_pesantren' => 'PP Darul Uji',
                'nama_pengasuh' => 'KH. Maulana Uji',
                'nama_media' => 'Darul Visual',
                'alamat_singkat' => 'Blitar',
                'no_wa_pendaftar' => '081230000003',
                'region_index' => 2,
                'crew_nama' => 'Rizki Darul',
                'crew_jabatan' => 'Editor',
                'crew_status' => 'pending',
                'crew_xp' => 640,
            ],
        ];

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                ['id' => (string) Str::uuid(), 'password_hash' => Hash::make('bismillah')]
            );

            $region = null;
            if (isset($data['region_index'])) {
                $region = $regions->get($data['region_index']) ?? $regions->first();
            } elseif ($data['role'] === 'admin_regional') {
                $region = $regions->first();
            }

            $profile = PesantrenProfile::firstOrCreate(['user_id' => $user->id], [
                'id' => (string) Str::uuid(),
            ]);

            $profile->fill(array_filter([
                'status_account' => $data['status_account'],
                'status_payment' => $data['status_payment'] ?? 'unpaid',
                'profile_level' => $data['profile_level'] ?? 'basic',
                'nama_pesantren' => $data['nama_pesantren'] ?? null,
                'nama_pengasuh' => $data['nama_pengasuh'] ?? null,
                'nama_media' => $data['nama_media'] ?? null,
                'alamat_singkat' => $data['alamat_singkat'] ?? null,
                'no_wa_pendaftar' => $data['no_wa_pendaftar'] ?? null,
                'nip' => $data['nip'] ?? null,
                'region_id' => $region?->id,
                'jumlah_kru' => $data['role'] === 'user' ? 1 : null,
            ], fn ($value) => $value !== null));
            $profile->save();

            // Assign role via user_roles
            $roleModel = Role::findByEnum($data['role']);
            $existingRole = UserRole::where('user_id', $user->id)->first();
            if (!$existingRole) {
                UserRole::create([
                    'id'      => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'role_id' => $roleModel?->id,
                ]);
            }

            // Untuk role user, buat crew awal dan set reff_type/reff_id
            if ($data['role'] === 'user') {
                $crew = Crew::firstOrCreate(
                    ['profile_id' => $profile->id],
                    ['id' => (string) Str::uuid()]
                );

                $crew->fill(array_filter([
                    'nama' => $data['crew_nama'] ?? 'Pengasuh',
                    'jabatan' => $data['crew_jabatan'] ?? 'Koordinator',
                    'status' => $data['crew_status'] ?? 'pending',
                    'niam' => $data['crew_niam'] ?? null,
                    'xp_level' => $data['crew_xp'] ?? 0,
                    'no_wa' => $data['no_wa_pendaftar'] ?? null,
                ], fn ($value) => $value !== null));
                $crew->save();

                User::where('id', $user->id)->update([
                    'reff_type' => 'crew',
                    'reff_id' => $crew->id,
                ]);
            }
        }
    }
}
