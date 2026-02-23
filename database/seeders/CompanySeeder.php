<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [

            ['name' => 'Openserve', 'code' => 'OPS', 'is_group_company' => true, 'timezone' => 'Africa/Johannesburg'],
            ['name' => 'BCX', 'code' => 'BCX', 'is_group_company' => true, 'timezone' => 'Africa/Johannesburg'],
            ['name' => 'Telkom Consumer', 'code' => 'TCS', 'is_group_company' => true, 'timezone' => 'Africa/Johannesburg'],
            ['name' => 'Gyro', 'code' => 'GYR', 'is_group_company' => true, 'timezone' => 'Africa/Johannesburg'],
        ];

        foreach ($companies as $company) {
            Company::updateOrCreate(
                ['code' => $company['code']],
                [
                    'name' => $company['name'],
                    'is_group_company' => $company['is_group_company'],
                    'timezone' => $company['timezone'],
                ]
            );
        }
    }
}
