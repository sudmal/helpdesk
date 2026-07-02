<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketStatus extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'is_final', 'requires_comment', 'is_active', 'sort_order'];
    protected $casts = ['is_final' => 'boolean', 'requires_comment' => 'boolean', 'is_active' => 'boolean'];

    public function tickets(): HasMany { return $this->hasMany(Ticket::class, 'status_id'); }

    public function scopeActive($query) { return $query->where('is_active', true)->orderBy('sort_order'); }
    public function scopeOpen($query)   { return $query->where('is_final', false); }
}
