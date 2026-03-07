<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetRequest;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|min:6',
            'namaPesantren' => 'nullable|string',
            'namaPengasuh'  => 'nullable|string',
        ]);

        $email = strtolower($data['email']);

        $user = User::create([
            'id'            => Str::uuid(),
            'email'         => $email,
            'password_hash' => Hash::make($data['password']),
        ]);

        Profile::create([
            'id'             => $user->id,
            'role'           => 'user',
            'status_account' => 'active',
            'nama_pesantren' => $data['namaPesantren'] ?? null,
            'nama_pengasuh'  => $data['namaPengasuh'] ?? null,
        ]);

        \DB::table('user_roles')->insert([
            'id'         => Str::uuid(),
            'user_id'    => $user->id,
            'role'       => 'user',
            'created_at' => now(),
        ]);

        $profile = Profile::find($user->id);
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'email' => $user->email,
                'role'  => $profile->role ?? 'user',
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $email = strtolower($data['email']);
        $user  = User::where('email', $email)->first();

        if (!$user || !password_verify($data['password'], $user->password_hash)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $profile = Profile::find($user->id);
        $token   = JWTAuth::fromUser($user);

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'email' => $user->email,
                'role'  => $profile->role ?? 'user',
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user    = auth()->user();
        $profile = Profile::find($user->id);

        if (!$profile) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json([
            'user' => [
                'id'             => $user->id,
                'email'          => $user->email,
                'role'           => $profile->role ?? 'user',
                'statusAccount'  => $profile->status_account,
                'statusPayment'  => $profile->status_payment ?? 'unpaid',
                'profileLevel'   => $profile->profile_level ?? 'basic',
                'nip'            => $profile->nip,
                'namaPesantren'  => $profile->nama_pesantren,
                'namaPengasuh'   => $profile->nama_pengasuh,
                'namaMedia'      => $profile->nama_media,
                'alamatSingkat'  => $profile->alamat_singkat,
                'regionId'       => $profile->region_id,
                'logoUrl'        => $profile->logo_url,
            ],
        ]);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'currentPassword' => 'required',
            'newPassword'     => 'required|min:6',
        ]);

        $user = auth()->user();

        if (!password_verify($data['currentPassword'], $user->password_hash)) {
            return response()->json(['message' => 'Current password is invalid'], 401);
        }

        User::where('id', $user->id)->update([
            'password_hash' => Hash::make($data['newPassword']),
        ]);

        return response()->json(['success' => true]);
    }

    public function forgotPassword(Request $request)
    {
        $data  = $request->validate(['email' => 'required|string']);
        $email = strtolower($data['email']);
        $user  = User::where('email', $email)->first();

        if ($user) {
            $recentRequest = PasswordResetRequest::where('email', $email)
                ->where('status', 'pending')
                ->where('created_at', '>=', now()->subHour())
                ->first();

            if (!$recentRequest) {
                PasswordResetRequest::create([
                    'id'    => Str::uuid(),
                    'email' => $email,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Jika akun terdaftar, permintaan reset password telah dikirim ke admin.',
        ]);
    }
}
