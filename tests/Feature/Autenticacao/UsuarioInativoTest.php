<?php

/*
 * Critério de aceite 1 — parte B:
 * usuário inativo não autentica e perde o acesso imediatamente.
 */

use App\Models\Eixo;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

it('não autentica um usuário inativo', function () {
    $usuario = User::factory()->inativo()->create(['email' => 'inativo@exemplo.test']);

    $resposta = $this->post(route('login.store'), [
        'email' => $usuario->email,
        'password' => 'senha-de-teste',
    ]);

    $resposta->assertSessionHasErrors('email');
    expect(Auth::check())->toBeFalse();
});

it('autentica um usuário ativo com a mesma senha', function () {
    $usuario = User::factory()->create(['email' => 'ativo@exemplo.test']);

    $this->post(route('login.store'), [
        'email' => $usuario->email,
        'password' => 'senha-de-teste',
    ])->assertRedirect();

    expect(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($usuario->id);
});

it('recusa senha errada sem revelar se a conta existe', function () {
    $usuario = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $usuario->email,
        'password' => 'senha-errada',
    ])->assertSessionHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

it('derruba a sessão em curso assim que a conta é desativada', function () {
    $usuario = paeet();

    $this->actingAs($usuario)->get(route('painel'))->assertOk();

    $usuario->update(['ativo' => false]);

    $this->get(route('painel'))->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
});

it('nega qualquer autorização a uma conta inativa', function () {
    $usuario = paeet();
    $usuario->update(['ativo' => false]);

    expect($usuario->can('viewAny', User::class))->toBeFalse()
        ->and($usuario->can('create', Eixo::class))->toBeFalse();
});

it('registra o motivo do bloqueio na auditoria', function () {
    $usuario = User::factory()->inativo()->create();

    $this->post(route('login.store'), [
        'email' => $usuario->email,
        'password' => 'senha-de-teste',
    ]);

    expect(Activity::query()
        ->where('log_name', 'autenticacao')
        ->where('description', 'Tentativa de login bloqueada: conta inativa')
        ->exists())->toBeTrue();
});

it('não envia link de redefinição de senha para conta inativa', function () {
    Notification::fake();

    $usuario = User::factory()->inativo()->create();

    $this->post(route('password.email'), ['email' => $usuario->email]);

    Notification::assertNothingSent();
});
