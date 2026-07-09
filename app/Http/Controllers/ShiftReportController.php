<?php

namespace App\Http\Controllers;

use App\Models\ShiftDefinition;
use App\Models\ShiftReport;
use App\Services\ShiftReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftReportController extends Controller
{
    public function current(ShiftReportService $service): JsonResponse
    {
        return response()->json($service->current());
    }

    /**
     * Разбор сырых сегментов статуса конкретного добавочного за смену --
     * для проверки спорных случаев (штраф по DND). Либо `shift_report_id`
     * (готовый отчёт, берём его сохранённые границы), либо `current=1`
     * (ещё идущая смена, окно до "сейчас").
     */
    public function audit(Request $request, ShiftReportService $service): JsonResponse
    {
        $data = $request->validate([
            'extension'       => 'required|string',
            'shift_report_id' => 'nullable|exists:shift_reports,id',
            'current'         => 'nullable|boolean',
        ]);

        if (!empty($data['current'])) {
            $current = $service->current();
            if (!$current) return response()->json(['segments' => []]);
            $start = \Carbon\Carbon::parse($current['shift_start_at']);
            $end   = now();
        } elseif (!empty($data['shift_report_id'])) {
            $report = ShiftReport::findOrFail($data['shift_report_id']);
            $start  = $report->shift_start_at;
            $end    = $report->shift_end_at;
        } else {
            return response()->json(['error' => 'shift_report_id or current required'], 422);
        }

        return response()->json([
            'segments' => $service->auditSegments($data['extension'], $start, $end),
            'window'   => ['from' => $start->toIso8601String(), 'to' => $end->toIso8601String()],
        ]);
    }

    // ── Настройка смен ──────────────────────────────────────────────

    public function definitions(): JsonResponse
    {
        return response()->json(
            ShiftDefinition::orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function storeDefinition(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:50',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
            'sort_order' => 'nullable|integer',
        ]);

        $definition = ShiftDefinition::create([
            'name'       => $data['name'],
            'start_time' => $data['start_time'],
            'end_time'   => $data['end_time'],
            'sort_order' => $data['sort_order'] ?? ((ShiftDefinition::max('sort_order') ?? 0) + 1),
            'is_active'  => true,
        ]);

        return response()->json($definition, 201);
    }

    public function updateDefinition(Request $request, ShiftDefinition $definition): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:50',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
        ]);

        $definition->update($data);

        return response()->json($definition);
    }

    public function destroyDefinition(ShiftDefinition $definition): JsonResponse
    {
        // Не удаляем физически -- уже сгенерированные отчёты хранят своё имя/
        // время смены независимо, но ссылаются на shift_definition_id (без FK).
        // Мягкое отключение не ломает историю и позволяет включить смену обратно.
        $definition->update(['is_active' => false]);

        return response()->json(['status' => 'ok']);
    }

    // ── Отчёты ───────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $query = ShiftReport::query()->orderByDesc('shift_date')->orderByDesc('shift_start_at');
        if ($dateFrom) $query->whereDate('shift_date', '>=', $dateFrom);
        if ($dateTo)   $query->whereDate('shift_date', '<=', $dateTo);

        $reports = $query->paginate(60);

        $grouped = $reports->getCollection()->groupBy(fn($r) => $r->shift_date->toDateString());

        return response()->json([
            'grouped'      => $grouped,
            'total'        => $reports->total(),
            'current_page' => $reports->currentPage(),
            'last_page'    => $reports->lastPage(),
        ]);
    }

    public function show(ShiftReport $shiftReport): JsonResponse
    {
        $shiftReport->load(['extensions' => fn($q) => $q->orderBy('extension')]);
        return response()->json($shiftReport);
    }

    public function regenerate(Request $request, ShiftReportService $service): JsonResponse
    {
        $data = $request->validate([
            'definition_id' => 'required|exists:shift_definitions,id',
            'date'          => 'required|date_format:Y-m-d',
        ]);

        $definition = ShiftDefinition::findOrFail($data['definition_id']);
        $report = $service->regenerate($definition, $data['date']);

        return response()->json($report);
    }
}
