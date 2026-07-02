<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IvrLog extends Model
{
    protected $fillable = [
        'call_id',
        'phone',
        'subscriber_name',
        'agreement_num',
        'address',
        'balance',
        'blocked',
        'action',
        'details',
    ];

    protected $casts = [
        'balance' => 'float',
        'blocked' => 'integer',
    ];

    public static array $actionLabels = [
        'balance_check'       => 'Проверка баланса',
        'pp_offered'          => 'Предложен кредит',
        'pp_activated'        => 'Кредит активирован',
        'pp_declined'         => 'Кредит отклонён',
        'transfer_to_support' => 'Переход к оператору',
        'not_found'           => 'Не найден в биллинге',
        'api_error'           => 'Ошибка API',
    ];
}
