<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Ticket::class);
    }

    public function rules(): array
    {
        return [
            'address_id'      => 'required|exists:addresses,id',
            'territory_id'    => 'required|exists:territories,id',
            'service_type_id' => 'nullable|exists:service_types,id',
            'type_id'      => 'required|exists:ticket_types,id',
            'brigade_id'   => 'nullable|exists:brigades,id',
            'assigned_to'  => 'nullable|exists:users,id',
            'description'  => 'required|string|max:5000',
            'phone'        => 'nullable|string|max:20',
            'apartment'    => 'nullable|string|max:20',
            'contract_no'  => 'nullable|string|max:50',
            'priority'     => 'required|in:low,normal,high,urgent',
            'scheduled_at' => 'nullable|date|after:now',
            // Вложения при создании
            'attachments'        => 'nullable|array|max:10',
            'attachments.*'      => 'file|max:102400|mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,mp3,ogg,wav,m4a,pdf,doc,docx,xls,xlsx',
        ];
    }

    public function messages(): array
    {
        return [
            'address_id.required'   => 'Выберите адрес абонента',
            'territory_id.required' => 'Выберите территорию',
            'address_id.exists'     => 'Указанный адрес не найден в базе',
            'type_id.required'      => 'Выберите тип заявки',
            'description.required'  => 'Заполните описание',
            'priority.required'     => 'Укажите приоритет',
            'scheduled_at.after'    => 'Дата выезда должна быть в будущем',
        ];
    }
}
