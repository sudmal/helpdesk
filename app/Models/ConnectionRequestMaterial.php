<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionRequestMaterial extends Model
{
    protected $fillable = [
        'connection_request_id', 'material_id', 'material_name',
        'material_code', 'material_unit', 'price_at_time', 'quantity', 'created_by',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
