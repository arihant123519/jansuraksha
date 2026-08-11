<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_id', 'user_id', 'vehicle_number', 'violation_type',
        'reported_at', 'location', 'location_lat', 'location_lng',
        'area_state', 'area_district', 'area_taluka', 'status', 'is_flagged',
    ];

    protected $casts = [
        'reported_at'  => 'datetime',
        'is_flagged'   => 'boolean',
        'location_lat' => 'float',
        'location_lng' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function evidenceFiles(): HasMany
    {
        return $this->hasMany(EvidenceFile::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ComplaintStatusLog::class)->orderBy('changed_at');
    }
}