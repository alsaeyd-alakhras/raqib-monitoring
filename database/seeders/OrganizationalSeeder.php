<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OrganizationalSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ExcelOrganizationalSeeder::class);
    }
}
