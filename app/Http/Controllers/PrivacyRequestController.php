<?php

namespace App\Http\Controllers;

use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Public intake for data-subject requests, with the legal deadline stamped on arrival. */
class PrivacyRequestController extends Controller
{
    public function create()
    {
        return view('pages.legal.privacy-request');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'type' => ['required', Rule::in(array_keys(PrivacyRequest::TYPES))],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);
        $email = mb_strtolower(trim($data['email']));

        PrivacyRequest::create([
            'user_id' => User::query()->where('email', $email)->value('id'),
            'email' => $email,
            'type' => $data['type'],
            'details' => $data['details'] ?? null,
            'status' => 'open',
            'due_at' => now()->addDays((int) config('legal.privacy_request_days', 30)),
            'ip_address' => $request->ip(),
        ]);

        // Same answer whether or not the email has an account.
        return back()->with('status', 'Thanks. We received your request and will contact you at that address to confirm your identity. We answer within '.config('legal.privacy_request_days').' days.');
    }
}
