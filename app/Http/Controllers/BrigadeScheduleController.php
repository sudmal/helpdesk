<?php

namespace App\Http\Controllers;

use App\Models\{Brigade, BrigadeSchedule, ScheduleHoliday};
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class BrigadeScheduleController extends Controller
{
    private array $monthNames = [
        1=>'Январь',2=>'Февраль',3=>'Март',4=>'Апрель',5=>'Май',6=>'Июнь',
        7=>'Июль',8=>'Август',9=>'Сентябрь',10=>'Октябрь',11=>'Ноябрь',12=>'Декабрь',
    ];

    public function show(Brigade $brigade, Request $request)
    {
        $this->authorizeForBrigade($brigade);
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

    public function export(Brigade $brigade, Request $request)
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
                'date'       => $dateStr,
                'day'        => $d,
                'dow'        => $dowMap[$dow],
                'isWeekend'  => in_array($dow, [0, 6]),
                'isSaturday' => $dow === 6,
                'isHoliday'  => isset($holidays[$dateStr]),
            ];
        }

        $members = $brigade->members()->orderBy('name')->get(['users.id', 'users.name']);

        $savedRows = BrigadeSchedule::where('brigade_id', $brigade->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->get();

        $schedule = [];
        foreach ($savedRows as $row) {
            $schedule[$row->user_id][$row->date->format('Y-m-d')] = $row->status;
        }

        // === Spreadsheet ===
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Расписание');

        $totalCols  = 1 + $daysInMonth + 1;
        $lastColLtr = Coordinate::stringFromColumnIndex($totalCols);
        $totColLtr  = Coordinate::stringFromColumnIndex($totalCols);

        // Row 1: Title
        $sheet->mergeCells("A1:{$lastColLtr}1");
        $sheet->setCellValue('A1', "Расписание бригады «{$brigade->name}» — {$this->monthNames[$mon]} {$year}");
        $s = $sheet->getStyle('A1');
        $s->getFont()->setBold(true)->setSize(12);
        $s->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $s->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDBEAFE');
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Row 2: Day headers
        $sheet->setCellValue('A2', 'Сотрудник');
        $this->styleHeader($sheet, 'A2', 'FFF3F4F6');
        $sheet->getColumnDimension('A')->setWidth(22);

        foreach ($days as $i => $day) {
            $col = $i + 2;
            $ltr = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue($ltr . '2', $day['day'] . "\n" . $day['dow']);
            $bg = $day['isHoliday'] ? 'FFEDE9FE'
                : ($day['isSaturday'] ? 'FFE0E7FF'
                : ($day['isWeekend']  ? 'FFFEE2E2'
                : 'FFF3F4F6'));
            $this->styleHeader($sheet, $ltr . '2', $bg, wrapText: true, fontSize: 8);
            $sheet->getColumnDimensionByColumn($col)->setWidth(4.5);
        }

        $sheet->setCellValue($totColLtr . '2', 'Вых.');
        $this->styleHeader($sheet, $totColLtr . '2', 'FFF3F4F6');
        $sheet->getColumnDimensionByColumn($totalCols)->setWidth(6);
        $sheet->getRowDimension(2)->setRowHeight(30);

        // Data rows
        $rowNum        = 3;
        $workersPerDay = array_fill(0, $daysInMonth, 0);

        foreach ($members as $member) {
            $sheet->setCellValue('A' . $rowNum, $member->name);
            $sheet->getStyle('A' . $rowNum)->getFont()->setSize(9);
            $sheet->getStyle('A' . $rowNum)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            $offCount = 0;
            foreach ($days as $i => $day) {
                $col    = $i + 2;
                $ltr    = Coordinate::stringFromColumnIndex($col);
                $ref    = $ltr . $rowNum;
                $status = $day['isHoliday'] ? 'holiday' : ($schedule[$member->id][$day['date']] ?? 'work');

                [$label, $bg] = match ($status) {
                    'holiday'   => ['П', 'FFEDE9FE'],
                    'off'       => ['В', 'FFD1D5DB'],
                    'requested' => ['?', 'FFFCD34D'],
                    default     => ['Р', 'FF86EFAC'],
                };

                if ($status !== 'work') {
                    $offCount++;
                } else {
                    $workersPerDay[$i]++;
                }

                $sheet->setCellValue($ref, $label);
                $cs = $sheet->getStyle($ref);
                $cs->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
                $cs->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $cs->getFont()->setSize(8)->setBold(true);
            }

            $sheet->setCellValue($totColLtr . $rowNum, $offCount);
            $sheet->getStyle($totColLtr . $rowNum)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension($rowNum)->setRowHeight(16);
            $rowNum++;
        }

        // "На участке" footer row
        $sheet->setCellValue('A' . $rowNum, 'На участке');
        $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle('A' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF9FAFB');

        foreach ($days as $i => $day) {
            $ltr = Coordinate::stringFromColumnIndex($i + 2);
            $ref = $ltr . $rowNum;
            if ($day['isHoliday']) {
                $sheet->setCellValue($ref, '—');
                $sheet->getStyle($ref)->getFont()->getColor()->setARGB('FFD1D5DB');
            } else {
                $sheet->setCellValue($ref, $workersPerDay[$i]);
                $sheet->getStyle($ref)->getFont()->setBold(true)->setSize(8);
            }
            $sheet->getStyle($ref)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($ref)->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFF9FAFB');
        }
        $sheet->getRowDimension($rowNum)->setRowHeight(16);

        // Borders
        $sheet->getStyle("A1:{$lastColLtr}{$rowNum}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB');
        $sheet->getStyle("A2:{$lastColLtr}2")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('FF6B7280');

        // Save and stream
        $writer  = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $tmpFile = tempnam(sys_get_temp_dir(), 'sched');
        $writer->save($tmpFile);

        $filename = 'schedule_' . preg_replace('/[^\w]/', '_', $brigade->name) . "_{$month}.xlsx";

        return response()->download($tmpFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function styleHeader($sheet, string $ref, string $bg, bool $wrapText = false, int $fontSize = 9): void
    {
        $s = $sheet->getStyle($ref);
        $s->getFont()->setBold(true)->setSize($fontSize);
        $s->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText($wrapText);
        $s->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
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

        $members     = $brigade->members()->orderBy('name')->pluck('users.id')->toArray();
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
