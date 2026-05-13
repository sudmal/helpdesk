<?php

namespace App\Http\Controllers;

use App\Models\{Brigade, BrigadeSchedule, ScheduleHoliday};
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class BrigadeScheduleController extends Controller
{
    public function show(Brigade $brigade, Request $request)
    {
        $month = $request->get('month', now()->addMonth()->format('Y-m'));

        [$year, $mon] = explode('-', $month);
        $year = (int)$year;
        $mon  = (int)$mon;

        $firstDay    = Carbon::create($year, $mon, 1);
        $daysInMonth = $firstDay->daysInMonth;

        $dowMap = [0 => 'Вс', 1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб'];

        $holidays = ScheduleHoliday::whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->get()
            ->keyBy(fn($h) => $h->date->format('Y-m-d'));

        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date    = Carbon::create($year, $mon, $d);
            $dateStr = $date->format('Y-m-d');
            $dow     = $date->dayOfWeek; // 0=Sun
            $days[]  = [
                'date'        => $dateStr,
                'day'         => $d,
                'dow'         => $dowMap[$dow],
                'isWeekend'   => in_array($dow, [0, 6]),
                'isHoliday'   => isset($holidays[$dateStr]),
                'holidayName' => isset($holidays[$dateStr]) ? $holidays[$dateStr]->name : null,
            ];
        }

        $members = $brigade->members()->orderBy('name')->get(['users.id', 'users.name']);

        $savedRows = BrigadeSchedule::where('brigade_id', $brigade->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->get();

        $schedule = [];
        foreach ($members as $m) {
            $schedule[$m->id] = [];
        }
        foreach ($savedRows as $row) {
            $schedule[$row->user_id][$row->date->format('Y-m-d')] = $row->status;
        }

        return Inertia::render('Brigades/Schedule', [
            'brigade'  => ['id' => $brigade->id, 'name' => $brigade->name],
            'members'  => $members,
            'month'    => $month,
            'days'     => $days,
            'schedule' => $schedule,
        ]);
    }

    public function save(Brigade $brigade, Request $request)
    {
        $request->validate([
            'month'    => 'required|date_format:Y-m',
            'schedule' => 'required|array',
        ]);

        [$year, $mon] = explode('-', $request->month);

        $members = $brigade->members()->pluck('users.id');

        BrigadeSchedule::where('brigade_id', $brigade->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->delete();

        $rows = [];
        foreach ($request->schedule as $userId => $days) {
            if (!$members->contains((int)$userId)) continue;
            foreach ($days as $date => $status) {
                if (!in_array($status, ['work', 'off', 'requested'])) continue;
                $rows[] = [
                    'brigade_id' => $brigade->id,
                    'user_id'    => (int)$userId,
                    'date'       => $date,
                    'status'     => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($rows)) {
            BrigadeSchedule::insert($rows);
        }

        return back()->with('success', 'Расписание сохранено');
    }

    public function generate(Brigade $brigade, Request $request)
    {
        $request->validate([
            'month'     => 'required|date_format:Y-m',
            'pre_marks' => 'array', // userId => [date => 'requested'|'off']
        ]);

        [$year, $mon] = explode('-', $request->month);
        $year = (int)$year;
        $mon  = (int)$mon;

        $firstDay    = Carbon::create($year, $mon, 1);
        $daysInMonth = $firstDay->daysInMonth;

        $holidays = ScheduleHoliday::whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->flip()
            ->toArray();

        $members    = $brigade->members()->orderBy('name')->pluck('users.id')->toArray();
        $memberCount = count($members);
        $minWorkers  = min(2, $memberCount);

        // Build working days
        $workingDays = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $mon, $d)->format('Y-m-d');
            if (!isset($holidays[$date])) {
                $workingDays[] = $date;
            }
        }

        // Init everyone as work on working days, off on holidays
        $schedule = [];
        foreach ($members as $uid) {
            foreach ($workingDays as $date) {
                $schedule[$uid][$date] = 'work';
            }
            foreach (array_keys($holidays) as $date) {
                $schedule[$uid][$date] = 'off';
            }
        }

        // Apply pre-marked requested/off days — validate min constraint
        $preMark = $request->pre_marks ?? [];
        // Count per day: how many are already going to be off due to requests
        $offPerDay = array_fill_keys($workingDays, 0);

        // Sort requests by number of requests per day (least contested first)
        $requestsByDay = [];
        foreach ($members as $uid) {
            $userMark = $preMark[$uid] ?? [];
            foreach ($workingDays as $date) {
                $s = $userMark[$date] ?? null;
                if ($s === 'requested' || $s === 'off') {
                    $requestsByDay[$date][] = $uid;
                }
            }
        }
        asort($requestsByDay);

        foreach ($requestsByDay as $date => $uids) {
            foreach ($uids as $uid) {
                $workersLeft = $memberCount - $offPerDay[$date];
                if ($workersLeft - 1 >= $minWorkers) {
                    $schedule[$uid][$date] = 'off';
                    $offPerDay[$date]++;
                }
                // If can't grant — silently keep as 'work'
            }
        }

        return response()->json(['schedule' => $schedule]);
    }

    public function toggleHoliday(Request $request)
    {
        $request->validate(['date' => 'required|date', 'name' => 'nullable|string|max:100']);

        $date    = Carbon::parse($request->date)->format('Y-m-d');
        $holiday = ScheduleHoliday::where('date', $date)->first();

        if ($holiday) {
            $holiday->delete();
            return response()->json(['isHoliday' => false]);
        }

        ScheduleHoliday::create(['date' => $date, 'name' => $request->name]);
        return response()->json(['isHoliday' => true]);
    }
}
