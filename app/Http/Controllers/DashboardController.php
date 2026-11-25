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
        $currentMonth = now()->startOfMonth();
        $previousMonth = $currentMonth->copy()->subMonth();
        $outstandingInvoices = collect();
        $monthTotal = 0;
        $monthInvoiced = 0;
        $invoicedPercentage = 0;

        if ($request->user()->isAdmin()) {
            $jobs = WorkJob::with(['client', 'invoices' => fn ($query) => $query
                ->where('year', '<=', $currentMonth->year)
                ->where(fn ($query) => $query->whereIn('year', [$previousMonth->year, $currentMonth->year])
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

            foreach ($jobs as $job) {
                $amount = $invoiceService->amountsForYear($job->invoices, $currentMonth->year)->get($currentMonth->format('F'));
                $pence = (int) round((float) $amount * 100);
                $monthTotal += $pence;
                $invoice = $job->invoices->first(fn ($invoice) => (int) $invoice->year === $currentMonth->year
                    && $invoice->month === $currentMonth->format('F'));
                if ($invoice?->state === 'Already Invoiced') {
                    $monthInvoiced += $pence;
                }
            }
            $invoicedPercentage = $monthTotal > 0 ? round(max(0, min(100, $monthInvoiced / $monthTotal * 100)), 1) : 0;
            $monthTotal /= 100;
            $monthInvoiced /= 100;
        }

        return view('dashboard', compact('previousMonth', 'outstandingInvoices', 'currentMonth', 'monthTotal', 'monthInvoiced', 'invoicedPercentage'));
    }
}
