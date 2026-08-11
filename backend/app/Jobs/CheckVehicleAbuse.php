<?php

namespace App\Jobs;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CheckVehicleAbuse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Complaint $complaint) {}

    public function handle(): void
    {
        // FIX: distinct('user_id') without select() counts all columns in some MySQL configs.
        // select('user_id')->distinct()->count() is unambiguous.
        $uniqueReporters = Complaint::where('vehicle_number', $this->complaint->vehicle_number)
            ->where('reported_at', '>=', now()->subDays(7))
            ->select('user_id')
            ->distinct()
            ->count('user_id');

        if ($uniqueReporters >= 5) {
            $this->complaint->update(['is_flagged' => true]);
            DB::table('vehicle_abuse_flags')->updateOrInsert(
                ['vehicle_number' => $this->complaint->vehicle_number, 'window_start' => now()->subDays(7)->toDateString()],
                ['report_count' => $uniqueReporters, 'admin_reviewed' => false, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}