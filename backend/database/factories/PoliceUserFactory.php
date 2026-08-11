<?php

namespace Database\Factories;

use App\Models\PoliceUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<PoliceUser>
 */
class PoliceUserFactory extends Factory
{
    protected $model = PoliceUser::class;

    public function definition(): array
    {
        return [
            'username'              => $this->faker->unique()->userName(),
            'password_hash'         => Hash::make('Police@1234'),
            'name'                  => $this->faker->name(),
            'badge_number'          => strtoupper($this->faker->unique()->bothify('??-TF-###')),
            'jurisdiction_state'    => 'Delhi',
            'jurisdiction_district' => 'Central Delhi',
            'role'                  => 'officer',
            'last_login_at'         => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role'                  => 'admin',
            'jurisdiction_state'    => null,
            'jurisdiction_district' => null,
        ]);
    }
}
