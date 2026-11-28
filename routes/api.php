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
use App\Http\Controllers\UserImportController;
use App\Models\Event;
use App\Models\Song;
use App\Models\User;
use App\Models\UserProfile;
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

    // ── Fiches de recensement des membres (Secrétaire et Admin) ─────────
    // whereNumber : `/members/export` (KAN-30) ne doit jamais être pris pour un identifiant
    Route::get('/members', [MemberController::class, 'index'])
        ->can('viewAny', UserProfile::class);
    // Ajout d'un membre (KAN-89) : compte actif créé par la secrétaire ou l'admin, qui reçoit un mail d'invitation
    Route::post('/members', [MemberController::class, 'store'])
        ->can('create', UserProfile::class);
    Route::get('/members/{member}', [MemberController::class, 'show'])
        ->whereNumber('member')
        ->can('view', UserProfile::class);
    Route::put('/members/{member}', [MemberController::class, 'update'])
        ->whereNumber('member')
        ->can('update', UserProfile::class);
    // Attribution de la voix seule (KAN-34, US-10) : la fiche complète n'est pas demandée
    Route::put('/members/{member}/voice', [MemberController::class, 'assignVoice'])
        ->whereNumber('member')
        ->can('update', UserProfile::class);

    // ── Validation des membres ──────────────────────────────────────────
    Route::post('/members/{member}/validate', [MemberController::class, 'validateAccount'])
        ->can('validate', 'member');

    // ── Export de la liste des membres (Secrétaire et Admin) ────────────
    Route::get('/members/export', [MemberController::class, 'export'])
        ->can('export', User::class);

    // ── Répertoire chants ───────────────────────────────────────────────
    // `{song}` : 18 chiffres au plus, sous PHP_INT_MAX, pour toutes les routes du groupe. Un identifiant non numérique ou
    // trop grand donne un 404, au lieu d'une erreur 500 sur le paramètre `int $song` du contrôleur (`whereNumber`
    // laissait passer les nombres trop grands)
    Route::prefix('songs')->where(['song' => '[0-9]{1,18}',])->group(function () {
        // Répertoire (KAN-18, US-04) : `?search=` (titre ou auteur) et `?page=`
        Route::get('/', [SongController::class, 'index'])
            ->can('viewAny', Song::class);
        // Chaque route contrôle le rôle elle-même, avec la classe (403 avant tout 404) : lecture pour les trois rôles,
        // ajout, modification et suppression pour l'admin (KAN-13). Avant, tout compte validé pouvait écrire. Les
        // FormRequests le vérifient aussi (UpdateSongRequest avec le cantique, lu grâce à la contrainte de `{song}`)
        Route::post('/', [SongController::class, 'store'])
            ->can('create', Song::class);
        // Un cantique se lit avec le même droit que la liste : un compte sans rôle reçoit un 403, avant tout 404
        Route::get('/{song}', [SongController::class, 'show'])
            ->can('viewAny', Song::class);
        Route::put('/{song}', [SongController::class, 'update'])
            ->can('update', Song::class);
        Route::delete('/{song}', [SongController::class, 'destroy'])
            ->can('delete', Song::class);
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
        // Import Excel (KAN-31) : déclaré avant `/{user}` ; réservé à l'admin et à la secrétaire
        Route::prefix('import')->middleware('can:import,'.User::class)->group(function () {
            Route::get('/template', [UserImportController::class, 'template']);
            Route::post('/preview', [UserImportController::class, 'preview']);
            Route::post('/', [UserImportController::class, 'import']);
            // Historique : l'admin voit tous les imports, la secrétaire les siens
            Route::get('/', [UserImportController::class, 'index']);
            // Détail et suivi d'un import en tâche de fond : réservé à celui qui l'a lancé et à l'admin (UserImportPolicy)
            Route::get('/{userImport}', [UserImportController::class, 'show'])
                ->whereNumber('userImport')
                ->middleware('can:view,userImport');
        });

        // Réservées à l'admin (KAN-35) : « Gestion des rôles » et comptes. Identifiants bruts et Policy appelée avec la
        // classe : le refus 403 passe avant tout 404
        Route::get('/', [UserController::class, 'index'])
            ->can('viewAny', User::class);
        Route::post('/', [UserController::class, 'store'])
            ->can('create', User::class);
        Route::get('/{user}', [UserController::class, 'show'])
            ->whereNumber('user')
            ->can('viewAny', User::class);
        // Un seul rôle par compte : remplace l'ancien POST /{user}/roles, qui ajoutait un rôle sans retirer les autres
        Route::put('/{user}/role', [UserController::class, 'assignRole'])
            ->whereNumber('user')
            ->can('assignRole', User::class);
    });

    // ── Événements ──────────────────────────────────────────────────────
    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::post('/', [EventController::class, 'store']);
        Route::get('/{event}', [EventController::class, 'show']);
        Route::post('/{event}/subscribe', [EventController::class, 'subscribe']);
        Route::post('/{event}/check-in', [EventController::class, 'checkIn']);
        // Saisie des présences (KAN-12, US-11) : Secrétaire et Admin. Policy appelée avec la classe, sans événement : le
        // refus 403 passe avant tout 404 ; `{event}` limité à 18 chiffres (sous PHP_INT_MAX), comme `{song}`
        Route::get('/{event}/attendances', [EventController::class, 'attendances'])
            ->where('event', '[0-9]{1,18}')
            ->can('manageAttendances', Event::class);
        Route::put('/{event}/attendances', [EventController::class, 'recordAttendances'])
            ->where('event', '[0-9]{1,18}')
            ->can('manageAttendances', Event::class);
    });

    // ── Paiements ───────────────────────────────────────────────────────
    Route::prefix('events/{event}/payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::get('/users', [PaymentController::class, 'userPayments']);
        Route::post('/record', [PaymentController::class, 'record']);
    });
});
