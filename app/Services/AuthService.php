<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Handles authentication use cases.
 *
 * This service owns credential verification and token lifecycle. It does not
 * perform HTTP concerns such as authorization checks or response shaping.
 */
final class AuthService
{
    /**
     * Register a new user and issue a personal access token.
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = User::create($data);

        Log::info('auth.registered', [
            'user_id' => $user->id,
        ]);

        return [
            'user' => $user,
            'token' => $this->issueToken($user),
        ];
    }

    /**
     * Verify credentials and issue a personal access token.
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException When the email or password is incorrect.
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            Log::warning('auth.login.failed', [
                'email_hash' => hash_hmac('sha256', Str::lower($email), (string) config('app.key')),
            ]);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        Log::info('auth.login', [
            'user_id' => $user->id,
        ]);

        return [
            'user' => $user,
            'token' => $this->issueToken($user),
        ];
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();
        $tokenId = $token->id;
        $token->delete();

        Log::info('auth.logged_out', [
            'user_id' => $user->id,
            'token_id' => $tokenId,
        ]);
    }

    /**
     * Issue a new personal access token for the user.
     */
    private function issueToken(User $user): string
    {
        return $user->createToken('auth-token')->plainTextToken;
    }
}
