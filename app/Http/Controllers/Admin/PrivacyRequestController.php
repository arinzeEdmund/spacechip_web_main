<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrivacyRequest;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Queue of data-subject requests with their legal deadline. */
class PrivacyRequestController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.privacy-requests', [
            'requests' => PrivacyRequest::query()->with('user')->orderByRaw('completed_at is not null')->orderBy('due_at')->paginate(30),
        ]);
    }

    public function complete(Request $request, PrivacyRequest $privacyRequest): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:completed,rejected'], 'resolution' => ['required', 'string', 'max:2000']]);
        $privacyRequest->update(['status' => $data['status'], 'resolution' => $data['resolution'], 'completed_at' => now()]);
        $this->audit->log('privacy_request.closed', "Privacy request #{$privacyRequest->id} {$data['status']}", $privacyRequest, ['type' => $privacyRequest->type]);

        return back()->with('status', 'Request closed.');
    }
}
