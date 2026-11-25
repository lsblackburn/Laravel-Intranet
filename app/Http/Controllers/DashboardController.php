<?php

namespace App\Http\Controllers;

use App\Models\WorkJob;
use App\Services\JobInvoices;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, JobInvoices $invoiceService): View
    {
        $previousMonth = now()->startOfMonth()->subMonth();
        $outstandingInvoices = collect();

        if ($request->user()->isAdmin()) {
            $jobs = WorkJob::with(['client', 'invoices' => fn ($query) => $query
                ->where('year', '<=', $previousMonth->year)
                ->where(fn ($query) => $query->where('year', $previousMonth->year)
                    ->orWhere('recurring', true)->orWhere('stops_recurrence', true))])
                ->orderBy('title')->get();
            $month = $previousMonth->format('F');

            $outstandingInvoices = $jobs->map(function ($job) use ($invoiceService, $previousMonth, $month) {
                $invoice = $job->invoices->first(fn ($invoice) => (int) $invoice->year === $previousMonth->year
                    && $invoice->month === $month);
                $amount = $invoiceService->amountsForYear($job->invoices, $previousMonth->year)->get($month);

                return $invoice?->state !== 'Already Invoiced' && (float) $amount !== 0.0
                    ? ['job' => $job, 'amount' => $amount] : null;
            })->filter()->values();
        }

        return view('dashboard', compact('previousMonth', 'outstandingInvoices'));
    }
}
