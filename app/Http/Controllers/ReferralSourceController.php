<?php

namespace App\Http\Controllers;

use App\Models\ReferralSource;
use Illuminate\Http\Request;

class ReferralSourceController extends Controller
{
    public function store(Request $request)
    {
        $this->authorize('manage-settings');
        $data = $request->validate([
            'label' => 'required|string|max:100',
        ]);

        $source = ReferralSource::create([
            'key'        => 'custom_' . uniqid(),
            'label'      => trim($data['label']),
            'is_active'  => true,
            'sort_order' => (int) ReferralSource::max('sort_order') + 1,
        ]);

        return back()->with('success', 'Вариант добавлен');
    }

    /**
     * Вариант, по которому уже есть ответы (есть заявки с этим referral_source),
     * переименовать нельзя -- менять можно только is_active (убрать/вернуть из
     * выбора в новых заявках), чтобы не искажать смысл уже собранных ответов.
     */
    public function update(Request $request, ReferralSource $referralSource)
    {
        $this->authorize('manage-settings');
        $data = $request->validate([
            'label'     => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);

        $locked = $referralSource->connectionRequests()->exists();
        if ($locked && trim($data['label']) !== $referralSource->label) {
            return back()->withErrors([
                'referral_source' => 'Нельзя изменить текст варианта — по нему уже есть ответы. Можно только отключить его от выбора в новых заявках.',
            ]);
        }

        $referralSource->update([
            'label'     => $locked ? $referralSource->label : trim($data['label']),
            'is_active' => $data['is_active'] ?? $referralSource->is_active,
        ]);

        return back()->with('success', 'Вариант обновлён');
    }

    public function destroy(ReferralSource $referralSource)
    {
        $this->authorize('manage-settings');

        if ($referralSource->connectionRequests()->exists()) {
            return back()->withErrors([
                'referral_source' => 'Нельзя удалить — по этому варианту уже есть ответы. Можно только отключить его.',
            ]);
        }

        $referralSource->delete();

        return back()->with('success', 'Вариант удалён');
    }
}
