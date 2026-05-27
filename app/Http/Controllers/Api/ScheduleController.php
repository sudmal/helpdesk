<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Brigade, BrigadeSchedule, ScheduleHoliday};
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $brigadeId = $request->get('brigade_id');
        if ($brigadeId) {
            $brigade = Brigade::find($brigadeId);
            if (!$brigade) {
                return response()->json(['error' => 'Бригада не найдена'], 404);
            }
            if (!$user->hasPermission('*') && !$user->hasPermission('settings.*')) {
                $isMember = $brigade->members()->where('users.id', $user->id)->exists();
                if (!$isMember) {
                    return response()->json(['error' => 'Нет доступа к этой бригаде'], 403);
                }
            }
        } else {
            $brigade = $user->brigades()->first();
            if (!$brigade) {
                return response()->json(['error' => 'Вы не состоите ни в одной бригаде'], 404);
            }
        }

        // months[] array or default: current + next month
        $monthsParam = $request->get('months');
        if ($monthsParam && is_array($monthsParam)) {
            $months = array_slice($monthsParam, 0, 3);
        } else {
            $months = [now()->format('Y-m'), now()->addMonth()->format('Y-m')];
        }

        foreach ($months as $m) {
            if (!preg_match('/^\d{4}-\d{2}$/', $m)) {
                return response()->json(['error' => "Неверный формат месяца: {$m} (ожидается YYYY-MM)"], 422);
            }
        }

        $members = $brigade->members()->orderBy('name')->get(['users.id', 'users.name']);

        $dowMap = [0 => 'Вс', 1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб'];

        $result = [];
        foreach ($months as $month) {
            [$year, $mon] = explode('-', $month);
            $year = (int)$year;
            $mon  = (int)$mon;

            $firstDay    = Carbon::create($year, $mon, 1);
            $daysInMonth = $firstDay->daysInMonth;

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
                    'holidayName' => $holidays[$dateStr]?->name ?? null,
                ];
            }

            $savedRows = BrigadeSchedule::where('brigade_id', $brigade->id)
                ->whereYear('date', $year)
                ->whereMonth('date', $mon)
                ->get();

            $scheduleMap = [];
            foreach ($members as $m) {
                $scheduleMap[$m->id] = [];
            }
            foreach ($savedRows as $row) {
                $scheduleMap[$row->user_id][$row->date->format('Y-m-d')] = $row->status;
            }

            $result[] = [
                'month'       => $month,
                'days'        => $days,
                'members'     => $members->map(fn($m) => [
                    'id'       => $m->id,
                    'name'     => $m->name,
                    'is_me'    => $m->id === $user->id,
                    'schedule' => $scheduleMap[$m->id],
                ])->values(),
                'my_schedule' => $scheduleMap[$user->id] ?? null,
            ];
        }

        return response()->json([
            'brigade'   => ['id' => $brigade->id, 'name' => $brigade->name],
            'months'    => $result,
            'synced_at' => now()->toIso8601String(),
        ]);
    }
}