<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $userRoles = [
            'admin@gmail.com'      => 'admin_pusat',
            'pusat@mpj.id'         => 'admin_pusat',
            'finance@mpj.id'       => 'admin_finance',
            'regional@mpj.id'      => 'admin_regional',
            'test@example.com'     => 'user',
            'user@mpj.id'          => 'user',
            'testuser99@test.com'  => 'user',
        ];

        foreach ($userRoles as $email => $roleEnum) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                continue;
            }

            $roleModel = Role::findByEnum($roleEnum);

            $alreadyExists = UserRole::where('user_id', $user->id)
                ->where('role_id', $roleModel?->id)
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            UserRole::create([
                'id'      => (string) Str::uuid(),
                'user_id' => $user->id,
                'role_id' => $roleModel?->id,
            ]);
        }
    }
}
