<?php

namespace App\Services;

use App\Models\User;
use App\Models\User as ModelsUser;
use Carbon\Carbon;

class RateLimitService
{
    public const DAILY_LIMIT = 10;
    public const TIMEZONE    = 'Asia/Kolkata';

    public function canSubmit(ModelsUser $user): bool
    {
        $this->resetIfNewDay($user);
        return $user->daily_report_count < self::DAILY_LIMIT;
    }

    public function increment(ModelsUser $user): void
    {
        $user->increment('daily_report_count');
        $user->refresh();
    }

    public function quota(ModelsUser $user): array
    {
        $this->resetIfNewDay($user);
        return [
            'used'      => $user->daily_report_count,
            'max'       => self::DAILY_LIMIT,
            'remaining' => max(0, self::DAILY_LIMIT - $user->daily_report_count),
            'resets_at' => Carbon::tomorrow(self::TIMEZONE)->startOfDay()->toIso8601String(),
        ];
    }

    private function resetIfNewDay(ModelsUser $user): void
    {
        $todayStart = Carbon::now(self::TIMEZONE)->startOfDay();
        $lastReset  = $user->last_reset_at
            ? Carbon::parse($user->last_reset_at)->setTimezone(self::TIMEZONE)
            : null;

        if (!$lastReset || $lastReset->lt($todayStart)) {
            $user->update(['daily_report_count' => 0, 'last_reset_at' => now()]);
        }
    }
}