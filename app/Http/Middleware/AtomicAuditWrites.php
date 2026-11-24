<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AtomicAuditWrites
{
    public function handle(Request $request, Closure $next)
    {
        // The invoice form materializes invoice records; email verification writes on GET.
        if ($request->isMethodSafe() && ! $request->routeIs('invoice.form', 'verification.verify')) {
            return $next($request);
        }

        $connection = DB::connection();
        $level = $connection->transactionLevel();
        $connection->beginTransaction();

        try {
            $response = $next($request);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            // Failed credentials and rate limits deliberately write audit events before validation redirects.
            if ($request->routeIs('login.store', '2fa')) {
                $connection->commit();
            } else {
                $connection->rollBack($level);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $connection->rollBack($level);
            $this->discardIncompleteLogin($request);
            throw $exception;
        }

        if ($response->getStatusCode() >= 400 && ! ($response->getStatusCode() < 500 && $request->routeIs('login.store', '2fa'))) {
            $connection->rollBack($level);
            $this->discardIncompleteLogin($request);
        } else {
            $connection->commit();
        }

        return $response;
    }

    private function discardIncompleteLogin(Request $request): void
    {
        if ($request->routeIs('login.store', '2fa')) {
            \Illuminate\Support\Facades\Auth::guard('web')->logoutCurrentDevice();
            $request->session()->forget(['2fa:user_id', '2fa:remember']);
        }
    }
}
