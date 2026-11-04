<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Google / Facebook sign-in via Socialite. An existing account is matched by provider id or
 * email (and linked); otherwise a verified account is created with a random password.
 */
class SocialAuthController extends Controller
{
    public function redirectToProvider($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'Social login failed.');
        }

        $email = mb_strtolower(trim((string) $socialUser->getEmail()));
        if ($email === '') {
            return redirect('/login')->with('error', 'Your social account did not share an email address, so we could not sign you in.');
        }

        // Match by the provider's own id. Only Google vouches for its email addresses, so only a Google sign-in
        // may attach to an existing account with the same (already verified) email; anything else could let a
        // stranger take over an account by registering someone else's email at another provider.
        $user = User::where("{$provider}_id", $socialUser->getId())->first();
        if (! $user && $provider === 'google') {
            $user = User::where('email', $email)->whereNotNull('email_verified_at')->first();
        }
        if (! $user && User::where('email', $email)->exists()) {
            return redirect('/login')->with('error', 'An account with this email already exists. Sign in with your password (or Google) and link it from your profile.');
        }

        if ($user) {
            $user->update([
                "{$provider}_id" => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
            ]);
        } else {
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                'email' => $email,
                "{$provider}_id" => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
                'password' => bcrypt(Str::random(24)),
            ]);
            if ($provider === 'google') {
                $user->forceFill(['email_verified_at' => now()])->save(); // Google vouches for the address
            } else {
                event(new \Illuminate\Auth\Events\Registered($user)); // everyone else verifies by email link
            }
            app(\App\Services\ConsentRecorder::class)->recordSignup($user->id, request(), 'social_register:'.$provider);
        }

        Auth::login($user);

        return redirect()->intended('/');
    }
}
