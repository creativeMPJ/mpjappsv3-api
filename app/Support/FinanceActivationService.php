<?php

namespace App\Support;

use App\Models\Crew;
use App\Models\JabatanCode;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\PesantrenClaim;
use App\Models\PesantrenProfile;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinanceActivationService
{
    public const TYPE_INSTITUTION_ACTIVATION = 'institution_activation';
    public const TYPE_CREW_ACTIVATION = 'crew_activation';
    public const TYPE_EVENT_REGISTRATION = 'event_registration';
    public const TYPE_SLOT_ADDON = 'slot_addon';

    public const STATUS_PENDING = 'pending';
    public const STATUS_WAITING_VERIFICATION = 'waiting_verification';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const REFERENCE_PROFILE = 'profile';
    public const REFERENCE_CREW = 'crew';
    public const REFERENCE_EVENT_REGISTRATION = 'event_registration';

    public static function normalizePaymentStatus(?string $status): string
    {
        return match ($status) {
            'pending_payment' => self::STATUS_PENDING,
            'pending_verification' => self::STATUS_WAITING_VERIFICATION,
            default => $status ?: self::STATUS_PENDING,
        };
    }

    public static function determineActivationState(
        ?PesantrenProfile $profile,
        ?Payment $payment = null,
        ?PesantrenClaim $claim = null
    ): string {
        $status = self::normalizePaymentStatus($payment?->status);

        if (!$profile) {
            return 'not_registered';
        }

        if ($profile->status_account === 'rejected') {
            return 'rejected';
        }

        if ($profile->status_account === 'active' && $profile->status_payment === 'paid' && $profile->nip) {
            return 'active';
        }

        if ($status === self::STATUS_VERIFIED) {
            return 'activation_completed';
        }

        if ($status === self::STATUS_WAITING_VERIFICATION) {
            return 'waiting_verification';
        }

        if ($status === self::STATUS_REJECTED) {
            return 'payment_rejected';
        }

        if ($status === self::STATUS_EXPIRED) {
            return 'expired';
        }

        if ($status === self::STATUS_CANCELLED) {
            return 'cancelled';
        }

        if ($claim?->status === 'regional_approved' || $status === self::STATUS_PENDING) {
            return 'awaiting_payment';
        }

        if ($claim?->status === 'pending') {
            return 'pending_review';
        }

        return 'draft';
    }

    public static function buildInvoiceNumber(string $paymentType): string
    {
        $prefix = match ($paymentType) {
            self::TYPE_CREW_ACTIVATION => 'CRA',
            self::TYPE_EVENT_REGISTRATION => 'EVR',
            self::TYPE_SLOT_ADDON => 'SLA',
            default => 'INA',
        };

        do {
            $invoiceNumber = sprintf(
                'INV-%s-%s-%04d',
                $prefix,
                now()->format('Ymd'),
                random_int(1000, 9999)
            );
        } while (Payment::where('invoice_number', $invoiceNumber)->exists());

        return $invoiceNumber;
    }

    public static function getCrewActivationPrice(): int
    {
        return (int) SystemSetting::getValue('crew_activation_price', 25000);
    }

    public static function getEventMemberPrice(): int
    {
        return (int) SystemSetting::getValue('event_member_price', 0);
    }

    public static function getEventPublicPrice(): int
    {
        return (int) SystemSetting::getValue('event_public_price', 35000);
    }

    public static function latestProfileClaim(PesantrenProfile $profile): ?PesantrenClaim
    {
        return PesantrenClaim::where('user_id', $profile->id)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public static function ensureInstitutionActivationInvoice(PesantrenProfile $profile, PesantrenClaim $claim): Payment
    {
        $existing = Payment::where('payment_type', self::TYPE_INSTITUTION_ACTIVATION)
            ->where('reference_type', self::REFERENCE_PROFILE)
            ->where('reference_id', $profile->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($existing) {
            return $existing;
        }

        $priceKey = $claim->jenis_pengajuan === 'klaim' ? 'claim_base_price' : 'registration_base_price';
        $baseAmount = (int) SystemSetting::getValue($priceKey, 50000);
        $uniqueCode = random_int(100, 999);

        $payment = Payment::create([
            'id' => Str::uuid(),
            'user_id' => $profile->id,
            'pesantren_claim_id' => $claim->id,
            'payment_type' => self::TYPE_INSTITUTION_ACTIVATION,
            'reference_type' => self::REFERENCE_PROFILE,
            'reference_id' => $profile->id,
            'invoice_number' => self::buildInvoiceNumber(self::TYPE_INSTITUTION_ACTIVATION),
            'base_amount' => $baseAmount,
            'unique_code' => $uniqueCode,
            'total_amount' => $baseAmount + $uniqueCode,
            'status' => self::STATUS_PENDING,
            'created_by' => $profile->user_id,
        ]);

        self::logPaymentStatusChange($payment, $profile->user_id, 'invoice_created', null, self::STATUS_PENDING, 'Invoice aktivasi institusi dibuat.');

        return $payment;
    }

    public static function ensureCrewActivationInvoice(PesantrenProfile $profile, Crew $crew, ?User $actor = null): Payment
    {
        $existing = Payment::where('payment_type', self::TYPE_CREW_ACTIVATION)
            ->where('reference_type', self::REFERENCE_CREW)
            ->where('reference_id', $crew->id)
            ->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_EXPIRED])
            ->orderBy('created_at', 'desc')
            ->first();

        if ($existing) {
            return $existing;
        }

        $claim = self::latestProfileClaim($profile);
        $baseAmount = self::getCrewActivationPrice();
        $uniqueCode = random_int(100, 999);

        $payment = Payment::create([
            'id' => Str::uuid(),
            'user_id' => $profile->id,
            'pesantren_claim_id' => $claim?->id,
            'payment_type' => self::TYPE_CREW_ACTIVATION,
            'reference_type' => self::REFERENCE_CREW,
            'reference_id' => $crew->id,
            'invoice_number' => self::buildInvoiceNumber(self::TYPE_CREW_ACTIVATION),
            'base_amount' => $baseAmount,
            'unique_code' => $uniqueCode,
            'total_amount' => $baseAmount + $uniqueCode,
            'status' => self::STATUS_PENDING,
            'created_by' => $actor?->id ?? $profile->user_id,
            'meta' => [
                'crew_name' => $crew->nama,
                'crew_role' => $crew->jabatan,
            ],
        ]);

        self::logPaymentStatusChange($payment, $actor?->id ?? $profile->user_id, 'invoice_created', null, self::STATUS_PENDING, 'Invoice aktivasi kru dibuat.');

        return $payment;
    }

    public static function logPaymentStatusChange(
        Payment $payment,
        ?string $actorUserId,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $notes = null,
        ?array $meta = null
    ): void {
        PaymentLog::create([
            'id' => Str::uuid(),
            'payment_id' => $payment->id,
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $notes,
            'meta' => $meta,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function issueCrewNiam(PesantrenProfile $profile, Crew $crew): string
    {
        if ($crew->niam) {
            return $crew->niam;
        }

        $jabatanCodeId = $crew->jabatan_code_id;
        if (!$jabatanCodeId && $crew->jabatan) {
            $foundCode = JabatanCode::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($crew->jabatan) . '%'])->first();
            if ($foundCode) {
                $jabatanCodeId = $foundCode->id;
                $crew->update(['jabatan_code_id' => $jabatanCodeId]);
            }
        }

        if ($jabatanCodeId) {
            $jabatanCode = JabatanCode::find($jabatanCodeId);
            $crewSeq = Crew::where('jabatan_code_id', $jabatanCodeId)
                ->whereHas('profile', fn($query) => $query->where('region_id', $profile->region_id))
                ->where('status', 'active')
                ->count();

            return $jabatanCode->code . $profile->nip . str_pad($crewSeq + 1, 2, '0', STR_PAD_LEFT);
        }

        return $profile->nip . str_pad(
            Crew::where('profile_id', $profile->id)->where('status', 'active')->count() + 1,
            2,
            '0',
            STR_PAD_LEFT
        );
    }

    public static function approveCrewActivation(Payment $payment, User $actor): Crew
    {
        $profile = PesantrenProfile::find($payment->user_id);
        $crew = Crew::find($payment->reference_id);

        if (!$profile || !$crew) {
            abort(404, 'Data aktivasi kru tidak ditemukan');
        }

        if ($profile->status_account !== 'active' || $profile->status_payment !== 'paid' || !$profile->nip) {
            abort(422, 'Institusi belum aktif penuh, kru belum bisa diaktifkan.');
        }

        $fromStatus = self::normalizePaymentStatus($payment->status);

        DB::transaction(function () use ($payment, $actor, $crew, $profile, $fromStatus) {
            $payment->update([
                'status' => self::STATUS_VERIFIED,
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'rejection_reason' => null,
            ]);

            $niam = self::issueCrewNiam($profile, $crew);

            $crew->update([
                'status' => 'active',
                'niam' => $niam,
            ]);

            self::logPaymentStatusChange(
                $payment->fresh(),
                $actor->id,
                'approved',
                $fromStatus,
                self::STATUS_VERIFIED,
                'Pembayaran aktivasi kru diverifikasi.'
            );
        });

        return $crew->fresh();
    }
}
