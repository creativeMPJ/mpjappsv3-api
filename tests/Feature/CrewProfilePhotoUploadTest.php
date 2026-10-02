<?php

namespace Tests\Feature;

use App\Models\Crew;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrewProfilePhotoUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['crews', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('reff_type')->nullable();
            $table->uuid('reff_id')->nullable();
        });

        Schema::create('crews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama')->nullable();
            $table->string('photo_url')->nullable();
            $table->timestamps();
        });
    }

    public function test_linked_crew_can_upload_public_profile_photo(): void
    {
        Storage::fake('public');
        [$user, $crew] = $this->linkedCrew();

        $response = $this->actingAs($user, 'api')->post('/api/media/profile-settings/photo', [
            'file' => UploadedFile::fake()->image('profile.jpg')->size(500),
        ]);

        $response->assertOk();
        $photoUrl = $response->json('photoUrl');
        $this->assertIsString($photoUrl);
        $this->assertStringStartsWith("/uploads/crew-photos/{$crew->id}/", $photoUrl);

        $relativePath = ltrim(str_replace('/uploads/', '', $photoUrl), '/');
        Storage::disk('public')->assertExists($relativePath);
        $this->assertSame($photoUrl, $crew->fresh()->photo_url);
        $this->get($photoUrl)->assertOk();
    }

    public function test_replacing_photo_removes_previous_owned_object(): void
    {
        Storage::fake('public');
        [$user, $crew] = $this->linkedCrew();
        $oldPath = "crew-photos/{$crew->id}/old.jpg";
        Storage::disk('public')->put($oldPath, 'old-photo');
        $crew->update(['photo_url' => '/uploads/' . $oldPath]);

        $response = $this->actingAs($user, 'api')->post('/api/media/profile-settings/photo', [
            'file' => UploadedFile::fake()->image('new.jpg')->size(500),
        ])->assertOk();

        $newPath = ltrim(str_replace('/uploads/', '', $response->json('photoUrl')), '/');
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_unlinked_user_cannot_upload_crew_photo(): void
    {
        Storage::fake('public');
        $user = User::create([
            'id' => (string) Str::uuid(),
            'email' => 'unlinked@example.test',
            'password_hash' => 'secret',
        ]);

        $this->actingAs($user, 'api')->post('/api/media/profile-settings/photo', [
            'file' => UploadedFile::fake()->image('profile.jpg')->size(500),
        ])->assertNotFound()->assertJsonPath('message', 'Profil kru tidak ditemukan');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_storage_failure_keeps_previous_photo(): void
    {
        [$user, $crew] = $this->linkedCrew();
        $crew->update(['photo_url' => '/uploads/crew-photos/old.jpg']);
        config(['filesystems.disks.public.root' => '/dev/null/crew-photos']);
        Storage::forgetDisk('public');

        $this->actingAs($user, 'api')->post('/api/media/profile-settings/photo', [
            'file' => UploadedFile::fake()->image('profile.jpg')->size(500),
        ])->assertStatus(500)->assertJsonPath('message', 'Foto gagal disimpan. Silakan coba lagi.');

        $this->assertSame('/uploads/crew-photos/old.jpg', $crew->fresh()->photo_url);
    }

    private function linkedCrew(): array
    {
        $crew = Crew::create([
            'id' => (string) Str::uuid(),
            'nama' => 'Crew Test',
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => 'secret',
            'reff_type' => 'crew',
            'reff_id' => $crew->id,
        ]);

        return [$user, $crew];
    }
}
