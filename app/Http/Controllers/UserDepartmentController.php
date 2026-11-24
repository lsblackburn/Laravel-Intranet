<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserDepartment;
use App\Services\ActivityLogger;

class UserDepartmentController extends Controller
{
    public function createCompanyDepartments(Request $request)
    {
        $validated = $request->validate([
            'department' => 'required|string|max:255|unique:user_departments',
        ]);

        $department = UserDepartment::create($validated); 

        app(ActivityLogger::class)->record(
            actor: $request->user(),
            subject: $department,
            action: 'create_company_department',
            description: "created a company department called $department->department",
        );

        return redirect()->route('admin.view-company-departments')->with('success', 'Department created successfully.');
    }

    public function deleteCompanyDepartment(Request $request, UserDepartment $department)
    {
        $department->delete();

        app(ActivityLogger::class)->record(
            actor: $request->user(),
            subject: $department,
            action: 'delete_company_department',
            description: "deleted a company department called $department->department",
        );

        return redirect()->route('admin.view-company-departments')->with('success', 'Department deleted successfully.');
    }
}
