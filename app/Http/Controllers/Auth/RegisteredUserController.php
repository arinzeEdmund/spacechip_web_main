<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpVerificationMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'accept_terms' => ['accepted'],
        ], ['accept_terms.accepted' => 'Please accept the Terms and Privacy Policy to create an account.']);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        app(\App\Services\ConsentRecorder::class)->recordSignup($user->id, $request, 'web_register');

        $otp = $user->issueEmailOtp();

        try {
            Mail::to($user->email)->send(new OtpVerificationMail($otp, $user->name));
        } catch (\Throwable) {
        }

        // Store user id in session — NOT logged in yet
        $request->session()->put('otp_user_id', $user->id);

        return redirect()->route('verification.otp');
    }
}
