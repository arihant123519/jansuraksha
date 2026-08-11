<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function generate(string $mobile): string
    {
        if (app()->environment('local', 'testing') && !config('services.msg91.auth_key')) {
            return '123456';
        }
        return (string) random_int(100000, 999999);
    }

    public function send(string $mobile, string $otp): void
    {
        if (app()->environment('local', 'testing') && !config('services.msg91.auth_key')) {
            Log::info("DEV OTP [{$mobile}]: {$otp}");
            return;
        }
        try {
            Http::withHeaders([
                'authkey'      => config('services.msg91.auth_key'),
                'content-type' => 'application/json',
            ])->post('https://api.msg91.com/api/v5/otp', [
                'mobile'      => '91' . $mobile,
                'template_id' => config('services.msg91.template_id_otp'),
                'otp'         => $otp,
            ]);
        } catch (\Throwable $e) {
            Log::error("OTP send failed [{$mobile}]: " . $e->getMessage());
        }
    }

    public function sendStatusUpdate(string $mobile, string $complaintId, string $status): void
    {
        if (app()->environment('local', 'testing')) {
            Log::info("DEV SMS [{$mobile}]: {$complaintId} → {$status}");
            return;
        }
        try {
            Http::withHeaders([
                'authkey'      => config('services.msg91.auth_key'),
                'content-type' => 'application/json',
            ])->post('https://api.msg91.com/api/v5/flow/', [
                'template_id' => config('services.msg91.template_id_status'),
                'recipients'  => [[
                    'mobiles'      => '91' . $mobile,
                    'complaint_id' => $complaintId,
                    'status'       => ucfirst($status),
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::error("Status SMS failed [{$mobile}]: " . $e->getMessage());
        }
    }
}