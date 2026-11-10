<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use App\Models\ProjectType;

class ProjectTypeController extends Controller
{
    public function createProjectTypes(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:project_types',
        ]);

        ProjectType::create($validated); 

        return redirect()->route('admin.view-project-types')->with('success', 'Project type created successfully.');
    }

    public function updateProjectType(Request $request, ProjectType $type): RedirectResponse
    {
        $validated = $request->validateWithBag('updateProjectType'.$type->id, [
            'name' => ['required', 'string', 'max:255', Rule::unique('project_types', 'name')->ignore($type)],
        ]);

        $type->update($validated);

        return redirect()->route('admin.view-project-types')->with('success', 'Project type updated successfully.');
    }

    public function deleteProjectTypes(ProjectType $type): RedirectResponse
    {
        $type->delete();

        return redirect()->route('admin.view-project-types')->with('success', 'Project type deleted successfully.');
    }
}
