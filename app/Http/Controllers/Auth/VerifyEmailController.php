<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            app(\App\Services\ActivityLogger::class)->record(
                actor: $request->user(),
                subject: $request->user(),
                action: 'email_verified',
                description: 'verified their email address',
            );
            \Illuminate\Support\Facades\DB::afterCommit(fn () => event(new Verified($request->user())));
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
