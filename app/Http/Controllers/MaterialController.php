<?php
namespace App\Http\Controllers;

use App\Models\{Material, TicketMaterial, Ticket};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MaterialController extends Controller
{
    public function index()
    {
        $materials = Material::orderBy('code')->get();

        $lastUsed  = $this->lastUsedByMaterial();
        $monthStats = $this->usageSince(now()->subDays(30));

        $materials = $materials->map(function ($m) use ($lastUsed, $monthStats) {
            $m->last_used    = $lastUsed[$m->id] ?? null;
            $m->month_qty    = $monthStats[$m->id]->qty ?? 0;
            $m->month_amount = $monthStats[$m->id]->amount ?? 0;
            return $m;
        });

        return Inertia::render('Materials/Index', [
            'materials'     => $materials,
            'canManage'     => auth()->user()->hasPermission('materials.manage'),
        ]);
    }

    // Общий union расхода материалов (акты по заявкам + заявки на подключение), без ограничения по дате
    private function usageUnion()
    {
        $ticketSide = DB::table('act_materials as tm')
            ->join('acts as a', 'tm.act_id', '=', 'a.id')
            ->join('tickets as t', 'a.ticket_id', '=', 't.id')
            ->whereNull('t.deleted_at')
            ->selectRaw('tm.material_id, tm.quantity, tm.price_at_time, tm.created_at');

        $crSide = DB::table('connection_request_materials as crm')
            ->selectRaw('crm.material_id, crm.quantity, crm.price_at_time, crm.created_at');

        return $ticketSide->unionAll($crSide);
    }

    private function lastUsedByMaterial()
    {
        $union = $this->usageUnion();

        return DB::table(DB::raw("({$union->toSql()}) as x"))
            ->mergeBindings($union)
            ->whereNotNull('material_id')
            ->selectRaw('material_id, MAX(created_at) as last_used')
            ->groupBy('material_id')
            ->pluck('last_used', 'material_id');
    }

    private function usageSince(\Carbon\Carbon $since)
    {
        // фильтр по дате внутри каждой ветки union'а (см. MaterialReportController — ->where() поверх
        // готового union'а ломает порядок биндингов при mergeBindings)
        $ticketSide = DB::table('act_materials as tm')
            ->join('acts as a', 'tm.act_id', '=', 'a.id')
            ->join('tickets as t', 'a.ticket_id', '=', 't.id')
            ->whereNull('t.deleted_at')
            ->where('tm.created_at', '>=', $since)
            ->selectRaw('tm.material_id, tm.quantity, tm.price_at_time');

        $crSide = DB::table('connection_request_materials as crm')
            ->where('crm.created_at', '>=', $since)
            ->selectRaw('crm.material_id, crm.quantity, crm.price_at_time');

        $union = $ticketSide->unionAll($crSide);

        return DB::table(DB::raw("({$union->toSql()}) as x"))
            ->mergeBindings($union)
            ->whereNotNull('material_id')
            ->selectRaw('material_id, SUM(quantity) as qty, SUM(quantity * price_at_time) as amount')
            ->groupBy('material_id')
            ->get()
            ->keyBy('material_id');
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('materials.manage'), 403);
        $data = $request->validate([
            'code'       => 'nullable|string|max:50',
            'name'       => 'required|string|max:255',
            'unit'       => 'required|string|max:20',
            'price'      => 'required|numeric|min:0',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ]);
        Material::create($data);
        return back()->with('success', 'Материал добавлен');
    }

    public function update(Request $request, Material $material)
    {
        abort_unless(auth()->user()->hasPermission('materials.manage'), 403);
        $data = $request->validate([
            'code'       => 'nullable|string|max:50',
            'name'       => 'required|string|max:255',
            'unit'       => 'required|string|max:20',
            'price'      => 'required|numeric|min:0',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ]);
        $material->update($data);
        return back()->with('success', 'Материал обновлён');
    }

    public function destroy(Material $material)
    {
        abort_unless(auth()->user()->hasPermission('materials.manage'), 403);
        $material->update(['is_active' => false]);
        return back()->with('success', 'Материал деактивирован');
    }

    public function storeForTicket(Request $request, Ticket $ticket)
    {
        $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.material_id'    => 'required|exists:materials,id',
            'items.*.quantity'       => 'required|numeric|min:0.001',
        ]);

        $ticket->materials()->delete();

        foreach ($request->items as $item) {
            $material = Material::findOrFail($item['material_id']);
            $ticket->materials()->create([
                'material_id'    => $material->id,
                'material_name'  => $material->name,
                'material_code'  => $material->code,
                'material_unit'  => $material->unit,
                'price_at_time'  => $material->price,
                'quantity'       => $item['quantity'],
                'created_by'     => auth()->id(),
            ]);
        }

        return back()->with('success', 'Расходники сохранены');
    }
}