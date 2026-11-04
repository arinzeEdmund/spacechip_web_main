<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/** Records every admin action so changes to users, money and orders are traceable. */
class AuditLogger
{
    public function log(string $action, string $description, ?Model $subject = null, array $meta = []): AdminAuditLog
    {
        return AdminAuditLog::create([
            'admin_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => mb_substr($description, 0, 500),
            'meta' => $meta ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 1000),
        ]);
    }
}
