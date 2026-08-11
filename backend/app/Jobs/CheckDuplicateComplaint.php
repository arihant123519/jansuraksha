<?php

namespace App\Jobs;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckDuplicateComplaint implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Complaint $complaint) {}

    public function handle(): void
    {
        $twoHoursAgo = $this->complaint->reported_at->copy()->subHours(2);

        $duplicate = Complaint::where('vehicle_number', $this->complaint->vehicle_number)
            ->where('id', '!=', $this->complaint->id)
            ->where('status', '!=', 'duplicate')
            ->whereBetween('reported_at', [$twoHoursAgo, $this->complaint->reported_at])
            ->whereRaw(
                'ST_Distance_Sphere(location, ST_GeomFromText(?)) <= 500',
                ["POINT({$this->complaint->location_lng} {$this->complaint->location_lat})"]
            )
            ->first();

        if ($duplicate) {
            $this->complaint->update(['status' => 'duplicate']);
        }
    }
}