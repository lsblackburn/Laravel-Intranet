<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use App\Models\ProjectType;
use App\Models\WorkJob;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($type, $validated) {
            $type = ProjectType::whereKey($type->id)->lockForUpdate()->firstOrFail();
            $previousName = $type->name;
            $type->update($validated);

            if ($previousName !== $type->name) {
                WorkJob::where('project_type', $previousName)->update(['project_type' => $type->name]);
            }
        });

        return redirect()->route('admin.view-project-types')->with('success', 'Project type updated successfully.');
    }

    public function deleteProjectTypes(ProjectType $type): RedirectResponse
    {
        DB::transaction(function () use ($type) {
            $type = ProjectType::whereKey($type->id)->lockForUpdate()->firstOrFail();
            WorkJob::where('project_type', $type->name)->update(['project_type' => null]);
            $type->delete();
        });

        return redirect()->route('admin.view-project-types')->with('success', 'Project type deleted successfully.');
    }
}
