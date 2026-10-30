<?php

/*
 * A primeira conta de produção nasce pelo terminal: `usuario:criar-admin`.
 * O banco recém-migrado está vazio, e o comando é o único caminho até a
 * tela de login.
 */

use App\Enums\PerfilUsuario;
use App\Models\Eixo;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('cria um PAEET Admin ativo num banco vazio', function () {
    $this->artisan('usuario:criar-admin', [
        '--nome' => 'Coordenação PAEET',
        '--email' => 'coordenacao@escola.test',
        '--senha' => 'Senha123&x',
    ])->assertSuccessful();

    $usuario = User::query()->sole();

    expect($usuario->email)->toBe('coordenacao@escola.test')
        ->and($usuario->perfil)->toBe(PerfilUsuario::PaeetAdmin)
        ->and($usuario->ativo)->toBeTrue()
        ->and(Hash::check('Senha123&x', $usuario->password))->toBeTrue()
        ->and($usuario->eixos()->count())->toBe(0);
});

it('vincula os Eixos informados', function () {
    $eixos = Eixo::factory()->count(2)->create();

    $this->artisan('usuario:criar-admin', [
        '--nome' => 'Coordenação PAEET',
        '--email' => 'coordenacao@escola.test',
        '--senha' => 'Senha123&x',
        '--eixo' => $eixos->pluck('id')->all(),
    ])->assertSuccessful();

    expect(User::query()->sole()->eixos()->pluck('eixos.id')->sort()->values()->all())
        ->toBe($eixos->pluck('id')->sort()->values()->all());
});

it('recusa e-mail já cadastrado sem criar outra conta', function () {
    User::factory()->create(['email' => 'coordenacao@escola.test']);

    $this->artisan('usuario:criar-admin', [
        '--nome' => 'Coordenação PAEET',
        '--email' => 'coordenacao@escola.test',
        '--senha' => 'Senha123&x',
    ])->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('recusa senha fraca', function (string $senha) {
    $this->artisan('usuario:criar-admin', [
        '--nome' => 'Coordenação PAEET',
        '--email' => 'coordenacao@escola.test',
        '--senha' => $senha,
    ])->assertFailed();

    expect(User::query()->exists())->toBeFalse();
})->with(['curta1', 'somenteletras', '12345678901']);
