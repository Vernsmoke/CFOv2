<?php

namespace App\Http\Controllers;

use App\Services\AdminNavigation;

class NavBadgeController extends Controller
{
    public function __invoke()
    {
        return response()->json(AdminNavigation::badges())->header('Cache-Control', 'no-store');
    }
}
