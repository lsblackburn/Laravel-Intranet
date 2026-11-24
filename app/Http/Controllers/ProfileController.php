<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use App\Models\User;
use App\Models\UserDepartment;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $departments = UserDepartment::orderBy('department', 'asc')->get();

        return view('profile.edit', [
            'user' => $request->user(),
            'departments' => $departments,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $actor = clone $request->user();
        $request->user()->fill($request->validated());
        $changedFields = array_keys($request->user()->getDirty());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        if ($changedFields !== []) {
            app(\App\Services\ActivityLogger::class)->record(
                actor: $actor,
                subject: $request->user(),
                action: 'profile_updated',
                description: 'updated their profile (changed: '.implode(', ', $changedFields).')',
            );
        }

        return Redirect::route('profile.edit')->with('success', 'Profile information updated successfully');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request, ?User $user = null): RedirectResponse
    {
        $targetUser = $user ?? $request->user();

        if ($user && $targetUser->id === $request->user()->id) {
            return redirect()->route('admin.users')->with('error', 'You cannot delete yourself through the Admin panel.');
        }

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $actor = clone $request->user();
        $targetName = $targetUser->name;
        $targetUser->delete();

        app(\App\Services\ActivityLogger::class)->record(
            actor: $actor,
            subject: $targetUser,
            action: 'user_deleted',
            description: $user ? "deleted the user account for {$targetName}" : 'deleted their account',
        );

        if ($targetUser->id === $actor->id) {
            Auth::logout();
        }

        if ($user) {
            return redirect()->route('admin.users')->with('success', 'User deleted successfully.');
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
