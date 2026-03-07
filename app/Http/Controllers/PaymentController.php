<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PesantrenClaim;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function current(Request $request)
    {
        $user  = auth()->user();
        $claim = PesantrenClaim::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$claim) {
            return response()->json(['accessDeniedReason' => 'Anda belum mendaftarkan pesantren.']);
        }

        if ($claim->status === 'pending') {
            return response()->json(['accessDeniedReason' => 'Menunggu verifikasi dari Admin Wilayah. Silakan tunggu proses validasi dokumen Anda.']);
        }

        if ($claim->status === 'rejected') {
            return response()->json(['accessDeniedReason' => 'Pengajuan Anda ditolak oleh Admin Wilayah. Silakan hubungi admin untuk informasi lebih lanjut.']);
        }

        if (in_array($claim->status, ['approved', 'pusat_approved'])) {
            return response()->json(['redirectTo' => '/user']);
        }

        if ($claim->status !== 'regional_approved') {
            return response()->json(['accessDeniedReason' => 'Status pengajuan tidak valid untuk pembayaran.']);
        }

        $priceKey  = $claim->jenis_pengajuan === 'klaim' ? 'claim_base_price' : 'registration_base_price';
        $baseAmount = (int) (SystemSetting::getValue($priceKey, 50000));

        $bankName          = SystemSetting::getValue('bank_name', 'Bank Syariah Indonesia (BSI)');
        $bankAccountNumber = SystemSetting::getValue('bank_account_number', '7171234567890');
        $bankAccountName   = SystemSetting::getValue('bank_account_name', 'MEDIA PONDOK JAWA TIMUR');

        $payment = Payment::where('user_id', $user->id)
            ->where('pesantren_claim_id', $claim->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$payment) {
            $uniqueCode = random_int(100, 999);
            $payment = Payment::create([
                'id'                 => Str::uuid(),
                'user_id'            => $user->id,
                'pesantren_claim_id' => $claim->id,
                'base_amount'        => $baseAmount,
                'unique_code'        => $uniqueCode,
                'total_amount'       => $baseAmount + $uniqueCode,
                'status'             => 'pending_payment',
            ]);
        }

        if ($payment->status === 'pending_verification') {
            return response()->json([
                'redirectTo' => '/payment-pending',
                'payment'    => ['id' => $payment->id, 'status' => $payment->status, 'rejectionReason' => $payment->rejection_reason],
            ]);
        }

        if ($payment->status === 'verified') {
            return response()->json([
                'redirectTo' => '/user',
                'payment'    => ['id' => $payment->id, 'status' => $payment->status, 'rejectionReason' => null],
            ]);
        }

        return response()->json([
            'payment' => [
                'id'              => $payment->id,
                'baseAmount'      => $payment->base_amount,
                'uniqueCode'      => $payment->unique_code,
                'totalAmount'     => $payment->total_amount,
                'status'          => $payment->status,
                'rejectionReason' => $payment->rejection_reason,
            ],
            'claim'   => [
                'id'               => $claim->id,
                'pesantren_name'   => $claim->pesantren_name,
                'jenis_pengajuan'  => $claim->jenis_pengajuan,
                'status'           => $claim->status,
            ],
            'bankInfo' => [
                'bank'          => (string) $bankName,
                'accountNumber' => (string) $bankAccountNumber,
                'accountName'   => (string) $bankAccountName,
            ],
        ]);
    }

    public function submitProof(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'paymentId'  => 'required|uuid',
            'senderName' => 'required|string',
            'file'       => 'required|file|mimes:jpeg,png,webp,pdf|max:350',
        ]);

        $payment = Payment::where('id', $request->paymentId)
            ->where('user_id', $user->id)
            ->first();

        if (!$payment) return response()->json(['message' => 'Pembayaran tidak ditemukan'], 404);

        $file         = $request->file('file');
        $relativePath = "payment-proofs/{$user->id}/" . time() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('payment-proofs/' . $user->id, time() . '.' . $file->getClientOriginalExtension(), 'public');

        $payment->update([
            'proof_file_url'  => '/uploads/' . $relativePath,
            'status'          => 'pending_verification',
            'rejection_reason'=> null,
        ]);

        return response()->json(['success' => true]);
    }
}
