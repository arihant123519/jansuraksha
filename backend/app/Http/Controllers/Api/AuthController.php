<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PoliceUser;
use App\Models\User as ModelsUser;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private OtpService $otpService) {}

    // POST /api/auth/otp/send
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate(['mobile' => ['required', 'regex:/^[6-9]\d{9}$/']]);

        $mobile      = $request->mobile;
        $throttleKey = "otp_send:{$mobile}";

        if (Cache::get($throttleKey, 0) >= 5) {
            return response()->json(['message' => 'Too many OTP requests. Wait 10 minutes.'], 429);
        }

        Cache::put($throttleKey, Cache::get($throttleKey, 0) + 1, now()->addMinutes(10));

        $otp = $this->otpService->generate($mobile);
        Cache::put("otp:{$mobile}", $otp, now()->addMinutes(10));
        $this->otpService->send($mobile, $otp);

        return response()->json(['message' => 'OTP sent successfully.']);
    }

    // POST /api/auth/otp/verify
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'otp'    => ['required', 'digits:6'],
        ]);

        $mobile = $request->mobile;
        $cached = Cache::get("otp:{$mobile}");

        // TESTING ONLY: master OTP bypass, gated to non-production environments.
        $testBypass = app()->environment('local', 'testing') && $request->otp === '000000';

        if (!$testBypass && (!$cached || $cached !== $request->otp)) {
            throw ValidationException::withMessages(['otp' => ['Invalid or expired OTP.']]);
        }

        Cache::forget("otp:{$mobile}");
        Cache::forget("otp_send:{$mobile}");

        // Log mobile only — never log OTP or full request (PII)
        Log::info('OTP verified', ['mobile' => $mobile]);

        $user = ModelsUser::firstOrCreate(
            ['mobile_number' => $mobile],
            ['otp_verified_at' => now(), 'daily_report_count' => 0, 'last_reset_at' => now()]
        );
        $user->update(['otp_verified_at' => now()]);
        $user->tokens()->where('name', 'citizen-token')->delete();
        $token = $user->createToken('citizen-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'                 => $user->id,
                'mobile_number'      => $user->mobile_number,
                'daily_report_count' => $user->daily_report_count,
                'last_reset_at'      => $user->last_reset_at,
            ],
        ]);
    }

    // POST /api/auth/police/login
    public function policeLogin(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $officer = PoliceUser::where('username', $request->username)->first();

        if (!$officer || !Hash::check($request->password, $officer->password_hash)) {
            throw ValidationException::withMessages(['username' => ['Invalid credentials.']]);
        }

        $officer->update(['last_login_at' => now()]);
        $officer->tokens()->where('name', 'police-token')->delete();
        $token = $officer->createToken('police-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'                    => $officer->id,
                'name'                  => $officer->name,
                'badge_number'          => $officer->badge_number,
                'jurisdiction_state'    => $officer->jurisdiction_state,
                'jurisdiction_district' => $officer->jurisdiction_district,
                'role'                  => $officer->role,
            ],
        ]);
    }

    // POST /api/auth/police/logout
    public function policeLogout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    // POST /api/auth/logout
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }
}