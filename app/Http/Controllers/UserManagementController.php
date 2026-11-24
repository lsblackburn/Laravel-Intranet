<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\ActivityLogger;

class UserManagementController extends Controller
{
    public function promote(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users')->with('error', 'You cannot promote yourself.');
        }

        $user->role = 'admin';
        $user->save();

        app(ActivityLogger::class)->record(
            actor: $request->user(),
            subject: $user,
            action: 'user_promotion',
            description: "promoted {$user->name} to Admin",
        );

        return redirect()->route('admin.users')->with('success', 'User promoted to admin successfully.');
    }

    public function demote(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users')->with('error', 'You cannot demote yourself.');
        }

        $user->role = 'employee';
        $user->save();

        app(ActivityLogger::class)->record(
            actor: $request->user(),
            subject: $user,
            action: 'user_demotion',
            description: "demoted {$user->name} to general user",
        );

        return redirect()->route('admin.users')->with('success', 'User demoted to regular user successfully.');
    }

    public function update(ProfileUpdateRequest $request, User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users')->with('error', 'You cannot update your own profile in the Admin panel.');
        }

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        app(ActivityLogger::class)->record(
            actor: $request->user(),
            subject: $user,
            action: 'user_update',
            description: "updated {$user->name} account details",
        );

        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }

}
