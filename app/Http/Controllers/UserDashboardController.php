<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class UserDashboardController extends Controller
{
    /**
     * Display the user dashboard.
     */
    public function __invoke(): View
    {
        return view('dashboard.user');
    }
}
