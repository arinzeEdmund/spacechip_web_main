<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AuditLogger;
use App\Services\Admin\Totp;
use App\Services\QrCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** The signed-in admin's own security settings: authenticator app enrolment. */
class SecurityController extends Controller
{
    public function __construct(private Totp $totp, private AuditLogger $audit) {}

    public function show(Request $request, QrCodeService $qr): View
    {
        $user = $request->user();
        $pending = $request->session()->get('admin_totp_pending');

        return view('admin.security', [
            'enrolled' => (bool) ($user->two_factor_secret && $user->two_factor_confirmed_at),
            'enrolledAt' => $user->two_factor_confirmed_at,
            'pendingSecret' => $pending,
            'qrSvg' => $pending ? $qr->svgString($this->totp->uri($pending, $user->email), 200) : null,
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $request->session()->put('admin_totp_pending', $this->totp->generateSecret());

        return redirect()->route('admin.security');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $secret = (string) $request->session()->get('admin_totp_pending', '');
        $step = $secret !== '' ? $this->totp->verify($secret, $data['code']) : null;
        if ($step === null) {
            return back()->withErrors(['code' => 'That code did not match. Check the time on your phone and try again.']);
        }

        $user = $request->user();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step])->save();
        $request->session()->forget('admin_totp_pending');
        $this->audit->log('admin.2fa_enabled', 'Authenticator app enabled', $user);

        return redirect()->route('admin.security')->with('status', 'Authenticator app enabled. You can still use an emailed code.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        if (! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return back()->withErrors(['password' => 'Wrong password.']);
        }
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        $this->audit->log('admin.2fa_disabled', 'Authenticator app disabled (emailed codes remain)', $user);

        return redirect()->route('admin.security')->with('status', 'Authenticator app removed. Sign-in now uses emailed codes.');
    }
}
