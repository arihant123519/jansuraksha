<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintStatusLog extends Model
{
    protected $fillable = [
        'complaint_id', 'changed_by', 'old_status', 'new_status', 'reason', 'changed_at',
    ];

    protected $casts = ['changed_at' => 'datetime'];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }
}