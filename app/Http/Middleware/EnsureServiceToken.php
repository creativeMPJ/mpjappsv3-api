<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi server-to-server untuk API integrasi (prefix /api/external).
 *
 * Token di sini sengaja terpisah dari token pengguna: konsumennya adalah
 * backend aplikasi lain, bukan orang yang login, sehingga tidak boleh ikut
 * membawa hak akses milik peran mana pun. Satu token mewakili satu konsumen
 * supaya bisa dicabut sendiri-sendiri tanpa mengganggu konsumen lain.
 *
 * Token dibaca dari config services.external_api.tokens, yang isinya berasal
 * dari env EXTERNAL_API_TOKENS berformat "nama:token,nama-lain:token-lain".
 */
class EnsureServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $tokens = config('services.external_api.tokens', []);

        // Tanpa token terdaftar, endpoint ditutup rapat. Membiarkannya terbuka
        // saat konfigurasi belum diisi jauh lebih berbahaya daripada menolak.
        if (empty($tokens)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Integrasi API eksternal belum dikonfigurasi di server ini.',
            ], 503);
        }

        $presented = $this->presentedToken($request);

        if (!$presented) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token layanan tidak disertakan. Kirim header X-Api-Key atau Authorization: Bearer <token>.',
            ], 401);
        }

        $consumer = $this->resolveConsumer($tokens, $presented);

        if (!$consumer) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token layanan tidak dikenali.',
            ], 401);
        }

        // Nama konsumen ikut dibawa supaya controller dan log tahu siapa yang
        // memanggil tanpa perlu membaca ulang header.
        $request->attributes->set('service_consumer', $consumer);

        return $next($request);
    }

    private function presentedToken(Request $request): ?string
    {
        $apiKey = trim((string) $request->header('X-Api-Key', ''));
        if ($apiKey !== '') {
            return $apiKey;
        }

        $authorization = trim((string) $request->header('Authorization', ''));
        if (stripos($authorization, 'Bearer ') === 0) {
            $bearer = trim(substr($authorization, 7));
            return $bearer !== '' ? $bearer : null;
        }

        return null;
    }

    /**
     * Pembandingan memakai hash_equals supaya lama pencocokan tidak bergantung
     * pada seberapa banyak karakter awal yang benar.
     */
    private function resolveConsumer(array $tokens, string $presented): ?string
    {
        foreach ($tokens as $name => $token) {
            if (is_string($token) && $token !== '' && hash_equals($token, $presented)) {
                return (string) $name;
            }
        }

        return null;
    }
}
