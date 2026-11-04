<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpVerificationMail;
use App\Models\User;
use App\Services\Admin\LoginRecorder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Throwable;

/**
 * Token-based authentication for the mobile app (Sanctum). Registration
 * issues no token until the emailed one-time code is verified.
 */
class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', PasswordRule::defaults()],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $email = mb_strtolower(trim((string) $data['email']));
        if (User::query()->where('email', $email)->exists()) {
            return response()->json(['message' => 'Email already in use.'], 422);
        }

        $user = User::create([
            'name' => (string) $data['name'],
            'email' => $email,
            'password' => (string) $data['password'],
        ]);

        // The app shows the Terms/Privacy notice at sign-up; we record which version was in force and when.
        app(\App\Services\ConsentRecorder::class)->recordSignup($user->id, $request, 'mobile_register');

        $this->sendOtp($user); // no token until the code is verified

        return response()->json([
            'ok' => true,
            'requires_otp' => true,
            'user_id' => (int) $user->id,
            'message' => 'A 6-digit verification code has been sent to your email.',
            'user' => $this->userPayload($user),
        ], 201);
    }

    /**
     * Mobile: verify OTP and receive Sanctum token (auto-login)
     */
    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::find((int) $data['user_id']);
        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            // Already verified. Do NOT issue a token here — this endpoint does not
            // authenticate the caller, so minting a session for an already-verified
            // account without checking the OTP is an account-takeover vector.
            return response()->json([
                'message' => 'Email already verified. Please sign in.',
            ], 409);
        }

        if (! $user->checkEmailOtp((string) $data['otp'])) {
            return response()->json(['message' => 'Invalid or expired verification code. After 5 wrong tries, request a new code.'], 422);
        }

        $user->clearEmailOtp();
        $user->forceFill(['email_verified_at' => now()])->save();

        event(new Verified($user));

        return $this->tokenResponse($user, $data['device_name'] ?? null, $this->userPayload($user));
    }

    /**
     * Mobile: resend OTP (rate limited to 3 per 10 min per user)
     */
    public function resendOtp(Request $request)
    {
        $data = $request->validate(['user_id' => ['required', 'integer']]);

        $user = User::find((int) $data['user_id']);
        if (! $user || $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Not applicable.'], 422);
        }

        $this->sendOtp($user);

        return response()->json(['ok' => true, 'message' => 'A new verification code has been sent.']);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $email = mb_strtolower(trim((string) $data['email']));
        $user = User::query()->where('email', $email)->first();
        $recorder = app(LoginRecorder::class);

        // Wrong password and unknown email get the same answer so accounts can't be enumerated.
        if (! $user || ! Hash::check((string) $data['password'], (string) $user->password)) {
            $recorder->failure($email, $user, $request, 'api');

            return response()->json(['message' => 'Invalid credentials.'], 422);
        }
        if ($user->isSuspended()) {
            $recorder->failure($email, $user, $request, 'api');

            return response()->json(['message' => 'This account has been suspended. Contact support@spacechipltd.com for help.', 'code' => 'account_suspended'], 403);
        }

        $recorder->success($user, $request, 'api');

        return $this->tokenResponse($user, $data['device_name'] ?? null, $this->userPayload($user, withWallet: true));
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['ok' => true]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json(['ok' => true, 'user' => $this->userPayload($user, withWallet: true)]);
    }

    public function resendVerification(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if ($user->hasVerifiedEmail()) {
            return response()->json(['ok' => true, 'message' => 'Email already verified.']);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable) {
        }

        return response()->json(['ok' => true]);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);
        $status = Password::sendResetLink(['email' => (string) $data['email']]);
        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json(['message' => 'Unable to send reset link.'], 422);
        }

        return response()->json(['ok' => true]);
    }

    /** Permanently delete the account and revoke every token (App Store account-deletion requirement). */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['ok' => true]);
    }

    /** Issue (and store) a fresh 6-digit code and email it. Mail failures are swallowed; the user can resend. */
    private function sendOtp(User $user): void
    {
        $otp = $user->issueEmailOtp();

        try {
            Mail::to($user->email)->send(new OtpVerificationMail($otp, $user->name));
        } catch (Throwable) {
        }
    }

    private function tokenResponse(User $user, ?string $deviceName, array $userPayload)
    {
        return response()->json([
            'ok' => true,
            'token_type' => 'Bearer',
            'token' => $user->createToken(trim((string) $deviceName) ?: 'mobile')->plainTextToken,
            'user' => $userPayload,
        ]);
    }

    private function userPayload(User $user, bool $withWallet = false): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
        ] + ($withWallet ? ['wallet_balance_minor' => (int) ($user->wallet_balance_minor ?? 0)] : []);
    }
}
