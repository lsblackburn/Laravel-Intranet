<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            'suggestedColour' => User::generateUniqueColour(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'employment_start_date' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}(?:$|[T\s])/'],
            'colour' => ['nullable', 'string', 'size:7', 'regex:/^#[0-9A-Fa-f]{6}$/', 'unique:'.User::class],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'employment_start_date' => $request->employment_start_date,
            'colour' => $request->input('colour') ?: User::generateUniqueColour(),
        ]);

        event(new Registered($user));

        return redirect()->route('admin.users')->with('success', 'User created successfully.');
    }
}
