<?php

namespace App\Jobs;

use App\Models\Complaint;
use App\Services\OtpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendStatusUpdateNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public Complaint $complaint) {}

    public function handle(OtpService $otpService): void
    {
        $this->complaint->load('user');
        $otpService->sendStatusUpdate(
            $this->complaint->user->mobile_number,
            $this->complaint->complaint_id,
            $this->complaint->status
        );
    }
}