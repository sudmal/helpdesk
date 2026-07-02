<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServiceRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = ServiceRequest::with(['creator', 'processor'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('phone', 'like', $s)
                  ->orWhere('address_string', 'like', $s)
                  ->orWhere('service_name', 'like', $s);
            });
        }

        return Inertia::render('ServiceRequests/Index', [
            'requests'     => $query->paginate(50)->withQueryString(),
            'filters'      => $request->only(['status', 'search']),
            'servicesList' => $this->getServicesList(),
            'totalPending' => ServiceRequest::where('status', 'pending')->count(),
            'canProcess'   => $request->user()->canManageSettings(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'phone'          => 'required|string|max:30',
            'address_string' => 'required|string|max:255',
            'service_name'   => 'required|string|max:100',
            'description'    => 'nullable|string|max:2000',
        ]);
        $data['created_by'] = $request->user()->id;
        $data['status']     = 'pending';

        ServiceRequest::create($data);

        return back()->with('success', 'Заявка на услугу создана');
    }

    public function update(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless($serviceRequest->status === 'pending', 403, 'Редактировать можно только ожидающие заявки');

        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'phone'          => 'required|string|max:30',
            'address_string' => 'required|string|max:255',
            'service_name'   => 'required|string|max:100',
            'description'    => 'nullable|string|max:2000',
        ]);

        $serviceRequest->update($data);

        return back()->with('success', 'Заявка обновлена');
    }

    public function accept(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless($request->user()->canManageSettings(), 403);

        $request->validate([
            'admin_comment' => 'required|string|max:2000',
        ]);

        $serviceRequest->update([
            'status'        => 'accepted',
            'admin_comment' => $request->admin_comment,
            'processed_by'  => $request->user()->id,
            'processed_at'  => now(),
        ]);

        return back()->with('success', 'Заявка принята');
    }

    public function reject(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless($request->user()->canManageSettings(), 403);

        $request->validate([
            'admin_comment' => 'required|string|max:2000',
        ]);

        $serviceRequest->update([
            'status'        => 'rejected',
            'admin_comment' => $request->admin_comment,
            'processed_by'  => $request->user()->id,
            'processed_at'  => now(),
        ]);

        return back()->with('success', 'Заявка отклонена');
    }

    public function destroy(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $serviceRequest->delete();
        return back()->with('success', 'Заявка удалена');
    }

    public function detail(\App\Models\ServiceRequest $serviceRequest)
    {
        $serviceRequest->load(['creator', 'processor']);
        return response()->json($serviceRequest);
    }

    private function getServicesList(): array
    {
        $val = SystemSetting::get('service_request_services');
        if (is_array($val) && count($val)) return $val;
        return ['Реальный IP', 'IPTV'];
    }
}
