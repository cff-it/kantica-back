<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public const string ERROR_CODE = 'ACCOUNT_PENDING_VALIDATION';

    public const string EMAIL_NOT_VERIFIED_CODE = 'EMAIL_NOT_VERIFIED';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && !$user->hasVerifiedEmail()) {
            return response()->json(
                [
                    'message' => 'Veuillez vérifier votre adresse email.',
                    'code'    => self::EMAIL_NOT_VERIFIED_CODE,
                ],
                403,
            );
        }

        if ($user !== null && $user->status !== UserStatus::Active) {
            return response()->json(
                [
                    'message' => 'Votre compte est en attente de validation.',
                    'code'    => self::ERROR_CODE,
                ],
                403,
            );
        }

        return $next($request);
    }
}
