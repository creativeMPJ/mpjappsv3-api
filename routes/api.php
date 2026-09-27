<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExternalApiController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RegionalController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

// ── Health check ─────────────────────────────────────────────────────
Route::get('/health', fn() => response()->json(['status' => 'ok', 'timestamp' => now()]));

// Catatan: kedua endpoint di bawah dikurung environment local/testing sehingga
// tidak terjangkau di production. Keduanya tetap tanpa autentikasi, jadi jangan
// sampai APP_ENV di server salah setel — migrate:fresh dan truncate seluruh
// tabel bisa dipanggil lewat HTTP.
if (app()->environment(['local', 'testing'])) {
    // ── Local-only Artisan runner (deploy helper) ─────────────────────
    Route::post('/artisan', function (\Illuminate\Http\Request $request) {
        $allowed = [
            'config:clear', 'cache:clear', 'route:clear', 'view:clear',
            'optimize', 'optimize:clear', 'storage:link', 'migrate',
            'migrate:status', 'migrate:fresh', 'migrate:rollback', 'db:seed', 'queue:restart',
        ];

        $command = $request->input('command');

        if (!in_array($command, $allowed)) {
            return response()->json(['message' => 'Command not allowed', 'allowed' => $allowed], 422);
        }

        $needsForce = in_array($command, ['db:seed', 'migrate', 'migrate:fresh', 'migrate:rollback']);
        $params = $needsForce ? ['--force' => true] : [];
        \Illuminate\Support\Facades\Artisan::call($command, $params);

        return response()->json([
            'command' => $command,
            'output'  => \Illuminate\Support\Facades\Artisan::output(),
        ]);
    });

    // ── Local-only dev reset (truncate all data) ──────────────────────
    Route::post('/dev/reset', function (\Illuminate\Http\Request $request) {
        if ($request->input('password') !== 'sulip') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $tables = [
            'otp_verifications', 'follow_up_logs', 'payments',
            'pesantren_claims', 'pesantren_directory', 'crews',
            'user_roles', 'profiles', 'users',
            'region_regencies', 'regions', 'cache',
        ];

        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            \Illuminate\Support\Facades\DB::table($table)->truncate();
        }
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');

        return response()->json(['success' => true, 'message' => 'Data truncated.']);
    });
}

// ── Auth ──────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register',        [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login',           [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');

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
    Route::get('/directory-search',                [PublicController::class, 'directorySearch']);
    Route::get('/directory/{id}',                   [PublicController::class, 'directoryDetail']);
    Route::get('/niam-lookup',                     [PublicController::class, 'lookupNiam']);
    Route::get('/pesantren',                       [PublicController::class, 'pesantrenSearch']);
    Route::get('/pesantren/{nip}/profile',         [PublicController::class, 'pesantrenProfile']);
    Route::get('/pesantren/{nip}/crew/{niamSuffix}',[PublicController::class, 'pesantrenCrew']);
});

// ── Integrasi eksternal (server-to-server) ────────────────────────────
// Dipakai aplikasi lain yang menjadikan MPJApps sebagai master data anggota
// dan lembaga. Autentikasinya token layanan, bukan sesi pengguna, sehingga
// pemanggilnya tidak pernah membawa hak akses peran mana pun.
Route::prefix('external')->middleware(['service.token', 'throttle:120,1'])->group(function () {
    Route::get('/institutions',                 [ExternalApiController::class, 'institutions']);
    Route::get('/institutions/{id}',            [ExternalApiController::class, 'institutionDetail']);
    Route::get('/institutions/{id}/members',    [ExternalApiController::class, 'institutionMembers']);
    Route::get('/members/{id}/institutions',    [ExternalApiController::class, 'memberInstitutions']);
});

// ── Public claim flow used by landing page ────────────────────────────
Route::prefix('claims')->group(function () {
    Route::get('/search',                  [ClaimController::class, 'search']);
    Route::post('/send-otp',               [ClaimController::class, 'sendOtp']);
    Route::post('/verify-otp',             [ClaimController::class, 'verifyOtp']);
    Route::get('/contact/{claimId}',       [ClaimController::class, 'contact']);
});

// ── Authenticated routes ───────────────────────────────────────────────
Route::middleware('auth:api')->group(function () {

    // ── Claims ────────────────────────────────────────────────────────
    Route::prefix('claims')->group(function () {
        Route::get('/pending-count',           [ClaimController::class, 'pendingCount']);
    });

    // ── Payments ──────────────────────────────────────────────────────
    Route::prefix('payments')->group(function () {
        Route::get('/current',       [PaymentController::class, 'current']);
        Route::get('/summary',       [PaymentController::class, 'summary']);
        Route::post('/submit-proof', [PaymentController::class, 'submitProof']);
    });

    // ── Profile pesantren ─────────────────────────────────────────────
    Route::prefix('profile')->group(function () {
        Route::get('/pesantren',  [ProfileController::class, 'getPesantren']);
        Route::put('/pesantren',  [ProfileController::class, 'updatePesantren']);
        Route::post('/upgrade/request', [ProfileController::class, 'requestUpgrade']);
        Route::post('/renewal/request', [ProfileController::class, 'requestRenewal']);
    });
    Route::post('/media/upload-pesantren', [ProfileController::class, 'uploadPesantren']);

    // ── Media (user dashboard) ────────────────────────────────────────
    Route::prefix('media')->group(function () {
        Route::get('/jabatan-codes',     [MediaController::class, 'jabatanCodes']);
        Route::get('/crew',              [MediaController::class, 'getCrew']);
        Route::post('/crew',             [MediaController::class, 'createCrew']);
        Route::put('/crew/{id}',         [MediaController::class, 'updateCrew']);
        Route::delete('/crew/{id}',      [MediaController::class, 'deleteCrew']);
        Route::get('/slot-config',       [MediaController::class, 'slotConfig']);
        Route::post('/slot-addons/request', [MediaController::class, 'requestSlotAddon']);
        Route::get('/dashboard-context', [MediaController::class, 'dashboardContext']);
        Route::get('/profile-settings',  [MediaController::class, 'profileSettings']);
        Route::put('/profile-settings',  [MediaController::class, 'updateProfileSettings']);
        Route::get('/notification-preferences', [MediaController::class, 'notificationPreferences']);
        Route::put('/notification-preferences', [MediaController::class, 'updateNotificationPreferences']);
        Route::post('/profile-settings/photo', [MediaController::class, 'uploadCrewPhoto']);
        Route::post('/profile-settings/cv', [MediaController::class, 'uploadCrewCv']);
    });

    // ── Institution ───────────────────────────────────────────────────
    Route::prefix('institution')->group(function () {
        Route::get('/ownership',                    [InstitutionController::class, 'ownership']);
        Route::post('/upload-registration-document',[InstitutionController::class, 'uploadRegistrationDocument']);
        Route::post('/initial-data',                [InstitutionController::class, 'initialData']);
        Route::post('/location',                    [InstitutionController::class, 'location']);
        Route::get('/pending-status',               [InstitutionController::class, 'pendingStatus']);
    });

    // Dokumen pendaftaran privat: authorization diperiksa terhadap entitas klaim.
    Route::get('/documents/klaim/{claimId}', [DocumentController::class, 'dokumenKlaim']);

    // ── Hub resources ────────────────────────────────────────────────
    Route::prefix('hub')->group(function () {
        Route::get('/resources', [AdminController::class, 'hubResources']);
    });

    // ── Militansi ────────────────────────────────────────────────────
    Route::prefix('militansi')->group(function () {
        Route::get('/overview', [AdminController::class, 'myMilitansiOverview']);
    });

    // ── Regional admin ────────────────────────────────────────────────
    Route::prefix('regional')->group(function () {
        Route::get('/master-data',                        [RegionalController::class, 'masterData'])->middleware('access:data-master');
        Route::get('/pending-claims',                     [RegionalController::class, 'pendingClaims'])->middleware('access:validasi-pendaftar');
        Route::get('/pricing-packages',                   [RegionalController::class, 'pricingPackages'])->middleware('access:validasi-pendaftar');
        Route::post('/claims/{id}/approve',               [RegionalController::class, 'approveClaim'])->middleware('access:validasi-pendaftar,update');
        Route::post('/claims/{id}/reject',                [RegionalController::class, 'rejectClaim'])->middleware('access:validasi-pendaftar,update');
        Route::get('/late-payments',                      [RegionalController::class, 'latePayments'])->middleware('access:late-payment');
        Route::post('/late-payments/{claimId}/follow-up', [RegionalController::class, 'followUp'])->middleware('access:late-payment,create');
        Route::get('/performance',                        [RegionalController::class, 'performance'])->middleware('access:data-master');
        Route::get('/leaderboard',                        [RegionalController::class, 'leaderboard'])->middleware('access:data-master');
        Route::get('/reports',                            [RegionalController::class, 'reports'])->middleware('access:laporan');
        Route::post('/reports',                           [RegionalController::class, 'submitReport'])->middleware('access:laporan,create');
        Route::delete('/reports/{id}',                    [RegionalController::class, 'deleteReport'])->middleware('access:laporan,delete');
        Route::get('/download-center',                    [RegionalController::class, 'downloadCenter'])->middleware('access:download-center');
        Route::get('/militansi/overview',                 [RegionalController::class, 'militansiOverview'])->middleware('access:militansi');
    });

    // ── MPJ Hub & Militansi XP (semua user login) ─────────────────────
    Route::get('/hub/resources',      [AdminController::class, 'hubResources']);
    Route::get('/militansi/overview', [AdminController::class, 'myMilitansiOverview']);

    // ── Admin pusat ───────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {
        Route::get('/home-summary',                        [AdminController::class, 'homeSummary']);

        // Clearing house
        Route::get('/clearing-house/pending',              [AdminController::class, 'clearingHousePending'])->middleware('access:administrasi');
        Route::post('/clearing-house/{id}/approve',        [AdminController::class, 'clearingHouseApprove'])->middleware('access:administrasi,update');
        Route::post('/clearing-house/{id}/reject',         [AdminController::class, 'clearingHouseReject'])->middleware('access:administrasi,update');

        Route::get('/pending-profiles',                    [AdminController::class, 'pendingProfiles'])->middleware('access:administrasi');

        // Admin settings
        Route::get('/admin-settings/data',                 [AdminController::class, 'adminSettingsData'])->middleware('access:pengaturan');
        Route::get('/admin-settings/search-crew',          [AdminController::class, 'adminSettingsSearchCrew'])->middleware('access:pengaturan');
        Route::post('/admin-settings/assign',              [AdminController::class, 'adminSettingsAssign'])->middleware('access:pengaturan,update');
        Route::delete('/admin-settings/{userId}',          [AdminController::class, 'adminSettingsRemove'])->middleware('access:pengaturan,delete');

        // Master data
        Route::get('/master-data',                         [AdminController::class, 'masterData'])->middleware('access:master-data');
        Route::put('/master-data/pesantren/{id}',          [AdminController::class, 'masterDataUpdatePesantren'])->middleware('access:master-data,update');
        Route::put('/master-data/media/{id}',              [AdminController::class, 'masterDataUpdateMedia'])->middleware('access:master-data,update');
        Route::put('/master-data/crew/{id}',               [AdminController::class, 'masterDataUpdateCrew'])->middleware('access:master-data,update');
        Route::delete('/master-data/crew/{id}',            [AdminController::class, 'masterDataDeleteCrew'])->middleware('access:master-data,delete');
        Route::post('/master-data/import',                 [AdminController::class, 'masterDataImport'])->middleware('access:master-data,create');

        // Jabatan codes
        Route::get('/jabatan-codes',                       [AdminController::class, 'jabatanCodes'])->middleware('access:master-data');
        Route::post('/jabatan-codes',                      [AdminController::class, 'createJabatanCode'])->middleware('access:master-data,create');
        Route::put('/jabatan-codes/{id}',                  [AdminController::class, 'updateJabatanCode'])->middleware('access:master-data,update');
        Route::delete('/jabatan-codes/{id}',               [AdminController::class, 'deleteJabatanCode'])->middleware('access:master-data,delete');

        // Search & stats
        Route::get('/global-search',                       [AdminController::class, 'globalSearch']);
        Route::get('/super-stats',                         [AdminController::class, 'superStats']);
        Route::get('/hub/resources',                       [AdminController::class, 'adminHubResources'])->middleware('access:mpj-hub');
        Route::post('/hub/resources',                      [AdminController::class, 'storeHubResource'])->middleware('access:mpj-hub,create');
        Route::delete('/hub/resources/{id}',               [AdminController::class, 'deleteHubResource'])->middleware('access:mpj-hub,delete');
        Route::get('/militansi/summary',                   [AdminController::class, 'militansiSummary'])->middleware('access:militansi');
        Route::get('/audit-logs',                          [AdminController::class, 'auditLogs'])->middleware('access:hierarchy');
        Route::get('/late-payment-count',                  [AdminController::class, 'latePaymentCount'])->middleware('access:administrasi');

        // Pusat assistants
        Route::get('/pusat-assistants',                    [AdminController::class, 'pusatAssistants'])->middleware('access:pengaturan');
        Route::post('/pusat-assistants',                   [AdminController::class, 'addPusatAssistant'])->middleware('access:pengaturan,update');
        Route::delete('/pusat-assistants/{crewId}',        [AdminController::class, 'removePusatAssistant'])->middleware('access:pengaturan,delete');

        // Regional management
        Route::get('/regional-management/data',            [AdminController::class, 'regionalManagementData'])->middleware('access:master-regional');
        Route::post('/regional-management/regions',        [AdminController::class, 'addRegion'])->middleware('access:master-regional,create');
        Route::put('/regional-management/regions/{id}',    [AdminController::class, 'updateRegion'])->middleware('access:master-regional,update');
        Route::delete('/regional-management/regions/{id}', [AdminController::class, 'deleteRegion'])->middleware('access:master-regional,delete');
        Route::post('/regional-management/regions/merge',   [AdminController::class, 'mergeRegions'])->middleware('access:master-regional,update');
        Route::post('/regional-management/cities',         [AdminController::class, 'addCity'])->middleware('access:master-regional,create');
        Route::delete('/regional-management/cities/{id}',  [AdminController::class, 'deleteCity'])->middleware('access:master-regional,delete');
        Route::post('/regional-management/assign-admin',   [AdminController::class, 'assignRegionalAdmin'])->middleware('access:master-regional,update');

        // Users management
        Route::get('/users-management',                    [AdminController::class, 'usersManagement'])->middleware('access:user-management');
        Route::post('/users/{id}',                         [AdminController::class, 'updateUser'])->middleware('access:user-management,update');

        // Settings
        Route::get('/bank-settings',                       [AdminController::class, 'bankSettings'])->middleware('access:harga');
        Route::post('/bank-settings',                      [AdminController::class, 'updateBankSettings'])->middleware('access:harga,update');
        Route::get('/price-settings',                      [AdminController::class, 'priceSettings'])->middleware('access:harga');
        Route::post('/price-settings',                     [AdminController::class, 'updatePriceSettings'])->middleware('access:harga,update');

        // Regions detail
        Route::get('/regions/{id}/detail',                 [AdminController::class, 'regionDetail'])->middleware('access:master-regional');

        // Claims & payments
        Route::get('/claims',                              [AdminController::class, 'claims'])->middleware('access:administrasi');
        // Antrean aktivasi Crew Media di dalam kuota Golden 3: tanpa invoice,
        // diverifikasi Admin Pusat lewat Administrasi, bukan Admin Finance.
        Route::get('/crew-activations',                    [AdminController::class, 'crewActivations'])->middleware('access:administrasi');
        Route::post('/crew-activations/{id}/approve',      [AdminController::class, 'approveCrewActivation'])->middleware('access:administrasi,update');
        Route::post('/crew-activations/{id}/reject',       [AdminController::class, 'rejectCrewActivation'])->middleware('access:administrasi,update');
        Route::get('/payments',                            [AdminController::class, 'payments'])->middleware('access:verifikasi');
        Route::post('/payments/{id}/reject',               [AdminController::class, 'rejectPayment'])->middleware('access:verifikasi,update');
        Route::post('/payments/{id}/approve',              [AdminController::class, 'approvePayment'])->middleware('access:verifikasi,update');
        Route::get('/payments/{id}/logs',                  [AdminController::class, 'paymentLogs'])->middleware('access:verifikasi');
        Route::post('/payments/{id}/cancel',               [AdminController::class, 'cancelPayment'])->middleware('access:verifikasi,update');
        Route::post('/payments/{id}/expire',               [AdminController::class, 'expirePayment'])->middleware('access:verifikasi,update');

        // Leveling
        Route::get('/leveling-profiles',                   [AdminController::class, 'levelingProfiles'])->middleware('access:master-data');
        Route::post('/leveling/{id}/promote-platinum',     [AdminController::class, 'promotePlatinum'])->middleware('access:master-data,update');

        // Pricing packages
        Route::get('/pricing-packages',                    [AdminController::class, 'pricingPackages'])->middleware('access:harga');
        Route::post('/pricing-packages',                   [AdminController::class, 'createPricingPackage'])->middleware('access:harga,create');
        Route::put('/pricing-packages/{id}',               [AdminController::class, 'updatePricingPackage'])->middleware('access:harga,update');
        Route::patch('/pricing-packages/{id}/toggle',      [AdminController::class, 'togglePricingPackage'])->middleware('access:harga,update');
    });

    // ── Roles (Hak Akses) ─────────────────────────────────────────────
    Route::prefix('roles')->group(function () {
        Route::get('/',       [RoleController::class, 'index'])->middleware('access:hak-akses');
        Route::get('/{id}',   [RoleController::class, 'show'])->middleware('access:hak-akses');
        Route::post('/',      [RoleController::class, 'store'])->middleware('access:hak-akses,create');
        Route::put('/{id}',   [RoleController::class, 'update'])->middleware('access:hak-akses,update');
        Route::delete('/{id}',[RoleController::class, 'destroy'])->middleware('access:hak-akses,delete');
    });

    // ── Finance ───────────────────────────────────────────────────────
    Route::prefix('finance')->group(function () {
        Route::get('/stats', [AdminController::class, 'financeStats'])->middleware('access:finance');
    });

    // ── Events ────────────────────────────────────────────────────────
    Route::prefix('events')->group(function () {
        Route::get('/my-registrations',            [EventController::class, 'myRegistrations']);
        Route::get('/my-history',                  [EventController::class, 'myHistory']);
        Route::get('/my-ticket/{registrationId}',  [EventController::class, 'myTicket']);
        Route::get('/my-certificates',             [EventController::class, 'myCertificates']);

        // Regional event routes
        Route::get('/regional',                    [EventController::class, 'regionalIndex'])->middleware('access:admin-regional-manajemen-event');
        Route::post('/regional',                   [EventController::class, 'regionalStore'])->middleware('access:admin-regional-manajemen-event,create');
        Route::put('/regional/{id}',               [EventController::class, 'regionalUpdate'])->middleware('access:admin-regional-manajemen-event,update');
        Route::post('/regional/{id}/report',       [EventController::class, 'regionalSubmitReport'])->middleware('access:admin-regional-manajemen-event,create');

        Route::get('/',                        [EventController::class, 'index']);
        Route::post('/',                       [EventController::class, 'store'])->middleware('access:admin-pusat-manajemen-event,create');
        Route::get('/{id}',                    [EventController::class, 'show']);

        // Kelola event nasional (Admin Pusat). Sebelumnya hanya ada index dan
        // store, sehingga halaman Master Event tidak punya cara mengubah,
        // mengganti status, atau menghapus event.
        Route::put('/{id}',                    [EventController::class, 'update'])->middleware('access:admin-pusat-manajemen-event,update');
        Route::patch('/{id}/status',           [EventController::class, 'changeStatus'])->middleware('access:admin-pusat-manajemen-event,update');
        Route::delete('/{id}',                 [EventController::class, 'destroy'])->middleware('access:admin-pusat-manajemen-event,delete');

        Route::post('/{id}/speakers',          [EventController::class, 'addSpeaker'])->middleware('access:admin-pusat-manajemen-event,update');
        Route::delete('/{id}/speakers/{speakerId}', [EventController::class, 'deleteSpeaker'])->middleware('access:admin-pusat-manajemen-event,delete');
        Route::get('/{id}/participants',       [EventController::class, 'participants'])->middleware('access:admin-pusat-manajemen-event');
        Route::post('/{id}/register',          [EventController::class, 'register']);
        Route::post('/{id}/check-ticket',      [EventController::class, 'checkTicket'])->middleware('access:admin-pusat-manajemen-event,update');
        Route::post('/{id}/check-in',          [EventController::class, 'checkIn'])->middleware('access:admin-pusat-manajemen-event,update');
        Route::get('/{id}/reports',            [EventController::class, 'reports']);
        Route::post('/{id}/report',            [EventController::class, 'submitReport']);
    });
});
