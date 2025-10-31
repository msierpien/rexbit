<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationImportMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_id',
        'target_type',
        'source_field',
        'target_field',
        'transform',
    ];

    protected $casts = [
        'transform' => 'array',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(IntegrationImportProfile::class, 'profile_id');
    }
}
