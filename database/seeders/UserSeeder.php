<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command?->warn('No companies found; skipping user seeding.');

            return;
        }

        $defaultCompany = $companies->first();

        $groupAdmin = User::updateOrCreate(
            ['email' => 'admin@tlkm.test'],
            [
                'name' => 'Group',
                'surname' => 'Admin',
                'password' => Hash::make('password'),
                'company_id' => $defaultCompany->id,
                'email_verified_at' => now(),
            ]
        );
        $groupAdmin->syncRoles(['group_admin']);

        foreach ($companies as $company) {
            $code = Str::lower($company->code ?? Str::slug($company->name));

            $admin = User::updateOrCreate(
                ['email' => "{$code}.admin@tlkm.test"],
                [
                    'name' => "{$company->name} Admin",
                    'surname' => 'User',
                    'password' => Hash::make('password'),
                    'company_id' => $company->id,
                    'email_verified_at' => now(),
                ]
            );
            $admin->syncRoles(['company_admin']);

            $preparer = User::updateOrCreate(
                ['email' => "{$code}.preparer@tlkm.test"],
                [
                    'name' => "{$company->name} Preparer",
                    'surname' => 'User',
                    'password' => Hash::make('password'),
                    'company_id' => $company->id,
                    'email_verified_at' => now(),
                ]
            );
            $preparer->syncRoles(['company_preparer']);

            $reviewer = User::updateOrCreate(
                ['email' => "{$code}.reviewer@tlkm.test"],
                [
                    'name' => "{$company->name} Reviewer",
                    'surname' => 'User',
                    'password' => Hash::make('password'),
                    'company_id' => $company->id,
                    'email_verified_at' => now(),
                ]
            );
            $reviewer->syncRoles(['company_reviewer']);
        }
    }
}
