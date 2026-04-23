<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Leave;

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

    public function create(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'half_day' => 'nullable|boolean',
            'reason' => 'required|string|max:255',
            'additional_info' => 'nullable|string|max:255',
        ]);

        // Logic to save the leave request to the database would go here

        $leave = Leave::create($request->all());

        return redirect()->route('leave.view')->with('success', 'Leave request created successfully.');
    }
}
