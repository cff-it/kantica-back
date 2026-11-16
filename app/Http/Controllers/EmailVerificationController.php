<?php

namespace App\Http\Controllers;

use App\Actions\Auth\ResendEmailVerificationAction;
use App\Actions\Auth\VerifyEmailAction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * @unauthenticated
     */
    public function verify(User $user, string $hash, VerifyEmailAction $action): JsonResponse
    {
        $action($user, $hash);

        return response()->json(['message' => 'Adresse email vérifiée.',]);
    }

    public function resend(Request $request, ResendEmailVerificationAction $action): JsonResponse
    {
        $sent = $action($request->user());

        return response()->json([
            'message' => $sent ? 'Email de vérification envoyé.' : 'Adresse email déjà vérifiée.',
        ]);
    }
}
