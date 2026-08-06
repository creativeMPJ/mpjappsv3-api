<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Seluruh handler upload (bukti bayar, logo pesantren, dokumen pendaftaran,
 * laporan regional, resource MPJ Hub) menyimpan file ke disk `public`, yaitu
 * storage/app/public, tetapi mengembalikan URL berawalan /uploads/. Tidak ada
 * direktori public/uploads maupun route yang melayaninya, sehingga SEMUA URL
 * file yang tersimpan di database mati.
 *
 * Route ini melayani prefix /uploads dari disk public. Dipilih daripada
 * mengganti prefix menjadi /storage karena URL lama sudah terlanjur tersimpan
 * di ribuan baris database; dengan cara ini yang lama maupun yang baru sama-sama
 * bisa diakses tanpa migrasi data.
 */
Route::get('/uploads/{path}', function (string $path) {
    // Normalisasi terpisah dari pengecekan: pemeriksaan '..' pada string mentah
    // bisa dilewati lewat penyandian, sedangkan Laravel sudah mendekode {path}.
    $path = ltrim(str_replace('\\', '/', $path), '/');

    foreach (explode('/', $path) as $segment) {
        if ($segment === '..' || $segment === '.' || $segment === '') {
            abort(404);
        }
    }

    $disk = Storage::disk('public');

    if (!$disk->exists($path)) {
        abort(404);
    }

    $mime = $disk->mimeType($path) ?: 'application/octet-stream';

    // Hanya tipe yang aman dirender inline. Sisanya dipaksa diunduh supaya file
    // HTML atau SVG yang lolos validasi upload tidak bisa dieksekusi sebagai
    // halaman di origin yang sama (stored XSS).
    $inlineSafe = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
    $disposition = in_array($mime, $inlineSafe, true) ? 'inline' : 'attachment';

    return $disk->response($path, basename($path), [
        'Content-Type'            => $mime,
        'Content-Disposition'     => $disposition . '; filename="' . basename($path) . '"',
        'X-Content-Type-Options'  => 'nosniff',
    ]);
})->where('path', '.*');
