<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function __invoke(Request $request): View
    {
        $warehouses = $request->user()->warehouseLocations()->orderBy('name')->get();

        return view('warehouse.settings.index', compact('warehouses'));
    }
}
