<?php

use App\Http\Controllers\ApiEventCompatController;
use Illuminate\Support\Facades\Route;

Route::prefix('event')->group(function () {
    Route::get('/', [ApiEventCompatController::class, 'index']);
    Route::get('/niam/validate/{niam}', [ApiEventCompatController::class, 'validateNiam']);
    Route::get('/ticket/{token}', [ApiEventCompatController::class, 'ticket']);
    Route::post('/payment/proof', [ApiEventCompatController::class, 'uploadProof']);

    Route::get('/admin/list', [ApiEventCompatController::class, 'adminIndex']);
    Route::post('/admin', [ApiEventCompatController::class, 'store']);
    Route::get('/admin/{id}', [ApiEventCompatController::class, 'adminShow']);
    Route::put('/admin/{id}', [ApiEventCompatController::class, 'update']);
    Route::delete('/admin/{id}', [ApiEventCompatController::class, 'destroy']);
    Route::patch('/admin/{id}/status', [ApiEventCompatController::class, 'changeStatus']);
    Route::post('/admin/{id}/custom-fields', [ApiEventCompatController::class, 'syncCustomFields']);
    Route::post('/admin/{id}/poster', [ApiEventCompatController::class, 'uploadPoster']);
    Route::get('/admin/{id}/participants', [ApiEventCompatController::class, 'participants']);
    Route::get('/admin/{id}/stats', [ApiEventCompatController::class, 'stats']);
    Route::get('/admin/{id}/export-csv', [ApiEventCompatController::class, 'exportCsv']);
    Route::get('/admin/payments/{participantId}/proof', [ApiEventCompatController::class, 'proofPreview']);
    Route::post('/admin/payments/{participantId}/approve', [ApiEventCompatController::class, 'approve']);
    Route::post('/admin/payments/{participantId}/reject', [ApiEventCompatController::class, 'reject']);

    Route::post('/payment/{paymentId}/approve', [ApiEventCompatController::class, 'approvePayment']);
    Route::post('/payment/{paymentId}/reject', [ApiEventCompatController::class, 'rejectPayment']);

    Route::post('/admin/participants/{participantId}/cancel', [ApiEventCompatController::class, 'cancel']);
    Route::post('/attendance/check-in', [ApiEventCompatController::class, 'checkIn']);
    Route::get('/attendance/{eventId}/log', [ApiEventCompatController::class, 'attendanceLog']);
    Route::get('/attendance/verify/{token}', [ApiEventCompatController::class, 'verifyAttendance']);

    Route::get('/finance/summary', [ApiEventCompatController::class, 'financeSummary']);
    Route::get('/finance/recap', [ApiEventCompatController::class, 'financeRecap']);
    Route::get('/finance/export', [ApiEventCompatController::class, 'financeExport']);
    Route::get('/{eventId}/finance/transactions', [ApiEventCompatController::class, 'financeTransactions']);
    Route::post('/{eventId}/finance/transactions', [ApiEventCompatController::class, 'storeFinanceTransaction']);
    Route::put('/{eventId}/finance/transactions/{transactionId}', [ApiEventCompatController::class, 'updateFinanceTransaction']);
    Route::post('/{eventId}/finance/transactions/{transactionId}/void', [ApiEventCompatController::class, 'voidFinanceTransaction']);

    Route::post('/{id}/register', [ApiEventCompatController::class, 'register']);
    Route::get('/{id}', [ApiEventCompatController::class, 'show']);
});
