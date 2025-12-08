<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'group_admin',
            'company_admin',
            'company_user',
            'company_preparer',
            'company_reviewer',
        ];

        foreach ($roles as $role) {
            Role::findOrCreate($role);
        }
    }
}
