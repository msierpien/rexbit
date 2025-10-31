<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class AdminDashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function __invoke(): View
    {
        return view('dashboard.admin');
    }
}
