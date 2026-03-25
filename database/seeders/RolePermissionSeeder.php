<?php

namespace Database\Seeders;

use App\Support\AccessControl;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        AccessControl::ensureSeeded();
    }
}
