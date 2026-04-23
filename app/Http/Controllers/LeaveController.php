<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function view()
    {
        return view('leave.view');
    }

    public function form()
    {
        return view('leave.form');
    }
}
