<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MassController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberProfileController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Choralis Back
|--------------------------------------------------------------------------
*/

// ── Authentification ────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

// Lien signé envoyé par email : ouvert sans token
Route::get('/email/verify/{user}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1',])
    ->name('verification.verify');

// ── Routes accessibles aux comptes en attente de validation ────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1');

    // Fiche de recensement de l'utilisateur courant (aucun identifiant dans l'URL)
    Route::get('/me/profile', [MemberProfileController::class, 'show']);
    Route::put('/me/profile', [MemberProfileController::class, 'update']);
});

// ── Routes réservées aux comptes validés ───────────────────────────────
Route::middleware(['auth:sanctum', 'account.active',])->group(function () {

    // ── Validation des membres ──────────────────────────────────────────
    Route::post('/members/{member}/validate', [MemberController::class, 'validateAccount'])
        ->can('validate', 'member');

    // ── Export de la liste des membres (Secrétaire et Admin) ────────────
    Route::get('/members/export', [MemberController::class, 'export'])
        ->can('export', User::class);

    // ── Répertoire chants ───────────────────────────────────────────────
    Route::prefix('songs')->group(function () {
        Route::get('/', [SongController::class, 'index']);
        Route::post('/', [SongController::class, 'store']);
        Route::get('/{song}', [SongController::class, 'show']);
        Route::put('/{song}', [SongController::class, 'update']);
        Route::delete('/{song}', [SongController::class, 'destroy']);
    });

    // ── Liturgie messes ─────────────────────────────────────────────────
    Route::prefix('masses')->group(function () {
        Route::get('/', [MassController::class, 'index']);
        Route::post('/', [MassController::class, 'store']);
        Route::get('/{mass}', [MassController::class, 'show']);
        Route::post('/{mass}/parts/{part}/songs', [MassController::class, 'assignSong']);
        Route::get('/{mass}/pptx', [MassController::class, 'generatePptx']);
    });

    // ── Membres & rôles ─────────────────────────────────────────────────
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::post('/{user}/roles', [UserController::class, 'assignRole']);
    });

    // ── Événements ──────────────────────────────────────────────────────
    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::post('/', [EventController::class, 'store']);
        Route::get('/{event}', [EventController::class, 'show']);
        Route::post('/{event}/subscribe', [EventController::class, 'subscribe']);
        Route::post('/{event}/check-in', [EventController::class, 'checkIn']);
    });

    // ── Paiements ───────────────────────────────────────────────────────
    Route::prefix('events/{event}/payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::get('/users', [PaymentController::class, 'userPayments']);
        Route::post('/record', [PaymentController::class, 'record']);
    });
});
