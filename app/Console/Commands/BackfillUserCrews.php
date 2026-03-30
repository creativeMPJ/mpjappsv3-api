<?php

namespace App\Console\Commands;

use App\Models\Crew;
use App\Models\PesantrenProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillUserCrews extends Command
{
    protected $signature   = 'app:backfill-user-crews';
    protected $description = 'Buat crew record untuk user lama yang belum punya reff_id';

    public function handle(): void
    {
        $users = User::whereNull('reff_id')
            ->whereHas('profile', fn($q) => $q->where('role', 'user'))
            ->with('profile')
            ->get();

        $this->info("Ditemukan {$users->count()} user tanpa reff_id.");

        $created = 0;

        foreach ($users as $user) {
            $profile = $user->profile;

            $crew = Crew::create([
                'id'         => (string) Str::uuid(),
                'profile_id' => $user->id,
                'nama'       => $profile->nama_pengasuh ?? 'Pengasuh',
                'no_wa'      => $profile->no_wa_pendaftar ?? null,
            ]);

            User::where('id', $user->id)->update([
                'reff_type' => 'crew',
                'reff_id'   => $crew->id,
            ]);

            $created++;
        }

        $this->info("Selesai. {$created} crew record dibuat.");
    }
}
