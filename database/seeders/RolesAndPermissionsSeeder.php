<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * The platform's user roles, from least to most privileged.
     *
     * @var list<string>
     */
    public const ROLES = [
        'guest',
        'listener',
        'creator',
        'advertiser',
        'moderator',
        'super-admin',
    ];

    /**
     * Baseline permissions mapped to the roles that hold them.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'listener' => ['stream-content', 'manage-own-library'],
        'creator' => ['stream-content', 'manage-own-library', 'upload-content', 'view-own-analytics'],
        'advertiser' => ['manage-own-campaigns', 'view-own-analytics'],
        'moderator' => ['moderate-content', 'manage-users'],
        'super-admin' => ['*'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionNames = collect(self::ROLE_PERMISSIONS)
            ->flatten()
            ->reject(fn (string $permission) => $permission === '*')
            ->unique();

        $permissionNames->each(
            fn (string $permission) => Permission::findOrCreate($permission)
        );

        foreach (self::ROLES as $roleName) {
            $role = Role::findOrCreate($roleName);

            if ($roleName === 'super-admin') {
                $role->syncPermissions(Permission::all());

                continue;
            }

            $role->syncPermissions(self::ROLE_PERMISSIONS[$roleName] ?? []);
        }
    }
}
