<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PesantrenClaim;
use App\Models\PesantrenProfile;
use App\Models\SystemSetting;
use App\Support\FinanceActivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function current(Request $request)
    {
        $user    = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user->id)->first();

        $claim = $profile
            ? PesantrenClaim::where('user_id', $profile->id)
                ->orderBy('created_at', 'desc')
                ->first()
            : null;

        if (!$claim) {
            return response()->json(['accessDeniedReason' => 'Anda belum mendaftarkan pesantren.']);
        }

        if ($claim->status === 'pending') {
            return response()->json(['accessDeniedReason' => 'Menunggu verifikasi dari Admin Wilayah. Silakan tunggu proses validasi dokumen Anda.']);
        }

        if ($claim->status === 'rejected') {
            return response()->json(['accessDeniedReason' => 'Pengajuan Anda ditolak oleh Admin Wilayah. Silakan hubungi admin untuk informasi lebih lanjut.']);
        }

        if (!in_array($claim->status, ['regional_approved', 'approved', 'pusat_approved'])) {
            return response()->json(['accessDeniedReason' => 'Status pengajuan tidak valid untuk pembayaran.']);
        }

        if ($claim->jenis_pengajuan === 'klaim') {
            return response()->json([
                'redirectTo' => '/user-dashboard',
                'claim' => [
                    'id'               => $claim->id,
                    'pesantren_name'   => $claim->pesantren_name,
                    'jenis_pengajuan'  => $claim->jenis_pengajuan,
                    'status'           => $claim->status,
                ],
                'profile' => $profile ? [
                    'id' => $profile->id,
                    'nama_pesantren' => $profile->nama_pesantren,
                    'nama_media' => $profile->nama_media,
                    'status_account' => $profile->status_account,
                    'status_payment' => $profile->status_payment,
                    'profile_level' => $profile->profile_level,
                    'nip' => $profile->nip,
                ] : null,
            ]);
        }

        $priceKey  = $claim->jenis_pengajuan === 'klaim' ? 'claim_base_price' : 'registration_base_price';
        $baseAmount = (int) (SystemSetting::getValue($priceKey, 50000));

        $bankName          = SystemSetting::getValue('bank_name', 'Bank Syariah Indonesia (BSI)');
        $bankAccountNumber = SystemSetting::getValue('bank_account_number', '7171234567890');
        $bankAccountName   = SystemSetting::getValue('bank_account_name', 'MEDIA PONDOK JAWA TIMUR');

        $payment = Payment::where('user_id', $profile->id)
            ->where('payment_type', FinanceActivationService::TYPE_INSTITUTION_ACTIVATION)
            ->where('reference_type', FinanceActivationService::REFERENCE_PROFILE)
            ->where('reference_id', $profile->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$payment && $claim->status === 'regional_approved' && $claim->jenis_pengajuan !== 'klaim') {
            $payment = FinanceActivationService::ensureInstitutionActivationInvoice($profile, $claim);
        }

        if (!$payment && in_array($claim->status, ['approved', 'pusat_approved'])) {
            return response()->json([
                'redirectTo' => '/cms',
                'claim' => [
                    'id'               => $claim->id,
                    'pesantren_name'   => $claim->pesantren_name,
                    'jenis_pengajuan'  => $claim->jenis_pengajuan,
                    'status'           => $claim->status,
                ],
                'profile' => $profile ? [
                    'id' => $profile->id,
                    'nama_pesantren' => $profile->nama_pesantren,
                    'nama_media' => $profile->nama_media,
                    'status_account' => $profile->status_account,
                    'status_payment' => $profile->status_payment,
                    'profile_level' => $profile->profile_level,
                    'nip' => $profile->nip,
                ] : null,
            ]);
        }

        $normalizedStatus = FinanceActivationService::normalizePaymentStatus($payment->status);
        $claimPayload = [
            'id'               => $claim->id,
            'pesantren_name'   => $claim->pesantren_name,
            'jenis_pengajuan'  => $claim->jenis_pengajuan,
            'status'           => $claim->status,
        ];
        $profilePayload = $profile ? [
            'id' => $profile->id,
            'nama_pesantren' => $profile->nama_pesantren,
            'nama_media' => $profile->nama_media,
            'status_account' => $profile->status_account,
            'status_payment' => $profile->status_payment,
            'profile_level' => $profile->profile_level,
            'nip' => $profile->nip,
        ] : null;

        if ($normalizedStatus === FinanceActivationService::STATUS_WAITING_VERIFICATION) {
            return response()->json([
                'redirectTo' => '/payment-pending',
                'claim' => $claimPayload,
                'profile' => $profilePayload,
                'payment'    => [
                    'id' => $payment->id,
                    'status' => $normalizedStatus,
                    'rejectionReason' => $payment->rejection_reason,
                    'paymentType' => $payment->payment_type,
                    'invoiceNumber' => $payment->invoice_number,
                    'activationState' => FinanceActivationService::determineActivationState($profile, $payment, $claim),
                ],
            ]);
        }

        if ($normalizedStatus === FinanceActivationService::STATUS_VERIFIED) {
            return response()->json([
                'redirectTo' => '/cms',
                'claim' => $claimPayload,
                'profile' => $profilePayload,
                'payment'    => [
                    'id' => $payment->id,
                    'status' => $normalizedStatus,
                    'rejectionReason' => null,
                    'paymentType' => $payment->payment_type,
                    'invoiceNumber' => $payment->invoice_number,
                    'activationState' => FinanceActivationService::determineActivationState($profile, $payment, $claim),
                ],
            ]);
        }

        $crewInvoices = Payment::where('user_id', $profile->id)
            ->where('payment_type', FinanceActivationService::TYPE_CREW_ACTIVATION)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($invoice) => [
                'id' => $invoice->id,
                'status' => FinanceActivationService::normalizePaymentStatus($invoice->status),
                'paymentType' => $invoice->payment_type,
                'referenceId' => $invoice->reference_id,
                'invoiceNumber' => $invoice->invoice_number,
                'totalAmount' => $invoice->total_amount,
                'rejectionReason' => $invoice->rejection_reason,
            ]);

        return response()->json([
            'payment' => [
                'id'              => $payment->id,
                'baseAmount'      => $payment->base_amount,
                'uniqueCode'      => $payment->unique_code,
                'totalAmount'     => $payment->total_amount,
                'status'          => $normalizedStatus,
                'rejectionReason' => $payment->rejection_reason,
                'paymentType'     => $payment->payment_type,
                'invoiceNumber'   => $payment->invoice_number,
                'activationState' => FinanceActivationService::determineActivationState($profile, $payment, $claim),
            ],
            'claim'   => $claimPayload,
            'profile' => $profilePayload,
            'crewInvoices' => $crewInvoices,
            'bankInfo' => [
                'bank'          => (string) $bankName,
                'accountNumber' => (string) $bankAccountNumber,
                'accountName'   => (string) $bankAccountName,
            ],
        ]);
    }

    public function summary(Request $request)
    {
        $user    = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user->id)->first();

        $claim = $profile
            ? PesantrenClaim::where('user_id', $profile->id)
                ->orderBy('created_at', 'desc')
                ->first()
            : null;

        if (!$claim) {
            return response()->json([
                'paymentStatus' => 'pending_payment',
                'payment'       => null,
            ]);
        }

        $payment = Payment::where('user_id', $profile->id)
            ->where('payment_type', FinanceActivationService::TYPE_INSTITUTION_ACTIVATION)
            ->where('reference_type', FinanceActivationService::REFERENCE_PROFILE)
            ->where('reference_id', $profile->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$payment && $claim->status === 'regional_approved') {
            $payment = FinanceActivationService::ensureInstitutionActivationInvoice($profile, $claim);
        }

        if (!$payment) {
            return response()->json([
                'paymentStatus' => FinanceActivationService::STATUS_PENDING,
                'payment'       => null,
            ]);
        }

        $crewInvoices = Payment::where('user_id', $profile->id)
            ->where('payment_type', FinanceActivationService::TYPE_CREW_ACTIVATION)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'paymentStatus' => FinanceActivationService::normalizePaymentStatus($payment->status),
            'payment'       => [
                'id'              => $payment->id,
                'baseAmount'      => $payment->base_amount,
                'uniqueCode'      => $payment->unique_code,
                'totalAmount'     => $payment->total_amount,
                'status'          => FinanceActivationService::normalizePaymentStatus($payment->status),
                'rejectionReason' => $payment->rejection_reason,
            ],
            'crewInvoices' => $crewInvoices->map(fn($invoice) => [
                'id' => $invoice->id,
                'status' => FinanceActivationService::normalizePaymentStatus($invoice->status),
                'paymentType' => $invoice->payment_type,
                'referenceId' => $invoice->reference_id,
                'invoiceNumber' => $invoice->invoice_number,
                'totalAmount' => $invoice->total_amount,
                'rejectionReason' => $invoice->rejection_reason,
            ]),
        ]);
    }

    public function submitProof(Request $request)
    {
        $user    = auth()->user();
        $profile = PesantrenProfile::where('user_id', $user->id)->first();

        $request->validate([
            'paymentId'  => 'required|uuid',
            'senderName' => 'required|string',
            'file'       => 'required|file|mimes:jpeg,png,webp,pdf|max:350',
        ]);

        $payment = Payment::where('id', $request->paymentId)
            ->where('user_id', $profile?->id)
            ->first();

        if (!$payment) return response()->json(['message' => 'Pembayaran tidak ditemukan'], 404);

        $file         = $request->file('file');
        $relativePath = "payment-proofs/{$user->id}/" . time() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('payment-proofs/' . $user->id, time() . '.' . $file->getClientOriginalExtension(), 'public');

        $fromStatus = FinanceActivationService::normalizePaymentStatus($payment->status);

        $payment->update([
            'proof_file_url'   => '/uploads/' . $relativePath,
            'status'           => FinanceActivationService::STATUS_WAITING_VERIFICATION,
            'rejection_reason' => null,
            'submitted_at'     => now(),
            'meta'             => array_merge($payment->meta ?? [], ['sender_name' => $request->senderName]),
        ]);

        FinanceActivationService::logPaymentStatusChange(
            $payment->fresh(),
            $user->id,
            'submit_proof',
            $fromStatus,
            FinanceActivationService::STATUS_WAITING_VERIFICATION,
            'Bukti pembayaran diunggah.'
        );

        return response()->json(['success' => true]);
    }
}
