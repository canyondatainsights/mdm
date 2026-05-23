<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['Admin', 'Steward', 'Contributor', 'Viewer'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@canyondatainsights.com'],
            [
                'name' => 'Admin',
                'title' => 'Data Architect',
                'password' => Hash::make('password'),
            ],
        );
        $admin->syncRoles(['Admin']);

        $steward = User::updateOrCreate(
            ['email' => 'steward@canyondatainsights.com'],
            [
                'name' => 'Amelia Voss',
                'title' => 'Data Steward · EMEA',
                'password' => Hash::make('password'),
            ],
        );
        $steward->syncRoles(['Steward']);
    }
}
