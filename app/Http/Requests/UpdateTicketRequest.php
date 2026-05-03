<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'type_id'      => 'required|exists:ticket_types,id',
            'status_id'    => 'required|exists:ticket_statuses,id',
            'brigade_id'   => 'nullable|exists:brigades,id',
            'assigned_to'  => 'nullable|exists:users,id',
            'description'  => 'required|string|max:5000',
            'phone'        => 'nullable|string|max:20',
            'contract_no'  => 'nullable|string|max:50',
            'priority'     => 'required|in:low,normal,high,urgent',
            'scheduled_at' => 'nullable|date',
        ];
    }
}
