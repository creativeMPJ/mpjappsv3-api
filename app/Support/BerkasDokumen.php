<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class BerkasDokumen
{
    public const AVAILABLE = 'available';
    public const MISSING = 'missing';
    public const NONE = 'none';

    public static function pathRelatif(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $path = ltrim(preg_replace('#^/?(uploads|storage)/#', '', trim($url)), '/');

        return $path === '' ? null : $path;
    }

    public static function status(?string $url): string
    {
        $path = self::pathRelatif($url);

        if ($path === null) {
            return self::NONE;
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return self::AVAILABLE;
            }
        }

        return self::MISSING;
    }
}
