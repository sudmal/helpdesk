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
            'month'       => 'required|date_format:Y-m',
            'pre_marks'   => 'array',
            'target_days' => 'integer|min:1',
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

        $members     = $brigade->members()->orderBy('name')->pluck('users.id')->toArray();
        $memberCount = count($members);
        $minWorkers  = min(2, $memberCount);
        $targetDays  = (int)($request->target_days ?? 24);

        // Working days as numerically-indexed array (holidays excluded)
        $workingDays = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $mon, $d)->format('Y-m-d');
            if (!isset($holidays[$date])) {
                $workingDays[] = $date;
            }
        }
        $totalWorking = count($workingDays);

        // Init schedule: work on working days, off on holidays
        $schedule = [];
        foreach ($members as $uid) {
            foreach ($workingDays as $date) {
                $schedule[$uid][$date] = 'work';
            }
            foreach (array_keys($holidays) as $date) {
                $schedule[$uid][$date] = 'off';
            }
        }

        // offPerDay: how many workers are off on each working day
        $offPerDay = array_fill_keys($workingDays, 0);

        // Step 1: honor pre-marked requests, validate min-workers constraint
        $preMark       = $request->pre_marks ?? [];
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
        uasort($requestsByDay, fn($a, $b) => count($a) - count($b)); // least-contested days first

        foreach ($requestsByDay as $date => $uids) {
            foreach ($uids as $uid) {
                if (($memberCount - $offPerDay[$date]) - 1 >= $minWorkers) {
                    $schedule[$uid][$date] = 'off';
                    $offPerDay[$date]++;
                }
            }
        }

        // Step 2: distribute additional offs evenly across the month.
        // Target: each person gets (totalWorking - targetDays) off days.
        // Algorithm: greedy — each round pick the working day with max distance
        // from already-assigned offs. Constraint: no 3+ consecutive offs.
        $targetOff = max(0, $totalWorking - $targetDays);

        foreach ($members as $uid) {
            $currentOff = 0;
            foreach ($workingDays as $date) {
                if ($schedule[$uid][$date] === 'off') $currentOff++;
            }
            $need = max(0, $targetOff - $currentOff);

            for ($i = 0; $i < $need; $i++) {
                $bestIdx   = null;
                $bestScore = -1;

                foreach ($workingDays as $idx => $date) {
                    if ($schedule[$uid][$date] === 'off') continue;

                    // Min-workers constraint
                    if (($memberCount - $offPerDay[$date]) - 1 < $minWorkers) continue;

                    // Consecutive constraint: adding this day must not create 3+ consecutive offs
                    $offPrev1 = isset($workingDays[$idx - 1]) && $schedule[$uid][$workingDays[$idx - 1]] === 'off';
                    $offPrev2 = isset($workingDays[$idx - 2]) && $schedule[$uid][$workingDays[$idx - 2]] === 'off';
                    $offNext1 = isset($workingDays[$idx + 1]) && $schedule[$uid][$workingDays[$idx + 1]] === 'off';
                    $offNext2 = isset($workingDays[$idx + 2]) && $schedule[$uid][$workingDays[$idx + 2]] === 'off';

                    if ($offPrev2 && $offPrev1) continue; // prev2 + prev1 + this = 3
                    if ($offPrev1 && $offNext1) continue; // prev1 + this + next1 = 3
                    if ($offNext1 && $offNext2) continue; // this + next1 + next2 = 3

                    // Score: distance to nearest existing off (larger = better spread)
                    $minDist = $totalWorking + 1;
                    foreach ($workingDays as $j => $d2) {
                        if ($schedule[$uid][$d2] === 'off') {
                            $dist = abs($idx - $j);
                            if ($dist < $minDist) $minDist = $dist;
                        }
                    }
                    // No offs yet: prefer days near the center for initial placement
                    if ($minDist === $totalWorking + 1) {
                        $minDist = abs($idx - intdiv($totalWorking, 2));
                    }

                    if ($minDist > $bestScore) {
                        $bestScore = $minDist;
                        $bestIdx   = $idx;
                    }
                }

                if ($bestIdx !== null) {
                    $date = $workingDays[$bestIdx];
                    $schedule[$uid][$date] = 'off';
                    $offPerDay[$date]++;
                }
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
