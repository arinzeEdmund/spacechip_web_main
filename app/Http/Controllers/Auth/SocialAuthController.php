<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

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

        $user = User::where("{$provider}_id", $socialUser->getId())
                    ->orWhere('email', $socialUser->getEmail())
                    ->first();

        if ($user) {
            $user->update([
                "{$provider}_id" => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
            ]);
        } else {
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                "{$provider}_id" => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
                'password' => bcrypt(Str::random(24)),
                'email_verified_at' => now(), // Assume social provider emails are verified
            ]);
        }

        Auth::login($user);

        return redirect()->intended('/');
    }
}
