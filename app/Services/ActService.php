<?php

namespace App\Services;

use App\Models\Act;
use App\Models\ActMaterial;
use App\Models\Material;
use App\Models\User;

/**
 * Полевые действия с актом (утверждение бригадиром, правка состава,
 * подтверждение монтажником) — вынесены из ActController, чтобы веб и
 * мобильный API (App\Http\Controllers\Api\ActController) не расходились
 * в логике гейтов/истории. Авторизация (ActPolicy) остаётся на вызывающей
 * стороне — сюда попадают только уже проверенные действия.
 */
class ActService
{
    public function approve(Act $act, User $user): Act
    {
        $act->update([
            'status'              => 'approved',
            'foreman_reviewed_by' => $user->id,
            'foreman_reviewed_at' => now(),
        ]);
        $this->logHistory($act, $user->id, 'approved');

        return $act;
    }

    public function addMaterial(Act $act, User $user, int $materialId, float $quantity): ActMaterial
    {
        $material = Material::findOrFail($materialId);

        $actMaterial = $act->materials()->create([
            'material_id'   => $material->id,
            'material_name' => $material->name,
            'material_code' => $material->code,
            'material_unit' => $material->unit,
            'price_at_time' => $material->price,
            'quantity'      => $quantity,
            'created_by'    => $user->id,
        ]);

        $this->flagMaterialsChanged($act, $user->id);
        $this->logHistory(
            $act, $user->id, 'material_added', null, null,
            "{$actMaterial->material_name} — {$actMaterial->quantity} {$actMaterial->material_unit}",
            $actMaterial->id
        );

        return $actMaterial;
    }

    public function updateMaterial(Act $act, ActMaterial $material, User $user, float $quantity): ActMaterial
    {
        $old = "{$material->material_name} — {$material->quantity} {$material->material_unit}";
        $material->update(['quantity' => $quantity]);
        $new = "{$material->material_name} — {$material->quantity} {$material->material_unit}";

        $this->flagMaterialsChanged($act, $user->id);
        $this->logHistory($act, $user->id, 'material_changed', 'quantity', $old, $new, $material->id);

        return $material;
    }

    public function removeMaterial(Act $act, ActMaterial $material, User $user): void
    {
        $old = "{$material->material_name} — {$material->quantity} {$material->material_unit}";
        $material->delete();

        $this->flagMaterialsChanged($act, $user->id);
        $this->logHistory($act, $user->id, 'material_removed', null, $old, null);
    }

    public function acknowledge(Act $act, User $user): Act
    {
        $act->history()->whereNull('acknowledged_at')->update(['acknowledged_at' => now()]);
        $act->update(['materials_changed_at' => null]);
        $this->logHistory($act, $user->id, 'acknowledged');

        return $act;
    }

    /** Флаг "монтажнику есть что подтвердить" — не поднимаем, если бригадир правит акт, который сам же создал. */
    private function flagMaterialsChanged(Act $act, int $editorId): void
    {
        if ($act->created_by !== $editorId) {
            $act->materials_changed_at = now();
            $act->save();
        }
    }

    private function logHistory(
        Act $act, int $userId, string $action,
        ?string $field = null, ?string $old = null, ?string $new = null,
        ?int $relatedMaterialId = null
    ): void {
        $act->history()->create([
            'user_id'             => $userId,
            'action'              => $action,
            'field'               => $field,
            'old_value'           => $old,
            'new_value'           => $new,
            'related_material_id' => $relatedMaterialId,
        ]);
    }
}
