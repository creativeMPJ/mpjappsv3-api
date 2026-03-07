<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RegionalController;
use Illuminate\Support\Facades\Route;

// ── Health check ─────────────────────────────────────────────────────
Route::get('/health', fn() => response()->json(['status' => 'ok', 'timestamp' => now()]));

// ── Auth ──────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register',        [AuthController::class, 'register']);
    Route::post('/login',           [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/me',              [AuthController::class, 'me']);
        Route::post('/change-password',[AuthController::class, 'changePassword']);
    });
});

// ── Public (no auth) ──────────────────────────────────────────────────
Route::prefix('public')->group(function () {
    Route::get('/regions',                         [PublicController::class, 'regions']);
    Route::get('/cities',                          [PublicController::class, 'cities']);
    Route::get('/cities/{id}/region',              [PublicController::class, 'cityRegion']);
    Route::get('/directory',                       [PublicController::class, 'directory']);
    Route::get('/pesantren',                       [PublicController::class, 'pesantrenSearch']);
    Route::get('/pesantren/{nip}/profile',         [PublicController::class, 'pesantrenProfile']);
    Route::get('/pesantren/{nip}/crew/{niamSuffix}',[PublicController::class, 'pesantrenCrew']);
});

// ── Authenticated routes ───────────────────────────────────────────────
Route::middleware('auth:api')->group(function () {

    // ── Claims ────────────────────────────────────────────────────────
    Route::prefix('claims')->group(function () {
        Route::get('/pending-count',           [ClaimController::class, 'pendingCount']);
        Route::get('/search',                  [ClaimController::class, 'search']);
        Route::post('/send-otp',               [ClaimController::class, 'sendOtp']);
        Route::post('/verify-otp',             [ClaimController::class, 'verifyOtp']);
        Route::get('/contact/{claimId}',       [ClaimController::class, 'contact']);
    });

    // ── Payments ──────────────────────────────────────────────────────
    Route::prefix('payments')->group(function () {
        Route::get('/current',       [PaymentController::class, 'current']);
        Route::post('/submit-proof', [PaymentController::class, 'submitProof']);
    });

    // ── Media (user dashboard) ────────────────────────────────────────
    Route::prefix('media')->group(function () {
        Route::get('/jabatan-codes',     [MediaController::class, 'jabatanCodes']);
        Route::get('/crew',              [MediaController::class, 'getCrew']);
        Route::post('/crew',             [MediaController::class, 'createCrew']);
        Route::put('/crew/{id}',         [MediaController::class, 'updateCrew']);
        Route::delete('/crew/{id}',      [MediaController::class, 'deleteCrew']);
        Route::get('/dashboard-context', [MediaController::class, 'dashboardContext']);
        Route::get('/profile-settings',  [MediaController::class, 'profileSettings']);
    });

    // ── Institution ───────────────────────────────────────────────────
    Route::prefix('institution')->group(function () {
        Route::get('/ownership',                    [InstitutionController::class, 'ownership']);
        Route::post('/upload-registration-document',[InstitutionController::class, 'uploadRegistrationDocument']);
        Route::post('/initial-data',                [InstitutionController::class, 'initialData']);
        Route::post('/location',                    [InstitutionController::class, 'location']);
        Route::get('/pending-status',               [InstitutionController::class, 'pendingStatus']);
    });

    // ── Regional admin ────────────────────────────────────────────────
    Route::prefix('regional')->group(function () {
        Route::get('/master-data',                        [RegionalController::class, 'masterData']);
        Route::get('/pending-claims',                     [RegionalController::class, 'pendingClaims']);
        Route::get('/pricing-packages',                   [RegionalController::class, 'pricingPackages']);
        Route::post('/claims/{id}/approve',               [RegionalController::class, 'approveClaim']);
        Route::post('/claims/{id}/reject',                [RegionalController::class, 'rejectClaim']);
        Route::get('/late-payments',                      [RegionalController::class, 'latePayments']);
        Route::post('/late-payments/{claimId}/follow-up', [RegionalController::class, 'followUp']);
        Route::get('/performance',                        [RegionalController::class, 'performance']);
        Route::get('/leaderboard',                        [RegionalController::class, 'leaderboard']);
    });

    // ── Admin pusat ───────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {
        Route::get('/home-summary',                        [AdminController::class, 'homeSummary']);

        // Clearing house
        Route::get('/clearing-house/pending',              [AdminController::class, 'clearingHousePending']);
        Route::post('/clearing-house/{id}/approve',        [AdminController::class, 'clearingHouseApprove']);
        Route::post('/clearing-house/{id}/reject',         [AdminController::class, 'clearingHouseReject']);

        Route::get('/pending-profiles',                    [AdminController::class, 'pendingProfiles']);

        // Admin settings
        Route::get('/admin-settings/data',                 [AdminController::class, 'adminSettingsData']);
        Route::get('/admin-settings/search-crew',          [AdminController::class, 'adminSettingsSearchCrew']);
        Route::post('/admin-settings/assign',              [AdminController::class, 'adminSettingsAssign']);
        Route::delete('/admin-settings/{userId}',          [AdminController::class, 'adminSettingsRemove']);

        // Master data
        Route::get('/master-data',                         [AdminController::class, 'masterData']);
        Route::put('/master-data/pesantren/{id}',          [AdminController::class, 'masterDataUpdatePesantren']);
        Route::put('/master-data/media/{id}',              [AdminController::class, 'masterDataUpdateMedia']);
        Route::put('/master-data/crew/{id}',               [AdminController::class, 'masterDataUpdateCrew']);
        Route::delete('/master-data/crew/{id}',            [AdminController::class, 'masterDataDeleteCrew']);
        Route::post('/master-data/import',                 [AdminController::class, 'masterDataImport']);

        // Jabatan codes
        Route::get('/jabatan-codes',                       [AdminController::class, 'jabatanCodes']);
        Route::post('/jabatan-codes',                      [AdminController::class, 'createJabatanCode']);
        Route::put('/jabatan-codes/{id}',                  [AdminController::class, 'updateJabatanCode']);
        Route::delete('/jabatan-codes/{id}',               [AdminController::class, 'deleteJabatanCode']);

        // Search & stats
        Route::get('/global-search',                       [AdminController::class, 'globalSearch']);
        Route::get('/super-stats',                         [AdminController::class, 'superStats']);
        Route::get('/late-payment-count',                  [AdminController::class, 'latePaymentCount']);

        // Pusat assistants
        Route::get('/pusat-assistants',                    [AdminController::class, 'pusatAssistants']);
        Route::post('/pusat-assistants',                   [AdminController::class, 'addPusatAssistant']);
        Route::delete('/pusat-assistants/{crewId}',        [AdminController::class, 'removePusatAssistant']);

        // Regional management
        Route::get('/regional-management/data',            [AdminController::class, 'regionalManagementData']);
        Route::post('/regional-management/regions',        [AdminController::class, 'addRegion']);
        Route::delete('/regional-management/regions/{id}', [AdminController::class, 'deleteRegion']);
        Route::post('/regional-management/cities',         [AdminController::class, 'addCity']);
        Route::delete('/regional-management/cities/{id}',  [AdminController::class, 'deleteCity']);
        Route::post('/regional-management/assign-admin',   [AdminController::class, 'assignRegionalAdmin']);

        // Users management
        Route::get('/users-management',                    [AdminController::class, 'usersManagement']);
        Route::post('/users/{id}',                         [AdminController::class, 'updateUser']);

        // Settings
        Route::get('/bank-settings',                       [AdminController::class, 'bankSettings']);
        Route::post('/bank-settings',                      [AdminController::class, 'updateBankSettings']);
        Route::get('/price-settings',                      [AdminController::class, 'priceSettings']);
        Route::post('/price-settings',                     [AdminController::class, 'updatePriceSettings']);

        // Regions detail
        Route::get('/regions/{id}/detail',                 [AdminController::class, 'regionDetail']);

        // Claims & payments
        Route::get('/claims',                              [AdminController::class, 'claims']);
        Route::get('/payments',                            [AdminController::class, 'payments']);
        Route::post('/payments/{id}/reject',               [AdminController::class, 'rejectPayment']);
        Route::post('/payments/{id}/approve',              [AdminController::class, 'approvePayment']);

        // Leveling
        Route::get('/leveling-profiles',                   [AdminController::class, 'levelingProfiles']);
        Route::post('/leveling/{id}/promote-platinum',     [AdminController::class, 'promotePlatinum']);

        // Pricing packages
        Route::get('/pricing-packages',                    [AdminController::class, 'pricingPackages']);
        Route::post('/pricing-packages',                   [AdminController::class, 'createPricingPackage']);
        Route::put('/pricing-packages/{id}',               [AdminController::class, 'updatePricingPackage']);
        Route::patch('/pricing-packages/{id}/toggle',      [AdminController::class, 'togglePricingPackage']);
    });

    // ── Events ────────────────────────────────────────────────────────
    Route::prefix('events')->group(function () {
        Route::get('/',                        [EventController::class, 'index']);
        Route::post('/',                       [EventController::class, 'store']);
        Route::get('/{id}/reports',            [EventController::class, 'reports']);
        Route::post('/{id}/report',            [EventController::class, 'submitReport']);

        // Regional event routes
        Route::get('/regional',                [EventController::class, 'regionalIndex']);
        Route::post('/regional',               [EventController::class, 'regionalStore']);
        Route::put('/regional/{id}',           [EventController::class, 'regionalUpdate']);
        Route::post('/regional/{id}/report',   [EventController::class, 'regionalSubmitReport']);
    });
});
