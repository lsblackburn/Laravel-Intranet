<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use App\Models\ProjectType;
use App\Models\WorkJob;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;

class ProjectTypeController extends Controller
{
    public function createProjectTypes(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:project_types',
        ]);

        $projectType = ProjectType::create($validated); 

        app(ActivityLogger::class)->record(
            actor: $request->user(),
            subject: $projectType,
            action: 'projectTypeCreate',
            description: "created a project type called $projectType->name",
        );

        return redirect()->route('admin.view-project-types')->with('success', 'Project type created successfully.');
    }

    public function updateProjectType(Request $request, ProjectType $type): RedirectResponse
    {
        $validated = $request->validateWithBag('updateProjectType'.$type->id, [
            'name' => ['required', 'string', 'max:255', Rule::unique('project_types', 'name')->ignore($type)],
        ]);

        [$type, $previousName] = DB::transaction(function () use ($type, $validated) {
            $type = ProjectType::whereKey($type->id)->lockForUpdate()->firstOrFail();
            $previousName = $type->name;
            $type->update($validated);

            if ($previousName !== $type->name) {
                WorkJob::where('project_type', $previousName)->update(['project_type' => $type->name]);
            }

            return [$type, $previousName];
        });

        if ($previousName !== $type->name) {
            app(ActivityLogger::class)->record(
                actor: $request->user(),
                subject: $type,
                action: 'projectTypeUpdate',
                description: "renamed project type {$previousName} to {$type->name} and updated its associated jobs",
            );
        }

        return redirect()->route('admin.view-project-types')->with('success', 'Project type updated successfully.');
    }

    public function deleteProjectTypes(Request $request, ProjectType $type): RedirectResponse
    {
        DB::transaction(function () use ($type) {
            $type = ProjectType::whereKey($type->id)->lockForUpdate()->firstOrFail();
            WorkJob::where('project_type', $type->name)->update(['project_type' => null]);
            $type->delete();
        });

        app(ActivityLogger::class)->record(
            actor: $request->user(),
            subject: $type,
            action: 'projectTypeDelete',
            description: "deleted project type {$type->name} and cleared it from associated jobs",
        );

        return redirect()->route('admin.view-project-types')->with('success', 'Project type deleted successfully.');
    }
}
