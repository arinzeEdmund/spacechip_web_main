<?php

namespace App\Http\Controllers;

use App\Services\MyEsimService;
use Illuminate\Support\Facades\Auth;

/**
 * The signed-in user's home: eSIMs, wallet, numbers.
 */
class DashboardController extends Controller
{
    public function __invoke(MyEsimService $myEsims)
    {
        return view('dashboard', [
            'initialMyEsims' => $myEsims->listForUser((int) Auth::id(), 'valid', 1, 10),
        ]);
    }
}
