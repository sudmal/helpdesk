<?php

namespace App\Http\Controllers;

use App\Models\{Brigade, BrigadeSchedule, ScheduleHoliday};
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
// SimpleXLSXGen loaded via require_once in export() to avoid autoloader dependency

class BrigadeScheduleController extends Controller
{
    private array $monthNames = [
        1=>'Январь',2=>'Февраль',3=>'Март',4=>'Апрель',5=>'Май',6=>'Июнь',
        7=>'Июль',8=>'Август',9=>'Сентябрь',10=>'Октябрь',11=>'Ноябрь',12=>'Декабрь',
    ];

    public function show(Brigade $brigade, Request $request)
    {
        $this->authorizeForBrigade($brigade);
        $month = $request->get('month', now()->format('Y-m'));

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
            $dow     = $date->dayOfWeek;
            $days[]  = [
                'date'        => $dateStr,
                'day'         => $d,
                'dow'         => $dowMap[$dow],
                'isWeekend'   => in_array($dow, [0, 6]),
                'isHoliday'   => isset($holidays[$dateStr]),
                'holidayName' => isset($holidays[$dateStr]) ? $holidays[$dateStr]->name : null,
            ];
        }

        $membersCollection = $brigade->members()->orderBy('name')->get(['users.id', 'users.name']);

        $savedRows = BrigadeSchedule::where('brigade_id', $brigade->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->get();

        $schedule = [];
        foreach ($membersCollection as $m) {
            $schedule[$m->id] = [];
        }
        foreach ($savedRows as $row) {
            $schedule[$row->user_id][$row->date->format('Y-m-d')] = $row->status;
        }

        $members = $membersCollection->map(fn($m) => [
            'id'       => $m->id,
            'name'     => $m->name,
            'excluded' => (bool) $m->pivot->exclude_from_schedule,
        ]);

        return Inertia::render('Brigades/Schedule', [
            'brigade'  => ['id' => $brigade->id, 'name' => $brigade->name, 'min_workers' => $brigade->min_workers],
            'members'  => $members,
            'month'    => $month,
            'days'     => $days,
            'schedule' => $schedule,
        ]);
    }

    public function export(Brigade $brigade, Request $request)
    {
        $this->authorizeForBrigade($brigade);
        $month = $request->get('month', now()->format('Y-m'));

        [$year, $mon] = explode('-', $month);
        $year = (int)$year;
        $mon  = (int)$mon;

        $firstDay    = Carbon::create($year, $mon, 1);
        $daysInMonth = $firstDay->daysInMonth;
        $totalCols   = 1 + $daysInMonth + 1;

        $dowMap = [0 => 'Вс', 1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб'];

        $holidays = ScheduleHoliday::whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->get()
            ->keyBy(fn($h) => $h->date->format('Y-m-d'));

        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date    = Carbon::create($year, $mon, $d);
            $dateStr = $date->format('Y-m-d');
            $dow     = $date->dayOfWeek;
            $days[]  = [
                'date'       => $dateStr,
                'day'        => $d,
                'dow'        => $dowMap[$dow],
                'isWeekend'  => in_array($dow, [0, 6]),
                'isSaturday' => $dow === 6,
                'isHoliday'  => isset($holidays[$dateStr]),
            ];
        }

        // Excluded members are skipped in the export
        $members = $brigade->members()
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->filter(fn($m) => !$m->pivot->exclude_from_schedule)
            ->values();

        $savedRows = BrigadeSchedule::where('brigade_id', $brigade->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->get();

        $schedule = [];
        foreach ($savedRows as $row) {
            $schedule[$row->user_id][$row->date->format('Y-m-d')] = $row->status;
        }

        // Helper: 1-based column number to Excel letter
        $colLetter = static function (int $n): string {
            $r = '';
            while ($n > 0) { $n--; $r = chr(65 + $n % 26) . $r; $n = (int)($n / 26); }
            return $r;
        };

        // Row 1: title (merged across all columns)
        $title    = "Расписание бригады «{$brigade->name}» — {$this->monthNames[$mon]} {$year}";
        $titleRow = ["<b><center><style bgcolor=\"DBEAFE\" font-size=\"12\">{$title}</style></center></b>"];
        for ($i = 1; $i < $totalCols; $i++) { $titleRow[] = ''; }
        $rows = [$titleRow];

        // Row 2: column headers
        $headerRow = ['<b><style bgcolor="F3F4F6">Сотрудник</style></b>'];
        foreach ($days as $day) {
            $bg = $day['isHoliday'] ? 'EDE9FE'
                : ($day['isSaturday'] ? 'E0E7FF'
                : ($day['isWeekend']  ? 'FEE2E2'
                : 'F3F4F6'));
            $headerRow[] = "<b><center><style bgcolor=\"{$bg}\" font-size=\"8\">{$day['day']} {$day['dow']}</style></center></b>";
        }
        $lastDayCol = $colLetter(1 + $daysInMonth); // last day column letter (e.g. AE for 30 days)
        $headerRow[] = '<b><center><style bgcolor="F3F4F6">Выходов</style></center></b>';
        $rows[] = $headerRow;

        // Data rows — 1 for work, empty for off/holiday
        $workersPerDay = array_fill(0, $daysInMonth, 0);
        $dataStartRow  = 3; // row 1 = title, row 2 = header

        foreach ($members as $idx => $member) {
            $row    = [$member->name];
            $rowNum = $dataStartRow + $idx;

            foreach ($days as $i => $day) {
                $status = $day['isHoliday'] ? 'holiday' : ($schedule[$member->id][$day['date']] ?? 'work');

                if ($status === 'work') {
                    $workersPerDay[$i]++;
                    $row[] = "<center><style bgcolor=\"86EFAC\">1</style></center>";
                } else {
                    $bg = match ($status) {
                        'holiday'   => 'EDE9FE',
                        'requested' => 'FCD34D',
                        default     => 'F3F4F6',
                    };
                    $row[] = "<style bgcolor=\"{$bg}\"></style>";
                }
            }

            // SUM formula counts 1s = work days
            $row[]  = "<f>=SUM(B{$rowNum}:{$lastDayCol}{$rowNum})</f>";
            $rows[] = $row;
        }

        // Footer row
        $footerRow = ['<b><style bgcolor="F9FAFB">На участке</style></b>'];
        foreach ($days as $i => $day) {
            if ($day['isHoliday']) {
                $footerRow[] = '<center><style color="9CA3AF" bgcolor="F9FAFB">—</style></center>';
            } else {
                $footerRow[] = "<b><center><style bgcolor=\"F9FAFB\">{$workersPerDay[$i]}</style></center></b>";
            }
        }
        $footerRow[] = '';
        $rows[] = $footerRow;

        // Build xlsx
        require_once base_path('vendor/shuchkin/simplexlsxgen/src/SimpleXLSXGen.php');
        $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($rows);

        // Merge title row
        $xlsx->mergeCells('A1:' . $colLetter($totalCols) . '1');

        $xlsx->setColWidth(1, 22);
        for ($c = 2; $c <= $daysInMonth + 1; $c++) {
            $xlsx->setColWidth($c, 4.5);
        }
        $xlsx->setColWidth($daysInMonth + 2, 6);

        $filename = 'schedule_' . preg_replace('/[^\w]/', '_', $brigade->name) . "_{$month}.xlsx";
        $tmpFile  = tempnam(sys_get_temp_dir(), 'sched') . '.xlsx';
        $xlsx->saveAs($tmpFile);

        return response()->download($tmpFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function save(Brigade $brigade, Request $request)
    {
        $this->authorizeForBrigade($brigade);
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
        $this->authorizeForBrigade($brigade);
        $request->validate([
            'month'       => 'required|date_format:Y-m',
            'pre_marks'   => 'array',
            'target_days' => 'integer|min:1',
            'min_workers' => 'integer|min:1|max:50',
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

        // Excluded members do not participate in generation
        $members = $brigade->members()
            ->orderBy('name')
            ->get()
            ->filter(fn($m) => !$m->pivot->exclude_from_schedule)
            ->pluck('id')
            ->toArray();

        $memberCount = count($members);
        $minWorkers  = min((int)($request->min_workers ?? $brigade->min_workers ?? 2), $memberCount);
        $targetDays  = (int)($request->target_days ?? 24);

        $workingDays = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $mon, $d)->format('Y-m-d');
            if (!isset($holidays[$date])) {
                $workingDays[] = $date;
            }
        }
        $totalWorking = count($workingDays);

        $schedule = [];
        foreach ($members as $uid) {
            foreach ($workingDays as $date) {
                $schedule[$uid][$date] = 'work';
            }
            foreach (array_keys($holidays) as $date) {
                $schedule[$uid][$date] = 'off';
            }
        }

        $offPerDay = array_fill_keys($workingDays, 0);

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
        uasort($requestsByDay, fn($a, $b) => count($a) - count($b));

        foreach ($requestsByDay as $date => $uids) {
            foreach ($uids as $uid) {
                if (($memberCount - $offPerDay[$date]) - 1 >= $minWorkers) {
                    $schedule[$uid][$date] = 'off';
                    $offPerDay[$date]++;
                }
            }
        }

        $targetOff       = max(0, $totalWorking - $targetDays);
        $shuffledMembers = $members;
        shuffle($shuffledMembers);

        foreach ($shuffledMembers as $uid) {
            $currentOff = 0;
            foreach ($workingDays as $date) {
                if ($schedule[$uid][$date] === 'off') $currentOff++;
            }
            $need = max(0, $targetOff - $currentOff);

            for ($i = 0; $i < $need; $i++) {
                $bestScore      = PHP_INT_MIN;
                $bestCandidates = [];

                foreach ($workingDays as $idx => $date) {
                    if ($schedule[$uid][$date] === 'off') continue;
                    if (($memberCount - $offPerDay[$date]) - 1 < $minWorkers) continue;

                    $offPrev1 = isset($workingDays[$idx - 1]) && $schedule[$uid][$workingDays[$idx - 1]] === 'off';
                    $offPrev2 = isset($workingDays[$idx - 2]) && $schedule[$uid][$workingDays[$idx - 2]] === 'off';
                    $offNext1 = isset($workingDays[$idx + 1]) && $schedule[$uid][$workingDays[$idx + 1]] === 'off';
                    $offNext2 = isset($workingDays[$idx + 2]) && $schedule[$uid][$workingDays[$idx + 2]] === 'off';

                    if ($offPrev2 && $offPrev1) continue;
                    if ($offPrev1 && $offNext1) continue;
                    if ($offNext1 && $offNext2) continue;

                    $ownDist = $totalWorking + 1;
                    foreach ($workingDays as $j => $d2) {
                        if ($schedule[$uid][$d2] === 'off') {
                            $dist = abs($idx - $j);
                            if ($dist < $ownDist) $ownDist = $dist;
                        }
                    }
                    if ($ownDist === $totalWorking + 1) {
                        $ownDist = abs($idx - intdiv($totalWorking, 2));
                    }

                    $score = $ownDist - 2 * $offPerDay[$date];

                    if ($score > $bestScore) {
                        $bestScore      = $score;
                        $bestCandidates = [$idx];
                    } elseif ($score === $bestScore) {
                        $bestCandidates[] = $idx;
                    }
                }

                if (!empty($bestCandidates)) {
                    $bestIdx = $bestCandidates[array_rand($bestCandidates)];
                    $date    = $workingDays[$bestIdx];
                    $schedule[$uid][$date] = 'off';
                    $offPerDay[$date]++;
                }
            }
        }

        return response()->json(['schedule' => $schedule]);
    }

    public function toggleExclude(Brigade $brigade, Request $request)
    {
        $this->authorizeForBrigade($brigade);
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $member = $brigade->members()->where('users.id', $request->user_id)->first();
        if (!$member) abort(404, 'Пользователь не в бригаде');

        $newValue = !$member->pivot->exclude_from_schedule;
        $brigade->members()->updateExistingPivot($request->user_id, [
            'exclude_from_schedule' => $newValue,
        ]);

        return response()->json(['excluded' => $newValue]);
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

    private function authorizeForBrigade(Brigade $brigade): void
    {
        $user = auth()->user();
        if (!$user->canManageSettings() && $brigade->foreman_id !== $user->id) {
            abort(403, 'Нет доступа к этой бригаде');
        }
    }
}