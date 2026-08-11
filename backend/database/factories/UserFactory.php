<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 *
 * Citizen users authenticate by mobile OTP — there is no email/password.
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'mobile_number'      => (string) $this->faker->numerify('9#########'),
            'otp_verified_at'    => now(),
            'daily_report_count' => 0,
            'last_reset_at'      => now(),
        ];
    }
}
