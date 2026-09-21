<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Nenhum seeder cria conta de acesso público. O ambiente de
     * demonstração é explícito e só roda fora de produção.
     */
    public function run(): void
    {
        $this->call(DemonstracaoSeeder::class);
    }
}
