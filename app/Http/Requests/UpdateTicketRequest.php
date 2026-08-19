<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'address_id'   => 'nullable|exists:addresses,id',
            'apartment'    => 'nullable|string|max:50',
            'type_id'      => 'required|exists:ticket_types,id',
            // Закрыть/отменить — только через отдельные действия с обязательной
            // причиной (см. TicketController::close()/cancel()), не через
            // свободный дропдаун в форме редактирования: is_final-статусы
            // сюда намеренно не проходят, даже если кто-то соберёт запрос вручную.
            'status_id'    => ['required', Rule::exists('ticket_statuses', 'id')->where('is_final', false)],
            'brigade_id'   => 'nullable|exists:brigades,id',
            'assigned_to'  => 'nullable|exists:users,id',
            'description'  => 'required|string|max:5000',
            'phone'        => 'nullable|string|max:20',
            'contract_no'  => 'nullable|string|max:50',
            'priority'     => 'required|in:low,normal,high,urgent',
            'scheduled_at'    => 'nullable|date',
            'service_type_id' => 'nullable|exists:service_types,id',
        ];
    }
}
