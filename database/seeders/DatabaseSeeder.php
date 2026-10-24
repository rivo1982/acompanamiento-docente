<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the module's global catalog and the generic funciones proposed to Vinkured for staff invitations.
     * Host rows (establecimientos, funcionarios...) belong to Vinkured.
     */
    public function run(): void
    {
        $this->call(CatalogSeeder::class);
        $this->call(FuncionesGenericasSeeder::class);
    }
}
