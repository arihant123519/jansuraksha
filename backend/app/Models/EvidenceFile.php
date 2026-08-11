<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceFile extends Model
{
    protected $fillable = [
        'complaint_id', 'file_type', 's3_path', 'thumbnail_s3_path', 'uploaded_at',
    ];

    protected $casts = ['uploaded_at' => 'datetime'];

    public ?string $temp_url = null;

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }
}