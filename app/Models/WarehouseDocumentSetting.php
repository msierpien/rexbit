<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseDocumentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'prefix',
        'suffix',
        'next_number',
        'padding',
        'reset_period',
        'last_reset_at',
    ];

    protected $casts = [
        'next_number' => 'integer',
        'padding' => 'integer',
        'last_reset_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
