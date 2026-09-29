<?php

/*
 * A mesma varredura de diretiva crua, nas telas que não pedem login.
 *
 * Elas ficam de fora de DiretivasCompiladasTest porque exigem o contrário
 * do que aquele teste monta: estar deslogado, ou estar logado com a senha
 * ainda provisória. São também as telas que um estranho vê, e todas têm
 * formulário — que é onde `@disabled` e `@required` moram.
 */

use App\Models\User;

it('não deixa diretiva crua nas telas de quem ainda não entrou', function (string $rota) {
    $html = $this->get(route($rota))->assertOk()->getContent();

    expect(sobrasDeDiretiva($html))->toBe([]);
})->with(['login', 'password.request']);

it('não deixa diretiva crua na escolha da primeira senha', function () {
    $usuario = User::factory()->semSenhaPropria()->create();

    $html = $this->actingAs($usuario)
        ->get(route('primeira-senha'))
        ->assertOk()
        ->getContent();

    expect(sobrasDeDiretiva($html))->toBe([]);
});

it('não deixa diretiva crua no desafio de dois fatores', function () {
    $usuario = User::factory()->create();

    $html = $this->withSession([
        'login.id' => $usuario->id,
        'login.remember' => false,
    ])->get(route('two-factor.login'))->assertOk()->getContent();

    expect(sobrasDeDiretiva($html))->toBe([]);
});
