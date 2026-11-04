<?php

return [
    // false = the full Content-Security-Policy is only reported (Report-Only) and violations are logged;
    // true = it is enforced. Flip after the logged violations are clean (see launch:check).
    'csp_enforce' => (bool) env('CSP_ENFORCE', false),
];
