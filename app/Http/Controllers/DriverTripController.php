<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DriverTripController extends Controller
{
    public function tripListPage()
    {
        return view('driver.trip-list');
    }
}
