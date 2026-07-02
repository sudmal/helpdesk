<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMaterial extends Model
{
    protected $fillable = [
        'ticket_id', 'material_id', 'material_name', 'material_code', 'material_unit',
        'price_at_time', 'quantity', 'created_by',
    ];
    protected $casts = [
        'price_at_time' => 'float',
        'quantity'      => 'float',
    ];

    public function material(): BelongsTo { return $this->belongsTo(Material::class); }
    public function ticket():   BelongsTo { return $this->belongsTo(Ticket::class); }
    public function creator():  BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function getTotalAttribute(): float
    {
        return round($this->price_at_time * $this->quantity, 2);
    }
}