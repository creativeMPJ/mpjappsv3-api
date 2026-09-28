<?php

namespace App\Support;

use App\Models\PesantrenProfile;
use App\Models\SystemSetting;

class PaymentContactResolver
{
    public static function resolve(): array
    {
        return self::fromSettings('finance_contact', 'Admin Finance')
            ?? self::fromRole(['Admin Keuangan', 'Admin Finance'], 'Admin Finance')
            ?? self::fromSettings('cak_apps_contact', 'Admin Pusat / Cak Apps')
            ?? self::fromRole(['Admin Pusat'], 'Admin Pusat / Cak Apps')
            ?? [
                'label' => 'Admin Pusat / Cak Apps',
                'type' => 'unavailable',
                'value' => null,
                'url' => null,
                'message' => 'Kontak bantuan belum dikonfigurasi. Silakan hubungi Admin Regional untuk diteruskan ke Admin Pusat.',
            ];
    }

    private static function fromSettings(string $prefix, string $defaultLabel): ?array
    {
        $active = SystemSetting::getValue("{$prefix}_active", true);
        if ($active === false || $active === 'false' || $active === 0 || $active === '0') {
            return null;
        }

        $label = trim((string) SystemSetting::getValue("{$prefix}_label", $defaultLabel));
        $phone = self::normalizePhone(SystemSetting::getValue("{$prefix}_whatsapp"));
        if ($phone) {
            return self::whatsapp($label ?: $defaultLabel, $phone);
        }

        $email = trim((string) SystemSetting::getValue("{$prefix}_email", ''));
        if ($email !== '') {
            return self::email($label ?: $defaultLabel, $email);
        }

        return null;
    }

    private static function fromRole(array $roleNames, string $label): ?array
    {
        $profile = PesantrenProfile::query()
            ->where('status_account', 'active')
            ->whereIn('user_id', function ($sub) use ($roleNames) {
                $sub->select('user_roles.user_id')
                    ->from('user_roles')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->whereIn('roles.nama', $roleNames);
            })
            ->whereNotNull('no_wa_pendaftar')
            ->where('no_wa_pendaftar', '!=', '')
            ->orderByDesc('updated_at')
            ->first();

        $phone = self::normalizePhone($profile?->no_wa_pendaftar);
        if ($phone) {
            return self::whatsapp($label, $phone);
        }

        return null;
    }

    private static function whatsapp(string $label, string $phone): array
    {
        return [
            'label' => $label,
            'type' => 'whatsapp',
            'value' => $phone,
            'url' => "https://wa.me/{$phone}",
            'message' => null,
        ];
    }

    private static function email(string $label, string $email): array
    {
        return [
            'label' => $label,
            'type' => 'email',
            'value' => $email,
            'url' => "mailto:{$email}",
            'message' => null,
        ];
    }

    private static function normalizePhone($value): ?string
    {
        $phone = preg_replace('/\D/', '', (string) $value);
        if (!$phone) {
            return null;
        }

        if (str_starts_with($phone, '0')) {
            return '62' . substr($phone, 1);
        }

        if (!str_starts_with($phone, '62')) {
            return '62' . $phone;
        }

        return $phone;
    }
}
