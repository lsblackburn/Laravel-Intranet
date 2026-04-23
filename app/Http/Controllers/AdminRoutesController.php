<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminRoutesController extends Controller
{

    public function dashboard()
    {
        return view('admin.dashboard');
    }

}
