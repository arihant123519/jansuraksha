<?php
namespace Database\Seeders;

use App\Models\PoliceUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PoliceUserSeeder extends Seeder
{
    public function run(): void
    {
        $officers = [
            ['username'=>'delhi_tp_001','password_hash'=>Hash::make('Police@1234'),'name'=>'Inspector Rajesh Kumar','badge_number'=>'DL-TF-001','jurisdiction_state'=>'Delhi','jurisdiction_district'=>'Central Delhi','role'=>'officer'],
            ['username'=>'mh_tp_001','password_hash'=>Hash::make('Police@1234'),'name'=>'SI Priya Sharma','badge_number'=>'MH-TF-001','jurisdiction_state'=>'Maharashtra','jurisdiction_district'=>'Mumbai City','role'=>'officer'],
            ['username'=>'ka_tp_001','password_hash'=>Hash::make('Police@1234'),'name'=>'Inspector Venkat Rao','badge_number'=>'KA-TF-001','jurisdiction_state'=>'Karnataka','jurisdiction_district'=>'Bangalore Urban','role'=>'supervisor'],
            ['username'=>'admin','password_hash'=>Hash::make('Admin@JanSuraksha2024'),'name'=>'Platform Admin','badge_number'=>'ADMIN-001','jurisdiction_state'=>null,'jurisdiction_district'=>null,'role'=>'admin'],
        ];

        foreach ($officers as $data) {
            PoliceUser::updateOrCreate(['badge_number' => $data['badge_number']], $data);
        }

        $this->command->info('Seeded. Default password: Police@1234');
    }
}