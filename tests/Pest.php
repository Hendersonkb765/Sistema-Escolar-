<?php

use App\Enums\PerfilUsuario;
use App\Models\Eixo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers de domínio
|--------------------------------------------------------------------------
*/

/** Cria um usuário de gestão já vinculado aos Eixos indicados. */
function gestor(PerfilUsuario $perfil, Eixo ...$eixos): User
{
    $usuario = User::factory()->create(['perfil' => $perfil]);

    if ($eixos !== []) {
        $usuario->eixos()->sync(collect($eixos)->map->getKey()->all());
        $usuario->esquecerEscopo();
    }

    return $usuario;
}

function paeetAdmin(Eixo ...$eixos): User
{
    return gestor(PerfilUsuario::PaeetAdmin, ...$eixos);
}

function paeet(Eixo ...$eixos): User
{
    return gestor(PerfilUsuario::Paeet, ...$eixos);
}

function professor(Eixo ...$eixos): User
{
    return gestor(PerfilUsuario::Professor, ...$eixos);
}
