<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

use App\Models\Leave;
use App\Models\LeaveSetting;
use App\Models\NonWorkDay;
use App\Models\User;
use App\Models\WorkDay;
use App\Notifications\LeaveRequestResponded;
use App\Notifications\LeaveRequestRespondedEmail;
use App\Notifications\LeaveRequestSubmitted;
use App\Notifications\LeaveRequestSubmittedEmail;
use App\Services\LeaveAllowanceService;
use App\Services\LeaveDepartmentAvailabilityService;
use App\Services\ActivityLogger;

class LeaveController extends Controller
{
    public function __construct(private LeaveAllowanceService $leaveAllowance, private LeaveDepartmentAvailabilityService $departmentAvailability
    ) {
    }

    public function view()
    {
        $leaveRequests = Leave::where('user_id', Auth::id())->orderBy('start_date', 'asc')->paginate(30);

        return view('leave.view', compact('leaveRequests'));
    }

    public function edit(Leave $request)
    {
        if ($request->user_id !== Auth::id()) {
            abort(403, 'Unauthorised action.');
        }

        if ($request->status !== 'pending') {
            return redirect()->route('leave.view')->with('error', 'Only pending leave requests can be modified.');
        }

        return view('leave.edit', compact('request'));
    }

    public function form()
    {
        return view('leave.form');
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'start_date' => $this->leaveStartDateRules(),
            'end_date' => 'required|date_format:d-m-Y|after_or_equal:start_date',
            'is_half_day' => 'nullable|boolean',
            'reason' => 'required|string|max:255',
            'additional_info' => 'nullable|string|max:255',
        ]);

        $validated['start_date'] = \Carbon\Carbon::createFromFormat('d-m-Y', $validated['start_date'])->format('Y-m-d');
        $validated['end_date'] = \Carbon\Carbon::createFromFormat('d-m-Y', $validated['end_date'])->format('Y-m-d');
        $validated['user_id'] = Auth::id();
        $validated['is_half_day'] = $request->boolean('is_half_day');

        if ($validated['is_half_day'] && !($validated['start_date'] === $validated['end_date'])) {
            return redirect()->route('leave.view')->with('error', 'A half day must have the start date equal to the end date');
        }

        $leave = DB::transaction(function () use ($validated): Leave {
            $user = $this->lockedUser((int) Auth::id());

            $this->ensureUserHasNoOverlappingLeave($user, $validated);
            $this->leaveAllowance->ensureLeaveRequestFitsAllowance($user, $validated);
            $this->departmentAvailability->ensureDepartmentHasCoverage($user, $validated);

            return Leave::create($validated);
        });

        // Send in-app notifications immediately and queue email delivery through a separate notification class.
        User::query()
            ->where('role', 'admin')
            ->get()
            ->each(function (User $admin) use ($leave) {
                $admin->notify(new LeaveRequestSubmitted($leave));
                $admin->notify(new LeaveRequestSubmittedEmail($leave));
            });

        $startDate = \Carbon\Carbon::parse($leave->start_date)->format('d/m/Y');
        $endDate =  \Carbon\Carbon::parse($leave->end_date)->format('d/m/Y');

        app(ActivityLogger::class)->record(
            actor: $request->user(),
            subject: $leave,
            action: 'create_leave_request',
            description: "submitted a leave request from $startDate to $endDate ",
        );

        return redirect()->route('leave.view')->with('success', 'Leave request created successfully.');
    }

    public function update(Request $request, Leave $leaveRequest)
    {
        if ($leaveRequest->user_id !== Auth::id()) {
            abort(403, 'Unauthorised action.');
        }

        if ($leaveRequest->status !== 'pending') {
            return redirect()->route('leave.view')->with('error', 'Only pending leave requests can be modified.');
        }

        $validated = $request->validate([
            'start_date' => $this->leaveStartDateRules(),
            'end_date' => 'required|date_format:d-m-Y|after_or_equal:start_date',
            'is_half_day' => 'nullable|boolean',
            'reason' => 'required|string|max:255',
            'additional_info' => 'nullable|string|max:255',
        ]);

        $validated['start_date'] = \Carbon\Carbon::createFromFormat('d-m-Y', $validated['start_date'])->format('Y-m-d');
        $validated['end_date'] = \Carbon\Carbon::createFromFormat('d-m-Y', $validated['end_date'])->format('Y-m-d');
        $validated['is_half_day'] = $request->boolean('is_half_day');
        $validated['user_id'] = Auth::id();

        if ($validated['is_half_day'] && !($validated['start_date'] === $validated['end_date'])) {
            return redirect()->route('leave.view')->with('error', 'A half day must have the start date equal to the end date');
        }

        $originalDates = DB::transaction(function () use ($validated, $leaveRequest): array {
            $user = $this->lockedUser((int) Auth::id());
            $lockedLeaveRequest = Leave::query()
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureUserHasNoOverlappingLeave($user, $validated, $lockedLeaveRequest);
            $this->leaveAllowance->ensureLeaveRequestFitsAllowance($user, $validated, $lockedLeaveRequest);
            $this->departmentAvailability->ensureDepartmentHasCoverage($user, $validated, $lockedLeaveRequest);

            $originalDates = [
                'start_date' => $lockedLeaveRequest->start_date,
                'end_date' => $lockedLeaveRequest->end_date,
            ];

            $lockedLeaveRequest->update($validated);

            return $originalDates;
        });

        $originalStartDate = Carbon::parse($originalDates['start_date'])->format('d/m/Y');
        $originalEndDate = Carbon::parse($originalDates['end_date'])->format('d/m/Y');
        $startDate = Carbon::parse($validated['start_date'])->format('d/m/Y');
        $endDate = Carbon::parse($validated['end_date'])->format('d/m/Y');

        app(ActivityLogger::class)->record(
            actor: $request->user(),
            subject: $leaveRequest,
            action: 'updated_leave_request',
            description: "modified a leave request from {$originalStartDate} to {$originalEndDate} to the new date range {$startDate} to {$endDate}",
        );

        return redirect()->route('leave.view')->with('success', 'Leave request updated successfully.');
    }

    public function leaveResponse(Request $request, $id)
    {
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->input('response') !== 'approved' && $request->input('response') !== 'rejected') {
            return redirect()->route('admin.leave-requests')->with('error', 'Invalid response value.');
        }

        $leaveRequest = Leave::findOrFail($id);

        $validated = $request->validate([
            'manager_comment' => 'nullable',
        ]);

        if ($leaveRequest->status !== 'pending') {
            return redirect()->route('admin.leave-requests')->with('error', 'This leave request has already been processed.');
        }

        $responseAction = $request->input('response');

        $result = DB::transaction(function () use ($leaveRequest, $validated, $responseAction): array {
            $user = $this->lockedUser((int) $leaveRequest->user_id);
            $lockedLeaveRequest = Leave::with('user')
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLeaveRequest->status !== 'pending') {
                return ['error' => 'processed', 'leave' => $lockedLeaveRequest];
            }

            if ($responseAction === 'approved') {
                try {
                    $this->ensureUserHasNoOverlappingLeave(
                        $user,
                        $this->leaveAllowance->leaveRequestData($lockedLeaveRequest),
                        $lockedLeaveRequest
                    );
                } catch (ValidationException) {
                    return ['error' => 'overlap', 'leave' => $lockedLeaveRequest];
                }

                try {
                    // Re-check approval using approved leave only; pending requests should not block the request being approved.
                    $this->leaveAllowance->ensureLeaveRequestFitsAllowance(
                        $user,
                        $this->leaveAllowance->leaveRequestData($lockedLeaveRequest),
                        $lockedLeaveRequest,
                        ['approved']
                    );
                } catch (ValidationException) {
                    return ['error' => 'allowance', 'leave' => $lockedLeaveRequest];
                }

                try {
                    // Department coverage can change while a request is pending, so validate again at approval time.
                    $this->departmentAvailability->ensureDepartmentHasCoverage(
                        $user,
                        $this->leaveAllowance->leaveRequestData($lockedLeaveRequest),
                        $lockedLeaveRequest,
                        ['approved']
                    );
                } catch (ValidationException) {
                    return ['error' => 'coverage', 'leave' => $lockedLeaveRequest];
                }
            }

            $lockedLeaveRequest->manager_comment = $validated['manager_comment'] ?? null;
            $lockedLeaveRequest->status = $responseAction;
            $lockedLeaveRequest->save();

            return ['error' => null, 'leave' => $lockedLeaveRequest->fresh('user')];
        });

        if ($result['error'] === 'processed') {
            return redirect()->route('admin.leave-requests')->with('error', 'This leave request has already been processed.');
        }

        if ($result['error'] === 'overlap') {
            return redirect()->route('admin.leave-requests')->with('error', 'This leave request overlaps another pending or approved leave request.');
        }

        if ($result['error'] === 'allowance') {
            return redirect()->route('admin.leave-requests')->with('error', 'This leave request would exceed the employee\'s remaining allowance.');
        }

        if ($result['error'] === 'coverage') {
            return redirect()->route('admin.leave-requests')->with('error', 'This leave request would leave the employee\'s department without cover.');
        }

        $leaveRequest = $result['leave'];

        // Keep the database notification synchronous, but send the email variant through the queue.
        $leaveRequest->user->notify(new LeaveRequestResponded($leaveRequest));
        $leaveRequest->user->notify(new LeaveRequestRespondedEmail($leaveRequest));

        $startDate =  \Carbon\Carbon::parse($leaveRequest->start_date)->format('d/m/Y');
        $endDate = \Carbon\Carbon::parse($leaveRequest->end_date)->format('d/m/Y');

        app(ActivityLogger::class)->record(
            actor: $request->user(),
            subject: $leaveRequest,
            action: 'leave_request_response',
            description: "{$responseAction} the leave request for {$leaveRequest->user->name} from {$startDate} to {$endDate}",
        );

        return redirect()->route('admin.leave-requests')->with('success', "Leave request {$responseAction} successfully.");
    }

    public function calendarEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['nullable', 'date', 'regex:/^\d{4}-\d{2}-\d{2}(?:$|[T\s])/', 'before_or_equal:end'],
            'end'   => ['nullable', 'date', 'regex:/^\d{4}-\d{2}-\d{2}(?:$|[T\s])/', 'after_or_equal:start'],
        ]);

        $startDate = $this->calendarRequestDate($validated['start'] ?? null);
        $endDate = $this->calendarRequestDate($validated['end'] ?? null);

        $query = Leave::with('user')
            ->where('status', 'approved');

        // Only return events that overlap with the requested date range in the calendar
        if ($startDate !== null) {
            $query->where('end_date', '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->where('start_date', '<', $endDate);
        }

        $nonWorkDayQuery = NonWorkDay::query();

        if ($startDate !== null) {
            $nonWorkDayQuery->whereDate('date', '>=', $startDate);
        }

        if ($endDate !== null) {
            $nonWorkDayQuery->whereDate('date', '<', $endDate);
        }

        $leaves = $query->get();
        $nonWorkDays = $nonWorkDayQuery->get();

        $events = $leaves->map(function ($leave) {
            return [
                'title' => $leave->user->name . ' - Annual Leave' . ($leave->is_half_day ? '(Half Day)' : ''),
                'start' => $leave->start_date,
                'end' => Carbon::parse($leave->end_date)->addDay()->toDateString(),
                'allDay' => true,
                'backgroundColor' => $leave->user->colour,
            ];
        })->toBase();

        $nonWorkDayEvents = $nonWorkDays->map(function ($nonWorkDay) {
            return [
                'title' => $nonWorkDay->name . ' - Non-work day',
                'start' => $nonWorkDay->date,
                'end' => Carbon::parse($nonWorkDay->date)->addDay()->toDateString(),
                'allDay' => true,
                'backgroundColor' => '#6b7280',
                'borderColor' => '#4b5563',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'type' => 'non_work_day',
                ],
            ];
        })->toBase();

        $events = $events->merge($nonWorkDayEvents)->values();

        return response()->json($events);
    }

    private function calendarRequestDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr($value, 0, 10);
    }

    private function leaveStartDateRules(): array
    {
        return [
            'required',
            'date_format:d-m-Y',
            function ($attribute, $value, $fail) {
                try {
                    $startDate = Carbon::createFromFormat('d-m-Y', $value)->startOfDay();
                } catch (\Throwable) {
                    return;
                }

                if ($startDate->lt(now()->startOfDay())) {
                    $fail('The start date must be today or a future date.');
                }
            },
        ];
    }

    private function ensureUserHasNoOverlappingLeave(User $user, array $leaveRequest, ?Leave $ignoredLeave = null): void
    {
        $requestedWorkingDates = $this->workingDatesCoveredByLeaveRequest($leaveRequest);

        $overlappingLeaveQuery = $user->leaves()
            ->whereIn('status', ['approved', 'pending'])
            ->where('start_date', '<=', $leaveRequest['end_date'])
            ->where('end_date', '>=', $leaveRequest['start_date']);

        if ($ignoredLeave) {
            $overlappingLeaveQuery->where('id', '!=', $ignoredLeave->id);
        }

        $hasWorkingDateOverlap = $overlappingLeaveQuery
            ->get()
            ->contains(function (Leave $leave) use ($requestedWorkingDates): bool {
                return ! empty(array_intersect(
                    $requestedWorkingDates,
                    $this->workingDatesCoveredByLeaveRequest($this->leaveAllowance->leaveRequestData($leave))
                ));
            });

        if (! $hasWorkingDateOverlap) {
            return;
        }

        throw ValidationException::withMessages([
            'start_date' => 'You already have pending or approved leave covering this date range.',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function workingDatesCoveredByLeaveRequest(array $leaveRequest): array
    {
        $startDate = Carbon::parse($leaveRequest['start_date'])->startOfDay();
        $endDate = Carbon::parse($leaveRequest['end_date'])->startOfDay();

        if ($leaveRequest['is_half_day']) {
            return $this->leaveAllowance->isWorkingDate($startDate)
                ? [$startDate->toDateString()]
                : [];
        }

        return collect(CarbonPeriod::create($startDate, $endDate))
            ->filter(fn (Carbon $date) => $this->leaveAllowance->isWorkingDate($date))
            ->map(fn (Carbon $date) => $date->toDateString())
            ->values()
            ->all();
    }

    private function lockedUser(int $userId): User
    {
        return User::withTrashed()
            ->whereKey($userId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function delete(Request $request, Leave $leave)
    {
        if ($leave->user_id !== Auth::id()) {
            abort(403, 'Unauthorised action.');
        }

        if ($leave->status !== 'pending') {
            return redirect()->route('leave.view')->with('error', 'Only pending leave requests can be cancelled.');
        }

        $leave->delete();

        $startDate = \Carbon\Carbon::parse($leave->start_date)->format('d/m/Y');
        $endDate = \Carbon\Carbon::parse($leave->end_date)->format('d/m/Y');

        app(ActivityLogger::class)->record(
            actor: $request->user(),
            subject: $leave,
            action: 'delete_leave_request',
            description: "deleted a $leave->status leave request with a start date of $startDate and an end date of $endDate",
        );

        return redirect()->route('leave.view')->with('success', 'Leave request cancelled successfully.');
    }

    public function updateLeaveRefresh(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorised action.');
        }

        $validated = $request->validate([
            'leave_refresh_month' => ['required', 'integer', 'min:1', 'max:12'],
            'leave_refresh_day' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) use ($request) {

                    $month = (int) $request->leave_refresh_month;

                    // Using a leap year so February can support 29
                    $daysInMonth = Carbon::create(2024, $month, 1)->daysInMonth;

                    if ($value > $daysInMonth) {
                        $fail("The selected month only has {$daysInMonth} days.");
                    }
                },
            ],
        ]);

        $leave = LeaveSetting::firstOrFail();

        $validated['leave_refresh_month'] = (int) $validated['leave_refresh_month'];
        $validated['leave_refresh_day'] = (int) $validated['leave_refresh_day'];

        $changed = (int) $leave->leave_refresh_month !== $validated['leave_refresh_month']
            || (int) $leave->leave_refresh_day !== $validated['leave_refresh_day'];
        $originalDate = Carbon::create(2024, (int) $leave->leave_refresh_month, (int) $leave->leave_refresh_day)->format('j F');
        $newDate = Carbon::create(2024, $validated['leave_refresh_month'], $validated['leave_refresh_day'])->format('j F');

        $leave->update($validated);

        if ($changed) {
            app(ActivityLogger::class)->record(
                actor: $request->user(),
                subject: $leave,
                action: 'update_leave_refresh',
                description: "changed the annual leave refresh date from {$originalDate} to {$newDate}",
            );
        }

        return redirect()->route('admin.view-leave-rules')->with('success', 'Leave refresh dates have updated successfully.');
    }

    public function updateLeaveAllowance(Request $request, LeaveSetting $leave)
    {
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorised action.');
        }

        $validated = $request->validate([
            'base_allowance' => ['required', 'numeric', 'min:1'],
            'increase_after_years' => ['required', 'integer', 'min:0'],
            'increase_by_days' => ['required', 'numeric', 'min:0'],
            'maximum_allowance' => ['required', 'numeric', 'min:1', 'gte:base_allowance'],
        ]);

        $leave = LeaveSetting::firstOrFail();

        $validated['base_allowance'] = (float) $validated['base_allowance'];
        $validated['increase_after_years'] = (int) $validated['increase_after_years'];
        $validated['increase_by_days'] = (float) $validated['increase_by_days'];
        $validated['maximum_allowance'] = (float) $validated['maximum_allowance'];

        $changes = [];
        $fields = [
            'base_allowance' => ['base allowance', 'days'],
            'increase_after_years' => ['service threshold for allowance increases', 'years'],
            'increase_by_days' => ['allowance increase', 'days'],
            'maximum_allowance' => ['maximum allowance', 'days'],
        ];

        foreach ($fields as $field => [$label, $unit]) {
            if ((float) $leave->{$field} !== (float) $validated[$field]) {
                $oldValue = (float) $leave->{$field};
                $newValue = $validated[$field];
                $changes[] = "{$label} from {$oldValue} to {$newValue} {$unit}";
            }
        }

        $leave->update($validated);

        if ($changes !== []) {
            app(ActivityLogger::class)->record(
                actor: $request->user(),
                subject: $leave,
                action: 'update_leave_allowance',
                description: 'updated leave allowance settings: '.implode('; ', $changes),
            );
        }

        return redirect()->route('admin.view-leave-rules')->with('success', 'Leave allowance settings have updated successfully.');
    }

    public function updateWorkDays(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorised action.');
        }

        $validated = $request->validate([
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['integer', 'exists:work_days,id'],
        ]);

        $activeWorkDayIds = collect($validated['work_days'])->map(fn ($id) => (int) $id);

        $changedDays = DB::transaction(function () use ($activeWorkDayIds) {
            $days = WorkDay::query()->lockForUpdate()->get();
            $changedDays = collect();

            foreach ($days as $day) {
                $active = $activeWorkDayIds->contains((int) $day->id);

                if ($day->active === $active) {
                    continue;
                }

                $day->update(['active' => $active]);
                $changedDays->push($day);
            }

            return $changedDays;
        });

        foreach ($changedDays as $day) {
            app(ActivityLogger::class)->record(
                actor: $request->user(),
                subject: $day,
                action: 'update_work_day',
                description: ($day->active ? 'enabled' : 'disabled')." {$day->day} as a working day",
            );
        }

        return redirect()->route('admin.view-leave-rules')->with('success', 'Working days updated successfully.');
    }

}
