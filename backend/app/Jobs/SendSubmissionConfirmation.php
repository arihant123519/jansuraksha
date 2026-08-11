<?php

namespace App\Jobs;

use App\Models\Complaint;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSubmissionConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public Complaint $complaint, public User $user) {}

    public function handle(OtpService $otpService): void
    {
        $otpService->sendStatusUpdate($this->user->mobile_number, $this->complaint->complaint_id, 'submitted');
    }
}