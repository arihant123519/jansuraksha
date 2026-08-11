<?php

namespace Database\Factories;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition(): array
    {
        $lat = 28.6139;
        $lng = 77.2090;

        return [
            'complaint_id'   => 'JS-' . now()->format('Y') . '-' . strtoupper(Str::random(8)),
            'user_id'        => User::factory(),
            'vehicle_number' => 'DL01AB1234',
            'violation_type' => 'signal_jump',
            'reported_at'    => now()->subDay(),
            'location'       => DB::raw("ST_GeomFromText('POINT({$lng} {$lat})')"),
            'location_lat'   => $lat,
            'location_lng'   => $lng,
            'area_state'     => 'Delhi',
            'area_district'  => 'Central Delhi',
            'area_taluka'    => null,
            'status'         => 'submitted',
            'is_flagged'     => false,
        ];
    }
}
